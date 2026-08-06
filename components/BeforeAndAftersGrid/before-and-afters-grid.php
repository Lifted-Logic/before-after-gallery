<?php
/**
 * Component: Before & Afters Grid
 *
 * $component_data is provided by the theme's ll_format_component_data(),
 * which strips the layout name prefix from field names. Sub-fields must be
 * named '{layout_name}_{field_name}' so they arrive here as $component_data['{field_name}'].
 *
 * Override: use add_filter( 'll_bag/inject_component_fields/ll_ba_grid', '__return_false' )
 * to disable this component entirely and handle it from the theme.
 */

defined('ABSPATH') || exit;

use LiftedLogic\LLBag\Frontend\TemplateLoader;

$posts         = $component_data['posts'] ?? [];
$content       = $component_data['content'] ?? '';
$columns       = (int) ( $component_data['columns'] ?? 3 );
$view_all      = $component_data['view_all'] ?? null;
$hide_provider = !empty( $component_data['hide_provider'] );

if ( empty( $posts ) ) return;

if ( !in_array( $columns, [ 2, 3, 4 ], true ) ) $columns = 3;

$extra_classes = implode( ' ', array_map( 'sanitize_html_class', apply_filters( 'lifted_logic/bag/grid_classes', [] ) ) );
?>

<div class="ll-ba-bag-grid ba_grid-cols-container component-spacing<?= $extra_classes ? ' ' . $extra_classes : '' ?>">
  <?php if ( $content ) : ?>
    <div class="ll-ba-bag-grid__heading wysiwyg">
      <?= wp_kses_post( $content ) ?>
    </div>
  <?php endif; ?>
  <div class="ll-ba-bag-grid__header">
    <div class="ll-ba-bag-grid__sensitive-bar ll-ba-sensitive-bar ll-ba-hidden">
      <span class="ll-ba-sensitive-bar__label">Sensitive Images</span>
      <div class="ll-ba-sensitive-bar__options" role="group" aria-label="Sensitive image display mode">
        <button type="button" class="ll-ba-sensitive-btn" data-mode="blur">
          <svg class='icon icon-check-mark' aria-hidden='true'><use xlink:href='#icon-check-mark'></use></svg>
          Blur
        </button>
        <button type="button" class="ll-ba-sensitive-btn" data-mode="unblur">
          <svg class='icon icon-check-mark' aria-hidden='true'><use xlink:href='#icon-check-mark'></use></svg>
          Unblur
        </button>
        <button type="button" class="ll-ba-sensitive-btn" data-mode="hide">
          <svg class='icon icon-check-mark' aria-hidden='true'><use xlink:href='#icon-check-mark'></use></svg>
          Hide
        </button>
      </div>
    </div>
    <?php if ( $view_all ) : ?>
      <a class="ll-ba-bag-grid__all-link ba_btn-secondary" href="<?= esc_url( $view_all['url'] ) ?>" <?= !empty( $view_all['target'] ) ? 'target="' . esc_attr( $view_all['target'] ) . '"' : '' ?>>
        <svg class='icon icon-arrow-right' aria-hidden='true'><use xlink:href='#icon-arrow-right'></use></svg>
        <?= esc_html( $view_all['title'] ) ?>
        <svg class='icon icon-arrow-right' aria-hidden='true'><use xlink:href='#icon-arrow-right'></use></svg>
        <?php if ( ( $view_all['target'] ?? '' ) === '_blank' ) : ?>
          <span class="sr-only"> (opens in new tab)</span>
        <?php endif; ?>
      </a>
    <?php endif; ?>
  </div>
  <div class="ll-ba-bag-grid__card-grid ll-ba-bag-grid__card-grid--cols-<?= $columns ?>">
    <?php foreach ( $posts as $post ) : ?>
      <?php TemplateLoader::get( 'partials/post-card.php', ['post' => $post, 'hide_provider' => $hide_provider] ); ?>
    <?php endforeach; ?>
  </div>
  <div class="ll-ba-bag-grid__pagination"></div>
</div>
