<?php

namespace LiftedLogic\LLBag\Support;

use LiftedLogic\LLBag\Admin\SettingsPage;
use LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType;
use WP_Term;

/**
 * Scoped category archive ("/{archive}/{categories}/{scope}/").
 *
 * A scope is one row of the Hero Navigation repeater (Settings → Taxonomy Archive
 * Settings): a term from any enabled filter taxonomy (e.g. Body Area → Breast).
 * A scoped page lists the featured-taxonomy terms used by posts that also carry
 * the scope term, so no term-to-term mapping is stored anywhere.
 *
 * Scope shape: array{label: string, taxonomy: string, term: WP_Term, slug: string, url: string, active: bool}
 */
class CategoryScope {
  public const QUERY_VAR      = 'll_ba_scope';
  public const FIELD_NAV      = 'll_ba_category_nav';
  public const FIELD_SHOW_ALL = 'll_ba_category_nav_show_all';
  public const FIELD_ALL_LABEL = 'll_ba_category_nav_all_label';
  public const REQUIRE_PARAM  = 'll_ba_require';

  /** @var array<int, array<string, mixed>>|null */
  private static ?array $navItems = null;

  /**
   * The configured hero navigation rows, resolved to terms. Rows whose term was
   * deleted or has no published posts are dropped so no pill leads to an empty page.
   *
   * @return array<int, array<string, mixed>>
   */
  public static function navItems(): array {
    if ( self::$navItems !== null ) {
      return self::$navItems;
    }

    $rows    = get_field( self::FIELD_NAV, 'option' ) ?: [];
    $current = self::requestedSlug();
    $items   = [];
    $seen    = [];

    foreach ( (array) $rows as $row ) {
      $taxonomy = (string) ( $row['taxonomy'] ?? '' );
      $termId   = (int) ( $row['term'] ?? 0 );

      if ( $taxonomy === '' || !$termId || !taxonomy_exists( $taxonomy ) ) continue;

      $term = get_term( $termId, $taxonomy );
      if ( !$term instanceof WP_Term || !self::hasPosts( $taxonomy, $term ) ) continue;

      // The slug is the URL segment, so the first row using a slug wins.
      if ( isset( $seen[ $term->slug ] ) ) continue;
      $seen[ $term->slug ] = true;

      $label = trim( (string) ( $row['label'] ?? '' ) );

      $items[] = [
        'label'    => $label !== '' ? $label : $term->name,
        'taxonomy' => $taxonomy,
        'term'     => $term,
        'slug'     => $term->slug,
        'url'      => BeforeAfterPostType::getScopedCategoriesArchiveUrl( $term->slug ),
        'active'   => $current === $term->slug,
      ];
    }

    return self::$navItems = $items;
  }

  /**
   * Pills for the categories hero: the optional "All" pill followed by the nav rows.
   *
   * @return array<int, array{label: string, url: string, active: bool}>
   */
  public static function pillItems(): array {
    $items = self::navItems();
    if ( empty( $items ) ) return [];

    $pills = [];

    if ( get_field( self::FIELD_SHOW_ALL, 'option' ) ) {
      $label   = trim( (string) get_field( self::FIELD_ALL_LABEL, 'option' ) );
      $pills[] = [
        'label'  => $label !== '' ? $label : 'All',
        'url'    => BeforeAfterPostType::getCategoriesArchiveUrl(),
        'active' => self::requestedSlug() === '',
      ];
    }

    foreach ( $items as $item ) {
      $pills[] = [
        'label'  => $item['label'],
        'url'    => $item['url'],
        'active' => $item['active'],
      ];
    }

    return apply_filters( 'lifted_logic/bag/categories_nav_items', $pills );
  }

  /**
   * Whether any published post carries the term or one of its child terms. A term's own
   * `count` only covers direct assignments, so a parent like Body Area → Breast reads 0 when
   * every post is tagged with a child (Chest) instead.
   */
  private static function hasPosts( string $taxonomy, WP_Term $term ): bool {
    if ( $term->count > 0 ) return true;
    if ( !is_taxonomy_hierarchical( $taxonomy ) ) return false;

    return (bool) get_posts( [
      'post_type'      => BeforeAfterPostType::SLUG,
      'post_status'    => 'publish',
      'posts_per_page' => 1,
      'fields'         => 'ids',
      'no_found_rows'  => true,
      'tax_query'      => [ [ 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => [ $term->term_id ] ] ],
    ] );
  }

  /** The scope slug from the URL, or '' on the unscoped categories page. */
  public static function requestedSlug(): string {
    return sanitize_title( (string) get_query_var( self::QUERY_VAR ) );
  }

  /** The active scope, or null when unscoped or when the slug matches no configured row. */
  public static function current(): ?array {
    $slug = self::requestedSlug();
    if ( $slug === '' ) return null;

    foreach ( self::navItems() as $item ) {
      if ( $item['slug'] === $slug ) return $item;
    }

    return null;
  }

  /**
   * Featured-taxonomy terms for the grid. Unscoped: every term with posts.
   * Scoped: only terms used by posts that also carry the scope term (or one of its
   * child terms; tax_query includes children by default for hierarchical taxonomies).
   *
   * @return WP_Term[]
   */
  public static function terms( ?array $scope = null ): array {
    $taxonomy = self::categoryTaxonomy();
    $args     = [ 'taxonomy' => $taxonomy, 'hide_empty' => true ];

    if ( $scope ) {
      $postIds = get_posts( [
        'post_type'      => BeforeAfterPostType::SLUG,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'tax_query'      => [ [
          'taxonomy' => $scope['taxonomy'],
          'field'    => 'term_id',
          'terms'    => [ $scope['term']->term_id ],
        ] ],
      ] );

      if ( empty( $postIds ) ) return [];

      $args['object_ids'] = $postIds;

      // Scoping by a term of the featured taxonomy itself: don't list the scope as its own card.
      if ( $scope['taxonomy'] === $taxonomy ) {
        $args['exclude'] = [ $scope['term']->term_id ];
      }
    }

    $terms = get_terms( apply_filters( 'lifted_logic/bag/categories_terms_args', $args, $scope ) );

    return is_wp_error( $terms ) ? [] : $terms;
  }

  /**
   * Archive URL a category card links to: pre-filtered by the category and, on a
   * scoped page, by the scope term too (e.g. ?ll_ba_body_area=breast&ll_ba_category=breast-lift,
   * or ?ll_ba_require[ll_ba_category]=botox&ll_ba_category=lazerz when both share a taxonomy).
   */
  public static function cardUrl( WP_Term $category, string $taxonomy, ?array $scope = null ): string {
    $archive = get_post_type_archive_link( BeforeAfterPostType::SLUG );
    if ( !$archive ) return '';

    $args = [];
    if ( $scope && $scope['taxonomy'] !== $taxonomy ) {
      // Different filter groups are AND'd by the archive, so a plain param narrows correctly.
      $args[ $scope['taxonomy'] ] = $scope['term']->slug;
    } elseif ( $scope ) {
      // Same group: checked terms are OR'd there, so mark the scope term as required instead
      // (filters.js sends it as its own AND clause; see AjaxHandler::handle()).
      $args[ self::REQUIRE_PARAM . '[' . $taxonomy . ']' ] = $scope['term']->slug;
    }
    $args[ $taxonomy ] = $category->slug;

    return add_query_arg( $args, $archive );
  }

  /** "View All" URL: the posts page, pre-filtered by the scope term when scoped. */
  public static function allPostsUrl( ?array $scope = null ): string {
    $url = SettingsPage::getPostsPageUrl();
    if ( $url === '' || !$scope ) return $url;

    return add_query_arg( $scope['taxonomy'], $scope['term']->slug, $url );
  }

  /** Subtitle above the grid. Scoped pages use a fixed, filterable pattern. */
  public static function subtitle( ?array $scope = null ): string {
    $default = (string) ( get_field( 'll_ba_categories_subtitle', 'option' ) ?: 'Select a category below to start exploring.' );

    if ( !$scope ) return $default;

    $text = sprintf( 'Showing %s categories. Select one to start exploring.', $scope['label'] );

    return (string) apply_filters( 'lifted_logic/bag/categories_scoped_subtitle', $text, $scope, $default );
  }

  public static function categoryTaxonomy(): string {
    return SettingsPage::getCategoryTaxonomy() ?: 'll_ba_category';
  }
}
