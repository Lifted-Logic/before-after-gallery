<?php
/**
 * Partial: Before & After post card
 *
 * Available variables:
 *   $post           WP_Post  The post object
 *   $hide_provider  bool     Optional. Suppress the provider photo (FRA-112). Defaults to false.
 *
 * Override: place this file at {theme}/ll-before-after/partials/post-card.php
 */

defined('ABSPATH') || exit;

use LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType;
use LiftedLogic\LLBag\Support\PostTerms;

$permalink = get_permalink($post->ID);
$hide_provider = $hide_provider ?? false;

// Resolve card images from the first row of the ACF images repeater
$firstRow = (get_field('ll_ba_images', $post->ID) ?: [])[0] ?? [];
$option   = $firstRow['ll_ba_image_options'] ?? '';
$beforeId = null;
$afterId  = null;

if ($option === 'two-images') {
  $beforeId = $firstRow['ll_ba_before_image'] ?? null;
  $afterId  = $firstRow['ll_ba_after_image']  ?? null;
} else {
  // one-image and video both store the display image in ll_ba_single_image
  $beforeId = $firstRow['ll_ba_single_image'] ?? null;
}

$card         = PostTerms::forCard($post->ID);
$visibleTerms = $card['visible'];
$overflow     = $card['overflow'];
$cardLabel    = $card['label'];
$cardTaxonomy = $card['taxonomy'];
$termCount    = count($card['terms']);

$archiveUrl = BeforeAfterPostType::getPostsArchiveUrl();

$images_field = get_field('field_ll_ba_images', $post->ID);
$ba_gallery_image = [];
if ( !empty($images_field) ) {
    foreach ( $images_field as $image ) {
        // Shared with the single template (bag_resolve_image_ratio()), so a row gets the same
        // shape on its own page and its card, matching the crop value it was calibrated for.
        $ratio       = bag_resolve_image_ratio( $image );
        $ratio_class = $ratio['class'];
        $ratio_value = $ratio['value'];

        $ba_gallery_image[] = [
            'option'           => $image['ll_ba_image_options'],
            'ratio'            => $ratio_class,
            'ratio_value'      => $ratio_value,
            'ratio_stacked'    => $ratio['stacked'],
            'single_image_id'  => $image['ll_ba_single_image'],
            'before_image_id'  => $image['ll_ba_before_image'],
            'after_image_id'   => $image['ll_ba_after_image'],
            'video_url'        => $image['ll_ba_video_url'] ?? '',
            'video_title'      => $image['ll_ba_video_title'] ?? '',
            'before_focal'     => $image['ll_ba_before_focal'] ?? '',
            'after_focal'      => $image['ll_ba_after_focal']  ?? '',
            // '' = legacy, render the untouched markup; otherwise "X Y Z" in this frame.
            'single_focal'     => bag_single_crop_value( $image, $ratio_value ),
        ];
    }
}

$card_image = $ba_gallery_image[0] ?? null;

$is_stacked = $card_image
    && $card_image['option'] === 'two-images'
    && !empty( $card_image['ratio_stacked'] );

$is_nsfw = get_field('ll_ba_is_nsfw', $post->ID);

$provider_terms  = wp_get_post_terms( $post->ID, 'll_ba_provider' );
$provider_term   = ( !is_wp_error( $provider_terms ) && !empty( $provider_terms ) ) ? $provider_terms[0] : null;
$provider_image  = $provider_term ? get_field( 'll_ba_provider_image', 'term_' . $provider_term->term_id ) : null;
$provider_link   = $provider_term ? get_field( 'll_ba_provider_link',  'term_' . $provider_term->term_id ) : null;
?>

<div class="ll-ba-card<?= $is_nsfw ? ' ll-ba-card--sensitive' : ''; ?>">
  <div class="ll-ba-card__visual">
    <?php if ( $card_image ) : ?>
      <?php if ( $card_image['option'] === 'one-image' && $card_image['single_image_id'] ) : ?>
        <?php $card_single_focal = (string) ( $card_image['single_focal'] ?? '' ); ?>
        <div class="ll-ba-card__image ll-ba-card__single-image<?= $card_single_focal !== '' ? ' ' . $card_image['ratio'] : '' ?>">
          <?php if ( $card_single_focal !== '' ) : ?>
            <?php bag_include_partial( 'fit-image', [
              'image_id'       => $card_image['single_image_id'],
              'thumbnail_size' => 'large',
              'fit'            => 'object-cover',
              'position'       => 'object-center',
              'frame_ratio'    => $card_image['ratio_value'] ?? 0,
              'focal'          => $card_single_focal,
              'loading'        => true,
            ] ); ?>
          <?php else : ?>
            <?php echo wp_get_attachment_image( $card_image['single_image_id'], 'large', "", [ "class" => "" ]); ?>
          <?php endif; ?>
        </div>

      <?php elseif ( $card_image['option'] === 'two-images' ) : ?>
        <div class="<?= $is_stacked ? 'll-ba-card__stacked' : 'll-ba-card__side-by-side' ?>">
          <?php if ( $card_image['before_image_id'] ) : ?>
            <div class="ll-ba-card__image <?= $card_image['ratio'] ?>">
              <?php bag_include_partial( 'fit-image', [
                'image_id'       => $card_image['before_image_id'],
                'thumbnail_size' => 'large',
                'fit'            => 'object-cover',
                'position'       => 'object-center',
                'frame_ratio'    => $card_image['ratio_value'] ?? 0,
                'focal'          => $card_image['before_focal'] ?? '',
                'loading'        => true,
              ] ); ?>
            </div>
          <?php endif; ?>
          <?php if ( $card_image['after_image_id'] ) : ?>
            <div class="ll-ba-card__image <?= $card_image['ratio'] ?>">
              <?php bag_include_partial( 'fit-image', [
                'image_id'       => $card_image['after_image_id'],
                'thumbnail_size' => 'large',
                'fit'            => 'object-cover',
                'position'       => 'object-center',
                'frame_ratio'    => $card_image['ratio_value'] ?? 0,
                'focal'          => $card_image['after_focal'] ?? '',
                'loading'        => true,
              ] ); ?>
            </div>
          <?php endif; ?>
        </div>
      <?php elseif ( $card_image['option'] === 'video' ) : ?>
          <?php if ( $card_image['single_image_id'] ) : ?>
            <div class="ll-ba-card__image ll-ba-card__video-image">
              <?php echo wp_get_attachment_image( $card_image['single_image_id'], 'large', "", [ "class" => "" ]); ?>
            </div>
          <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if ( $is_nsfw ) : ?>
    <div class="ll-ba-card__sensitive-overlay" aria-hidden="true">
      <span class="ll-ba-card__sensitive-label">
        <svg class='icon icon-info' aria-hidden='true'><use xlink:href='#icon-info'></use></svg>
        Sensitive Image
      </span>
    </div>
  <?php endif; ?>

  <a href="<?= esc_url($permalink); ?>" class="ll-ba-card__link" aria-label="<?= esc_attr(get_the_title($post)); ?>"></a>

  <?php if ( $provider_term && $provider_image && !$hide_provider ) : ?>
    <?php $provider_img = wp_get_attachment_image( $provider_image, 'thumbnail', false, [
      'class' => 'll-ba-card__provider-image',
      'alt'   => esc_attr( $provider_term->name ),
    ] ); ?>
    <?php if ( !empty( $provider_link['url'] ) ) : ?>
      <a class="ll-ba-card__provider" href="<?= esc_url( $provider_link['url'] ) ?>" <?= !empty( $provider_link['target'] ) ? 'target="' . esc_attr( $provider_link['target'] ) . '"' : '' ?> aria-label="<?= esc_attr( $provider_term->name ) ?>"><?= $provider_img ?></a>
    <?php else : ?>
      <span class="ll-ba-card__provider"><?= $provider_img ?></span>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($termCount < 2) : ?>


    <div class="ll-ba-card__hover-overlay"></div>

    <div class="ll-ba-card__pills ll-ba-card__pills--hover">
      <p class="ll-ba-card__hover-text">
        View Details
        <svg class='icon icon-arrow-right' aria-hidden='true'><use xlink:href='#icon-arrow-right'></use></svg>
      </p>

      <div class="ll-ba-card__pill-group">
        <?php foreach ($visibleTerms as $term) : ?>
          <?php if ( !empty($term->is_nsfw) ) : ?>
            <div class="ll-ba-card__pill">
              <svg class='icon icon-info' aria-hidden='true'><use xlink:href='#icon-info'></use></svg>
              <span>Sensitive Image</span>
            </div>
          <?php else : ?>
            <div class="ll-ba-card__pill">
              <?= esc_html($term->name); ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($overflow > 0) : ?>
          <span class="ll-ba-card__pill">+<?= $overflow; ?></span>
        <?php endif; ?>
      </div>
    </div>
  <?php elseif ($termCount > 1) : ?>

    <div class="ll-ba-card__pills ll-ba-card__pills--default">
      <span class="ll-ba-card__pill">
        <svg class='icon icon-multiple' aria-hidden='true'><use xlink:href='#icon-multiple'></use></svg>
        <span>
          Multiple <?= esc_html($cardLabel); ?>
        </span>
      </span>
    </div>

    <div class="ll-ba-card__hover-overlay"></div>

    <div class="ll-ba-card__pills ll-ba-card__pills--hover">
      <p class="ll-ba-card__hover-text">
        View Details
        <svg class='icon icon-arrow-right' aria-hidden='true'><use xlink:href='#icon-arrow-right'></use></svg>
      </p>

      <div class="ll-ba-card__pill-group">
        <?php foreach ($visibleTerms as $term) : ?>
          <?php if ( !empty($term->is_nsfw) ) : ?>
            <div class="ll-ba-card__pill">
              <svg class='icon icon-info' aria-hidden='true'><use xlink:href='#icon-info'></use></svg>
              <span>Sensitive Image</span>
            </div>
          <?php else : ?>
            <div class="ll-ba-card__pill">
              <?= esc_html($term->name); ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($overflow > 0) : ?>
          <span class="ll-ba-card__pill">+<?= $overflow; ?></span>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
