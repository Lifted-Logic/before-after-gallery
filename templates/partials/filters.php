<?php
/**
 * Partial: Filter sidebar
 *
 * Available variables:
 *   $filters  Illuminate\Support\Collection  Enabled filter config objects
 *             Each item: ['id', 'label', 'meta_key' (= taxonomy slug), 'display' => 'checkbox'|'dropdown', 'searchable' => bool]
 *
 * Override: place this file at {theme}/ll-before-after/partials/filters.php
 */

defined('ABSPATH') || exit;

use LiftedLogic\LLBag\Hooks\Hooks;

if ($filters->isEmpty()) {
  return;
}
?>

<div class="ll-ba-filters" id="ll-ba-filters">
  <div>
    <button type="button" id="ll-ba-filter-close" class="ll-ba-filter-close" aria-label="Close filters">
      <svg class='icon icon-exit' aria-hidden='true'><use xlink:href='#icon-exit'></use></svg>
    </button>
  
    <!-- Filtered by tags (FRA-108: sits above the filter groups) -->
    <div class="ll-ba-hidden ll-ba-filters__active" id="ll-ba-active-bar">
      <div class="ll-ba-filters__active-inner">
        <span class="ll-ba-filters__active-label">Filtered by:</span>
      </div>
      <ul class="ll-ba-filters__active-tags" id="ll-ba-active-tags">
        <!-- Tags are managed by updateActiveTags() in resources/js/filters.js -->
      </ul>
    </div>

    <!-- Filter groups -->
    <ul id="ll-ba-filter-groups">
      <?php foreach ($filters as $filter) :
        $taxonomy = $filter['meta_key'] ?? '';
        $display  = $filter['display'] ?? 'checkbox';
        $label    = $filter['label'] ?? '';
  
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]);
        if (is_wp_error($terms) || empty($terms)) continue;
      ?>
        <li
          class="ll-ba-filter-group"
          data-meta-key="<?= esc_attr($taxonomy); ?>"
          data-display="<?= esc_attr($display); ?>"
          data-label="<?= esc_attr($label); ?>"
        >
          <button
            type="button"
            class="ll-ba-filter-toggle"
            aria-expanded="false"
          >
            <span><?= esc_html($label); ?></span>
  
            <svg class='icon icon-chevron-down' aria-hidden='true'><use xlink:href='#icon-chevron-down'></use></svg>
          </button>
  
          <div class="ll-ba-hidden ll-ba-filter-content">
  
            <?php if (!empty($filter['searchable'])) : ?>
              <div class="ll-ba-option-search-wrap">
                <input
                  type="search"
                  class="ll-ba-option-search"
                  placeholder="<?= esc_attr( 'Search ' . $label ); ?>"
                >
                <svg class='icon icon-search' aria-hidden='true'><use xlink:href='#icon-search'></use></svg>
              </div>
            <?php endif; ?>
  
            <div class="ll-ba-checkbox-list">
              <?php foreach ($terms as $term) : ?>
                <label class="ll-ba-checkbox-option">
                  <input
                    type="checkbox"
                    class="ll-ba-checkbox-filter"
                    value="<?= esc_attr($term->slug); ?>"
                    data-term-name="<?= esc_attr($term->name); ?>"
                  >
                  <span class="ll-ba-checkbox-ui" aria-hidden="true">
                    <svg class="icon icon-check-mark"><use xlink:href="#icon-check-mark"></use></svg>
                  </span>
                  <?= esc_html($term->name); ?>
                </label>
              <?php endforeach; ?>
            </div>
  
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  
  <div>
    <?= Hooks::bag_filter_actions_markup() ?>
  </div>

</div>
