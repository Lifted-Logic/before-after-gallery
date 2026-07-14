<?php
/**
 * Partial: Before & After post card
 *
 * Available variables:
 *   $post  WP_Post  The post object
 *
 * Override: place this file at {theme}/ll-before-after/partials/post-card.php
 */

defined('ABSPATH') || exit;

use LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType;
use LiftedLogic\LLBag\Support\PostTerms;

$permalink = get_permalink($post->ID);

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

$archiveUrl = get_post_type_archive_link(BeforeAfterPostType::SLUG);

$images_field = get_field('field_ll_ba_images', $post->ID);
$ba_gallery_image = [];
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
        $ba_gallery_image[] = [
            'option'           => $image['ll_ba_image_options'],
            'ratio'            => $ratio_class,
            'single_image_id'  => $image['ll_ba_single_image'],
            'before_image_id'  => $image['ll_ba_before_image'],
            'after_image_id'   => $image['ll_ba_after_image'],
            'video_url'        => $image['ll_ba_video_url'] ?? '',
            'video_title'      => $image['ll_ba_video_title'] ?? '',
        ];
    }
}

$card_image = $ba_gallery_image[0] ?? null;

$is_stacked = $card_image
    && $card_image['option'] === 'two-images'
    && in_array( $card_image['ratio'], ['ll-ba-single__ratio--wide', 'll-ba-single__ratio--panorama'] );

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
        <div class="ll-ba-card__image ll-ba-card__single-image">
          <?php echo wp_get_attachment_image( $card_image['single_image_id'], 'large', "", [ "class" => "" ]); ?>
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
    <div class="backdrop-blur-[20px] absolute inset-0"></div>
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

  <?php if ( $provider_term && $provider_image ) : ?>
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
