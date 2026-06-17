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
    $text       = 'Back to Gallery';
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

    $href   = esc_url( $refUrl ?: $archiveUrl );
    $markup = <<<HTML
      <a href="$href" class="$classes"><svg class='icon icon-arrow-left' aria-hidden='true'><use xlink:href='#icon-arrow-left'></use></svg>$text<svg class='icon icon-arrow-left' aria-hidden='true'><use xlink:href='#icon-arrow-left'></use></svg></a>
    HTML;

    return apply_filters( 'lifted_logic/bag/bag_back_button_markup', $markup, $classes, $text, $href );
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
        <button type="button" id="ll-ba-filter-clear-mobile" class="ll-ba-filter-clear-mobile">Clear</button>
        <button type="button" id="ll-ba-filter-apply" class="ll-ba-filter-apply">Apply</button>
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

  // CTA Link card
  public static function bag_link_card_markup( string $title, array $link ): string {
    if ( empty( $link ) ) return '';

    $href       = esc_url( $link['url'] ?? '' );
    $link_text  = esc_html( $link['title'] ?? '' );
    $target     = !empty( $link['target'] ) ? 'target="' . esc_attr( $link['target'] ) . '"' : '';
    $sr_text    = $link['target'] === '_blank' ? '<span class="sr-only"> (opens in new tab)</span>' : '';
    $markup     = <<<HTML
      <div class="ll-ba-single__cta-card">
        <p class="ll-ba-single__cta-title ba_hdg-small">$title</p>
        <a class="ll-ba-single__cta-button ba_btn-primary" href="$href" $target>$link_text $sr_text</a>
      </div>
    HTML;

    return apply_filters( 'lifted_logic/bag/link_card_markup', $markup, $title, $link );
  }

}
