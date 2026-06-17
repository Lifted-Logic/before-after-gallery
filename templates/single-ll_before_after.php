<?php
/**
 * Template: Single Before & After post
 *
 * Override: place this file at {theme}/ll-before-after/single-ll_before_after.php
 */

defined('ABSPATH') || exit;

use LiftedLogic\LLBag\Hooks\Hooks;
use LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType;
use LiftedLogic\LLBag\Support\PostTerms;

$post_treatment_title = get_field('ll_ba_title');
$global_treatment_title = get_field('ll_ba_global_default_single_page_label', 'options');
$treatment_title = $post_treatment_title ?: ( $global_treatment_title ?: 'Treatments Used:' );
$global_cta_title = get_field('ll_ba_global_cta_title', 'options') ?? '';
$global_cta_link = get_field('ll_ba_global_cta_link', 'options') ?? '';

$card_terms  = PostTerms::forCard( get_the_ID() );
$archive_url = get_post_type_archive_link( BeforeAfterPostType::SLUG );
$is_nsfw     = (bool) get_field( 'll_ba_is_nsfw' );
$nsfw_popup_text = get_field( 'll_bag_nsfw_popup_text', 'options' ) ?: 'This before and after contains sensitive content.';

$provider_terms = wp_get_post_terms( get_the_ID(), 'll_ba_provider' );
$provider_term  = ( !is_wp_error( $provider_terms ) && !empty( $provider_terms ) ) ? $provider_terms[0] : null;
$provider_image = $provider_term ? get_field( 'll_ba_provider_image', 'term_' . $provider_term->term_id ) : null;
$provider_link  = $provider_term ? get_field( 'll_ba_provider_link',  'term_' . $provider_term->term_id ) : null;


$detail_sections_field = get_field('ll_ba_detail_sections') ?? [];
$detail_sections = [];
if ( !empty($detail_sections_field) ) {
    $is_tabs = count($detail_sections_field) > 1;
    foreach ( $detail_sections_field as $section ) {
        $detail_sections[] = [
            'tab_id'            => 'll-ba-detail-tab-' . uniqid(),
            'title'             => $section['ll_ba_detail_title'] ?? '',
            'content'           => $section['ll_ba_detail_content'] ?? '',
            'read_more_content' => $section['ll_ba_detail_read_more_content'] ?? '',
            'read_more_id'      => 'll-ba-detail-read-more-' . uniqid(),
            'tag'               => $is_tabs ? 'button' : 'div',
            'is_tab'            => $is_tabs,
        ];
    }
}

$images_field = get_field('field_ll_ba_images');
$ba_images = [];
if ( !empty($images_field) ) {
    foreach ( $images_field as $image ) {
        $ratio_class = 'll-ba-single__ratio--square';
        if ( $image['ll_ba_image_ratio'] === 'wide' ) {
            $ratio_class = 'll-ba-single__ratio--wide';
        } elseif ( $image['ll_ba_image_ratio'] === 'panorama' ) {
            $ratio_class = 'll-ba-single__ratio--panorama';
        } elseif ( $image['ll_ba_image_ratio'] === 'vertical' ) {
            $ratio_class = 'll-ba-single__ratio--vertical';
        }

        $ba_images[] = [
            'option'           => $image['ll_ba_image_options'],
            'ratio'            => $ratio_class,
            'single_image_id'  => $image['ll_ba_single_image'],
            'before_image_id'  => $image['ll_ba_before_image'],
            'after_image_id'   => $image['ll_ba_after_image'],
            'video_url'        => $image['ll_ba_video_url'],
            'video_title'      => $image['ll_ba_video_title'],
            'comparison_slider'=> $image['ll_ba_comparison_slider'],
        ];
    }
}
?>

<main class="ll-ba-single">

    <?php if ( $is_nsfw ) : ?>
        <?= Hooks::bag_nsfw_modal_markup( $nsfw_popup_text, $archive_url ) ?>
    <?php endif; ?>

    <?php $sidebar_classes = implode( ' ', array_map( 'sanitize_html_class', apply_filters( 'lifted_logic/bag/single_sidebar_classes', [] ) ) ); ?>
    <div class="ll-ba-single__sidebar<?= $sidebar_classes ? ' ' . $sidebar_classes : '' ?>">

        <div class="ll-ba-single__top">
            <div class="ll-ba-single__back ba_btn-secondary--back">
                <?= Hooks::bag_back_button_markup() ?>
            </div>
    
            <div class="ll-ba-single__header">
                <h4 class="ll-ba-single__title ba_hdg-medium">
                    <?= esc_html( $treatment_title ) ?>
                </h4>
                <?php if ( !empty( $card_terms['terms'] ) ) : ?>
                    <ul class="ll-ba-single__categories">
                        <?php foreach ( $card_terms['terms'] as $term ) : ?>
                            <li class="ll-ba-single__category">
                                <a class="ll-ba-single__category-pill" href="<?= esc_url( add_query_arg( $card_terms['taxonomy'], $term->slug, $archive_url ) ) ?>">
                                    <?= esc_html( $term->name ) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ( $provider_term && ( $provider_image || $provider_link ) ) : ?>
                    <div class="ll-ba-single__provider">
                        <?php if ( $provider_image ) : ?>
                            <?php $img = wp_get_attachment_image( $provider_image, 'thumbnail', false, [
                                'class' => 'll-ba-single__provider-image',
                                'alt'   => esc_attr( $provider_term->name ),
                            ] ); ?>
                            <span class="ll-ba-single__provider-image-wrap">
                                <?= $img ?>
                            </span>
                        <?php endif; ?>
                        <?php if ( !empty( $provider_link['url'] ) ) : ?>
                            <a class="ll-ba-single__provider-link ba_btn-secondary" href="<?= esc_url( $provider_link['url'] ) ?>" <?= !empty( $provider_link['target'] ) ? 'target="' . esc_attr( $provider_link['target'] ) . '"' : '' ?>>
                                <svg class="icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
                                <?= esc_html( $provider_link['title'] ?: $provider_term->name ) ?>
                                <svg class="icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="ll-ba-single__info">
            <?php if ( !empty($detail_sections) ) : ?>
                <div class="ll-ba-single__details">
                    <div class="ll-ba-single__detail-triggers">
                        <?php foreach ( $detail_sections as $key => $section_trigger ) : ?>
                            <<?= $section_trigger['tag'] ?>
                                class="ll-ba-single__detail-trigger"
                                <?php if ( $section_trigger['is_tab'] ) : ?>
                                    data-toggle-target="#<?= $section_trigger['tab_id'] ?>"
                                    data-toggle-class="ll-ba-is-active"
                                    data-toggle-radio-group="ll-ba-single-page-detail-sections"
                                    aria-expanded="false"
                                    <?= $key === 0 ? 'data-toggle-is-active' : '' ?>
                                <?php endif; ?>
                            >
                                <?= esc_html( $section_trigger['title'] ) ?>
                            </<?= $section_trigger['tag'] ?>>
                        <?php endforeach; ?>
                    </div>
                    <div class="ll-ba-single__detail-panels">
                        <?php foreach ( $detail_sections as $section_content ) : ?>
                            <div id="<?= $section_content['tab_id'] ?>" class="ll-ba-single__detail-panel wysiwyg <?= $section_content['is_tab'] ? ' ll-ba-single__detail-panel--tab' : '' ?>">
                                <?= wp_kses_post( $section_content['content'] ) ?>
                                <?php if ( !empty($section_content['read_more_content']) ) : ?>
                                    <div class="ll-ba-single__detail-read-more">
                                        <button class="ll-ba-single__detail-read-more-trigger ba_btn-secondary" data-mfp-src="#<?= $section_content['read_more_id'] ?>">
                                            <svg class="icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
                                            Read More
                                            <svg class="icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
                                        </button>
                                        <div class="mfp-hide ll-ba-single__read-more-popup ll-ba-popup-modal" id="<?= $section_content['read_more_id'] ?>">
                                            <div class="wysiwyg">
                                                <?= wp_kses_post( $section_content['read_more_content'] ) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
    
            <!-- Link Card -->
            <?php if ( is_array($global_cta_link) ) : ?>
                <div class="ll-ba-single__cta">
                    <?= Hooks::bag_link_card_markup( $global_cta_title, $global_cta_link ) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Related Slider -->
        <div class="ll-ba-single__related">
            <div class="splide ll-ba-related-slider" data-post-id="<?= get_the_ID() ?>" aria-label="Before & After Gallery Related Posts">
                <div class="ll-ba-single__related-header">
                    <div class="ll-ba-single__related-title-wrap">
                        <p class="ll-ba-single__related-title ba_hdg-medium">
                            More Like This
                        </p>
                    </div>
                </div>
                <div class="splide__track">
                    <ul class="splide__list"></ul>
                </div>
                <?= Hooks::bag_related_slider_arrows_markup() ?>
            </div>
        </div>

    </div>

    <div class="ll-ba-single__gallery<?= $is_nsfw ? ' ll-ba-single__gallery--sensitive' : '' ?>">
        <div class="ll-ba-single__gallery-inner">
            <?php if ( !empty( $ba_images ) ) : ?>

                <div class="splide ll-ba-single-page-slider" aria-label="Before & After Gallery">
                    <div class="splide__track">
                        <ul class="splide__list">
                            <?php foreach ( $ba_images as $image ) : ?>

                                <?php if ( $image['option'] === 'one-image' && $image['single_image_id'] ) : ?>
                                    <li class="splide__slide">
                                        <div class="ll-ba-single__slide-inner">
                                            <div class="ll-ba-single__slide-image">
                                                <?php echo wp_get_attachment_image( $image['single_image_id'], 'large', "", [ "class" => "" ]); ?>
                                            </div>
                                        </div>
                                    </li>

                                <?php elseif ( $image['option'] === 'two-images' ) : ?>
                                    <?php if ( $image['comparison_slider'] ) : ?>
                                        <li class="splide__slide">
                                            <div class="ll-ba-single__slide-inner">
                                                <div class="ll-ba-comparison-slider <?= $image['ratio'] ?>">
                                                    <div class="ll-ba-comparison-slider__before">
                                                        <?php bag_include_partial( 'fit-image', [
                                                            'image_id'       => $image['before_image_id'],
                                                            'thumbnail_size' => 'large',
                                                            'fit'            => 'object-cover',
                                                            'position'       => 'object-center',
                                                            'loading'        => true,
                                                        ] ); ?>
                                                    </div>
                                                    <div class="ll-ba-comparison-slider__after">
                                                        <?php bag_include_partial( 'fit-image', [
                                                            'image_id'       => $image['after_image_id'],
                                                            'thumbnail_size' => 'large',
                                                            'fit'            => 'object-cover',
                                                            'position'       => 'object-center',
                                                            'loading'        => true,
                                                        ] ); ?>
                                                    </div>
                                                    <div class="ll-ba-comparison-slider__divider">
                                                        <div class="ll-ba-comparison-slider__line"></div>
                                                        <div class="ll-ba-comparison-slider__handle">
                                                            <svg class='icon icon-chevron-right' aria-hidden='true'><use xlink:href='#icon-chevron-right'></use></svg>
                                                            <svg class='icon icon-chevron-right' aria-hidden='true'><use xlink:href='#icon-chevron-right'></use></svg>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    <?php else : ?>
                                        <li class="splide__slide">
                                            <div class="ll-ba-single__slide-inner">
                                                <div class="ll-ba-single__side-by-side <?= $image['ratio'] === 'll-ba-single__ratio--wide' || $image['ratio'] === 'll-ba-single__ratio--panorama' ? 'll-ba-single__side-by-side--stacked' : '' ?>">
                                                    <?php if ( $image['before_image_id'] ) : ?>
                                                        <div class="ll-ba-single__side-by-side-image <?= $image['ratio'] ?>">
                                                            <?php bag_include_partial( 'fit-image', [
                                                                'image_id'       => $image['before_image_id'],
                                                                'thumbnail_size' => 'large',
                                                                'fit'            => 'object-cover',
                                                                'position'       => 'object-center',
                                                                'loading'        => true,
                                                            ] ); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ( $image['after_image_id'] ) : ?>
                                                        <div class="ll-ba-single__side-by-side-image <?= $image['ratio'] ?>">
                                                            <?php bag_include_partial( 'fit-image', [
                                                                'image_id'       => $image['after_image_id'],
                                                                'thumbnail_size' => 'large',
                                                                'fit'            => 'object-cover',
                                                                'position'       => 'object-center',
                                                                'loading'        => true,
                                                            ] ); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endif; ?>

                                <?php elseif ( $image['option'] === 'video' ) : ?>
                                    <li class="splide__slide">
                                        <div class="ll-ba-single__slide-inner">
                                            <div class="ll-ba-single__video">
                                                <?php echo wp_get_attachment_image( $image['single_image_id'], 'large', "", [ "class" => "" ]); ?>
                                                <div class="ll-ba-single__video-overlay">
                                                    <a class="ll-ba-single__video-trigger js-init-video" href="<?= esc_url( $image['video_url'] ) ?>" data-title="<?= esc_attr( $image['video_title'] ) ?>">
                                                        <svg class="ll-ba-single__video-icon icon icon-play-triangle" aria-hidden="true"><use xlink:href="#icon-play-triangle"></use></svg>
                                                        <span class="sr-only">View <?= esc_html( $image['video_title'] ) ?> video</span>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <div class="splide ll-ba-single-page-slider-nav" aria-label="Before & After Gallery Thumbnails">
                    <div class="splide__track">
                        <ul class="splide__list">
                            <?php foreach ( $ba_images as $image ) : ?>
                                <?php
                                    $nav_image_id = $image['option'] === 'two-images'
                                        ? $image['before_image_id']
                                        : $image['single_image_id'];
                                ?>
                                <?php if ( $nav_image_id ) : ?>
                                    <li class="splide__slide ll-ba-single__nav-slide">
                                        <div class="ll-ba-single__nav-slide-image-wrapper">
                                            <?php bag_include_partial( 'fit-image', [
                                                'image_id'       => $nav_image_id,
                                                'thumbnail_size' => 'medium',
                                                'fit'            => 'object-cover',
                                                'position'       => 'object-center',
                                                'loading'        => true,
                                            ] ); ?>
                                            <?php if ( $image['option'] === 'video' ) : ?>
                                                <div class="ll-ba-single__nav-video">
                                                    <div class="ll-ba-single__nav-video-overlay"></div>
                                                    <svg class="ll-ba-single__nav-video-icon icon icon-play-triangle" aria-hidden="true"><use xlink:href="#icon-play-triangle"></use></svg>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </div>

</main>
