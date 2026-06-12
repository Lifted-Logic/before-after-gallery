<?php
/**
* Fit Image
* -----------------------------------------------------------------------------
*
* Fit Image component
* fit accepts tailwind classes for object-fit and its variants
* https://tailwindcss.com/docs/object-fit/#app
*
* position accepts tailwind classes for object-position and its variants
* https://tailwindcss.com/docs/object-position/#app
*
*/

$classes = $component_args['classes'] ?? [];
$component_id = $component_args['id'] ?? false;
$defaults = [
  'image_id' => null,
  'thumbnail_size' => 'large',
  'fit'      => 'object-cover',
  'position' => 'object-center',
  'alt'      => null,
  'loading'  => true
];

$component_data = wp_parse_args( $component_data, $defaults );

$image_id       = $component_data['image_id'];
$thumbnail_size = $component_data['thumbnail_size'];
$fit            = $component_data['fit'];
$position       = $component_data['position'];
$image_url      = wp_get_attachment_url( $image_id );
$alt            = $component_data['alt'];

$image_args = [];

$image_args['class'] = $fit . ' ' .$position;

if ($alt) {
  $image_args['alt'] = $alt;
}

$image_args['loading'] = $component_data['loading'] ? 'lazy' : 'eager';

?>

<?php if ( !$image_id ) return; ?>
<div class="fit-image <?= esc_attr( implode( " ", $classes ) ); ?>" <?= ( $component_id ? 'id="' . esc_attr( $component_id ) . '"' : '' ); ?>>
  <?= wp_get_attachment_image(
    $image_id,
    $thumbnail_size,
    false,
    $image_args
  ); ?>
</div>

