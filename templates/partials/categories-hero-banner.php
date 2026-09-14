<?php
/**
 * Partial: Before & After categories archive hero banner
 *
 * Reads from the Category Settings tab in B&A Posts → Settings.
 * Uses the same markup as archive-hero-banner.php so both
 * can be styled identically while remaining independently overridable.
 *
 * Override: place this file at {theme}/ll-before-after/partials/categories-hero-banner.php
 */

defined('ABSPATH') || exit;

use LiftedLogic\LLBag\Hooks\Hooks;

$hero         = get_field( 'll_ba_category_archive_hero', 'option' ) ?: [];
$hero_content = $hero['content'] ?? '';
$hero_link    = $hero['link']    ?? [];
$hero_image   = $hero['image']   ?? null;
$hero_focus   = $hero['image_focus_point'] ?? 'object-center';
?>

<div class="ll-ba-hero-banner">
  <?php if ( $hero_image ) : ?>
    <?php bag_include_partial( 'fit-image', [
      'image_id'       => $hero_image,
      'thumbnail_size' => 'large',
      'position'       => $hero_focus,
      'fit'            => 'object-cover',
      'loading'        => '',
    ] ); ?>
  <?php endif; ?>
  <div class="ll-ba-hero-banner__overlay"></div>
  <div class="ll-ba-hero-banner__container ba_grid-cols-container">
    <div class="ll-ba-hero-banner__row js-fade-group">
      <div class="ll-ba-hero-banner__content">
        <div class="wysiwyg">
          <?= Hooks::bag_sanitize_wysiwyg( $hero_content ) ?>
        </div>
      </div>
      <?php if ( $hero_link ) : ?>
        <div class="ll-ba-hero-banner__link-wrap theme-four">
          <?= Hooks::bag_hero_banner_link_markup( $hero_link ) ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
