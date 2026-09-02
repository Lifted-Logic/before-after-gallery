<?php
/**
 * Template: Before & After categories archive
 *
 * Displays a grid of all categories that have published ll_before_after posts.
 * Override: place this file at {theme}/ll-before-after/archive-ll_before_after_categories.php
 */

defined('ABSPATH') || exit;

use LiftedLogic\LLBag\Admin\SettingsPage;
use LiftedLogic\LLBag\Frontend\TemplateLoader;

$categoryTaxonomy = SettingsPage::getCategoryTaxonomy() ?: 'll_ba_category';

$categories = get_terms( [
    'taxonomy'   => $categoryTaxonomy,
    'hide_empty' => true,
] );

if ( is_wp_error( $categories ) ) {
    $categories = [];
}

$subtitle    = get_field( 'll_ba_categories_subtitle', 'option' ) ?: 'Select a category below to start exploring.';
$allPostsUrl = SettingsPage::getPostsPageUrl();
?>

<div class="ll-ba-archive-categories">

  <?php TemplateLoader::get( 'partials/categories-hero-banner.php' ); ?>

  <div class="ll-ba-archive-categories__body">
    <div class="ll-ba-archive-categories__inner ba_grid-cols-container">
      <div class="ll-ba-archive-categories__container">

        <div class="ll-ba-archive-categories__header">
          <p class="ll-ba-archive-categories__subtitle"><?= esc_html( $subtitle ) ?></p>
          <?php if ( $allPostsUrl ) : ?>
            <a class="ll-ba-archive-categories__all-link ba_btn-secondary" href="<?= esc_url( $allPostsUrl ) ?>">
              <svg class='icon icon-arrow-right' aria-hidden='true'><use xlink:href='#icon-arrow-right'></use></svg>
              View All Before &amp; Afters
              <svg class='icon icon-arrow-right' aria-hidden='true'><use xlink:href='#icon-arrow-right'></use></svg>
            </a>
          <?php endif; ?>
        </div>

        <?php if ( !empty( $categories ) ) : ?>
          <div class="ll-ba-archive-categories__grid">
            <?php foreach ( $categories as $category ) :
              TemplateLoader::get( 'partials/category-card.php', ['category' => $category, 'taxonomy' => $categoryTaxonomy] );
            endforeach; ?>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>

</div>
