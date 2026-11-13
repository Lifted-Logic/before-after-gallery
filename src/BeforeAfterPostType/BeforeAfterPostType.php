<?php

namespace LiftedLogic\LLBag\BeforeAfterPostType;

use LiftedLogic\LLBag\Admin\SettingsPage;
use LiftedLogic\LLBag\Support\CategoryScope;

class BeforeAfterPostType {
  public const SLUG = 'll_before_after';
  public const MENU_ICON = 'dashicons-camera';

  public const MODE_POSTS      = 'posts';
  public const MODE_CATEGORIES = 'categories';
  public const FLUSH_FLAG      = 'll_bag_flush_rewrite';

  public function register(): void {
    add_action('init', [$this, 'registerPostType']);
    add_action('init', [$this, 'registerRewriteRules']);
    add_action('init', [$this, 'maybeFlushRewriteRules'], 999);
    add_filter('query_vars', [$this, 'registerQueryVars']);
    add_action('template_redirect', [$this, 'redirectCategoriesModeUrls']);
    add_filter('redirect_canonical', [$this, 'keepPostsArchiveUrl']);
  }

  public function registerQueryVars(array $vars): array {
    $vars[] = 'll_ba_view';
    $vars[] = CategoryScope::QUERY_VAR;
    return $vars;
  }

  /**
   * Main gallery view (Settings → Archive Settings → Main Gallery View):
   *   posts      — /{archive}/ is the posts archive; no categories pages are registered.
   *   categories — /{archive}/ is the categories page, scoped pages live at
   *                /{archive}/{categories-slug}/{scope}/, and the posts archive moves to
   *                /{archive}/{posts-slug}/ (default "all").
   * Reads the option directly: it's needed on init, before ACF options are reliable.
   */
  public static function archiveMode(): string {
    $mode = (string) get_option('options_ll_bag_archive_mode');
    if ($mode === self::MODE_POSTS || $mode === self::MODE_CATEGORIES) return $mode;

    // Not saved yet (sites from before this setting): the old "Use Taxonomy Archive?" toggle decides.
    return get_option('options_ll_bag_use_category_archive') ? self::MODE_CATEGORIES : self::MODE_POSTS;
  }

  public static function isCategoriesMode(): bool {
    return self::archiveMode() === self::MODE_CATEGORIES;
  }

  /** The gallery's main URL (/{archive}/): the posts archive or the categories page, by mode. */
  public static function getGalleryUrl(): string {
    return (string) get_post_type_archive_link(self::SLUG);
  }

  /** The posts archive (filterable grid). Link here for anything that should show posts. */
  public static function getPostsArchiveUrl(): string {
    $base = self::getGalleryUrl();
    if (!$base || !self::isCategoriesMode()) return $base;
    return trailingslashit($base) . trailingslashit(SettingsPage::getPostsArchiveSlug());
  }

  /** The top-level categories page, or '' in Posts mode (it isn't registered there). */
  public static function getCategoriesArchiveUrl(): string {
    return self::isCategoriesMode() ? self::getGalleryUrl() : '';
  }

  /** A scoped categories page (/{archive}/{categories-slug}/{scope}/), or '' in Posts mode. */
  public static function getScopedCategoriesArchiveUrl(string $scopeSlug): string {
    $base = self::getGalleryUrl();
    if (!$base || !self::isCategoriesMode()) return '';
    return trailingslashit($base) . trailingslashit(SettingsPage::getCategoryArchiveSlug()) . trailingslashit($scopeSlug);
  }

  public static function getRewriteSlug(): string {
    $pageId = (int) get_option('options_' . SettingsPage::FIELD_POSTS_PAGE);
    return $pageId ? (get_page_uri($pageId) ?: 'll-before-after') : 'll-before-after';
  }

  public function registerRewriteRules(): void {
    if ( !self::isCategoriesMode() ) return;

    $archive  = preg_quote(self::getRewriteSlug(), '/');
    $category = preg_quote(SettingsPage::getCategoryArchiveSlug(), '/');
    $posts    = preg_quote(SettingsPage::getPostsArchiveSlug(), '/');

    // Posts archive at /{archive}/{posts-slug}/ (pagination is ?paged=, plus /page/N/ for parity).
    add_rewrite_rule("^{$archive}/{$posts}/?$", 'index.php?post_type=' . self::SLUG . '&ll_ba_view=posts', 'top');
    add_rewrite_rule("^{$archive}/{$posts}/page/([0-9]{1,})/?$", 'index.php?post_type=' . self::SLUG . '&ll_ba_view=posts&paged=$matches[1]', 'top');

    // Old top-level categories URL; 301'd to /{archive}/ in redirectCategoriesModeUrls().
    add_rewrite_rule("^{$archive}/{$category}/?$", 'index.php?ll_ba_view=categories', 'top');

    // Scoped categories page, e.g. /before-afters/categories/breast/. The slug is matched
    // against the Hero Navigation rows at render time; unknown slugs 404.
    add_rewrite_rule(
      "^{$archive}/{$category}/([^/]+)/?$",
      'index.php?ll_ba_view=categories&' . CategoryScope::QUERY_VAR . '=$matches[1]',
      'top'
    );
  }

  /**
   * Settings saves set a flag instead of flushing on the spot: during that save, init has
   * already registered the rules for the *old* mode/slugs. This flushes on the next request.
   */
  public function maybeFlushRewriteRules(): void {
    if (!get_option(self::FLUSH_FLAG)) return;
    delete_option(self::FLUSH_FLAG);
    flush_rewrite_rules(false);
  }

  /**
   * Categories mode only:
   *  - /{archive}/{categories-slug}/ (the old top-level categories URL) → /{archive}/
   *  - /{archive}/?{filters} or ?paged= (links built before Categories mode) → /{archive}/{posts-slug}/?{same query}
   */
  public function redirectCategoriesModeUrls(): void {
    if (!self::isCategoriesMode()) return;

    $view = get_query_var('ll_ba_view');

    if ($view === 'categories' && CategoryScope::requestedSlug() === '') {
      wp_safe_redirect(self::getGalleryUrl(), 301);
      exit;
    }

    if (is_post_type_archive(self::SLUG) && $view !== 'posts' && !empty($_GET)) {
      $taxonomies = get_object_taxonomies(self::SLUG);
      $keys       = array_map(fn($k) => preg_replace('/\[.*$/', '', (string) $k), array_keys($_GET));
      if (array_intersect($keys, array_merge($taxonomies, ['paged', CategoryScope::REQUIRE_PARAM]))) {
        $query = wp_unslash($_SERVER['QUERY_STRING'] ?? '');
        wp_safe_redirect(self::getPostsArchiveUrl() . ($query !== '' ? '?' . $query : ''), 301);
        exit;
      }
    }
  }

  /** Don't let canonical redirects fold /{archive}/{posts-slug}/ back into /{archive}/. */
  public function keepPostsArchiveUrl($redirect) {
    return get_query_var('ll_ba_view') === 'posts' ? false : $redirect;
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
      'taxonomies'      => [],
      'has_archive'     => $rewriteSlug,
      'rewrite'         => ['slug' => $rewriteSlug],
      'capability_type' => 'post',
    ]);
  }
}
