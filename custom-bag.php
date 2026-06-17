<?php
/**
 * Custom Before & After Gallery hooks
 *
 * Drop this file into a theme (e.g. require it from functions.php) to
 * override plugin markup. Each filter reproduces the plugin default exactly —
 * copy the one(s) you want to change and modify as needed.
 *
 * Filter reference: ll-bag-starter/README.md → Hooks
 */

defined( 'ABSPATH' ) || exit;


// Back-to-gallery link on the single post page.
add_filter( 'lifted_logic/bag/bag_back_button_markup', function ( $markup, $classes, $text, $href ) {
	return "
		<a href=\"{$href}\" class=\"{$classes}\">
			<svg class='icon icon-arrow-left' aria-hidden='true'><use xlink:href='#icon-arrow-left'></use></svg>
			{$text}
			<svg class='icon icon-arrow-left' aria-hidden='true'><use xlink:href='#icon-arrow-left'></use></svg>
		</a>
	";
}, 10, 4 );


// Prev/next arrow buttons on the related posts slider.
// Buttons must keep splide__arrow--prev / splide__arrow--next for Splide to wire them up.
add_filter( 'lifted_logic/bag/related_slider_arrows_markup', function ( $markup, $prev, $next ) {
	$prev = '
		<button class="ll-ba-single__related-arrow ll-ba-single__related-arrow--prev splide__arrow--prev">
			<svg class="ll-ba-single__related-arrow-icon icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
			<span class="sr-only">Previous Slide</span>
		</button>
	';

	$next = '
		<button class="ll-ba-single__related-arrow ll-ba-single__related-arrow--next splide__arrow--next">
			<svg class="ll-ba-single__related-arrow-icon icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
			<span class="sr-only">Next Slide</span>
		</button>
	';

	return "
		<div class=\"ll-ba-single__related-arrows splide__arrows\">{$prev}{$next}</div>
	";
}, 10, 3 );


// CTA link card in the single post sidebar.
add_filter( 'lifted_logic/bag/link_card_markup', function ( $markup, $title, $link ) {
	$href      = esc_url( $link['url'] ?? '' );
	$link_text = esc_html( $link['title'] ?? '' );
	$target    = ! empty( $link['target'] ) ? 'target="' . esc_attr( $link['target'] ) . '"' : '';
	$sr_text   = $link['target'] === '_blank' ? '<span class="sr-only"> (opens in new tab)</span>' : '';

	return "
		<div class=\"ll-ba-single__cta-card\">
			<p class=\"ll-ba-single__cta-title ba_hdg-small\">{$title}</p>
			<a class=\"ll-ba-single__cta-button ba_btn-primary\" href=\"{$href}\" {$target}>{$link_text} {$sr_text}</a>
		</div>
	";
}, 10, 3 );


// Full-screen NSFW confirmation modal on sensitive single posts.
// Modal must keep id="ll-ba-nsfw-modal" and class="ll-ba-hidden" — nsfw-modal.js targets these.
// Buttons are wired via data-nsfw-action: unblur-once | unblur-all | leave.
// The close button needs data-fallback-url for visitors with no referrer.
add_filter( 'lifted_logic/bag/nsfw_modal_markup', function ( $markup, $message, $archive_url, $actions ) {
	$fallback_url = esc_url( $archive_url );
	$message_html = esc_html( $message );

	$actions = "
		<div class=\"ll-ba-nsfw-modal__actions\">
			<button type=\"button\" class=\"ll-ba-nsfw-modal__btn ba_btn-primary\" data-nsfw-action=\"unblur-once\">Unblur This Only</button>
			<button type=\"button\" class=\"ll-ba-nsfw-modal__btn ll-ba-nsfw-modal__btn--secondary\" data-nsfw-action=\"unblur-all\">
				<svg class=\"icon icon-arrow-right\" aria-hidden=\"true\"><use xlink:href=\"#icon-arrow-right\"></use></svg>
				Unblur All
				<svg class=\"icon icon-arrow-right\" aria-hidden=\"true\"><use xlink:href=\"#icon-arrow-right\"></use></svg>
			</button>
		</div>
	";

	return "
		<div class=\"ll-ba-nsfw-modal ll-ba-hidden\" id=\"ll-ba-nsfw-modal\" role=\"dialog\" aria-modal=\"true\" aria-label=\"Sensitive content\">
			<div class=\"ll-ba-nsfw-modal__panel ll-ba-popup-modal\">
				<button type=\"button\" class=\"ll-ba-nsfw-modal__close\" data-nsfw-action=\"leave\" data-fallback-url=\"{$fallback_url}\" aria-label=\"Go back\">
					<svg class=\"icon icon-exit\" aria-hidden=\"true\"><use xlink:href=\"#icon-exit\"></use></svg>
				</button>
				<p class=\"ll-ba-nsfw-modal__message\">{$message_html}</p>
				{$actions}
			</div>
		</div>
	";
}, 10, 4 );


// Clear / Apply button bar at the bottom of the mobile filter flyout.
// Buttons must keep id="ll-ba-filter-clear-mobile" and id="ll-ba-filter-apply" — filters.js targets these.
add_filter( 'lifted_logic/bag/filter_actions_markup', function ( $markup ) {
	return '
		<div class="ll-ba-filter-actions">
			<button type="button" id="ll-ba-filter-clear-mobile" class="ll-ba-filter-clear-mobile">Clear</button>
			<button type="button" id="ll-ba-filter-apply" class="ll-ba-filter-apply">Apply</button>
		</div>
	';
} );


// Extra classes on the .ll-ba-single__sidebar on the single post page.
add_filter( 'lifted_logic/bag/single_sidebar_classes', function ( $classes ) {
	return $classes;
} );


// Extra classes on the .ll-ba-archive__inner wrapper on the archive page.
add_filter( 'lifted_logic/bag/archive_inner_classes', function ( $classes ) {
	return $classes;
} );


// Extra classes on the Before & Afters Grid wrapper element.
// Return an array of class strings — e.g. a theme color class like 'theme-two'.
add_filter( 'lifted_logic/bag/grid_classes', function ( $classes ) {
	return $classes;
} );


// Sensitive-content overlay panel on Before & After Slider cards.
// Buttons are wired via data-slider-card-action: unblur-once | unblur-all.
add_filter( 'lifted_logic/bag/slider_card_sensitive_overlay_markup', function ( $markup, $message, $actions ) {
	$message_html = esc_html( $message );

	$actions = '
		<div class="ll-ba-slider-card__sensitive-actions">
			<button type="button" class="ll-ba-slider-card__sensitive-btn ba_btn-primary" data-slider-card-action="unblur-once">Unblur This Only</button>
			<button type="button" class="ll-ba-slider-card__sensitive-btn ba_btn-secondary" data-slider-card-action="unblur-all">
				<svg class="icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
				Unblur All
				<svg class="icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
			</button>
		</div>
	';

	return "
		<div class=\"ll-ba-slider-card__sensitive-overlay\" aria-label=\"Sensitive content\">
			<div class=\"ll-ba-slider-card__sensitive-panel\">
				<p class=\"ll-ba-slider-card__sensitive-message\">{$message_html}</p>
				{$actions}
			</div>
		</div>
	";
}, 10, 3 );
