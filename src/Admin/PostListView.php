<?php

namespace LiftedLogic\LLBag\Admin;

use LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType;

/**
 * List/grid view switch for the B&A posts list screen. Grid is CSS on top of the
 * regular WP_List_Table; this class owns the mode, the switch, the Image column
 * and the cache priming that keeps tile images off the per-row query path.
 */
class PostListView {
  public const SETTING = 'll_bag_list_view';

  private string $mode = 'list';

  public function register(): void {
    add_action('load-edit.php', [$this, 'maybeRegisterHooks']);
    // Quick Edit's inline-save re-renders the row via admin-ajax, which never fires load-edit.php.
    add_action('wp_ajax_inline-save', [$this, 'maybeRegisterAjaxHooks'], 0);
  }

  public function maybeRegisterAjaxHooks(): void {
    $postType = $_POST['post_type'] ?? '';
    if (!is_string($postType) || sanitize_key(wp_unslash($postType)) !== BeforeAfterPostType::SLUG) return;

    // Read only: the mode is saved by the list screen's own request, never here.
    $this->mode = get_user_setting(self::SETTING, 'list') === 'grid' ? 'grid' : 'list';

    $this->addColumnHooks();
  }

  public function maybeRegisterHooks(): void {
    if ((get_current_screen()?->post_type ?? '') !== BeforeAfterPostType::SLUG) return;

    if (isset($_GET['ll_bag_view'])) {
      $requested = $_GET['ll_bag_view'] === 'grid' ? 'grid' : 'list';
      set_user_setting(self::SETTING, $requested);
      $this->mode = $requested;
    } else {
      $this->mode = get_user_setting(self::SETTING, 'list') === 'grid' ? 'grid' : 'list';
    }

    add_filter('admin_body_class', [$this, 'addBodyClass']);
    add_action('manage_posts_extra_tablenav', [$this, 'renderSwitch']);

    // The Image column shows in both views (like the media library's list view);
    // only grid forces it visible, since a tile without its image is empty.
    $this->addColumnHooks();
    add_filter('the_posts', [$this, 'primeCaches'], 10, 2);

    if ($this->mode !== 'grid') return;

    add_filter('hidden_columns', [$this, 'keepImageColumnVisible'], 10, 2);
  }

  private function addColumnHooks(): void {
    $slug = BeforeAfterPostType::SLUG;
    add_filter("manage_{$slug}_posts_columns", [$this, 'addImageColumn'], 20);
    add_action("manage_{$slug}_posts_custom_column", [$this, 'renderImageColumn'], 10, 2);
  }

  public function addBodyClass(string $classes): string {
    return $classes . ' ll-bag-view-' . $this->mode;
  }

  public function renderSwitch(string $which): void {
    if ($which !== 'top') return;

    $base = remove_query_arg(['ll_bag_view', '_wpnonce', '_wp_http_referer', 'action', 'action2']);

    echo '<div class="view-switch">';
    foreach (['list' => __('List view', 'll-bag'), 'grid' => __('Grid view', 'll-bag')] as $mode => $label) {
      $current = $mode === $this->mode;
      printf(
        '<a href="%s" class="view-%s%s" id="view-switch-%s"%s><span class="screen-reader-text">%s</span></a>',
        esc_url(add_query_arg('ll_bag_view', $mode, $base)),
        esc_attr($mode),
        $current ? ' current' : '',
        esc_attr($mode),
        $current ? ' aria-current="page"' : '',
        esc_html($label)
      );
    }
    echo '</div>';
  }

  /**
   * @param array<string, string> $columns
   * @return array<string, string>
   */
  public function addImageColumn(array $columns): array {
    $anchor = array_key_exists('ll_bag_order_handle', $columns) ? 'll_bag_order_handle' : 'cb';
    // Grid view: a blank label keeps the column out of Screen Options, where its
    // checkbox could do nothing (the image is the tile; keepImageColumnVisible()).
    // The grid's header row is hidden, so the label isn't seen there anyway.
    $label  = $this->mode === 'grid' ? '' : __('Image', 'll-bag');

    if (!array_key_exists($anchor, $columns)) {
      return ['ll_bag_thumb' => $label] + $columns;
    }

    $ordered = [];
    foreach ($columns as $key => $value) {
      $ordered[$key] = $value;
      if ($key === $anchor) {
        $ordered['ll_bag_thumb'] = $label;
      }
    }
    return $ordered;
  }

  public function renderImageColumn(string $column, int $postId): void {
    if ($column !== 'll_bag_thumb') return;
    echo PostListThumbnail::render($postId); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside render()
  }

  /**
   * @param array<int, string> $hidden
   * @return array<int, string>
   */
  public function keepImageColumnVisible(array $hidden, \WP_Screen $screen): array {
    if ($screen->post_type !== BeforeAfterPostType::SLUG) return $hidden;
    return array_values(array_diff($hidden, ['ll_bag_thumb']));
  }

  /**
   * @param array<int, \WP_Post> $posts
   * @return array<int, \WP_Post>
   */
  public function primeCaches(array $posts, \WP_Query $query): array {
    if (!is_admin() || !$query->is_main_query()) return $posts;
    if ($query->get('post_type') !== BeforeAfterPostType::SLUG) return $posts;
    if (!$posts) return $posts;

    $postIds = array_map(static fn($post): int => (int) $post->ID, $posts);
    update_meta_cache('post', $postIds);

    $attachmentIds = [];
    foreach ($postIds as $postId) {
      // Raw meta only: the is-image check in resolve() runs after the prime below.
      $id = PostListThumbnail::candidate($postId)['id'];
      if ($id) $attachmentIds[] = $id;
    }

    if ($attachmentIds) {
      _prime_post_caches(array_values(array_unique($attachmentIds)), false, true);
    }

    return $posts;
  }
}
