<?php

namespace LiftedLogic\LLBag\BeforeAfterPostType;

use LiftedLogic\LLBag\Admin\SettingsPage;

class BeforeAfterPostType {
  public const SLUG = 'll_before_after';
  public const MENU_ICON = 'dashicons-camera';

  public function register(): void {
    add_action('init', [$this, 'registerPostType']);
    add_action('init', [$this, 'registerRewriteRules']);
    add_filter('query_vars', [$this, 'registerQueryVars']);
  }

  public function registerQueryVars(array $vars): array {
    $vars[] = 'll_ba_view';
    return $vars;
  }

  /**
   * Return the URL for the categories archive (/{archive-slug}/{category-slug}/).
   */
  public static function getCategoriesArchiveUrl(): string {
    $archiveLink  = get_post_type_archive_link(self::SLUG);
    $categorySlug = SettingsPage::getCategoryArchiveSlug();
    return $archiveLink ? trailingslashit($archiveLink) . trailingslashit($categorySlug) : '';
  }

  public static function getRewriteSlug(): string {
    $pageId = (int) get_option('options_' . SettingsPage::FIELD_POSTS_PAGE);
    return $pageId ? (get_page_uri($pageId) ?: 'll-before-after') : 'll-before-after';
  }

  public function registerRewriteRules(): void {
    if ( !get_option( 'options_ll_bag_use_category_archive' ) ) return;

    $archiveSlug  = self::getRewriteSlug();
    $categorySlug = SettingsPage::getCategoryArchiveSlug();
    add_rewrite_rule(
      '^' . preg_quote($archiveSlug, '/') . '/' . preg_quote($categorySlug, '/') . '/?$',
      'index.php?ll_ba_view=categories',
      'top'
    );
  }

  public function registerPostType(): void {
    $rewriteSlug = self::getRewriteSlug();

    register_post_type(self::SLUG, [
      'labels' => [
        'name'               => __('Before & After', 'll-bag'),
        'singular_name'      => __('Before & After', 'll-bag'),
        'add_new'            => __('Add New', 'll-bag'),
        'add_new_item'       => __('Add New Before & After', 'll-bag'),
        'edit_item'          => __('Edit Before & After', 'll-bag'),
        'new_item'           => __('New Before & After', 'll-bag'),
        'view_item'          => __('View Before & After', 'll-bag'),
        'search_items'       => __('Search Before & After', 'll-bag'),
        'not_found'          => __('No before & after posts found', 'll-bag'),
        'not_found_in_trash' => __('No before & after posts found in trash', 'll-bag'),
        'menu_name'          => __('Before & Afters', 'll-bag'),
      ],
      'public'          => true,
      'show_ui'         => true,
      'show_in_menu'    => true,
      'show_in_rest'    => true,
      'menu_icon'       => self::MENU_ICON,
      'menu_position'   => 25,
      'supports'        => ['title', 'page-attributes'],
      'taxonomies'      => ['category'],
      'has_archive'     => $rewriteSlug,
      'rewrite'         => ['slug' => $rewriteSlug],
      'capability_type' => 'post',
    ]);
  }
}
