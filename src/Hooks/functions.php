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
    return (string) ( get_option( 'options_ll_bag_category_taxonomy' ) ?: 'll_ba_category' );
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

if ( ! function_exists( 'bag_image_ratio_presets' ) ) {
  /**
   * Preset image ratios (width / height), keyed by the ll_ba_image_ratio choice.
   */
  function bag_image_ratio_presets(): array {
    return [ 'wide' => 16 / 9, 'square' => 1.0, 'panorama' => 3.0, 'vertical' => 0.8 ];
  }
}

if ( ! function_exists( 'bag_default_image_ratio' ) ) {
  /**
   * Site-wide default ratio (Settings → Archive Settings → Default Image Ratio), used by image
   * rows set to "Site default". Returns key (a preset or 'custom'), value (W/H) and css ("4 / 5").
   * Reads options directly (no ACF) so it's cheap to call per image row.
   */
  function bag_default_image_ratio(): array {
    static $cache = null;
    if ( $cache !== null ) return $cache;

    $presets = bag_image_ratio_presets();
    $key     = (string) ( get_option( 'options_ll_bag_default_image_ratio' ) ?: 'wide' );

    if ( $key === 'custom' ) {
      $raw = (string) get_option( 'options_ll_bag_default_image_ratio_custom' );
      if ( preg_match( '/^\s*(\d+(?:\.\d+)?)\s*[:\/x]\s*(\d+(?:\.\d+)?)\s*$/i', $raw, $m ) && (float) $m[1] > 0 && (float) $m[2] > 0 ) {
        return $cache = [ 'key' => 'custom', 'value' => (float) $m[1] / (float) $m[2], 'css' => $m[1] . ' / ' . $m[2] ];
      }
      $key = 'wide'; // invalid custom value: fall back rather than break layouts
    }

    if ( ! isset( $presets[ $key ] ) ) $key = 'wide';
    return $cache = [ 'key' => $key, 'value' => $presets[ $key ], 'css' => '' ];
  }
}

if ( ! function_exists( 'bag_resolve_image_ratio' ) ) {
  /**
   * Resolve one ll_ba_images row's ratio for templates.
   *
   * Returns:
   *   class   — CSS modifier class(es) for the image frame
   *   value   — width / height, for focal-point crop math
   *   stacked — whether two images stack vertically (wide frames) instead of side by side
   *
   * '' is legacy: one-image rows saved '' while the field was hidden (rendered as wide), and
   * two-image rows fell back to square. 'default' follows the site-wide default ratio.
   */
  function bag_resolve_image_ratio( array $row ): array {
    $presets = bag_image_ratio_presets();
    $key     = (string) ( $row['ll_ba_image_ratio'] ?? '' );

    if ( $key === '' && ( $row['ll_ba_image_options'] ?? '' ) === 'one-image' ) {
      $key = 'wide';
    }

    if ( $key === 'default' ) {
      $default = bag_default_image_ratio();
      if ( $default['key'] === 'custom' ) {
        $stacked = $default['value'] >= 1.7;
        return [
          'class'   => 'll-ba-single__ratio--default' . ( $stacked ? ' ll-ba-single__ratio--landscape' : '' ),
          'value'   => $default['value'],
          'stacked' => $stacked,
        ];
      }
      $key = $default['key'];
    }

    if ( ! isset( $presets[ $key ] ) ) $key = 'square';

    return [
      'class'   => 'll-ba-single__ratio--' . $key,
      'value'   => $presets[ $key ],
      'stacked' => in_array( $key, [ 'wide', 'panorama' ], true ),
    ];
  }
}

