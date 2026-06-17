<?php
/**
 * Component: Before & Afters Grid
 *
 * $component_data is provided by the theme's ll_format_component_data(),
 * which strips the layout name prefix from field names. Sub-fields must be
 * named '{layout_name}_{field_name}' so they arrive here as $component_data['{field_name}'].
 *
 * Override: place this file at {theme}/ll-before-after/components/BeforeAndAftersGrid/before-and-afters-grid.php
 */

defined('ABSPATH') || exit;

use LiftedLogic\LLBag\Frontend\TemplateLoader;

$posts = $component_data['posts'] ?? [];

if ( empty( $posts ) ) return;

$extra_classes = implode( ' ', array_map( 'sanitize_html_class', apply_filters( 'lifted_logic/bag/grid_classes', [] ) ) );
?>

<div class="ll-ba-bag-grid ba_grid-cols-container<?= $extra_classes ? ' ' . $extra_classes : '' ?>">
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
  <div class="ll-ba-bag-grid__card-grid">
    <?php foreach ( $posts as $post ) : ?>
      <?php TemplateLoader::get( 'partials/post-card.php', ['post' => $post] ); ?>
    <?php endforeach; ?>
  </div>
  <div class="ll-ba-bag-grid__pagination"></div>
</div>
