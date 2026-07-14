<?php
/**
 * Admin partial: Single filter row in the filter settings table
 *
 * Available variables:
 *   $filter  array{id: string, label: string, meta_key: string, display: string, enabled: bool, searchable: bool, builtin: bool}
 */

defined('ABSPATH') || exit;

$id        = $filter['id'];
$isBuiltin = !empty($filter['builtin']);
?>

<tr class="ll-bag-filter-row" data-id="<?= esc_attr($id); ?>" draggable="true">
  <!-- Drag handle -->
  <td class="ll-bag-drag-handle" title="Drag to reorder">⠿</td>

  <td>
    <?php if ($isBuiltin) : ?>
      <!-- Builtins: label and slug are fixed, passed as hidden inputs -->
      <strong><?= esc_html($filter['label']); ?></strong>
      <input type="hidden" name="ll_bag_filters[<?= esc_attr($id); ?>][label]"    value="<?= esc_attr($filter['label']); ?>">
      <input type="hidden" name="ll_bag_filters[<?= esc_attr($id); ?>][meta_key]" value="<?= esc_attr($filter['meta_key']); ?>">
      <input type="hidden" name="ll_bag_filters[<?= esc_attr($id); ?>][builtin]"  value="1">
      <p class="ll-bag-meta-key-hint"><?= esc_html($filter['meta_key']); ?></p>
    <?php else : ?>
      <input
        type="text"
        name="ll_bag_filters[<?= esc_attr($id); ?>][label]"
        value="<?= esc_attr($filter['label']); ?>"
        class="ll-bag-label-input"
        required
      >
      <input
        type="hidden"
        name="ll_bag_filters[<?= esc_attr($id); ?>][meta_key]"
        value="<?= esc_attr($filter['meta_key']); ?>"
        class="ll-bag-meta-key-input"
      >
      <p class="ll-bag-meta-key-hint"><?= esc_html($filter['meta_key']); ?></p>
    <?php endif; ?>
  </td>

  <td>
    <input
      type="checkbox"
      name="ll_bag_filters[<?= esc_attr($id); ?>][enabled]"
      value="1"
      <?php checked(!empty($filter['enabled'])); ?>
      class="ll-bag-filterable-input"
    >
  </td>

  <td>
    <input type="hidden" name="ll_bag_filters[<?= esc_attr($id); ?>][display]" value="checkbox">

    <label class="ll-bag-searchable-wrap">
      <input
        type="checkbox"
        name="ll_bag_filters[<?= esc_attr($id); ?>][searchable]"
        value="1"
        <?php checked(!empty($filter['searchable'])); ?>
        class="ll-bag-searchable-input"
      >
    </label>
  </td>

  <td class="ll-bag-card-display-td">
    <input
      type="checkbox"
      name="ll_bag_card_taxonomy"
      value="<?= esc_attr($filter['meta_key']); ?>"
      class="ll-bag-card-display"
      <?php checked($cardTaxonomy ?? '', $filter['meta_key']); ?>
    >

    <?php $showTagLabel = ($cardTaxonomy ?? '') === $filter['meta_key']; ?>
    <div class="ll-bag-tag-label-wrap<?= $showTagLabel ? '' : ' ll-bag-hidden'; ?>">
      <label>
        Tag Label
        <span class="ll-bag-field-tooltip dashicons dashicons-editor-help" tabindex="0" aria-label="This is the text that will show after &quot;Multiple&quot; on the card pills to tell the user &quot;this before and after uses multiple...&quot;">
          <span class="ll-bag-field-tooltip__bubble">This is the text that will show after "Multiple" on the card pills to tell the user "this before and after uses multiple..."</span>
        </span>
      </label>
      <input
        type="text"
        name="ll_bag_filters[<?= esc_attr($id); ?>][tag_label]"
        value="<?= esc_attr($filter['tag_label'] ?? ''); ?>"
        class="ll-bag-tag-label-input"
      >
    </div>
  </td>

  <td>
    <?php if (!$isBuiltin) : ?>
      <button type="button" class="button-link-delete ll-bag-remove-filter">Remove</button>
    <?php endif; ?>
  </td>
</tr>
