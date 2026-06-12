<?php

namespace LiftedLogic\LLBag\Frontend;

use LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType;
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
  }

  public function enqueueAssets(): void {
    Vite::enqueueFrontendAssets();
    $this->enqueueCssOverrides();
    $this->enqueueMagnificPopup();
  }

  private function enqueueMagnificPopup(): void {
    wp_enqueue_style(
      'll-bag-magnific-popup',
      LL_BAG_URL . 'resources/vendor/magnific-popup/magnific-popup.css',
      [],
      '1.1.0'
    );
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

    foreach (['primitives.css', 'ba-colors.css'] as $file) {
      $themeFile = get_stylesheet_directory() . '/ll-before-after/css/' . $file;
      $url = file_exists($themeFile)
        ? get_stylesheet_directory_uri() . '/ll-before-after/css/' . $file
        : LL_BAG_URL . 'resources/css/' . $file;

      $handle = 'll-bag-' . basename($file, '.css');
      wp_enqueue_style($handle, $url, $deps, LL_BAG_VERSION);
      $deps = [$handle];
    }
    $cardBgColor = get_field('ll_ba_card_bg_color', 'option') ?: '#000000';
    wp_add_inline_style('ll-bag-ba-colors', ':root { --ll-ba-card-bg: ' . sanitize_hex_color($cardBgColor) . '; }');

    wp_localize_script('ll-bag-frontend', 'llBag', [
      'ajaxUrl'       => admin_url('admin-ajax.php'),
      'nonce'         => wp_create_nonce(AjaxHandler::ACTION),
      'action'        => AjaxHandler::ACTION,
      'relatedAction' => AjaxHandler::RELATED_ACTION,
      'relatedNonce'  => wp_create_nonce(AjaxHandler::RELATED_ACTION),
    ]);
  }

  public function loadTemplate(string $template): string {
    if (is_singular(BeforeAfterPostType::SLUG)) {
      return self::resolve('single-ll_before_after.php') ?? $template;
    }

    if (is_post_type_archive(BeforeAfterPostType::SLUG)) {
      return self::resolve('archive-ll_before_after.php') ?? $template;
    }

    if ( get_query_var('ll_ba_view') === 'categories' && get_field( 'll_bag_use_category_archive', 'option' ) ) {
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
