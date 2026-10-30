<?php
/**
 * Template: Before & After categories archive
 *
 * Displays a grid of all categories that have published ll_before_after posts. On a scoped
 * page (/{archive}/{categories}/{scope}/) only categories used by posts carrying the scope
 * term are shown, and card links carry the scope through to the archive filters.
 * Override: place this file at {theme}/ll-before-after/archive-ll_before_after_categories.php
 */

defined('ABSPATH') || exit;

use LiftedLogic\LLBag\Frontend\TemplateLoader;
use LiftedLogic\LLBag\Hooks\Hooks;
use LiftedLogic\LLBag\Support\CategoryScope;

// $scope is set on a scoped page (/{archive}/{categories}/{scope}/), null on the top-level page.
$scope            = CategoryScope::current();
$categoryTaxonomy = CategoryScope::categoryTaxonomy();
$categories       = CategoryScope::terms( $scope );
$subtitle         = CategoryScope::subtitle( $scope );
$allPostsUrl      = CategoryScope::allPostsUrl( $scope );
?>

<div class="ll-ba-archive-categories">

  <?php TemplateLoader::get( 'partials/categories-hero-banner.php', ['scope' => $scope] ); ?>

  <div class="ll-ba-archive-categories__body">
    <div class="ll-ba-archive-categories__inner ba_grid-cols-container">
      <div class="ll-ba-archive-categories__container">

        <div class="ll-ba-archive-categories__header">
          <p class="ll-ba-archive-categories__subtitle"><?= esc_html( $subtitle ) ?></p>
          <?= Hooks::bag_categories_all_link_markup( $allPostsUrl, $scope ) ?>
        </div>

        <?php if ( !empty( $categories ) ) : ?>
          <div class="ll-ba-archive-categories__grid">
            <?php foreach ( $categories as $category ) :
              TemplateLoader::get( 'partials/category-card.php', [
                'category' => $category,
                'taxonomy' => $categoryTaxonomy,
                'scope'    => $scope,
                'link'     => CategoryScope::cardUrl( $category, $categoryTaxonomy, $scope ),
              ] );
            endforeach; ?>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>

</div>
