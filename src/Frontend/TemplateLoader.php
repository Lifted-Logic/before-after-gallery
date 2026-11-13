<?php

namespace LiftedLogic\LLBag\Frontend;

use LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType;
use LiftedLogic\LLBag\Support\CategoryScope;
use LiftedLogic\LLBag\Support\Vite;

class TemplateLoader {
  public function register(): void {
    add_filter('template_include', [$this, 'loadTemplate']);
    add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    add_action('pre_get_posts', [$this, 'setArchivePaged']);
  }

  public function setArchivePaged(\WP_Query $query): void {
    if (!$query->is_main_query() || !$query->is_post_type_archive(BeforeAfterPostType::SLUG)) {
      return;
    }
    if (isset($_GET['paged']) && is_numeric($_GET['paged'])) {
      $query->set('paged', max(1, (int) $_GET['paged']));
    }

    if (apply_filters('lifted_logic/bag/order_archive_by_menu_order', true)) {
      $query->set('orderby', ['menu_order' => 'ASC', 'date' => 'DESC']);
    }
  }

  public function enqueueAssets(): void {
    Vite::enqueueFrontendAssets();
    $this->enqueueCssOverrides();
    $this->enqueueMagnificPopup();
  }

  // Magnific Popup's CSS is bundled (and minified) into frontend.css by Vite; only the
  // jQuery plugin itself is enqueued here.
  private function enqueueMagnificPopup(): void {
    wp_enqueue_script(
      'll-bag-magnific-popup',
      LL_BAG_URL . 'resources/vendor/magnific-popup/jquery.magnific-popup.min.js',
      ['jquery'],
      '1.1.0',
      true
    );
  }

  private function enqueueCssOverrides(): void {
    $deps = [];

    // Color token files are printed inline (minified, cached by file mtime) instead of linked:
    // they're a few KB of custom properties, and inlining means no extra requests and no
    // minification step needed in the theme. A theme copy in {theme}/ll-before-after/css/ still wins.
    foreach (['primitives.css', 'ba-colors.css'] as $file) {
      $themeFile = get_stylesheet_directory() . '/ll-before-after/css/' . $file;
      $inTheme   = file_exists($themeFile);
      $path      = $inTheme ? $themeFile : LL_BAG_PATH . 'resources/css/' . $file;
      $baseUrl   = $inTheme ? get_stylesheet_directory_uri() . '/ll-before-after/css/' : LL_BAG_URL . 'resources/css/';

      $handle = 'll-bag-' . basename($file, '.css');
      wp_register_style($handle, false, $deps, LL_BAG_VERSION);
      wp_enqueue_style($handle);

      $css = self::minifiedCss($path, $baseUrl);
      if ($css !== '') wp_add_inline_style($handle, $css);

      $deps = [$handle];
    }

    $default_ratio = bag_default_image_ratio();
    if ( $default_ratio['key'] === 'custom' ) {
      wp_add_inline_style('ll-bag-ba-colors', sprintf(
        ':root{--ll-ba-default-ratio:%s;--ll-ba-default-ratio-value:%s}',
        $default_ratio['css'], round( $default_ratio['value'], 4 )
      ));
    }

    $this->enqueueCardBackgroundImage();

    wp_localize_script('ll-bag-frontend', 'llBag', [
      'ajaxUrl'       => admin_url('admin-ajax.php'),
      'nonce'         => wp_create_nonce(AjaxHandler::ACTION),
      'action'        => AjaxHandler::ACTION,
      'relatedAction' => AjaxHandler::RELATED_ACTION,
      'relatedNonce'  => wp_create_nonce(AjaxHandler::RELATED_ACTION),
    ]);
  }

  private function enqueueCardBackgroundImage(): void {
    $extensions = ['webp', 'jpg', 'jpeg', 'png'];

    foreach ($extensions as $ext) {
      $themeFile = get_stylesheet_directory() . '/ll-before-after/images/card-background.' . $ext;

      if (file_exists($themeFile)) {
        $url = get_stylesheet_directory_uri() . '/ll-before-after/images/card-background.' . $ext;

        wp_add_inline_style(
          'll-bag-ba-colors',
          ':root { --cards-background-image: url(' . esc_url($url) . '); }'
        );

        return;
      }
    }
  }

  /**
   * Minified contents of a CSS file, cached until the file changes. Relative url()s are made
   * absolute against $baseUrl, since the CSS is printed inline rather than loaded from its folder.
   */
  public static function minifiedCss(string $path, string $baseUrl): string {
    if (!is_readable($path)) return '';

    $signature = filemtime($path) . ':' . filesize($path) . ':' . LL_BAG_VERSION;
    $cacheKey  = 'll_bag_css_' . md5($path);
    $cached    = get_transient($cacheKey);
    if (is_array($cached) && ($cached['sig'] ?? '') === $signature) {
      return (string) $cached['css'];
    }

    $css = (string) file_get_contents($path);
    $css = preg_replace('!/\*.*?\*/!s', '', $css);                 // comments
    $css = preg_replace('/\s+/', ' ', $css);                       // collapse whitespace
    $css = preg_replace('/\s*([{};,])\s*/', '$1', $css);           // around braces, semicolons, commas
    $css = preg_replace('/:\s+/', ':', $css);                      // after colons (not before: keeps "a :hover")
    $css = str_replace(';}', '}', trim($css));

    $css = preg_replace_callback('/url\(\s*([\'"]?)(?!data:|https?:|\/|#)([^\'")]+)\1\s*\)/i',
      fn($m) => 'url(' . $m[1] . trailingslashit($baseUrl) . $m[2] . $m[1] . ')', $css);

    $css = str_replace('</', '<\/', $css); // never close the <style> tag early

    set_transient($cacheKey, ['sig' => $signature, 'css' => $css], WEEK_IN_SECONDS);
    return $css;
  }

  public function loadTemplate(string $template): string {
    if (is_singular(BeforeAfterPostType::SLUG)) {
      return self::resolve('single-ll_before_after.php') ?? $template;
    }

    $view       = get_query_var('ll_ba_view');
    $isArchive  = is_post_type_archive(BeforeAfterPostType::SLUG);

    // Categories mode: /{archive}/ and /{archive}/{categories-slug}/{scope}/ show the categories
    // page; the posts archive is /{archive}/{posts-slug}/ (ll_ba_view=posts). Posts mode never
    // shows the categories page.
    $showCategories = BeforeAfterPostType::isCategoriesMode()
      && ( $view === 'categories' || ( $isArchive && $view !== 'posts' ) );

    if ($isArchive && !$showCategories) {
      return self::resolve('archive-ll_before_after.php') ?? $template;
    }

    if ( $showCategories ) {
      // A scope slug that matches no Hero Navigation row is a real 404, not the unscoped grid.
      if ( CategoryScope::requestedSlug() !== '' && CategoryScope::current() === null ) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
        return get_404_template() ?: $template;
      }

      return self::resolve('archive-ll_before_after_categories.php') ?? $template;
    }

    return $template;
  }

  /**
   * Resolve a template file, checking theme override first.
   * Use this in shortcodes and partials to load a template file.
   *
   * @param string               $file Relative path within templates/ (e.g. 'partials/post-card.php')
   * @param array<string, mixed> $data Variables to extract into template scope
   */
  public static function get(string $file, array $data = []): void {
    $themeFile  = get_stylesheet_directory() . '/ll-before-after/' . $file;
    $pluginFile = LL_BAG_PATH . 'templates/' . $file;

    $resolved = file_exists($themeFile) ? $themeFile : (file_exists($pluginFile) ? $pluginFile : null);

    if ($resolved === null) {
      return;
    }

    if (!empty($data)) {
      extract($data, EXTR_SKIP);
    }

    require $resolved;
  }

  /**
   * Like get(), but captures output and returns it as a string.
   *
   * @param array<string, mixed> $data
   */
  public static function render(string $file, array $data = []): string {
    ob_start();
    self::get($file, $data);
    return (string) ob_get_clean();
  }

  /**
   * Return the resolved absolute path without loading it.
   */
  public static function resolve(string $file): ?string {
    $themeFile  = get_stylesheet_directory() . '/ll-before-after/' . $file;
    $pluginFile = LL_BAG_PATH . 'templates/' . $file;

    if (file_exists($themeFile))  return $themeFile;
    if (file_exists($pluginFile)) return $pluginFile;

    return null;
  }
}
