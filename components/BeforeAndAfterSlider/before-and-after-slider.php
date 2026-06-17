<?php
/**
 * Component: Before & After Slider
 *
 * $component_data is provided by the theme's ll_format_component_data(),
 * which strips the layout name prefix from field names. Sub-fields must be
 * named '{layout_name}_{field_name}' so they arrive here as $component_data['{field_name}'].
 *
 * Override: place this file at {theme}/ll-before-after/components/BeforeAndAfterSlider/before-and-after-slider.php
 */

defined('ABSPATH') || exit;

use LiftedLogic\LLBag\Frontend\TemplateLoader;

$color_theme = $component_data['color_theme'] ?? '';
$content     = $component_data['content']     ?? '';
$posts       = $component_data['posts']       ?? [];
$layout       = $component_data['layout']       ?? 'content-image';

if ( empty( $posts ) ) return;
?>

<div class="ll-ba-before-after-slider component-spacing ba_grid-cols-container <?= esc_attr( $color_theme ) ?>">
  <div class="ll-ba-before-after-slider__container <?= esc_attr( $layout ) ?>">
    <div class="ll-ba-before-after-slider__content">
      <?php if ( $content ) : ?>
        <div class="ll-ba-before-after-slider__content wysiwyg">
          <?= wp_kses_post( $content ) ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="splide ll-ba-before-after-slider__splide" aria-label="Before & After Slider">
      <div class="splide__track">
        <ul class="splide__list">
          <?php foreach ( $posts as $post ) : ?>
            <li class="splide__slide">
              <?php TemplateLoader::get( 'partials/before-after-slider-post-card.php', ['post' => $post] ); ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="splide__arrows ll-ba-before-after-slider__splide-arrows">
      
        <button class="ba_btn-secondary--back splide__arrow--prev">
          <svg class="size-3 icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
          Back
          <svg class="size-3 icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
        </button>

        <button class="ml-auto ba_btn-secondary splide__arrow--next">
          <svg class="size-3 icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
          Next
          <svg class="size-3 icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
        </button>

      </div>
    </div>
  </div>
</div>
