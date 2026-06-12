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
    $classes = 'bag_back-text bag-inline-block';
    $text    = 'Back to Gallery';
    $refUrl  = isset($_GET['ba_ref']) ? wp_validate_redirect(wp_unslash($_GET['ba_ref']), '') : '';
    $href    = esc_url($refUrl ?: get_post_type_archive_link('ll_before_after') ?: site_url('/'));
    $markup  = <<<HTML
      <a href="$href" class="$classes"><svg class='icon icon-arrow-right' aria-hidden='true'><use xlink:href='#icon-arrow-right'></use></svg>$text</a>
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
