<?php

namespace LiftedLogic\LLBag\Integration;

use LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType;

/**
 * Removes Yoast SEO's columns and filter dropdowns from the Before & After
 * admin list only. Does nothing when Yoast isn't active. Yoast's own
 * per-post-type "Show SEO settings" option is deliberately left alone, since
 * it also removes the SEO meta box from the edit screen.
 */
class YoastListCleanup {

  public function register(): void {
    add_action( 'load-edit.php', [$this, 'maybeRegisterHooks'] );
    // Quick Edit's inline-save re-renders the row via admin-ajax, which never fires load-edit.php.
    add_action( 'wp_ajax_inline-save', [$this, 'maybeRegisterAjaxHooks'], 0 );
  }

  public function maybeRegisterAjaxHooks(): void {
    $postType = $_POST['post_type'] ?? '';
    if ( !is_string( $postType ) || sanitize_key( wp_unslash( $postType ) ) !== BeforeAfterPostType::SLUG ) {
      return;
    }

    $this->addColumnFilters();
  }

  public function maybeRegisterHooks(): void {
    if ( ( get_current_screen()?->post_type ?? '' ) !== BeforeAfterPostType::SLUG ) {
      return;
    }

    $this->addColumnFilters();

    $this->removeYoastDropdowns();

    // Yoast's "Cornerstone content (n)" status link above the table.
    add_filter( 'views_edit-' . BeforeAfterPostType::SLUG, [$this, 'stripViews'], PHP_INT_MAX );

    // Fallback in case Yoast registers its dropdowns after load-edit.php.
    add_action( 'restrict_manage_posts', [$this, 'removeYoastDropdownsLate'], PHP_INT_MIN );
  }

  private function addColumnFilters(): void {
    add_filter( 'manage_edit-' . BeforeAfterPostType::SLUG . '_columns', [$this, 'stripColumns'], PHP_INT_MAX );
    add_filter( 'manage_edit-' . BeforeAfterPostType::SLUG . '_sortable_columns', [$this, 'stripColumns'], PHP_INT_MAX );
  }

  /**
   * Drop Yoast's status links (Cornerstone content), matched by key so a renamed
   * label still goes; core's All / Published / Drafts / Trash stay.
   *
   * @param array<string, string> $views
   * @return array<string, string>
   */
  public function stripViews( $views ) {
    if ( !is_array( $views ) ) {
      return $views;
    }

    return array_filter(
      $views,
      static fn( $key ): bool => !preg_match( '/^(yoast|wpseo)|cornerstone/i', (string) $key ),
      ARRAY_FILTER_USE_KEY
    );
  }

  /**
   * Drop every column whose key starts with "wpseo" (prefix match so new and
   * Premium-only Yoast columns are caught too).
   *
   * @param array<string|int, mixed> $columns
   * @return array<string|int, mixed>
   */
  public function stripColumns( $columns ) {
    if ( !is_array( $columns ) ) {
      return $columns;
    }

    return array_filter(
      $columns,
      static fn( $key ): bool => !str_starts_with( (string) $key, 'wpseo' ),
      ARRAY_FILTER_USE_KEY
    );
  }

  public function removeYoastDropdownsLate( string $postType = '' ): void {
    if ( $postType !== BeforeAfterPostType::SLUG ) {
      return;
    }

    $this->removeYoastDropdowns();
  }

  /**
   * Remove only restrict_manage_posts callbacks owned by Yoast classes.
   * Callbacks are collected first and removed after the loop.
   */
  private function removeYoastDropdowns(): void {
    global $wp_filter;

    $hook = $wp_filter['restrict_manage_posts'] ?? null;
    if ( !$hook instanceof \WP_Hook ) {
      return;
    }

    $remove = [];
    foreach ( $hook->callbacks as $priority => $callbacks ) {
      foreach ( $callbacks as $callback ) {
        $fn = $callback['function'] ?? null;
        if ( !is_array( $fn ) || !isset( $fn[0] ) ) continue;

        $owner = is_object( $fn[0] ) ? get_class( $fn[0] ) : (string) $fn[0];
        if ( str_starts_with( $owner, 'WPSEO_' ) || str_starts_with( $owner, 'Yoast\\' ) ) {
          $remove[] = [$fn, $priority];
        }
      }
    }

    foreach ( $remove as [$fn, $priority] ) {
      remove_action( 'restrict_manage_posts', $fn, $priority );
    }
  }
}
