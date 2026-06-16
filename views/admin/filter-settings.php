<?php
/**
 * Admin view: Filter Settings page
 *
 * Available variables:
 *   $filters       Illuminate\Support\Collection  Current filter config
 *   $cardTaxonomy  string                         Meta key of the card display taxonomy
 */

defined('ABSPATH') || exit;
?>

<div class="wrap">
  <h1><?php esc_html_e('B&A Filter Settings', 'll-bag'); ?></h1>

  <?php if (isset($_GET['duplicate'])) : ?>
    <div class="notice notice-warning is-dismissible">
      <p><?php esc_html_e('One or more filters with duplicate meta keys were removed before saving.', 'll-bag'); ?></p>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['saved'])) : ?>
    <div class="notice notice-success is-dismissible">
      <p><?php esc_html_e('Filters saved.', 'll-bag'); ?></p>
    </div>
  <?php endif; ?>

  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <div class="ll-bag-filter-actions">
      <button type="button" id="ll-bag-add-filter" class="button">+ Add Filter</button>
      <?php submit_button('Save Filters', 'primary', 'submit', false); ?>
    </div>

    <?php wp_nonce_field('ll_bag_save_filters', 'll_bag_filters_nonce'); ?>
    <input type="hidden" name="action" value="ll_bag_save_filters">
    <input type="hidden" name="ll_bag_card_taxonomy" value="">

    <table class="wp-list-table widefat striped ll-bag-filter-table" id="ll-bag-filter-list">
      <thead>
        <tr>
          <th></th>
          <th>Label</th>
          <th title="This will enable the filter's display in the filter sidebar">Filterable?</th>
          <th>Show Search Bar?</th>
          <th title="Show this taxonomy's terms as pills on each post card">Show on Cards</th>
          <th>Actions</th>
        </tr>
      </thead>

      <tbody id="ll-bag-filter-tbody">
        <?php foreach ($filters as $filter) : ?>
          <?php require __DIR__ . '/partials/filter-row.php'; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </form>
</div>

<?php
$filter = [
  'id'         => '__ID__',
  'label'      => '',
  'meta_key'   => '',
  'display'    => 'checkbox',
  'enabled'    => false,
  'searchable' => false,
];
?>
<template id="ll-bag-filter-template">
  <?php require __DIR__ . '/partials/filter-row.php'; ?>
</template>
