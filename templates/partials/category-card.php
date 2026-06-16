<?php
/**
 * Partial: Category card
 *
 * Available variables:
 *   $category  WP_Term  The category term object
 *   $taxonomy  string   The taxonomy slug (used as the archive filter query param key)
 *
 * Override: place this file at {theme}/ll-before-after/partials/category-card.php
 */

defined('ABSPATH') || exit;

$bgImageId  = get_field( 'll_ba_category_bg_image', $category );
$bgImageUrl = $bgImageId ? wp_get_attachment_image_url( (int) $bgImageId, 'large' ) : '';
$archiveLink = get_post_type_archive_link( \LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType::SLUG );
$link        = $archiveLink ? add_query_arg( $taxonomy, $category->slug, $archiveLink ) : '';
?>

<a href="<?= esc_url( $link ) ?>" class="ll-ba-category-card">
  <?php if ( $bgImageUrl ) : ?>
    <img
      class="ll-ba-category-card__image"
      src="<?= esc_url( $bgImageUrl ) ?>"
      alt="<?= esc_attr( $category->name ) ?>"
    >
  <?php endif; ?>
  <div class="ll-ba-category-card__overlay"></div>
  <span class="ll-ba-category-card__label">
    <?= esc_html( $category->name ) ?>
  </span>
  <div class="ll-ba-category-card__hover-text">
    View Before & Afters
    <svg class='icon icon-arrow-right' aria-hidden='true'><use xlink:href='#icon-arrow-right'></use></svg>
  </div>
</a>
