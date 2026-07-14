<?php

namespace LiftedLogic\LLBag\Filters;

use Illuminate\Support\Collection;

class FilterManager {
  public const OPTION_KEY        = 'll_bag_filters';
  public const CARD_TAXONOMY_KEY = 'll_bag_card_taxonomy';

  private const BUILTINS = [
    ['id' => '__builtin_category', 'label' => 'Categories', 'meta_key' => 'category',       'builtin' => true, 'display' => 'checkbox', 'enabled' => false, 'searchable' => false],
    ['id' => '__builtin_provider', 'label' => 'Providers',  'meta_key' => 'll_ba_provider', 'builtin' => true, 'display' => 'checkbox', 'enabled' => false, 'searchable' => false],
  ];

  /**
   * Return all configured filters with defaults
   *
   * Each filter: id, label, meta_key (= taxonomy slug), display (checkbox|dropdown), enabled (bool), searchable (bool), tag_label (string).
   *
   * @return Collection<int, array<string, mixed>>
   */
  public function all(): Collection {
    /** @var array<int, array<string, mixed>> $filters */
    $filters = get_option(self::OPTION_KEY, []);

    // Drop legacy saved entries for the retired post_tag builtin filter.
    $filters = array_values(array_filter($filters, fn(array $filter): bool => ($filter['meta_key'] ?? null) !== 'post_tag'));

    $savedIds = array_column($filters, 'id');
    foreach (self::BUILTINS as $builtin) {
      if (!in_array($builtin['id'], $savedIds, true)) {
        $filters[] = $builtin;
      }
    }

    return collect($filters)->map(function (array $filter): array {
      return array_merge([
        'enabled'    => false,
        'searchable' => false,
        'builtin'    => false,
        'tag_label'  => '',
      ], $filter);
    });
  }

  /**
   * Return only filters that are enabled for the sidebar
   *
   * @return Collection<int, array<string, mixed>>
   */
  public function getEnabled(): Collection {
    return $this->all()->filter(fn(array $filter): bool => (bool) ($filter['enabled'] ?? false))->values();
  }

  public function getCardTaxonomy(): string {
    $taxonomy = (string) get_option(self::CARD_TAXONOMY_KEY, '');

    // The post_tag builtin filter has been retired; never resolve to it.
    return $taxonomy === 'post_tag' ? '' : $taxonomy;
  }

  /**
   * Return the custom "tag label" configured for the filter currently
   * designated as the card display taxonomy, if any.
   */
  public function getCardTagLabel(): string {
    $taxonomy = $this->getCardTaxonomy();
    if (!$taxonomy) return '';

    $filter = $this->all()->firstWhere('meta_key', $taxonomy);
    return (string) ($filter['tag_label'] ?? '');
  }

  public function saveCardTaxonomy(string $metaKey): void {
    update_option(self::CARD_TAXONOMY_KEY, $metaKey);
  }

  /**
   * @param array<int, array<string, mixed>> $filters
   */
  public function save(array $filters): void {
    update_option(self::OPTION_KEY, $filters);
  }
}
