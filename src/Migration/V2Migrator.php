<?php

namespace LiftedLogic\LLBag\Migration;

use LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType;
use LiftedLogic\LLBag\BeforeAfterPostType\TaxonomyRegistrar;
use LiftedLogic\LLBag\Filters\FilterManager;
use WP_Post;

/**
 * Copies posts, terms and settings from the old "LL Before After Gallery" plugin (V2, post type
 * `ll_bag`) into this plugin (V3, post type `ll_before_after`).
 *
 * V2 data is only ever read, never changed, so the migration can be undone and re-run. Everything
 * is read straight from the database because V2's post type and taxonomies may not be registered
 * (the V2 plugin is usually deactivated once the migration is done).
 *
 * Runs in three steps so large sites don't time out: setup() (filters, terms, settings), then
 * migrateBatch() until nothing is left, then finish().
 */
class V2Migrator {
  public const V2_POST_TYPE = 'll_bag';
  public const SOURCE_META  = '_ll_bag_v2_source_id';
  public const STATE_OPTION = 'll_bag_v2_migration';
  public const LOG_OPTION   = 'll_bag_v2_migration_log';
  public const BATCH_SIZE   = 10;

  public const LEVEL_MANUAL = 'manual';
  public const LEVEL_REVIEW = 'review';
  public const LEVEL_INFO   = 'info';

  /**
   * Known V2 taxonomies and where they go in V3. Any other taxonomy attached to V2 posts
   * (e.g. one added by a theme) gets a new filter named after it, see targetFor().
   */
  private const TAXONOMY_MAP = [
    'll_bag_category' => ['taxonomy' => 'll_ba_category',       'label' => 'Categories',     'rewrite' => 'treatment-category'],
    'll_bag_provider' => ['taxonomy' => 'll_ba_provider',       'label' => 'Providers',      'rewrite' => 'treatment-provider'],
    'll_bag_name'     => ['taxonomy' => 'll_ba_treatment',      'label' => 'Treatment',      'rewrite' => 'treatment-name'],
    'll_bag_area'     => ['taxonomy' => 'll_ba_treatment_area', 'label' => 'Treatment Area', 'rewrite' => 'treatment-area'],
    'll_bag_gender'   => ['taxonomy' => 'll_ba_gender',         'label' => 'Gender',         'rewrite' => 'treatment-gender'],
  ];

  /** V2 ratio-free images are matched to the closest V3 frame. */
  private const RATIOS = [
    'wide'     => 16 / 9,
    'square'   => 1.0,
    'panorama' => 3.0,
    'vertical' => 0.8,
  ];

  /** How far (as a log-ratio, ~3%) an image may be from its frame before it's shown uncropped. */
  private const RATIO_TOLERANCE = 0.03;

  /** Shows the whole image inside the frame (focal point centred, zoom 100%). */
  private const CONTAIN_FOCAL = '50 50 100';

  /** V3's built-in NSFW text, treated as "not customised yet". */
  private const DEFAULT_NSFW_TEXT = 'This before and after contains sensitive content.';

  /** @var array<int, array<string, mixed>> */
  private array $log = [];

  /** Which step log entries belong to, so a re-run can replace its own site-wide entries. */
  private string $step = 'posts';

  /** V2 post IDs whose images were set to show in full during this batch. */
  private array $contained = [];

  public function __construct(
    private readonly FilterManager $filterManager,
    private readonly TaxonomyRegistrar $taxonomyRegistrar,
  ) {}

  // ---------------------------------------------------------------------------
  // Reading V2
  // ---------------------------------------------------------------------------

  public static function hasV2Data(): bool {
    global $wpdb;
    return (bool) $wpdb->get_var($wpdb->prepare(
      "SELECT 1 FROM {$wpdb->posts} WHERE post_type = %s LIMIT 1",
      self::V2_POST_TYPE
    ));
  }

  /**
   * Post statuses worth migrating. Trash, auto-drafts and revisions are left behind.
   */
  private function statusSql(): string {
    return "post_status IN ('publish','draft','pending','private','future')";
  }

  public function countV2Posts(): int {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND {$this->statusSql()}",
      self::V2_POST_TYPE
    ));
  }

  public function countTrashedV2Posts(): int {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'trash'",
      self::V2_POST_TYPE
    ));
  }

  public function countMigratedPosts(): int {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
       JOIN {$wpdb->posts} p ON p.ID = pm.post_id
       WHERE pm.meta_key = %s AND p.post_type = %s",
      self::SOURCE_META,
      BeforeAfterPostType::SLUG
    ));
  }

  /**
   * @return array<int, int> V2 post ID => V3 post ID
   */
  public function migratedIdMap(): array {
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT pm.meta_value AS v2, pm.post_id AS v3 FROM {$wpdb->postmeta} pm
       JOIN {$wpdb->posts} p ON p.ID = pm.post_id
       WHERE pm.meta_key = %s AND p.post_type = %s",
      self::SOURCE_META,
      BeforeAfterPostType::SLUG
    ));

    $map = [];
    foreach ($rows as $row) {
      $map[(int) $row->v2] = (int) $row->v3;
    }
    return $map;
  }

  /**
   * @param int[] $exclude V2 post IDs to skip (ones that already failed this run)
   * @return int[]
   */
  private function pendingV2PostIds(int $limit, array $exclude = []): array {
    global $wpdb;

    $excludeSql = $exclude ? 'AND p.ID NOT IN (' . implode(',', array_map('intval', $exclude)) . ')' : '';

    return array_map('intval', $wpdb->get_col($wpdb->prepare(
      "SELECT p.ID FROM {$wpdb->posts} p
       WHERE p.post_type = %s AND p.{$this->statusSql()} {$excludeSql}
         AND NOT EXISTS (
           SELECT 1 FROM {$wpdb->postmeta} pm
           JOIN {$wpdb->posts} np ON np.ID = pm.post_id AND np.post_type = %s
           WHERE pm.meta_key = %s AND pm.meta_value = p.ID
         )
       ORDER BY p.menu_order ASC, p.ID ASC
       LIMIT %d",
      self::V2_POST_TYPE,
      BeforeAfterPostType::SLUG,
      self::SOURCE_META,
      $limit
    )));
  }

  /**
   * Every taxonomy that has terms attached to at least one V2 post, plus the known V2
   * taxonomies that have terms at all.
   *
   * @return string[]
   */
  private function v2Taxonomies(): array {
    global $wpdb;

    $used = $wpdb->get_col($wpdb->prepare(
      "SELECT DISTINCT tt.taxonomy FROM {$wpdb->term_relationships} tr
       JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
       JOIN {$wpdb->posts} p ON p.ID = tr.object_id
       WHERE p.post_type = %s",
      self::V2_POST_TYPE
    ));

    $known = array_keys(self::TAXONOMY_MAP);
    $placeholders = implode(',', array_fill(0, count($known), '%s'));
    $withTerms = $wpdb->get_col($wpdb->prepare(
      "SELECT DISTINCT taxonomy FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ($placeholders)",
      ...$known
    ));

    // Known taxonomies first, in map order, then anything unexpected.
    $all = array_unique(array_merge($withTerms, $used));
    usort($all, function (string $a, string $b) use ($known): int {
      $ia = array_search($a, $known, true);
      $ib = array_search($b, $known, true);
      return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib) ?: strcmp($a, $b);
    });

    // post_format is WordPress internals, not a filter.
    return array_values(array_filter($all, fn(string $t): bool => $t !== 'post_format'));
  }

  /**
   * @return array<int, object{term_id: string, name: string, slug: string, description: string, parent: string, count: string}>
   */
  private function v2Terms(string $taxonomy): array {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
      "SELECT t.term_id, t.name, t.slug, tt.description, tt.parent, tt.count
       FROM {$wpdb->terms} t
       JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
       WHERE tt.taxonomy = %s
       ORDER BY tt.parent ASC, t.name ASC",
      $taxonomy
    ));
  }

  /**
   * @return array<string, int[]> taxonomy => term IDs
   */
  private function v2PostTerms(int $postId): array {
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT tt.taxonomy, tt.term_id FROM {$wpdb->term_relationships} tr
       JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
       WHERE tr.object_id = %d
       ORDER BY tr.term_order ASC",
      $postId
    ));

    $out = [];
    foreach ($rows as $row) {
      $out[$row->taxonomy][] = (int) $row->term_id;
    }
    return $out;
  }

  /**
   * The V2 archive base, e.g. "before-and-after" in /before-and-after/{post}/.
   */
  public function v2ArchiveSlug(): string {
    $slug = trim((string) get_option('options_slug'), '/');
    return $slug !== '' ? $slug : 'before-and-after';
  }

  /**
   * Where a V2 taxonomy goes in V3.
   *
   * @return array{taxonomy: string, label: string, rewrite: string, known: bool}
   */
  private function targetFor(string $v2Taxonomy): array {
    if (isset(self::TAXONOMY_MAP[$v2Taxonomy])) {
      return self::TAXONOMY_MAP[$v2Taxonomy] + ['known' => true];
    }

    $registered = get_taxonomy($v2Taxonomy);
    $label = $registered ? (string) $registered->labels->singular_name : ucwords(str_replace(['ll_bag_', '_', '-'], ['', ' ', ' '], $v2Taxonomy));
    $slug  = sanitize_key(preg_replace('/^ll_bag_/', '', $v2Taxonomy));

    return [
      'taxonomy' => 'll_ba_' . $slug,
      'label'    => $label,
      'rewrite'  => $registered && is_array($registered->rewrite) ? (string) ($registered->rewrite['slug'] ?? '') : '',
      'known'    => false,
    ];
  }

  // ---------------------------------------------------------------------------
  // Preview (no writes)
  // ---------------------------------------------------------------------------

  /**
   * What the migration would do, for the admin page.
   *
   * @return array<string, mixed>
   */
  public function preview(): array {
    $filters = $this->filterManager->all()->keyBy('meta_key');
    $taxonomies = [];

    foreach ($this->v2Taxonomies() as $v2Taxonomy) {
      $target = $this->targetFor($v2Taxonomy);
      $terms  = $this->v2Terms($v2Taxonomy);
      $filter = $filters->get($target['taxonomy']);

      $existing = 0;
      foreach ($terms as $term) {
        if (taxonomy_exists($target['taxonomy']) && $this->findExistingTerm($term, $target['taxonomy'])) {
          $existing++;
        }
      }

      $v2Object = get_taxonomy($v2Taxonomy);

      $taxonomies[] = [
        'v2_taxonomy' => $v2Taxonomy,
        'v2_label'    => $v2Object ? $v2Object->labels->name : $v2Taxonomy,
        'term_count'  => count($terms),
        'used_count'  => count(array_filter($terms, fn($t) => (int) $t->count > 0)),
        'target'      => $target['taxonomy'],
        'label'       => $filter['label'] ?? $target['label'],
        'filter'      => $filter ? (!empty($filter['builtin']) ? 'builtin' : 'existing') : 'new',
        'existing'    => $existing,
        'known'       => $target['known'],
      ];
    }

    return [
      'total'      => $this->countV2Posts(),
      'migrated'   => $this->countMigratedPosts(),
      'trashed'    => $this->countTrashedV2Posts(),
      'taxonomies' => $taxonomies,
      'v2_active'  => post_type_exists(self::V2_POST_TYPE),
      'state'      => $this->getState(),
    ];
  }

  // ---------------------------------------------------------------------------
  // Step 1: filters, terms, settings
  // ---------------------------------------------------------------------------

  public function setup(): void {
    $state = $this->getState() ?: [
      'started'         => time(),
      'finished'        => null,
      'v2_base'         => $this->v2ArchiveSlug(),
      'taxonomies'      => [],
      'term_map'        => [],
      'created_terms'   => [],
      'created_filters' => [],
      'enabled_filters' => [],
      'card_taxonomy'   => null,
      'options_prev'    => [],
      'failed'          => [],
      'contained'       => [],
    ];
    // A fresh run (or a re-run) retries posts that failed last time.
    $state['failed'] = [];
    $state['finished'] = null;

    // Keep per-post entries from earlier runs; the site-wide ones are about to be re-made.
    $this->step = 'setup';
    $this->log = array_values(array_filter(
      $this->getLog(),
      fn(array $entry): bool => !in_array($entry['step'] ?? '', ['setup', 'finish'], true)
    ));

    $state = $this->setupFilters($state);
    $state = $this->setupTerms($state);
    $state = $this->setupCardTaxonomy($state);
    $state = $this->setupSettings($state);

    $this->saveState($state);
    $this->saveLog();
  }

  /**
   * Make sure every V2 taxonomy has a V3 filter (and so a registered taxonomy).
   *
   * @param array<string, mixed> $state
   * @return array<string, mixed>
   */
  private function setupFilters(array $state): array {
    $filters = $this->filterManager->all()->values()->all();
    $byKey   = array_column($filters, null, 'meta_key');
    $changed = false;
    $i       = 0;

    foreach ($this->v2Taxonomies() as $v2Taxonomy) {
      $target = $this->targetFor($v2Taxonomy);
      $terms  = $this->v2Terms($v2Taxonomy);
      $state['taxonomies'][$v2Taxonomy] = $target;

      if (!$terms) {
        continue;
      }

      $hasUsedTerms = (bool) array_filter($terms, fn($t) => (int) $t->count > 0);
      $key = $target['taxonomy'];

      if (isset($byKey[$key])) {
        // Turn on an existing filter (e.g. the built-in Providers one) if V2 posts use it.
        if ($hasUsedTerms && empty($byKey[$key]['enabled'])) {
          foreach ($filters as &$filter) {
            if ($filter['meta_key'] === $key) {
              $filter['enabled'] = true;
            }
          }
          unset($filter);
          $state['enabled_filters'][] = $key;
          $changed = true;
          $this->addLog(self::LEVEL_INFO, "Turned on the \"{$byKey[$key]['label']}\" filter.");
        }
        continue;
      }

      $filters[] = [
        'id'         => 'f' . ((int) (microtime(true) * 1000) + $i++),
        'label'      => $target['label'],
        'meta_key'   => $key,
        'display'    => 'checkbox',
        'enabled'    => $hasUsedTerms,
        'searchable' => false,
        'builtin'    => false,
        'tag_label'  => '',
      ];
      $byKey[$key] = end($filters);
      $state['created_filters'][] = $key;
      $changed = true;

      $this->addLog(self::LEVEL_INFO, "Created the \"{$target['label']}\" filter for V2's {$v2Taxonomy} terms.");

      if (!$target['known']) {
        $this->addLog(self::LEVEL_REVIEW, "V2 posts used an extra taxonomy ({$v2Taxonomy}). It was copied into a new \"{$target['label']}\" filter. Check the label and whether it should show on the archive in Before & Afters → Filter Settings.");
      }
    }

    if ($changed) {
      $this->filterManager->save($filters);
    }

    // V3 taxonomies are registered from the filter list on `init`, which has already run.
    $this->taxonomyRegistrar->registerTaxonomies();

    return $state;
  }

  /**
   * Copy V2 terms into the matching V3 taxonomy, reusing terms that already exist.
   *
   * @param array<string, mixed> $state
   * @return array<string, mixed>
   */
  private function setupTerms(array $state): array {
    $createdCount = [];
    $matchedCount = [];

    foreach ($state['taxonomies'] as $v2Taxonomy => $target) {
      $v3Taxonomy = $target['taxonomy'];
      if (!taxonomy_exists($v3Taxonomy)) {
        $this->addLog(self::LEVEL_MANUAL, "Couldn't set up the {$v3Taxonomy} taxonomy, so V2's {$v2Taxonomy} terms weren't copied.");
        continue;
      }

      // Parents are ordered first, so each parent is mapped before its children.
      $terms = $this->v2Terms($v2Taxonomy);
      $pending = $terms;
      $guard = count($terms) + 1;

      while ($pending && $guard-- > 0) {
        $next = [];
        foreach ($pending as $term) {
          $v2Id = (int) $term->term_id;
          if (isset($state['term_map'][$v2Id])) {
            continue;
          }

          $parent = (int) $term->parent;
          if ($parent && !isset($state['term_map'][$parent])) {
            $next[] = $term;
            continue;
          }

          $existing = $this->findExistingTerm($term, $v3Taxonomy);
          if ($existing) {
            $state['term_map'][$v2Id] = $existing;
            $matchedCount[$v3Taxonomy] = ($matchedCount[$v3Taxonomy] ?? 0) + 1;
            continue;
          }

          $result = wp_insert_term(wp_specialchars_decode($term->name, ENT_QUOTES), $v3Taxonomy, [
            'slug'        => $term->slug,
            'description' => $term->description,
            'parent'      => $parent ? (int) $state['term_map'][$parent] : 0,
          ]);

          if (is_wp_error($result)) {
            $this->addLog(self::LEVEL_MANUAL, "Couldn't create the term \"{$term->name}\" in {$target['label']}: {$result->get_error_message()}");
            continue;
          }

          $newId = (int) $result['term_id'];
          $state['term_map'][$v2Id] = $newId;
          $state['created_terms'][] = ['id' => $newId, 'taxonomy' => $v3Taxonomy];
          $createdCount[$v3Taxonomy] = ($createdCount[$v3Taxonomy] ?? 0) + 1;

          foreach (get_term_meta($v2Id) ?: [] as $metaKey => $values) {
            foreach ($values as $value) {
              add_term_meta($newId, $metaKey, wp_slash(maybe_unserialize($value)));
            }
          }
        }

        // Children whose parent never got mapped go in at the top level next time round.
        if (count($next) === count($pending)) {
          foreach ($next as $orphan) {
            $orphan->parent = 0;
          }
        }
        $pending = $next;
      }
    }

    foreach ($state['taxonomies'] as $target) {
      $tax = $target['taxonomy'];
      $created = $createdCount[$tax] ?? 0;
      $matched = $matchedCount[$tax] ?? 0;
      if ($created || $matched) {
        $parts = array_filter([
          $created ? "created {$created} term" . ($created === 1 ? '' : 's') : '',
          $matched ? "matched {$matched} existing term" . ($matched === 1 ? '' : 's') : '',
        ]);
        $this->addLog(self::LEVEL_INFO, "{$target['label']}: " . implode(', ', $parts) . '.');
      }
    }

    return $state;
  }

  private function findExistingTerm(object $v2Term, string $v3Taxonomy): int {
    $bySlug = get_term_by('slug', $v2Term->slug, $v3Taxonomy);
    if ($bySlug) {
      return (int) $bySlug->term_id;
    }

    $byName = get_term_by('name', wp_specialchars_decode($v2Term->name, ENT_QUOTES), $v3Taxonomy);
    return $byName ? (int) $byName->term_id : 0;
  }

  /**
   * @param array<string, mixed> $state
   * @return array<string, mixed>
   */
  private function setupCardTaxonomy(array $state): array {
    if ($this->filterManager->getCardTaxonomy() !== '' || $state['card_taxonomy'] !== null) {
      return $state;
    }

    $categories = $state['taxonomies']['ll_bag_category']['taxonomy'] ?? null;
    if ($categories && taxonomy_exists($categories)) {
      $state['card_taxonomy'] = '';
      $this->filterManager->saveCardTaxonomy($categories);
      $this->addLog(self::LEVEL_INFO, 'Set the categories to show as pills on post cards. Change this in Before & Afters → Filter Settings → Show on Cards.');
    }

    return $state;
  }

  /**
   * Bring over the V2 settings that have a direct V3 equivalent, only where the V3 setting
   * hasn't been filled in yet. Everything else is logged for a person to look at.
   *
   * @param array<string, mixed> $state
   * @return array<string, mixed>
   */
  private function setupSettings(array $state): array {
    $state = $this->setupCta($state);

    // NSFW popup: V2 is a WYSIWYG with its own heading, V3 is one line of plain text.
    $v2Nsfw = (string) get_option('options_bag_nsfw_popup_content');
    $v3Nsfw = trim((string) get_option('options_ll_bag_nsfw_popup_text'));
    if (trim(wp_strip_all_tags($v2Nsfw)) !== '') {
      $plain = $this->plainText(preg_replace('#<h[1-6][^>]*>.*?</h[1-6]>#is', '', $v2Nsfw));
      if ($plain !== '' && ($v3Nsfw === '' || $v3Nsfw === self::DEFAULT_NSFW_TEXT)) {
        $state = $this->setOption($state, 'll_bag_nsfw_popup_text', 'field_ll_bag_nsfw_popup_text', $plain);
        $this->addLog(self::LEVEL_REVIEW, "Copied V2's NSFW popup text into Settings → NSFW Popup: \"{$plain}\". V3 shows plain text only, so any V2 heading or formatting was dropped.");
      } elseif ($plain !== '' && $plain !== $v3Nsfw) {
        $this->addLog(self::LEVEL_INFO, "V3 already has its own NSFW popup text, so V2's wasn't copied. V2's was: \"{$plain}\"");
      }
    }

    if (get_option('options_enable_nsfw_popup') === '0') {
      $this->addLog(self::LEVEL_REVIEW, "V2's NSFW warning was turned off, but V3 always shows it on posts marked Sensitive Images. Posts were copied with their V2 NSFW setting.");
    }

    // Archive hero: copy V2's banner text onto the V3 archive page if that page has none.
    $v2Hero = (string) get_option('options_archive_page_hero_banner');
    $pageId = (int) get_option('options_ll_bag_posts_page');
    if (trim(wp_strip_all_tags($v2Hero)) !== '') {
      if (!$pageId) {
        $this->addLog(self::LEVEL_MANUAL, 'V2 had archive banner text, but no V3 archive page is set yet (Settings → Archive Settings), so it wasn\'t copied. Copy it by hand once the page is chosen.');
      } elseif (trim(wp_strip_all_tags((string) get_post_meta($pageId, 'll_ba_hero_banner_content', true))) === '') {
        $state['options_prev']['__hero'] = [
          'page'  => $pageId,
          'value' => get_post_meta($pageId, 'll_ba_hero_banner_content', true),
          'ref'   => get_post_meta($pageId, '_ll_ba_hero_banner_content', true),
        ];
        update_post_meta($pageId, 'll_ba_hero_banner_content', wp_slash($v2Hero));
        update_post_meta($pageId, '_ll_ba_hero_banner_content', 'field_ll_ba_hero_banner_content');
        $this->addLog(self::LEVEL_REVIEW, 'Copied V2\'s archive banner text onto the V3 archive page. Check its heading styles, since V2 and V3 use different classes.', null, $pageId);
      }
    }

    // Things with no V3 equivalent.
    $colors = array_filter([
      'archive background' => get_option('options_archive_page_background_color'),
      'single page background' => get_option('options_single_page_background_color'),
      'banner background' => get_option('options_bag_archive_banner_background_color'),
    ]);
    if ($colors) {
      $list = implode(', ', array_map(fn($k, $v) => "{$k} {$v}", array_keys($colors), $colors));
      $this->addLog(self::LEVEL_REVIEW, "V2 colours ({$list}) weren't copied. V3 colours come from ba-colors.css and can be overridden in the theme.");
    }

    if (get_option('options_bag_archive_use_landing_page') === '1' || get_option('options_bag_archive_banner_treatment_category_buttons') === '1') {
      $this->addLog(self::LEVEL_REVIEW, 'V2 showed treatment categories as buttons or a landing page on the archive. The closest V3 option is Settings → Category Settings → "Use Category Archive", plus the Hero Navigation list.');
    }

    if (get_option('options_bag_treatment_landing_page_image')) {
      $this->addLog(self::LEVEL_REVIEW, 'V2 had a landing page image. V3 uses a background image on each category term instead (edit the category → Background Image).');
    }

    return $state;
  }

  /**
   * V2 stored a CTA heading + link on every post. V3 has one global CTA (Settings) that a
   * category term can override. Use the most common V2 CTA as the global one when V3's is
   * empty or still a "#" placeholder, and log any posts that used something different.
   *
   * @param array<string, mixed> $state
   * @return array<string, mixed>
   */
  private function setupCta(array $state): array {
    global $wpdb;

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT p.ID,
              MAX(CASE WHEN pm.meta_key = 'bag_single_link_section_bag_single_link_heading' THEN pm.meta_value END) AS heading,
              MAX(CASE WHEN pm.meta_key = 'bag_single_link_section_bag_single_link' THEN pm.meta_value END) AS link
       FROM {$wpdb->posts} p
       JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
       WHERE p.post_type = %s AND p.{$this->statusSql()}
         AND pm.meta_key IN ('bag_single_link_section_bag_single_link_heading', 'bag_single_link_section_bag_single_link')
       GROUP BY p.ID",
      self::V2_POST_TYPE
    ));

    $groups = [];
    foreach ($rows as $row) {
      $link = maybe_unserialize($row->link);
      if (!is_array($link) || empty($link['url'])) {
        continue;
      }
      $cta = [
        'title' => trim((string) $row->heading),
        'link'  => [
          'title'  => (string) ($link['title'] ?? ''),
          'url'    => (string) $link['url'],
          'target' => (string) ($link['target'] ?? ''),
        ],
      ];
      $key = md5(serialize($cta));
      $groups[$key] ??= ['cta' => $cta, 'posts' => []];
      $groups[$key]['posts'][] = (int) $row->ID;
    }

    if (!$groups) {
      return $state;
    }

    uasort($groups, fn($a, $b) => count($b['posts']) <=> count($a['posts']));
    $common = reset($groups)['cta'];

    $global = [
      'title' => trim((string) get_option('options_ll_ba_global_cta_title')),
      'link'  => maybe_unserialize(get_option('options_ll_ba_global_cta_link')),
    ];
    $globalUrl = is_array($global['link']) ? trim((string) ($global['link']['url'] ?? '')) : '';

    if ($globalUrl === '' || $globalUrl === '#') {
      $state = $this->setOption($state, 'll_ba_global_cta_link', 'field_ll_ba_global_cta_link', $common['link']);
      if ($common['title'] !== '') {
        $state = $this->setOption($state, 'll_ba_global_cta_title', 'field_ll_ba_global_cta_title', $common['title']);
      }
      $global = ['title' => $common['title'] ?: $global['title'], 'link' => $common['link']];
      $this->addLog(self::LEVEL_REVIEW, "Set the global CTA (Settings → Global Options) to V2's most common one: \"{$common['title']}\" → {$common['link']['title']} ({$common['link']['url']}).");
    }

    foreach ($groups as $group) {
      $cta = $group['cta'];
      $sameLink = is_array($global['link']) && ($global['link']['url'] ?? '') === $cta['link']['url'] && ($global['link']['title'] ?? '') === $cta['link']['title'];
      if ($sameLink && ($cta['title'] === '' || $cta['title'] === $global['title'])) {
        continue;
      }

      $count = count($group['posts']);
      $this->addLog(
        self::LEVEL_MANUAL,
        "{$count} V2 post" . ($count === 1 ? '' : 's') . " used the CTA \"{$cta['title']}\" → {$cta['link']['title']} ({$cta['link']['url']}), which differs from the V3 global CTA. V3 has no per-post CTA: set it on the post's category instead (edit the category → Before & After CTA), or change the global one.",
        $group['posts']
      );
    }

    return $state;
  }

  /**
   * Write an ACF options-page value, remembering the old raw value for undo.
   *
   * @param array<string, mixed> $state
   * @return array<string, mixed>
   */
  private function setOption(array $state, string $name, string $fieldKey, mixed $value): array {
    if (!array_key_exists($name, $state['options_prev'])) {
      $state['options_prev'][$name] = get_option("options_{$name}", null);
    }

    if (function_exists('update_field')) {
      update_field($fieldKey, $value, 'option');
    } else {
      update_option("options_{$name}", $value);
      update_option("_options_{$name}", $fieldKey);
    }

    return $state;
  }

  // ---------------------------------------------------------------------------
  // Step 2: posts
  // ---------------------------------------------------------------------------

  /**
   * Migrate the next batch of posts.
   *
   * @return array{processed: int, remaining: int}
   */
  public function migrateBatch(int $limit = self::BATCH_SIZE): array {
    $state = $this->getState();
    if (!$state) {
      throw new \RuntimeException('Run setup first.');
    }

    $this->log = $this->getLog();
    $ids = $this->pendingV2PostIds($limit, $state['failed']);

    foreach ($ids as $id) {
      $post = get_post($id);
      if (!$post instanceof WP_Post) {
        $state['failed'][] = $id;
        continue;
      }

      try {
        if (!$this->migratePost($post, $state)) {
          $state['failed'][] = $id;
        }
      } catch (\Throwable $e) {
        $state['failed'][] = $id;
        $this->addLog(self::LEVEL_MANUAL, "Couldn't migrate this post: {$e->getMessage()}", [$id]);
      }
    }

    $state['contained'] = array_values(array_unique(array_merge($state['contained'] ?? [], $this->contained)));
    $this->saveState($state);
    $this->saveLog();

    return [
      'processed' => count($ids),
      'remaining' => count($this->pendingV2PostIds(PHP_INT_MAX, $state['failed'])),
    ];
  }

  /**
   * @param array<string, mixed> $state
   */
  private function migratePost(WP_Post $v2, array $state): bool {
    $newId = wp_insert_post(wp_slash([
      'post_type'      => BeforeAfterPostType::SLUG,
      'post_title'     => $v2->post_title,
      'post_name'      => $v2->post_name,
      'post_status'    => $v2->post_status,
      'post_author'    => $v2->post_author,
      'post_date'      => $v2->post_date,
      'post_date_gmt'  => $v2->post_date_gmt,
      'post_password'  => $v2->post_password,
      'menu_order'     => $v2->menu_order,
      'post_content'   => $v2->post_content,
      'post_excerpt'   => $v2->post_excerpt,
      'comment_status' => 'closed',
      'ping_status'    => 'closed',
    ]), true);

    if (is_wp_error($newId)) {
      $this->addLog(self::LEVEL_MANUAL, "Couldn't create the V3 post: {$newId->get_error_message()}", [$v2->ID]);
      return false;
    }

    // Mark it straight away so a crash further down can't lead to a duplicate on re-run.
    update_post_meta($newId, self::SOURCE_META, $v2->ID);

    try {
      $this->fillPost($v2, $newId, $state);
    } catch (\Throwable $e) {
      // Remove the half-made post so the next run tries this one again from scratch.
      wp_delete_post($newId, true);
      throw $e;
    }

    return true;
  }

  /**
   * @param array<string, mixed> $state
   */
  private function fillPost(WP_Post $v2, int $newId, array $state): void {
    $v2Id = $v2->ID;
    $meta = fn(string $key): string => (string) get_post_meta($v2Id, $key, true);

    if ($v2->post_name !== '' && get_post_field('post_name', $newId) !== $v2->post_name) {
      $this->addLog(self::LEVEL_REVIEW, "The slug \"{$v2->post_name}\" was already taken, so this post's URL ends in \"" . get_post_field('post_name', $newId) . '" instead.', [$v2Id], $newId);
    }

    if ($v2->post_parent) {
      $this->addLog(self::LEVEL_INFO, 'This post had a parent post in V2. V3 posts are not nested, so it was copied at the top level.', [$v2Id], $newId);
    }

    if (trim(wp_strip_all_tags($v2->post_content)) !== '') {
      $this->addLog(self::LEVEL_MANUAL, 'This post had body content, which V3 doesn\'t show. Move it into a Detail Section if it\'s needed.', [$v2Id], $newId);
    }

    $this->migrateTerms($v2Id, $newId, $state);
    $this->copyOtherMeta($v2Id, $newId);

    // Single Page Label
    $title = trim($meta('bag_single_treatment_title'));
    if ($title !== '') {
      update_field('field_ll_ba_title', $title, $newId);
    }

    // Patient / Treatment details → Detail Sections
    $sections = [];
    foreach ([['top', 'Patient Details'], ['bottom', 'Treatment Details']] as [$where, $default]) {
      $details = trim($meta("bag_single_{$where}_content_details"));
      if ($details === '') continue;
      $heading = trim($meta("bag_single_{$where}_content_heading"));
      $sections[] = [
        'field_ll_ba_detail_title'             => $heading !== '' ? $heading : $default,
        'field_ll_ba_detail_content'           => $details,
        'field_ll_ba_detail_read_more_content' => '',
      ];
    }
    if ($sections) {
      update_field('field_ll_ba_detail_sections', $sections, $newId);
    }

    // NSFW
    update_field('field_ll_ba_is_nsfw', $meta('bag_single_nsfw') === '1' ? 1 : 0, $newId);

    $this->migrateImages($v2Id, $newId);
  }

  /**
   * @param array<string, mixed> $state
   */
  private function migrateTerms(int $v2Id, int $newId, array $state): void {
    foreach ($this->v2PostTerms($v2Id) as $v2Taxonomy => $termIds) {
      $target = $state['taxonomies'][$v2Taxonomy] ?? null;
      if (!$target || !taxonomy_exists($target['taxonomy'])) {
        if ($v2Taxonomy !== 'post_format') {
          $this->addLog(self::LEVEL_MANUAL, "This post's {$v2Taxonomy} terms had nowhere to go in V3 and weren't copied.", [$v2Id], $newId);
        }
        continue;
      }

      $mapped = array_values(array_filter(array_map(fn($id) => $state['term_map'][$id] ?? 0, $termIds)));
      if ($mapped) {
        wp_set_object_terms($newId, $mapped, $target['taxonomy']);
      }

      // Yoast's primary term, which V3 also uses to pick the category CTA.
      $primary = (int) get_post_meta($v2Id, '_yoast_wpseo_primary_' . $v2Taxonomy, true);
      if ($primary && isset($state['term_map'][$primary])) {
        update_post_meta($newId, '_yoast_wpseo_primary_' . $target['taxonomy'], $state['term_map'][$primary]);
      }
    }
  }

  /**
   * Copy meta that isn't V2's own fields (Yoast SEO, schema, other plugins).
   */
  private function copyOtherMeta(int $v2Id, int $newId): void {
    foreach (get_post_meta($v2Id) ?: [] as $key => $values) {
      if (
        preg_match('/^_?bag_/', $key)
        || preg_match('/^_?ll_ba_/', $key)
        || str_starts_with($key, '_yoast_wpseo_primary_')
        || in_array($key, ['_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', self::SOURCE_META], true)
      ) {
        continue;
      }

      foreach ($values as $value) {
        add_post_meta($newId, $key, wp_slash(maybe_unserialize($value)));
      }
    }
  }

  private function migrateImages(int $v2Id, int $newId): void {
    $count = (int) get_post_meta($v2Id, 'bag_single_photos_and_videos', true);
    $meta  = fn(int $i, string $key): string => (string) get_post_meta($v2Id, "bag_single_photos_and_videos_{$i}_{$key}", true);

    $rows       = [];
    $contained  = [];
    $captions   = [];

    for ($i = 0; $i < $count; $i++) {
      $n    = $i + 1;
      $type = $meta($i, 'bag_single_content_type');

      $caption = array_filter([
        trim($meta($i, 'bag_image_title')),
        trim($meta($i, 'bag_image_description')),
      ]);
      if ($caption) {
        $captions[] = "image {$n}: \"" . implode(' — ', $caption) . '"';
      }
      $ada = trim($meta($i, 'bag_image_description_ada'));
      if ($ada !== '') {
        $this->addLog(self::LEVEL_MANUAL, "Image {$n} had a screen-reader description: \"{$ada}\". V3 uses each image's Alt Text in the Media Library, so add it there.", [$v2Id], $newId);
      }

      $row = $this->emptyImageRow();

      if ($type === 'two') {
        $before = $this->attachmentId($meta($i, 'bag_single_two_images_first'));
        $after  = $this->attachmentId($meta($i, 'bag_single_two_images_second'));
        if (!$before || !$after) {
          $this->addLog(self::LEVEL_MANUAL, "Image {$n} is a two-image row missing its " . (!$before ? 'before' : 'after') . ' image (it may have been deleted from the Media Library), so the row was skipped.', [$v2Id], $newId);
          continue;
        }

        $aspects = array_filter([$this->aspect($before), $this->aspect($after)]);
        $aspect  = $aspects ? array_sum($aspects) / count($aspects) : null;
        $fallback = $meta($i, 'bag_single_image_orientation') === 'horizontal' ? 'wide' : 'vertical';
        [$ratio, $fits] = $this->closestRatio($aspect, $fallback);

        $row['field_ll_ba_image_options']     = 'two-images';
        $row['field_ll_ba_image_ratio']       = $ratio;
        $row['field_ll_ba_before_image']      = $before;
        $row['field_ll_ba_after_image']       = $after;
        $row['field_ll_ba_comparison_slider'] = $meta($i, 'bag_single_display_style') === 'slider' ? 1 : 0;
        if (!$fits) {
          $row['field_ll_ba_before_focal'] = self::CONTAIN_FOCAL;
          $row['field_ll_ba_after_focal']  = self::CONTAIN_FOCAL;
          $contained[] = $n;
        }
      } elseif ($type === 'video') {
        $url = trim($meta($i, 'bag_single_video_url'));
        if ($url === '') {
          $this->addLog(self::LEVEL_MANUAL, "Image {$n} is a video row with no video URL, so it was skipped.", [$v2Id], $newId);
          continue;
        }
        $still = $this->attachmentId($meta($i, 'bag_single_video_still_image'));
        if (!$still) {
          $this->addLog(self::LEVEL_MANUAL, "Video {$n} has no still image. V3 uses that image as the video's thumbnail, so add one.", [$v2Id], $newId);
        }

        $row['field_ll_ba_image_options'] = 'video';
        $row['field_ll_ba_single_image']  = $still ?: '';
        $row['field_ll_ba_video_url']     = $url;
        $row['field_ll_ba_video_title']   = trim($meta($i, 'bag_image_title')) ?: get_the_title($v2Id) . ' video';
      } else {
        // 'one', and anything unexpected that still has an image.
        $image = $this->attachmentId($meta($i, 'bag_single_single_image'));
        if (!$image) {
          $this->addLog(self::LEVEL_MANUAL, "Image {$n} has no image (it may have been deleted from the Media Library), so the row was skipped.", [$v2Id], $newId);
          continue;
        }

        [$row, $fits] = $this->oneImageRow($row, $image);
        if (!$fits) {
          $contained[] = $n;
        }
      }

      $rows[] = $row;
    }

    // V2's "Gallery Image" was the card image. With no Photos and Videos rows it's the only
    // image the post has, so make it the single image row.
    $cardImage = $this->attachmentId((string) get_post_meta($v2Id, 'bag_single_gallery_image', true));
    $usedCardImage = false;
    if (!$rows && $cardImage) {
      [$row, $fits] = $this->oneImageRow($this->emptyImageRow(), $cardImage);
      $rows[] = $row;
      $usedCardImage = true;
      if (!$fits) {
        $contained[] = 1;
      }
      $this->addLog(self::LEVEL_INFO, 'This post had no Photos and Videos in V2, so its Gallery Image was used as its only image.', [$v2Id], $newId);
    }

    if ($rows) {
      update_field('field_ll_ba_images', $rows, $newId);
    } else {
      $this->addLog(self::LEVEL_MANUAL, 'This post has no images (no Photos and Videos and no Gallery Image), so its card will be empty. Add images or unpublish it.', [$v2Id], $newId);
    }

    if ($captions) {
      $this->addLog(self::LEVEL_MANUAL, 'V2 showed captions under these images: ' . implode('; ', $captions) . '. V3 has no image captions; add them to a Detail Section if they matter.', [$v2Id], $newId);
    }

    if ($contained) {
      // Logged as one grouped entry in finish(); one line per post would bury everything else.
      $this->contained[] = $v2Id;
    }

    // V2 could use a separate image on the archive card; V3 always uses the first gallery row.
    if ($cardImage && $rows && !$usedCardImage) {
      $first = $rows[0];
      if (!in_array($cardImage, [$first['field_ll_ba_single_image'], $first['field_ll_ba_before_image'], $first['field_ll_ba_after_image']], true)) {
        $this->addLog(self::LEVEL_MANUAL, "V2 used a separate \"Gallery Image\" (Media Library #{$cardImage}) on this post's card. V3 always uses the first image row on the card, so add that image as the first row, or drag the right row to the top.", [$v2Id], $newId);
      }
    }
  }

  /**
   * @return array<string, mixed>
   */
  private function emptyImageRow(): array {
    return [
      'field_ll_ba_image_options'     => '',
      'field_ll_ba_image_ratio'       => '',
      'field_ll_ba_single_image'      => '',
      'field_ll_ba_single_fill'       => 0,
      'field_ll_ba_single_focal'      => '',
      'field_ll_ba_before_image'      => '',
      'field_ll_ba_after_image'       => '',
      'field_ll_ba_before_focal'      => '',
      'field_ll_ba_after_focal'       => '',
      'field_ll_ba_comparison_slider' => 0,
      'field_ll_ba_video_url'         => '',
      'field_ll_ba_video_title'       => '',
    ];
  }

  /**
   * Fill in a one-image row, picking the closest frame shape.
   *
   * @param array<string, mixed> $row
   * @return array{0: array<string, mixed>, 1: bool} [row, whether the image fits its frame closely]
   */
  private function oneImageRow(array $row, int $image): array {
    [$ratio, $fits] = $this->closestRatio($this->aspect($image), 'wide');
    $row['field_ll_ba_image_options'] = 'one-image';
    $row['field_ll_ba_image_ratio']   = $ratio;
    $row['field_ll_ba_single_image']  = $image;
    if (!$fits) {
      $row['field_ll_ba_single_fill']  = 1;
      $row['field_ll_ba_single_focal'] = self::CONTAIN_FOCAL;
    }
    return [$row, $fits];
  }

  private function attachmentId(string $value): int {
    $id = (int) $value;
    return $id && get_post_type($id) === 'attachment' ? $id : 0;
  }

  private function aspect(int $attachmentId): ?float {
    $data = wp_get_attachment_metadata($attachmentId);
    $w = (int) ($data['width'] ?? 0);
    $h = (int) ($data['height'] ?? 0);
    return $w && $h ? $w / $h : null;
  }

  /**
   * @return array{0: string, 1: bool} [ratio key, whether the image fits that frame closely]
   */
  private function closestRatio(?float $aspect, string $fallback): array {
    if (!$aspect) {
      return [$fallback, true];
    }

    $best = $fallback;
    $bestDiff = INF;
    foreach (self::RATIOS as $key => $ratio) {
      $diff = abs(log($aspect / $ratio));
      if ($diff < $bestDiff) {
        $best = $key;
        $bestDiff = $diff;
      }
    }

    return [$best, $bestDiff <= self::RATIO_TOLERANCE];
  }

  // ---------------------------------------------------------------------------
  // Step 3: finish
  // ---------------------------------------------------------------------------

  public function finish(): void {
    $state = $this->getState();
    if (!$state) return;

    $this->step = 'finish';
    $this->log = $this->getLog();

    if (!empty($state['contained'])) {
      $count = count($state['contained']);
      $this->addLog(
        self::LEVEL_REVIEW,
        "{$count} post" . ($count === 1 ? ' has' : 's have') . " images that aren't one of V3's frame shapes (wide, square, panorama, vertical). They were given the closest frame and set to show the whole image, with space around the edges, so nothing is cut off. Open a post's Crop & Zoom to fill the frame instead.",
        $state['contained']
      );
    }

    if ($state['failed']) {
      $this->addLog(self::LEVEL_MANUAL, count($state['failed']) . ' post(s) could not be migrated. See the entries above; run the migration again to retry them.', $state['failed']);
    }

    $state['finished'] = time();
    $this->saveState($state);

    // Pick up the new taxonomies' rewrite rules.
    flush_rewrite_rules(false);

    $this->addLog(self::LEVEL_INFO, 'Migration finished. ' . $this->countMigratedPosts() . ' post(s) are now in V3.');
    $this->saveLog();
  }

  // ---------------------------------------------------------------------------
  // Undo
  // ---------------------------------------------------------------------------

  /**
   * Delete everything the migration created and put changed settings back.
   * V2 data was never touched, so it's all still there.
   *
   * @return array{done: bool, deleted: int}
   */
  public function rollbackBatch(int $limit = 25): array {
    global $wpdb;

    $ids = array_map('intval', $wpdb->get_col($wpdb->prepare(
      "SELECT pm.post_id FROM {$wpdb->postmeta} pm
       JOIN {$wpdb->posts} p ON p.ID = pm.post_id
       WHERE pm.meta_key = %s AND p.post_type = %s
       LIMIT %d",
      self::SOURCE_META,
      BeforeAfterPostType::SLUG,
      $limit
    )));

    foreach ($ids as $id) {
      wp_delete_post($id, true);
    }

    if ($ids) {
      return ['done' => false, 'deleted' => count($ids)];
    }

    $state = $this->getState();
    if ($state) {
      foreach ($state['created_terms'] as $term) {
        if (taxonomy_exists($term['taxonomy'])) {
          wp_delete_term((int) $term['id'], $term['taxonomy']);
        }
      }

      $filters = $this->filterManager->all()->values()->all();
      $filters = array_values(array_filter($filters, fn($f) => !in_array($f['meta_key'], $state['created_filters'], true)));
      foreach ($filters as &$filter) {
        if (in_array($filter['meta_key'], $state['enabled_filters'], true)) {
          $filter['enabled'] = false;
        }
      }
      unset($filter);
      $this->filterManager->save($filters);

      if ($state['card_taxonomy'] !== null) {
        $this->filterManager->saveCardTaxonomy((string) $state['card_taxonomy']);
      }

      foreach ($state['options_prev'] as $name => $previous) {
        if ($name === '__hero') {
          update_post_meta($previous['page'], 'll_ba_hero_banner_content', wp_slash((string) $previous['value']));
          if ($previous['ref'] === '') {
            delete_post_meta($previous['page'], '_ll_ba_hero_banner_content');
          }
          continue;
        }
        if ($previous === null) {
          delete_option("options_{$name}");
        } else {
          update_option("options_{$name}", $previous);
        }
      }
    }

    delete_option(self::STATE_OPTION);
    delete_option(self::LOG_OPTION);
    flush_rewrite_rules(false);

    return ['done' => true, 'deleted' => 0];
  }

  // ---------------------------------------------------------------------------
  // Old URL → new URL
  // ---------------------------------------------------------------------------

  /**
   * Old V2 URL => new V3 URL for every migrated post and term, for redirects.
   *
   * @return array<string, string>
   */
  public function urlMap(): array {
    global $wpdb;

    $state = $this->getState();
    if (!$state) return [];

    $base = trailingslashit(home_url('/' . $state['v2_base']));
    $map  = [];

    $archive = get_post_type_archive_link(BeforeAfterPostType::SLUG);
    if ($archive) {
      $map[$base] = $archive;
    }

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT pm.post_id AS new_id, v2.post_name AS old_slug
       FROM {$wpdb->postmeta} pm
       JOIN {$wpdb->posts} v2 ON v2.ID = pm.meta_value
       JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = %s
       WHERE pm.meta_key = %s",
      BeforeAfterPostType::SLUG,
      self::SOURCE_META
    ));
    foreach ($rows as $row) {
      if ($row->old_slug === '') continue;
      $map[$base . $row->old_slug . '/'] = get_permalink((int) $row->new_id);
    }

    if ($archive) {
      foreach ($state['taxonomies'] as $v2Taxonomy => $target) {
        if (empty($target['rewrite'])) continue;
        foreach ($this->v2Terms($v2Taxonomy) as $term) {
          $newId = $state['term_map'][(int) $term->term_id] ?? 0;
          $new   = $newId ? get_term($newId, $target['taxonomy']) : null;
          if ($new && !is_wp_error($new)) {
            $map[home_url("/{$target['rewrite']}/{$term->slug}/")] = add_query_arg($target['taxonomy'], $new->slug, $archive);
          }
        }
      }
    }

    return $map;
  }

  // ---------------------------------------------------------------------------
  // State + log
  // ---------------------------------------------------------------------------

  /**
   * @return array<string, mixed>|null
   */
  public function getState(): ?array {
    $state = get_option(self::STATE_OPTION, null);
    return is_array($state) ? $state : null;
  }

  /**
   * @param array<string, mixed> $state
   */
  private function saveState(array $state): void {
    update_option(self::STATE_OPTION, $state, false);
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public function getLog(): array {
    $log = get_option(self::LOG_OPTION, []);
    return is_array($log) ? $log : [];
  }

  private function saveLog(): void {
    update_option(self::LOG_OPTION, $this->log, false);
  }

  /**
   * @param int[] $v2Ids
   */
  private function addLog(string $level, string $message, array $v2Ids = [], ?int $v3Id = null): void {
    $this->log[] = [
      'level'   => $level,
      'message' => $message,
      'v2_ids'  => array_values(array_map('intval', $v2Ids)),
      'v3_id'   => $v3Id,
      'step'    => $this->step,
      'time'    => time(),
    ];
  }

  private function plainText(string $html): string {
    return trim(preg_replace('/\s+/', ' ', html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES)));
  }
}
