<?php
/**
 * Template: Before & After posts archive (all posts)
 * URL:       /{archive-slug}
 *
 * Displays a grid of all published ll_before_after posts with a configurable filter sidebar.
 * Override: place this file at {theme}/ll-before-after/archive-ll_before_after.php
 */

defined('ABSPATH') || exit;

use LiftedLogic\LLBag\Admin\SettingsPage;
use LiftedLogic\LLBag\Filters\FilterManager;
use LiftedLogic\LLBag\Frontend\TemplateLoader;

$filters = (new FilterManager())->getEnabled();

?>

<div class="ll-ba-archive">
  <?php TemplateLoader::get( 'partials/archive-hero-banner.php' ); ?>
  <div class="ll-ba-archive__inner">
    <!-- Sensitive images bar (shown/hidden by JS based on whether sensitive cards are in the grid) -->
    <div class="ll-ba-sensitive-bar ll-ba-hidden" id="ll-ba-sensitive-bar">
      <span class="ll-ba-sensitive-bar__label">Sensitive Images</span>
      <div class="ll-ba-sensitive-bar__options" role="group" aria-label="Sensitive image display mode">
        <button type="button" class="ll-ba-sensitive-btn" data-mode="blur">
          <svg class='icon icon-check-mark' aria-hidden='true'><use xlink:href='#icon-check-mark'></use></svg>
          Blur
        </button>
        <button type="button" class="ll-ba-sensitive-btn" data-mode="unblur">
          <svg class='icon icon-check-mark' aria-hidden='true'><use xlink:href='#icon-check-mark'></use></svg>
          Unblur
        </button>
        <button type="button" class="ll-ba-sensitive-btn" data-mode="hide">
          <svg class='icon icon-check-mark' aria-hidden='true'><use xlink:href='#icon-check-mark'></use></svg>
          Hide
        </button>
      </div>
    </div>
    <?php if ($filters->isNotEmpty()) : ?>
      <button type="button" id="ll-ba-filter-trigger" class="ll-ba-filter-trigger" aria-expanded="false" aria-controls="ll-ba-sidebar">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor" width="16" height="16"><path d="M10 18h4v-2h-4v2zm-7-8v2h18v-2H3zm3-6v2h12V4H6z"/></svg>
        Filters
      </button>
      <div class="ll-ba-hidden ll-ba-filters__active ll-ba-filters__active--mobile" id="ll-ba-active-bar-mobile">
        <div class="ll-ba-filters__active-inner">
          <span class="ll-ba-filters__active-label">Filtered by:</span>
          <button type="button" id="ll-ba-clear-all-mobile" class="ll-ba-filters__clear-all">Clear All</button>
        </div>
        <ul class="ll-ba-filters__active-tags" id="ll-ba-active-tags-mobile"></ul>
      </div>
      <aside class="ll-ba-sidebar" id="ll-ba-sidebar">
        <?php TemplateLoader::get('partials/filters.php', ['filters' => $filters]); ?>
      </aside>
    <?php endif; ?>

    <div class="ll-ba-content">
      <?php if (have_posts()) : ?>
        <div class="ll-ba-grid" id="ll-ba-grid">
          <?php while (have_posts()) : the_post(); ?>
            <?php TemplateLoader::get('partials/post-card.php', ['post' => $GLOBALS['post']]); ?>
          <?php endwhile; ?>
        </div>
      <?php else : ?>
        <p class="ll-ba-no-posts">No before &amp; after posts found.</p>
      <?php endif; ?>

      <div
        id="ll-ba-pagination"
        data-total-pages="<?= (int) $GLOBALS['wp_query']->max_num_pages; ?>"
        data-current-page="<?= max(1, (int) (get_query_var('paged') ?: ($_GET['paged'] ?? 1))); ?>"
      ></div>
    </div>
  </div>
</div>
<?php
if ( locate_template( 'templates/partials/components.php' ) ) {
  $posts_page_id = (int) get_field( SettingsPage::FIELD_POSTS_PAGE, 'option' );
  if ( $posts_page_id ) {
    global $post;
    $post = get_post( $posts_page_id );
    setup_postdata( $post );
    get_template_part( 'templates/partials/components' );
    wp_reset_postdata();
  }
}
?>
