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
  // FRA-113: "X Y" percentages from the focal point picker. Tailwind's object-position
  // classes are discrete, so an arbitrary focal point has to come through as a style.
  'focal'    => '',
  // Kept for callers, but the focal point is clamped on the client instead: the frame's
  // rendered aspect is not the declared ratio (see .ll-ba-comparison-slider's
  // height: min(100%, 100cqw/--ratio)), so PHP cannot know the box it is clamping to.
  'frame_ratio' => 0,
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

// FRA-113: focal/crop value from the crop panel. Tailwind's object-position classes are
// discrete, so an arbitrary focal point has to come through as an inline style.
//
// An empty value means no crop was ever set, so the image renders exactly as it always
// has. That is what lets this ship without touching a single existing post.
$focal = trim( (string) ( $component_data['focal'] ?? '' ) );
if ( $focal !== '' && preg_match( '/^(\d{1,3})\s+(\d{1,3})(?:\s+(\d{1,4}))?$/', $focal, $m ) ) {
  $fx   = min( 100, (int) $m[1] );
  $fy   = min( 100, (int) $m[2] );
  $zoom = isset( $m[3] ) ? max( 100, min( 1000, (int) $m[3] ) ) : 100;

  // One model for every image: contained in a fixed-ratio frame, with zoom and the focal
  // point driving transform-origin. Single images used to take a separate cover+focal
  // path because their frame followed the visitor's screen, so a zoom percentage could
  // never mean anything stable there. One-image rows declare a ratio now, so that split
  // is gone — a value reaching here without a zoom simply sits at 100%, fully contained.
  //
  // Utility classes come from the theme's Tailwind, not this plugin, so set the fit
  // inline as well or the class alone would do nothing on a site without them.
  $image_args['class'] = trim( str_replace( 'object-cover', '', $image_args['class'] ) ) . ' object-contain';
  // object-position stays centred; the focal point drives transform-origin alone.
  // Using both put the image under two competing offsets.
  $style = 'object-fit:contain;object-position:50% 50%;';

  if ( $zoom > 100 ) {
    // Scaling about the focal point is what pans: at zoom z, an origin D% from centre
    // shifts the content by D% x (z - 1), and it can never expose an edge.
    // .fit-image clips, so the overflow is hidden rather than bleeding out.
    $style .= 'transform:scale(' . ( $zoom / 100 ) . ');transform-origin:' . $fx . '% ' . $fy . '%;';
  }

  $image_args['style'] = $style;
}

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

