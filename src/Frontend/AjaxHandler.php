<?php

namespace LiftedLogic\LLBag\Frontend;

use LiftedLogic\LLBag\Filters\FilterManager;
use LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType;
use LiftedLogic\LLBag\Support\RelatedPosts;

class AjaxHandler {
  public const ACTION         = 'll_before_after_filter';
  public const RELATED_ACTION = 'll_bag_related';

  public function __construct(
    private readonly FilterManager $filterManager,
    private readonly RelatedPosts $relatedPosts,
  ) {}

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

    // Required terms (from ?ll_ba_require[{taxonomy}]={slug} on scoped category links) get
    // their own clause, so they're AND'd with the rest even when they share a taxonomy with
    // other checked terms — which are OR'd within their group above.
    $required = isset($_POST['required']) && is_array($_POST['required']) ? $_POST['required'] : [];

    foreach ($required as $rawKey => $value) {
      $taxonomy = sanitize_key((string) $rawKey);
      if (!$validSlugs->has($taxonomy) || !is_string($value) || $value === '') continue;

      $taxQuery[] = [
        'taxonomy' => $taxonomy,
        'field'    => 'slug',
        'terms'    => sanitize_key($value),
        'operator' => 'IN',
      ];
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

    // Manual terms first, then weighted by shared-term rarity, then newest. See Support\RelatedPosts.
    $ids   = $this->relatedPosts->ids($excludeId, RelatedPosts::SLOTS);
    $posts = $ids
      ? get_posts([
          'post_type'      => BeforeAfterPostType::SLUG,
          'post_status'    => 'publish',
          'post__in'       => $ids,
          'orderby'        => 'post__in',
          'posts_per_page' => count($ids),
          'no_found_rows'  => true,
          'suppress_filters' => false,
        ])
      : [];

    $html = '';
    foreach ($posts as $post) {
      $html .= '<li class="splide__slide">'
             . TemplateLoader::render('partials/post-card.php', ['post' => $post])
             . '</li>';
    }

    wp_send_json_success(['html' => $html, 'count' => count($posts)]);
  }
}
