<?php

namespace LiftedLogic\LLBag\Frontend;

use LiftedLogic\LLBag\Filters\FilterManager;
use LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType;

class AjaxHandler {
  public const ACTION         = 'll_bag_filter';
  public const RELATED_ACTION = 'll_bag_related';

  public function __construct(private readonly FilterManager $filterManager) {}

  public function register(): void {
    add_action('wp_ajax_nopriv_' . self::ACTION, [$this, 'handle']);
    add_action('wp_ajax_'        . self::ACTION, [$this, 'handle']);
    add_action('wp_ajax_nopriv_' . self::RELATED_ACTION, [$this, 'handleRelated']);
    add_action('wp_ajax_'        . self::RELATED_ACTION, [$this, 'handleRelated']);
  }

  private function verifyNonce(string $action): void {
    if (is_user_logged_in()) {
      check_ajax_referer($action, 'nonce');
    }
  }

  public function handle(): void {
    $this->verifyNonce(self::ACTION);

    $submitted = isset($_POST['filters']) && is_array($_POST['filters'])
                 ? $_POST['filters']
                 : [];

    // Build a set of valid taxonomy slugs from configured filters
    $validSlugs = $this->filterManager->all()->pluck('meta_key')->flip();

    $taxQuery = ['relation' => 'AND'];

    foreach ($submitted as $rawKey => $value) {
      $taxonomy = sanitize_key((string) $rawKey);

      // Only allow taxonomies that are configured as filters
      if (!$validSlugs->has($taxonomy)) {
        continue;
      }

      if (is_array($value) && !empty($value)) {
        $taxQuery[] = [
          'taxonomy' => $taxonomy,
          'field'    => 'slug',
          'terms'    => array_map('sanitize_key', $value),
          'operator' => 'IN',
        ];
      } elseif (is_string($value) && $value !== '') {
        $taxQuery[] = [
          'taxonomy' => $taxonomy,
          'field'    => 'slug',
          'terms'    => sanitize_key($value),
          'operator' => 'IN',
        ];
      }
    }

    $perPage = (int) get_option('posts_per_page', 10);
    $paged   = max(1, (int) ($_POST['paged'] ?? 1));

    $args = [
      'post_type'      => BeforeAfterPostType::SLUG,
      'post_status'    => 'publish',
      'posts_per_page' => $perPage,
      'paged'          => $paged,
      'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
    ];

    if (count($taxQuery) > 1) {
      $args['tax_query'] = $taxQuery;
    }

    $query = new \WP_Query($args);

    $html = '';
    foreach ($query->posts as $post) {
      $html .= TemplateLoader::render('partials/post-card.php', ['post' => $post]);
    }

    wp_send_json_success([
      'html'         => $html,
      'count'        => $query->found_posts,
      'total_pages'  => (int) $query->max_num_pages,
      'current_page' => $paged,
    ]);
  }

  public function handleRelated(): void {
    $this->verifyNonce(self::RELATED_ACTION);

    $excludeId = isset($_POST['exclude_id']) ? (int) $_POST['exclude_id'] : 0;
    $postDate  = $excludeId ? get_post_field('post_date', $excludeId) : '';
    $exclude   = $excludeId ? [$excludeId] : [];

    $posts = [];

    // ── Pass 1: Card Display taxonomy (with wrap-around) ────────────────────
    $cardTaxonomy = $this->filterManager->getCardTaxonomy();
    if ($cardTaxonomy && $excludeId) {
      $cardTerms = wp_get_post_terms($excludeId, $cardTaxonomy, ['fields' => 'slugs']);
      if (!empty($cardTerms) && !is_wp_error($cardTerms)) {
        $cardTaxQuery = [
          'relation' => 'AND',
          ['taxonomy' => $cardTaxonomy, 'field' => 'slug', 'terms' => $cardTerms, 'operator' => 'IN'],
        ];

        // Forward: older than current post
        $fwdArgs = [
          'post_type'      => BeforeAfterPostType::SLUG,
          'post_status'    => 'publish',
          'posts_per_page' => 6,
          'orderby'        => 'date',
          'order'          => 'DESC',
          'post__not_in'   => $exclude,
          'tax_query'      => $cardTaxQuery,
        ];
        if ($postDate) {
          $fwdArgs['date_query'] = [['before' => $postDate, 'inclusive' => false]];
        }
        $posts = (new \WP_Query($fwdArgs))->posts;

        // Wrap-around: fill remaining from newer posts
        $remaining = 6 - count($posts);
        if ($remaining > 0 && $postDate) {
          $wrapArgs = [
            'post_type'      => BeforeAfterPostType::SLUG,
            'post_status'    => 'publish',
            'posts_per_page' => $remaining,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'post__not_in'   => array_merge($exclude, array_map(fn($p) => $p->ID, $posts)),
            'date_query'     => [['after' => $postDate, 'inclusive' => false]],
            'tax_query'      => $cardTaxQuery,
          ];
          $posts = array_merge($posts, (new \WP_Query($wrapArgs))->posts);
        }
      }
    }

    // ── Pass 2: "Match Related Posts By" override field ─────────────────────
    $remaining = 6 - count($posts);
    if ($remaining > 0 && $excludeId) {
      $selectedRaw = (array) (get_post_meta($excludeId, 'll_ba_related_terms', true) ?: []);
      if (!empty($selectedRaw)) {
        $grouped = [];
        foreach ($selectedRaw as $pair) {
          [$taxonomy, $slug] = explode(':', $pair, 2) + ['', ''];
          $taxonomy = sanitize_key($taxonomy);
          $slug     = sanitize_key($slug);
          if ($taxonomy && $slug) $grouped[$taxonomy][] = $slug;
        }
        if (!empty($grouped)) {
          $overrideTaxQuery = ['relation' => 'OR'];
          foreach ($grouped as $taxonomy => $slugs) {
            $overrideTaxQuery[] = [
              'taxonomy' => $taxonomy,
              'field'    => 'slug',
              'terms'    => $slugs,
              'operator' => 'IN',
            ];
          }
          $pass2Args = [
            'post_type'      => BeforeAfterPostType::SLUG,
            'post_status'    => 'publish',
            'posts_per_page' => $remaining,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'post__not_in'   => array_merge($exclude, array_map(fn($p) => $p->ID, $posts)),
            'tax_query'      => $overrideTaxQuery,
          ];
          $posts = array_merge($posts, (new \WP_Query($pass2Args))->posts);
        }
      }
    }

    // ── Pass 3: Fallback — any recent posts ──────────────────────────────────
    $remaining = 6 - count($posts);
    if ($remaining > 0) {
      $pass3Args = [
        'post_type'      => BeforeAfterPostType::SLUG,
        'post_status'    => 'publish',
        'posts_per_page' => $remaining,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'post__not_in'   => array_merge($exclude, array_map(fn($p) => $p->ID, $posts)),
      ];
      $posts = array_merge($posts, (new \WP_Query($pass3Args))->posts);
    }

    $html = '';
    foreach ($posts as $post) {
      $html .= '<li class="splide__slide">'
             . TemplateLoader::render('partials/post-card.php', ['post' => $post])
             . '</li>';
    }

    wp_send_json_success(['html' => $html, 'count' => count($posts)]);
  }
}
