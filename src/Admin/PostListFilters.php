<?php

namespace LiftedLogic\LLBag\Admin;

use LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType;
use LiftedLogic\LLBag\Filters\FilterManager;

/**
 * Taxonomy filter dropdowns on the Before & After admin list.
 *
 * One dropdown per plugin filter taxonomy (FilterManager::all(), regardless of
 * the filter's enabled flag) that has a term in use in the current status tab.
 * Filtering itself is core's: the dropdown submits ?{query_var}={slug}.
 */
class PostListFilters {

  /** @var array<string, array<int, int>>|null taxonomy => term IDs to list */
  private ?array $termIds = null;

  public function __construct( private readonly FilterManager $filterManager ) {}

  public function register(): void {
    add_action( 'load-edit.php', [$this, 'maybeRegisterHooks'] );
  }

  public function maybeRegisterHooks(): void {
    if ( ( get_current_screen()?->post_type ?? '' ) !== BeforeAfterPostType::SLUG ) {
      return;
    }

    add_action( 'restrict_manage_posts', [$this, 'renderDropdowns'] );
  }

  public function renderDropdowns( string $postType ): void {
    if ( $postType !== BeforeAfterPostType::SLUG ) {
      return;
    }

    $termIds = $this->usedTermIds();

    foreach ( $this->candidates() as $slug => $candidate ) {
      $taxonomy = $candidate['taxonomy'];
      $queryVar = $taxonomy->query_var;
      $selected = $this->selectedSlug( $queryVar );
      $include  = $termIds[ $slug ] ?? [];
      $id       = 'll-bag-filter-' . $slug;

      // Keep the URL selection (and its ancestors) visible, even when it is
      // not in use in this tab or nothing else is.
      if ( $selected !== '' ) {
        $term = get_term_by( 'slug', $selected, $slug );
        if ( $term instanceof \WP_Term ) {
          $include[] = $term->term_id;
          foreach ( get_ancestors( $term->term_id, $slug, 'taxonomy' ) as $ancestor ) {
            $include[] = (int) $ancestor;
          }
        }
      }

      if ( !$include ) continue;

      printf(
        '<label class="screen-reader-text" for="%s">%s</label>',
        esc_attr( $id ),
        esc_html( sprintf( __( 'Filter by %s', 'll-bag' ), $candidate['label'] ) )
      );

      wp_dropdown_categories( [
        'taxonomy'        => $slug,
        'name'            => $queryVar,
        'id'              => $id,
        'value_field'     => 'slug',
        'selected'        => $selected,
        'include'         => array_values( array_unique( $include ) ),
        'hide_empty'      => false,
        'hierarchical'    => true,
        'orderby'         => 'name',
        'show_option_all' => sprintf( __( 'All %s', 'll-bag' ), esc_html( $candidate['label'] ) ),
      ] );
    }
  }

  /** Selected term slug from the URL; arrays and other non-strings mean no selection. */
  private function selectedSlug( string $queryVar ): string {
    $value = $_GET[ $queryVar ] ?? '';
    if ( !is_string( $value ) ) {
      return '';
    }

    return sanitize_title( wp_unslash( $value ) );
  }

  /**
   * Candidate taxonomies in Filter Settings order.
   *
   * @return array<string, array{label: string, taxonomy: \WP_Taxonomy}>
   */
  private function candidates(): array {
    $attached   = get_object_taxonomies( BeforeAfterPostType::SLUG );
    $candidates = [];

    foreach ( $this->filterManager->all() as $filter ) {
      $slug = (string) ( $filter['meta_key'] ?? '' );
      if ( $slug === '' || isset( $candidates[ $slug ] ) ) continue;
      if ( !in_array( $slug, $attached, true ) ) continue;

      $taxonomy = get_taxonomy( $slug );
      if ( !$taxonomy || empty( $taxonomy->query_var ) ) continue;

      $candidates[ $slug ] = [
        'label'    => (string) ( $filter['label'] ?? $taxonomy->labels->name ),
        'taxonomy' => $taxonomy,
      ];
    }

    return $candidates;
  }

  /**
   * Term IDs (used terms plus ancestors) per candidate taxonomy, for posts in
   * the current status tab. Computed once per page load.
   *
   * @return array<string, array<int, int>>
   */
  private function usedTermIds(): array {
    if ( $this->termIds !== null ) {
      return $this->termIds;
    }

    $this->termIds = [];

    $candidates = $this->candidates();
    if ( !$candidates ) {
      return $this->termIds;
    }

    $rawStatus = $_GET['post_status'] ?? '';
    $status    = is_string( $rawStatus ) ? sanitize_key( wp_unslash( $rawStatus ) ) : '';
    $statuses = ( $status !== '' && $status !== 'all' )
      ? [$status]
      : array_values( get_post_stati( ['show_in_admin_all_list' => true] ) );

    $postIds = get_posts( [
      'post_type'      => BeforeAfterPostType::SLUG,
      'post_status'    => $statuses,
      'posts_per_page' => -1,
      'fields'         => 'ids',
      'no_found_rows'  => true,
    ] );

    if ( !$postIds ) {
      return $this->termIds;
    }

    $terms = get_terms( [
      'taxonomy'   => array_keys( $candidates ),
      'object_ids' => $postIds,
      'hide_empty' => false,
    ] );
    if ( is_wp_error( $terms ) ) {
      return $this->termIds;
    }

    foreach ( $terms as $term ) {
      $this->termIds[ $term->taxonomy ][ $term->term_id ] = $term->term_id;
      foreach ( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) as $ancestor ) {
        $this->termIds[ $term->taxonomy ][ (int) $ancestor ] = (int) $ancestor;
      }
    }

    return $this->termIds = array_map( 'array_values', $this->termIds );
  }
}
