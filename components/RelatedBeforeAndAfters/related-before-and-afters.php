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
use LiftedLogic\LLBag\Hooks\Hooks;

$content = $component_data['content'] ?? '';
$link    = $component_data['link']    ?? [];
$posts   = $component_data['posts']   ?? [];
$color_theme = $component_data['color_theme'] ?? '';
$hide_provider = !empty( $component_data['hide_provider'] );
?>

<div class="ll-ba-related-bna <?= esc_attr( $color_theme ) ?> component-spacing ba_grid-cols-container">
  <div class="ll-ba-related-bna__container">
    <div class="ll-ba-related-bna__heading-content">
      <?php if ( $content ) : ?>
        <div class="wysiwyg">
          <?= Hooks::bag_sanitize_wysiwyg( $content ) ?>
        </div>
      <?php endif; ?>
      <?= Hooks::bag_related_bna_link_markup( $link ) ?>
    </div>
    <?php if( !empty($posts) ) : ?>    
      <div class="ll-ba-related-bna__card-grid">
        <?php foreach( $posts as $post ) : ?>
          <?php TemplateLoader::get('partials/post-card.php', ['post' => $post, 'hide_provider' => $hide_provider]); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
