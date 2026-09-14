<?php

namespace LiftedLogic\LLBag\Hooks;

class Hooks {

  public function register(): void {
    add_action( 'wp_footer', [$this, 'inlineSymbolDefs'] );
  }

  public function inlineSymbolDefs(): void {
    $file = LL_BAG_PATH . 'resources/img/symbol-defs.svg';
    if ( file_exists( $file ) ) {
      echo file_get_contents( $file ); // phpcs:ignore WordPress.Security.EscapeOutput
    }
  }

  /*
    SINGLE-LL_BEFORE_AFTER.PHP FILTERS
    Use add_filter( 'lifted_logic/bag/{hook}', ... ) to override output.
  */

  // Back button
  public static function bag_back_button_markup(): string {
    $classes    = 'bag_back-text bag-inline-block';
    $label      = function_exists( 'get_field' ) ? get_field( 'll_ba_global_back_label', 'options' ) : '';
    $label_raw  = $label ?: 'Back to Gallery';
    $archiveUrl = get_post_type_archive_link('ll_before_after') ?: site_url('/');
    $refUrl     = isset($_GET['ba_ref']) ? wp_validate_redirect(wp_unslash($_GET['ba_ref']), '') : '';

    // Only honor ba_ref if it actually points back to the gallery archive page
    // (preserving any active filters). If the card was clicked from some other
    // page on the site, fall back to the plain, unfiltered archive URL.
    if ( $refUrl ) {
      $refPath     = untrailingslashit( (string) wp_parse_url( $refUrl, PHP_URL_PATH ) );
      $archivePath = untrailingslashit( (string) wp_parse_url( $archiveUrl, PHP_URL_PATH ) );
      if ( $refPath !== $archivePath ) {
        $refUrl = '';
      }
    }

    $href_raw = $refUrl ?: $archiveUrl;
    // $text/$href below are pre-escaped ONLY for this method's own outer filter signature
    // (unchanged from before this refactor). The generator call further down is passed the
    // *raw* $label_raw/$href_raw instead — it does its own esc_html()/esc_url() internally,
    // so passing the already-escaped versions would double-encode entities.
    $text = esc_html( $label_raw );
    $href = esc_url( $href_raw );

    $markup = self::bag_secondary_button_markup( $label_raw, $href_raw, [
      'base_class' => $classes,
      'icon'       => 'arrow-left',
    ] );

    return apply_filters( 'lifted_logic/bag/bag_back_button_markup', $markup, $classes, $text, $href );
  }

  public static function bag_lightbox_items( array $image_ids ): string {
    $items = [];

    foreach ( $image_ids as $id ) {
      if ( !$id ) continue;
      $src = wp_get_attachment_image_url( $id, 'full' );
      if ( !$src ) continue;
      $items[] = [
        'src'   => $src,
        'title' => wp_get_attachment_caption( $id ) ?: '',
      ];
    }

    $items = apply_filters( 'lifted_logic/bag/lightbox_items', $items, $image_ids );

    return $items ? esc_attr( (string) wp_json_encode( $items ) ) : '';
  }

  public static function bag_lightbox_expand_markup( int $index = 0 ): string {
    $markup = <<<HTML
      <button type="button" class="ll-ba-single__expand" data-ba-open data-ba-index="$index">
        <svg class="ll-ba-single__expand-icon icon icon-expand" aria-hidden="true"><use xlink:href="#icon-expand"></use></svg>
        <span class="sr-only">View these images full size</span>
      </button>
    HTML;

    return apply_filters( 'lifted_logic/bag/lightbox_expand_markup', $markup, $index );
  }

  // Related slider arrows
  public static function bag_related_slider_arrows_markup(): string {
    $prev = <<<HTML
      <button class="ll-ba-single__related-arrow ll-ba-single__related-arrow--prev splide__arrow--prev">
        <svg class="ll-ba-single__related-arrow-icon icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
        <span class="sr-only">Previous Slide</span>
      </button>
    HTML;

    $next = <<<HTML
      <button class="ll-ba-single__related-arrow ll-ba-single__related-arrow--next splide__arrow--next">
        <svg class="ll-ba-single__related-arrow-icon icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
        <span class="sr-only">Next Slide</span>
      </button>
    HTML;

    $markup = <<<HTML
      <div class="ll-ba-single__related-arrows splide__arrows">$prev$next</div>
    HTML;

    return apply_filters( 'lifted_logic/bag/related_slider_arrows_markup', $markup, $prev, $next );
  }

  // NSFW confirmation modal
  public static function bag_nsfw_modal_markup( string $message, string $archive_url ): string {
    $fallback_url = esc_url( $archive_url );
    $message_html = esc_html( $message );

    $actions = <<<HTML
      <div class="ll-ba-nsfw-modal__actions">
          <button type="button" class="ll-ba-nsfw-modal__btn ba_btn-primary" data-nsfw-action="unblur-once">Unblur This Only</button>
          <button type="button" class="ll-ba-nsfw-modal__btn ll-ba-nsfw-modal__btn--secondary" data-nsfw-action="unblur-all">
            <svg class="icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
            Unblur All
            <svg class="icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
          </button>
      </div>
    HTML;

    $markup = <<<HTML
      <div class="ll-ba-nsfw-modal ll-ba-hidden" id="ll-ba-nsfw-modal" role="dialog" aria-modal="true" aria-label="Sensitive content">
          <div class="ll-ba-nsfw-modal__panel ll-ba-popup-modal">
              <button type="button" class="ll-ba-nsfw-modal__close" data-nsfw-action="leave" data-fallback-url="$fallback_url" aria-label="Go back">
                  <svg class="icon icon-exit" aria-hidden="true"><use xlink:href="#icon-exit"></use></svg>
              </button>
              <p class="ll-ba-nsfw-modal__message">$message_html</p>
              $actions
          </div>
      </div>
    HTML;

    return apply_filters( 'lifted_logic/bag/nsfw_modal_markup', $markup, $message, $archive_url, $actions );
  }

  // Mobile filter flyout actions (Clear + Apply buttons)
  public static function bag_filter_actions_markup(): string {
    $markup = <<<HTML
      <div class="ll-ba-filter-actions">
        <button type="button" id="ll-ba-filter-clear-mobile" class="ll-ba-filter-clear-mobile">Clear All Filters</button>
        <button type="button" id="ll-ba-filter-apply" class="ll-ba-filter-apply">Apply Filters</button>
      </div>
    HTML;

    return apply_filters( 'lifted_logic/bag/filter_actions_markup', $markup );
  }

  // Sensitive overlay panel on slider cards
  public static function bag_slider_card_sensitive_overlay_markup( string $message ): string {
    $message_html = esc_html( $message );

    $actions = <<<HTML
      <div class="ll-ba-slider-card__sensitive-actions">
        <button type="button" class="ll-ba-slider-card__sensitive-btn ba_btn-primary" data-slider-card-action="unblur-once">Unblur This Only</button>
        <button type="button" class="ll-ba-slider-card__sensitive-btn ba_btn-secondary" data-slider-card-action="unblur-all">
          <svg class="icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
          Unblur All
          <svg class="icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
        </button>
      </div>
    HTML;

    $markup = <<<HTML
      <div class="ll-ba-slider-card__sensitive-overlay" aria-label="Sensitive content">
        <div class="ll-ba-slider-card__sensitive-panel">
          <p class="ll-ba-slider-card__sensitive-message">$message_html</p>
          $actions
        </div>
      </div>
    HTML;

    return apply_filters( 'lifted_logic/bag/slider_card_sensitive_overlay_markup', $markup, $message, $actions );
  }

  // Shared button generators — used internally by the CTA link filters below.
  // Hook the shared filter to restyle every button of that style plugin-wide in one place;
  // hook a specific method's own filter (below) only to change a single instance.

  // Shared "primary" CTA button (solid background, no icon).
  public static function bag_primary_button_markup( string $text, string $url, array $args = [] ): string {
    $target      = $args['target'] ?? '';
    $extra_class = $args['class'] ?? '';
    $base_class  = $args['base_class'] ?? 'ba_btn-primary';
    $classes     = esc_attr( trim( $extra_class . ( $extra_class && $base_class ? ' ' : '' ) . $base_class ) );
    $href        = esc_url( $url );
    $text_html   = esc_html( $text );
    $target_attr = $target ? 'target="' . esc_attr( $target ) . '"' : '';
    $sr_text     = $target === '_blank' ? '<span class="sr-only"> (opens in new tab)</span>' : '';

    $markup = <<<HTML
      <a class="$classes" href="$href" $target_attr>$text_html $sr_text</a>
    HTML;

    return apply_filters( 'lifted_logic/bag/primary_button_markup', $markup, $text, $url, $args );
  }

  // Shared "secondary" CTA button (icon-flanked link). Icon/text are concatenated with no
  // literal whitespace between them: ba_btn-secondary is a flex container (whitespace-only
  // text nodes aren't rendered as flex items anyway), and this matches bag_back_button_markup's
  // existing single-line markup, which relies on there being no gap between icon and text.
  public static function bag_secondary_button_markup( string $text, string $url, array $args = [] ): string {
    $target      = $args['target'] ?? '';
    $extra_class = $args['class'] ?? '';
    $base_class  = $args['base_class'] ?? 'ba_btn-secondary';
    $classes     = esc_attr( trim( $extra_class . ( $extra_class && $base_class ? ' ' : '' ) . $base_class ) );
    $icon        = $args['icon'] ?? 'arrow-right';
    $href        = esc_url( $url );
    $text_html   = esc_html( $text );
    $target_attr = $target ? 'target="' . esc_attr( $target ) . '"' : '';
    $sr_text     = $target === '_blank' ? '<span class="sr-only"> (opens in new tab)</span>' : '';
    $icon_html   = $icon ? "<svg class='icon icon-$icon' aria-hidden='true'><use xlink:href='#icon-$icon'></use></svg>" : '';

    $markup = <<<HTML
      <a class="$classes" href="$href" $target_attr>$icon_html$text_html$icon_html$sr_text</a>
    HTML;

    return apply_filters( 'lifted_logic/bag/secondary_button_markup', $markup, $text, $url, $args );
  }

  // CTA Link card
  public static function bag_link_card_markup( string $title, $link ): string {
    // ACF's link field returns '' (not an array) when empty.
    if ( empty( $link ) || !is_array( $link ) ) return '';

    $button = self::bag_primary_button_markup( $link['title'] ?? '', $link['url'] ?? '', [
      'target' => $link['target'] ?? '',
      'class'  => 'll-ba-single__cta-button',
    ] );

    $markup = <<<HTML
      <div class="ll-ba-single__cta-card">
        <p class="ll-ba-single__cta-title ba_hdg-small">$title</p>
        $button
      </div>
    HTML;

    return apply_filters( 'lifted_logic/bag/link_card_markup', $markup, $title, $link );
  }

  // Hero banner CTA (used by both the archive and category/taxonomy archive hero banners)
  public static function bag_hero_banner_link_markup( $link ): string {
    // ACF's link field returns '' (not an array) when empty.
    if ( empty( $link ) || !is_array( $link ) ) return '';

    $markup = self::bag_primary_button_markup( $link['title'] ?? '', $link['url'] ?? '', [
      'target' => $link['target'] ?? '',
    ] );

    return apply_filters( 'lifted_logic/bag/hero_banner_link_markup', $markup, $link );
  }

  // "View All Before & Afters" link on the categories archive
  public static function bag_categories_all_link_markup( string $url ): string {
    if ( empty( $url ) ) return '';

    $markup = self::bag_secondary_button_markup( 'View All Before & Afters', $url, [
      'class' => 'll-ba-archive-categories__all-link',
    ] );

    return apply_filters( 'lifted_logic/bag/categories_all_link_markup', $markup, $url );
  }

  // "View All" link on the Before & Afters Grid component
  public static function bag_grid_view_all_link_markup( $link ): string {
    // ACF's link field returns '' (not an array) when empty.
    if ( empty( $link ) || !is_array( $link ) ) return '';

    $markup = self::bag_secondary_button_markup( $link['title'] ?? '', $link['url'] ?? '', [
      'target' => $link['target'] ?? '',
      'class'  => 'll-ba-bag-grid__all-link',
    ] );

    return apply_filters( 'lifted_logic/bag/grid_view_all_link_markup', $markup, $link );
  }

  // CTA on the Related Before & Afters component
  public static function bag_related_bna_link_markup( $link ): string {
    // ACF's link field returns '' (not an array) when empty.
    if ( empty( $link ) || !is_array( $link ) ) return '';

    $markup = self::bag_primary_button_markup( $link['title'] ?? '', $link['url'] ?? '', [
      'target' => $link['target'] ?? '',
    ] );

    return apply_filters( 'lifted_logic/bag/related_bna_link_markup', $markup, $link );
  }

  /**
   * Sanitize wysiwyg content for output the same way wp_kses_post() would,
   * but additionally allowing the inline SVG icon markup (<svg>/<use>/<path>)
   * that add_button_markup-style theme filters inject into acf_the_content.
   * Scoped to this helper only — does not touch the global 'post' kses context.
   */
  public static function bag_sanitize_wysiwyg( string $content ): string {
    $allowed = wp_kses_allowed_html( 'post' );

    $allowed['svg'] = [
      'xmlns'       => true,
      'fill'        => true,
      'viewbox'     => true,
      'role'        => true,
      'aria-hidden' => true,
      'focusable'   => true,
      'class'       => true,
    ];
    $allowed['path'] = [
      'd'    => true,
      'fill' => true,
    ];
    $allowed['use'] = [
      'xlink:href' => true,
    ];

    return wp_kses( $content, $allowed );
  }

}
