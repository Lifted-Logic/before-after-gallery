<?php

namespace LiftedLogic\LLBag\Support;

use LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType;
use LiftedLogic\LLBag\Filters\FilterManager;

/**
 * Picks the "related" posts for a single before & after post.
 *
 * Order of authority:
 *  1. Editor's manual pick: posts having any term saved in `ll_ba_related_terms`, ranked by rarity score
 *     over those terms only.
 *  2. Weighted: posts sharing terms with the current post, scored by sum of ln(N / df) over shared terms.
 *  3. Newest published posts (last resort).
 * Ties in score go to the post closest to the current one in gallery order (menu_order ASC, date DESC),
 * then to the earlier one in that order.
 *
 * Results are cached per post. Every cached payload carries the site-wide generation number it was built
 * under; a change that can alter results bumps the generation, so older payloads are ignored.
 */
class RelatedPosts {
  public const META_KEY        = 'll_ba_related_terms';
  public const GENERATION_KEY  = 'll_bag_related_generation';
  public const POST_ORDER_HOOK = 'll_bag/post_order_saved';
  public const SLOTS           = 6;

  private const TRANSIENT_POST  = 'll_bag_rel_';
  private const TRANSIENT_INDEX = 'll_bag_rel_index';
  private const EXPIRY          = DAY_IN_SECONDS;

  /** @var array<string, mixed>|null Request-local copy of the site index. */
  private ?array $index = null;

  public function __construct(private readonly FilterManager $filterManager) {}

  // ── Invalidation ──────────────────────────────────────────────────────────

  public function register(): void {
    $slug = BeforeAfterPostType::SLUG;

    add_action('save_post_' . $slug,    [$this, 'bump'], 99);
    add_action('trashed_post',          [$this, 'bumpForPost']);
    add_action('untrashed_post',        [$this, 'bumpForPost']);
    add_action('before_delete_post',    [$this, 'bumpForPost']);
    add_action('deleted_post',          [$this, 'bumpForPost'], 10, 2);
    add_action('transition_post_status', [$this, 'bumpForTransition'], 10, 3);
    add_action('set_object_terms',      [$this, 'bumpForObjectTerms'], 10, 4);
    add_action('added_term_relationship',    [$this, 'bumpForObjectTerms'], 10, 3);
    add_action('deleted_term_relationships', [$this, 'bumpForObjectTerms'], 10, 3);
    add_action('added_post_meta',       [$this, 'bumpForMeta'], 10, 3);
    add_action('updated_post_meta',     [$this, 'bumpForMeta'], 10, 3);
    add_action('deleted_post_meta',     [$this, 'bumpForMeta'], 10, 3);
    add_action('edited_term',           [$this, 'bumpForTerm'], 10, 3);
    add_action('created_term',          [$this, 'bumpForTerm'], 10, 3);
    add_action('delete_term',           [$this, 'bumpForTerm'], 10, 3);
    add_action('add_option_'    . FilterManager::OPTION_KEY, [$this, 'bump']);
    add_action('update_option_' . FilterManager::OPTION_KEY, [$this, 'bump']);
    add_action(self::POST_ORDER_HOOK,   [$this, 'bump']);
  }

  /**
   * Invalidate every cached result. Always writes: skipping a later bump in the same request
   * (a debounce) lets another request cache a result between two changes and keep it.
   */
  public function bump(): void {
    update_option(self::GENERATION_KEY, $this->generation() + 1, true);
    $this->index = null;
  }

  public function bumpForPost(int $postId, ?\WP_Post $post = null): void {
    // deleted_post passes the post (WP 5.5+); by then get_post_type() can no longer find it.
    $type = $post !== null ? $post->post_type : get_post_type($postId);

    if ($type === BeforeAfterPostType::SLUG) {
      $this->bump();
    }
  }

  public function bumpForTransition(string $newStatus, string $oldStatus, \WP_Post $post): void {
    if ($post->post_type === BeforeAfterPostType::SLUG && $newStatus !== $oldStatus) {
      $this->bump();
    }
  }

  /**
   * Hooked to set_object_terms (object_id, terms, tt_ids, taxonomy), added_term_relationship
   * (object_id, tt_id, taxonomy) and deleted_term_relationships (object_id, tt_ids, taxonomy); only the
   * object ID is used.
   *
   * @param mixed $objectId
   */
  public function bumpForObjectTerms($objectId): void {
    if (get_post_type((int) $objectId) === BeforeAfterPostType::SLUG) {
      $this->bump();
    }
  }

  /**
   * added_post_meta / updated_post_meta (meta_id, object_id, meta_key, value) and deleted_post_meta
   * (meta_ids, object_id, meta_key, value): any route that writes the manual terms.
   *
   * @param mixed $metaId
   * @param mixed $objectId
   * @param mixed $metaKey
   */
  public function bumpForMeta($metaId, $objectId, $metaKey): void {
    if ($metaKey === self::META_KEY && get_post_type((int) $objectId) === BeforeAfterPostType::SLUG) {
      $this->bump();
    }
  }

  public function bumpForTerm($termId, $ttId, $taxonomy): void {
    if (in_array($taxonomy, $this->taxonomies(), true)) {
      $this->bump();
    }
  }

  private function generation(): int {
    return (int) get_option(self::GENERATION_KEY, 0);
  }

  // ── Public API ────────────────────────────────────────────────────────────

  /**
   * Post IDs in display order.
   *
   * @return int[]
   */
  public function ids(int $postId, int $limit = self::SLOTS): array {
    return array_map(fn(array $pick): int => $pick['id'], $this->explain($postId, $limit));
  }

  /**
   * Picks in display order, with why each one was picked.
   *
   * @return array<int, array{id:int, source:string, shared:array<int, array{taxonomy:string, name:string}>}>
   *         source is 'manual' | 'weighted' | 'newest'; shared lists the terms in common (manual: the
   *         selected terms the pick has; weighted: terms shared with the current post; newest: none).
   */
  public function explain(int $postId, int $limit = self::SLOTS, bool $preview = false): array {
    $limit = max(1, $limit);

    // Only a published before & after is computed and cached. Anything else (made-up ID, other post
    // type, unpublished) gets the newest posts and no transient, so the public AJAX action can't be used
    // to fill wp_options. $preview lets the edit screen explain a saved draft of this type (uncached).
    if (!$this->isPublishedPost($postId)) {
      if ($preview && get_post_type($postId) === BeforeAfterPostType::SLUG) {
        return $this->compute($postId, $limit);
      }

      return $this->newestPicks($limit, [$postId]);
    }

    $gen = $this->generation();
    $key = self::TRANSIENT_POST . $postId . '_' . $limit;

    $cached = get_transient($key);
    if (is_array($cached) && ($cached['gen'] ?? null) === $gen && isset($cached['picks'])) {
      return $cached['picks'];
    }

    $picks = $this->compute($postId, $limit);

    // Stored under the generation read *before* computing: if a change landed meanwhile, this entry is
    // already stale and the next read recomputes.
    set_transient($key, ['gen' => $gen, 'picks' => $picks], self::EXPIRY);

    return $picks;
  }

  private function isPublishedPost(int $postId): bool {
    return $postId > 0
      && get_post_type($postId) === BeforeAfterPostType::SLUG
      && get_post_status($postId) === 'publish';
  }

  /**
   * @param int[] $exclude
   * @return array<int, array{id:int, source:string, shared:array<int, array{taxonomy:string, name:string}>}>
   */
  private function newestPicks(int $count, array $exclude): array {
    $picks = [];
    foreach ($this->newestIds($count, $exclude) as $id) {
      $picks[] = ['id' => $id, 'source' => 'newest', 'shared' => []];
    }
    return $picks;
  }

  // ── Computation ───────────────────────────────────────────────────────────

  /**
   * @return array<int, array{id:int, source:string, shared:array<int, array{taxonomy:string, name:string}>}>
   */
  private function compute(int $postId, int $limit): array {
    $index = $this->index();

    $manual = [];
    if ($postId > 0) {
      $saved = get_post_meta($postId, self::META_KEY, true);
      foreach ((array) ($saved ?: []) as $pair) {
        // Tolerate any saved shape (imports, REST): only strings can be a "taxonomy:slug" pair.
        if (is_string($pair)) {
          $manual[] = $pair;
        }
      }
    }

    $ownKeys = $postId > 0 ? ($index['terms'][$postId] ?? $this->termKeysFor($postId, $index)) : [];

    $picks = self::select($index, $postId, $ownKeys, $manual, $limit);

    if (count($picks) < $limit) {
      $exclude = array_merge([$postId], array_column($picks, 'id'));
      $picks = array_merge($picks, $this->newestPicks($limit - count($picks), $exclude));
    }

    return $picks;
  }

  /**
   * The scoring. Pure: works only on the index it is given.
   *
   * @param array<string, mixed> $index   See buildIndex().
   * @param string[]             $ownKeys Term keys ("taxonomy:term_id") of the current post.
   * @param string[]             $manual  Saved "taxonomy:slug" pairs.
   * @return array<int, array{id:int, source:string, shared:array<int, array{taxonomy:string, name:string}>}>
   */
  public static function select(array $index, int $postId, array $ownKeys, array $manual, int $limit): array {
    $picks = [];
    $taken = [$postId => true];

    // Position of the current post in gallery order. A post that isn't in the index (draft, preview)
    // sits at 0, so ties then simply favour the front of the gallery.
    $position = $index['position'];
    $here     = $position[$postId] ?? 0;

    $n = max(1, (int) $index['n']);
    $idf = static function (string $termKey) use ($index, $n): float {
      $df = (int) ($index['df'][$termKey] ?? 0);
      return $df > 0 ? log($n / $df) : 0.0;
    };

    // Pass 1: the editor's manual terms.
    $manualKeys = [];
    foreach ($manual as $pair) {
      if (isset($index['slugs'][$pair])) {
        $manualKeys[$index['slugs'][$pair]] = true;
      }
    }

    if ($manualKeys) {
      $ranked = self::rank($index, $postId, $manualKeys, $idf, $here, true);
      foreach ($ranked as $row) {
        if (count($picks) >= $limit) break;
        $picks[]            = ['id' => $row['id'], 'source' => 'manual', 'shared' => self::describe($index, $row['shared'])];
        $taken[$row['id']]  = true;
      }
    }

    // Pass 2: weighted by rarity, against every term the current post has.
    if (count($picks) < $limit && $ownKeys) {
      $ranked = self::rank($index, $postId, array_fill_keys($ownKeys, true), $idf, $here, false);
      foreach ($ranked as $row) {
        if (count($picks) >= $limit) break;
        if (isset($taken[$row['id']])) continue;
        $picks[]           = ['id' => $row['id'], 'source' => 'weighted', 'shared' => self::describe($index, $row['shared'])];
        $taken[$row['id']] = true;
      }
    }

    return $picks;
  }

  /**
   * Score every other post against a set of term keys and sort: score DESC, distance from the current
   * post in gallery order ASC, gallery position ASC.
   *
   * @param array<string, true> $keys
   * @param bool                $anyMatch true: any shared term qualifies (manual); false: score must be above zero.
   * @return array<int, array{id:int, score:float, shared:string[]}>
   */
  private static function rank(array $index, int $postId, array $keys, callable $idf, int $here, bool $anyMatch): array {
    $rows = [];
    foreach ($index['ids'] as $pos => $id) {
      if ($id === $postId) continue;

      $shared = [];
      $score  = 0.0;
      foreach ($index['terms'][$id] ?? [] as $termKey) {
        if (isset($keys[$termKey])) {
          $shared[] = $termKey;
          $score   += $idf($termKey);
        }
      }

      if (!$shared || (!$anyMatch && $score <= 0.0)) continue;

      $rows[] = [
        'id'       => $id,
        // Rounded so equal sets of terms always tie, whatever order the floats were added in.
        'score'    => round($score, 9),
        'distance' => abs($pos - $here),
        'pos'      => $pos,
        'shared'   => $shared,
      ];
    }

    usort($rows, static fn(array $a, array $b): int =>
      $b['score'] <=> $a['score'] ?: $a['distance'] <=> $b['distance'] ?: $a['pos'] <=> $b['pos']
    );

    return $rows;
  }

  /**
   * @param string[] $termKeys
   * @return array<int, array{taxonomy:string, name:string}>
   */
  private static function describe(array $index, array $termKeys): array {
    $out = [];
    foreach ($termKeys as $termKey) {
      if (isset($index['names'][$termKey])) {
        $out[] = $index['names'][$termKey];
      }
    }
    return $out;
  }

  /**
   * Newest published posts, excluding some IDs.
   *
   * @param int[] $exclude
   * @return int[]
   */
  private function newestIds(int $count, array $exclude): array {
    $ids = get_posts([
      'post_type'              => BeforeAfterPostType::SLUG,
      'post_status'            => 'publish',
      'posts_per_page'         => $count,
      'orderby'                => ['date' => 'DESC', 'ID' => 'DESC'],
      'post__not_in'           => array_values(array_filter($exclude)),
      'fields'                 => 'ids',
      'no_found_rows'          => true,
      'update_post_meta_cache' => false,
      'update_post_term_cache' => false,
      'suppress_filters'       => false,
    ]);

    return array_map('intval', $ids);
  }

  // ── Site-wide index ───────────────────────────────────────────────────────

  /** @return string[] Taxonomies in use: every filter taxonomy (enabled or not) attached to the post type. */
  private function taxonomies(): array {
    $taxonomies = [];
    foreach ($this->filterManager->all() as $filter) {
      $slug = (string) ($filter['meta_key'] ?? '');
      if ($slug !== '' && taxonomy_exists($slug) && is_object_in_taxonomy(BeforeAfterPostType::SLUG, $slug)) {
        $taxonomies[$slug] = $slug;
      }
    }
    return array_values($taxonomies);
  }

  /** @return array<string, mixed> */
  private function index(): array {
    $gen = $this->generation();

    if ($this->index !== null && $this->index['gen'] === $gen) {
      return $this->index;
    }

    $cached = get_transient(self::TRANSIENT_INDEX);
    if (is_array($cached) && ($cached['gen'] ?? null) === $gen) {
      return $this->index = $cached;
    }

    $index = $this->buildIndex($gen);
    set_transient(self::TRANSIENT_INDEX, $index, self::EXPIRY);

    return $this->index = $index;
  }

  /**
   * Two queries: published IDs in gallery order, then every term of those posts.
   *
   * Shape: gen; n (published posts); ids (gallery order); position (id => index in ids); terms (id =>
   * term keys); df (term key => posts with it); names (term key => taxonomy + name); slugs
   * ("taxonomy:slug" => term key). A term key is "taxonomy:term_id".
   *
   * @return array<string, mixed>
   */
  private function buildIndex(int $gen): array {
    $ids = array_map('intval', get_posts([
      'post_type'              => BeforeAfterPostType::SLUG,
      'post_status'            => 'publish',
      'posts_per_page'         => -1,
      'orderby'                => ['menu_order' => 'ASC', 'date' => 'DESC', 'ID' => 'DESC'],
      'fields'                 => 'ids',
      'no_found_rows'          => true,
      'update_post_meta_cache' => false,
      'update_post_term_cache' => false,
    ]));

    $terms = [];
    $df    = [];
    $names = [];
    $slugs = [];

    $taxonomies = $this->taxonomies();
    $rows       = $ids && $taxonomies
      ? wp_get_object_terms($ids, $taxonomies, ['fields' => 'all_with_object_id', 'update_term_meta_cache' => false])
      : [];

    if (!is_wp_error($rows)) {
      foreach ($rows as $term) {
        $termKey = $term->taxonomy . ':' . $term->term_id;

        $terms[(int) $term->object_id][$termKey] = $termKey;
        $df[$termKey]                            = ($df[$termKey] ?? 0) + 1;
        $names[$termKey]                         = ['taxonomy' => $term->taxonomy, 'name' => $term->name];
        $slugs[$term->taxonomy . ':' . $term->slug] = $termKey;
      }
    }

    foreach ($terms as $id => $keys) {
      $terms[$id] = array_values($keys);
    }

    return [
      'gen'      => $gen,
      'n'        => count($ids),
      'ids'      => $ids,
      'position' => array_flip($ids),
      'terms'    => $terms,
      'df'       => $df,
      'names'    => $names,
      'slugs'    => $slugs,
    ];
  }

  /**
   * Term keys of a post that isn't in the index (a draft, or a preview). One extra query.
   *
   * @param array<string, mixed> $index
   * @return string[]
   */
  private function termKeysFor(int $postId, array &$index): array {
    $taxonomies = $this->taxonomies();
    if (!$taxonomies) return [];

    $terms = wp_get_object_terms($postId, $taxonomies);
    if (is_wp_error($terms)) return [];

    $keys = [];
    foreach ($terms as $term) {
      $termKey = $term->taxonomy . ':' . $term->term_id;
      $keys[]  = $termKey;
      // The index (built from published posts only) may not know this term yet.
      $index['names'][$termKey] ??= ['taxonomy' => $term->taxonomy, 'name' => $term->name];
    }

    return $keys;
  }
}
