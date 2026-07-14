<?php

namespace LiftedLogic\LLBag\Admin;

use LiftedLogic\LLBag\Filters\FilterManager;

class FilterSettingsPage {
  public function __construct(private readonly FilterManager $filterManager) {}

  public function render(): void {
    $filters      = $this->filterManager->all();
    $cardTaxonomy = $this->filterManager->getCardTaxonomy();
    require LL_BAG_PATH . 'views/admin/filter-settings.php';
  }

  public function handleSave(): void {
    check_admin_referer('ll_bag_save_filters', 'll_bag_filters_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to do this.', 'll-bag'));
    }

    $raw = isset($_POST['ll_bag_filters']) && is_array($_POST['ll_bag_filters'])
            ? $_POST['ll_bag_filters']
            : [];

    $filters = [];
    foreach ($raw as $id => $data) {
      if (!is_array($data)) continue;

      $id      = sanitize_key((string) $id);
      $label   = sanitize_text_field($data['label'] ?? '');
      $metaKey = sanitize_key($data['meta_key'] ?? '');
      $display    = 'checkbox';
      $enabled    = !empty($data['enabled']);
      $searchable = !empty($data['searchable']);
      $tagLabel   = sanitize_text_field($data['tag_label'] ?? '');

      $builtin = !empty($data['builtin']);

      if ($id === '' || $label === '' || $metaKey === '') continue;

      $filters[] = [
        'id'         => $id,
        'label'      => $label,
        'meta_key'   => $metaKey,
        'display'    => $display,
        'enabled'    => $enabled,
        'searchable' => $searchable,
        'builtin'    => $builtin,
        'tag_label'  => $tagLabel,
      ];
    }

    $seenKeys = [];
    $unique   = [];
    foreach ($filters as $filter) {
      if (isset($seenKeys[$filter['meta_key']])) continue;
      $seenKeys[$filter['meta_key']] = true;
      $unique[] = $filter;
    }
    $hasDuplicates = count($unique) < count($filters);

    $this->filterManager->save($unique);
    $this->filterManager->saveCardTaxonomy(sanitize_key($_POST['ll_bag_card_taxonomy'] ?? ''));

    wp_safe_redirect(add_query_arg(
      array_filter(['saved' => '1', 'duplicate' => $hasDuplicates ? '1' : null]),
      admin_url('edit.php?post_type=ll_before_after&page=ll-bag-filters')
    ));
    exit;
  }
}
