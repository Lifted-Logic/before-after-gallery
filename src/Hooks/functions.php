<?php

// No namespace — these must be available globally.

if ( ! function_exists( 'bag_include_partial' ) ) {
  function bag_include_partial( string $partial, array $component_data = [], array $component_args = [] ): void {
    include plugin_dir_path( LL_BAG_FILE ) . "templates/partials/{$partial}.php";
  }
}

if ( ! function_exists( 'bag_single_crop_value' ) ) {
  /**
   * Every image now stores "X Y Z" — focal point plus zoom, contained in the ratio the editor picked.
   */
  function bag_single_crop_value( array $row, float $frame_ratio ): string {
    if ( empty( $row['ll_ba_single_fill'] ) ) {
      return '';
    }

    $value = trim( (string) ( $row['ll_ba_single_focal'] ?? '' ) );

    // unified — "X Y Z".
    if ( preg_match( '/^\d{1,3}\s+\d{1,3}\s+\d{1,4}$/', $value ) ) {
      return $value;
    }

    $src = wp_get_attachment_image_src( (int) ( $row['ll_ba_single_image'] ?? 0 ), 'large' );
    if ( empty( $src[1] ) || empty( $src[2] ) || $frame_ratio <= 0 ) {
      return '';
    }

    $image_ratio = $src[1] / $src[2];
    $fill        = max( $image_ratio / $frame_ratio, $frame_ratio / $image_ratio );

    $focal = preg_match( '/^(\d{1,3})\s+(\d{1,3})$/', $value, $m )
      ? $m[1] . ' ' . $m[2]
      : '50 50';

    return $focal . ' ' . (int) ceil( $fill * 100 );
  }
}

if ( ! function_exists( 'bag_cta_taxonomy' ) ) {
  /**
   * The taxonomy treated as the post's "category".
   */
  function bag_cta_taxonomy(): string {
    return (string) ( get_option( 'options_ll_bag_category_taxonomy' ) ?: 'category' );
  }
}

if ( ! function_exists( 'bag_single_page_label' ) ) {
  function bag_single_page_label( ?int $post_id = null ): string {
    $post_id = $post_id ?: (int) get_the_ID();

    $label = trim( (string) get_field( 'll_ba_title', $post_id ) );
    if ( $label !== '' ) {
      return $label;
    }

    return trim( (string) get_field( 'll_ba_global_default_single_page_label', 'options' ) );
  }
}

if ( ! function_exists( 'bag_primary_term_id' ) ) {
  /**
   * The "primary category" the ticket asks for already exists on the site — Yoast adds primary-term selection to every hierarchical public taxonomy, and TaxonomyRegistrar registers all of ours that way.
   */
  function bag_primary_term_id( string $taxonomy, int $post_id ): int {
    return (int) get_post_meta( $post_id, '_yoast_wpseo_primary_' . $taxonomy, true );
  }
}

if ( ! function_exists( 'bag_resolve_cta' ) ) {
  /**
   * Resolve the CTA for a single post, in order:
   *
   *   1. the primary term's CTA
   *   2. the first assigned term's CTA   (so it still works without Yoast)
   *   3. the global CTA
   *
   * @return array{title: string, link: mixed}
   */
  function bag_resolve_cta( int $post_id ): array {
    $global = [
      'title' => (string) ( get_field( 'll_ba_global_cta_title', 'options' ) ?: '' ),
      'link'  => get_field( 'll_ba_global_cta_link', 'options' ),
    ];

    $taxonomy = bag_cta_taxonomy();
    if ( $taxonomy === '' || ! taxonomy_exists( $taxonomy ) ) {
      return $global;
    }

    $term_id = bag_primary_term_id( $taxonomy, $post_id );
    if ( ! $term_id ) {
      $terms = get_the_terms( $post_id, $taxonomy );
      if ( is_array( $terms ) && ! empty( $terms ) ) {
        $term_id = (int) $terms[0]->term_id;
      }
    }
    if ( ! $term_id ) {
      return $global;
    }

    $term = get_term( $term_id, $taxonomy );
    if ( ! $term instanceof WP_Term ) {
      return $global;
    }

    $link = get_field( 'll_ba_term_cta_link', $term );
    if ( ! is_array( $link ) || empty( $link['url'] ) ) {
      return $global;
    }

    $title = (string) ( get_field( 'll_ba_term_cta_title', $term ) ?: '' );

    return [
      'title' => $title !== '' ? $title : $global['title'],
      'link'  => $link,
    ];
  }
}
