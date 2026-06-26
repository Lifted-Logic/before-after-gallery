<?php
/**
 * Component: Related Before & Afters
 *
 * $component_data is provided by the theme's ll_format_component_data(),
 * which strips the layout name prefix from field names. Sub-fields must be
 * named '{layout_name}_{field_name}' so they arrive here as $component_data['{field_name}'].
 *
 * Override: use add_filter( 'll_bag/inject_component_fields/ll_ba_related_bna', '__return_false' )
 * to disable this component entirely and handle it from the theme.
 */

defined('ABSPATH') || exit;

use LiftedLogic\LLBag\Frontend\TemplateLoader;

$content = $component_data['content'] ?? '';
$link    = $component_data['link']    ?? [];
$posts   = $component_data['posts']   ?? [];
$color_theme = $component_data['color_theme'] ?? '';
?>

<div class="ll-ba-related-bna <?= esc_attr( $color_theme ) ?> component-spacing ba_grid-cols-container">
  <div class="ll-ba-related-bna__container">
    <div class="ll-ba-related-bna__heading-content">
      <?php if ( $content ) : ?>
        <div class="wysiwyg">
          <?= wp_kses_post( $content ) ?>
        </div>
      <?php endif; ?>
      <?php if ( $link ) : ?>
        <a class="btn-primary" href="<?= esc_url( $link['url'] ?? '' ); ?>" <?= !empty( $link['target'] ) ? 'target="' . esc_attr( $link['target'] ) . '"' : '' ?>>
          <?= esc_html( $link['title'] ?? '' ); ?>
          <?php if($link['target'] === '_blank') : ?>
            <span class="sr-only"> (opens in new tab)</span>
          <?php endif; ?>
        </a>
      <?php endif; ?>
    </div>
    <?php if( !empty($posts) ) : ?>    
      <div class="ll-ba-related-bna__card-grid">
        <?php foreach( $posts as $post ) : ?>
          <?php TemplateLoader::get('partials/post-card.php', ['post' => $post]); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
