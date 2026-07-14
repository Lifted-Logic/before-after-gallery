<?php

namespace LiftedLogic\LLBag\Support;

use LiftedLogic\LLBag\Filters\FilterManager;

class PostTerms {
  /**
   * Get term data for a post and taxonomy.
   *
   * Returns an array with:
   *   terms    - all matched WP_Term objects
   *   visible  - WP_Term objects up to $limit
   *   overflow - count of terms beyond $limit
   *   label    - taxonomy display name
   *   taxonomy - taxonomy slug
   *
   * @return array{terms: \WP_Term[], visible: \WP_Term[], overflow: int, label: string, taxonomy: string}
   */
  public static function get(int $postId, string $taxonomy, int $limit = 5): array {
    $terms = wp_get_post_terms($postId, $taxonomy);
    $terms = is_wp_error($terms) ? [] : $terms;

    $tax   = get_taxonomy($taxonomy);
    $label = $tax ? (string) $tax->labels->name : '';

    return [
      'terms'    => $terms,
      'visible'  => array_slice($terms, 0, $limit),
      'overflow' => max(0, count($terms) - $limit),
      'label'    => $label,
      'taxonomy' => $taxonomy,
    ];
  }

  /**
   * Get term data for the taxonomy designated as the card display field.
   *
   * @return array{terms: \WP_Term[], visible: \WP_Term[], overflow: int, label: string, taxonomy: string}
   */
  public static function forCard(int $postId, int $limit = 5): array {
    $filterManager = new FilterManager();
    $taxonomy      = $filterManager->getCardTaxonomy();

    if (!$taxonomy) {
      return ['terms' => [], 'visible' => [], 'overflow' => 0, 'label' => '', 'taxonomy' => ''];
    }

    $result = self::get($postId, $taxonomy, $limit);

    // Prefer the admin-configured "Tag Label" over the taxonomy's registered name.
    $tagLabel = $filterManager->getCardTagLabel();
    if ($tagLabel !== '') {
      $result['label'] = $tagLabel;
    }

    // Prepend a synthetic NSFW pill as the first visible term when applicable.
    // Template checks $term->is_nsfw to render the info icon instead of a text label.
    if ( get_field('ll_ba_is_nsfw', $postId) ) {
      $nsfw_term = (object) [
        'term_id' => 0,
        'name'    => 'Sensitive Image',
        'slug'    => '',
        'is_nsfw' => true,
      ];
      array_unshift( $result['terms'],   $nsfw_term );
      array_unshift( $result['visible'], $nsfw_term );
      $result['overflow'] = max( 0, count( $result['terms'] ) - 1 - $limit );
    }

    return $result;
  }
}
