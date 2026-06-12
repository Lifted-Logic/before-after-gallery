<?php
/**
 * Partial: Before & After archive hero banner
 *
 * Override: place this file at {theme}/ll-before-after/partials/before-after-hero-banner.php
 */

defined('ABSPATH') || exit;

$hero         = get_field( 'll_ba_hero_banner', 'option' ) ?: [];
$hero_content = $hero['content'] ?? '';
$hero_link    = $hero['link']    ?? [];
$hero_image   = $hero['image']   ?? null;
?>

<div class="ll-ba-hero-banner">
  <?php if ( $hero_image ) : ?>
    <?php bag_include_partial( 'fit-image', [
      'image_id'       => $hero_image,
      'thumbnail_size' => 'large',
      'position'       => '',
      'fit'            => 'object-cover',
      'loading'        => '',
    ] ); ?>
  <?php endif; ?>
  <div class="ll-ba-hero-banner__overlay"></div>
  <div class="ll-ba-hero-banner__container ba_grid-cols-container">
    <div class="ll-ba-hero-banner__row js-fade-group">
      <div class="ll-ba-hero-banner__content">
        <div class="wysiwyg">
          <?= wp_kses_post( $hero_content ) ?>
        </div>
      </div>
      <?php if ( $hero_link ) : ?>
        <div class="ll-ba-hero-banner__link-wrap theme-four">
          <a class="btn-primary" href="<?= esc_url( $hero_link['url'] ); ?>" <?= $hero_link['target'] ? 'target="' . esc_attr( $hero_link['target'] ) . '"' : '' ?>>
            <?= esc_html( $hero_link['title'] ); ?>
            <?php if ( $hero_link['target'] === '_blank' ) : ?>
              <span class="sr-only"> (opens in new tab)</span>
            <?php endif; ?>
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
