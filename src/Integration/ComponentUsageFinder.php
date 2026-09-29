<?php

namespace LiftedLogic\LLBag\Integration;

class ComponentUsageFinder {

  public function isAvailable(): bool {
    // The predecessor plugin ("LL Before After Gallery") unconditionally defines this
    // constant at the top of its main file, before its own ACF-Pro check — so it's only
    // ever set while that plugin is active, regardless of the folder it's installed under
    // (which varies from site to site, so we can't key off a fixed plugin path here).
    return defined( 'LL_BEFORE_AFTER_GALLERY_VERSION' );
  }

  /**
   * Every theme component available on this site, as [snake_case_name => 'Spaced Label'],
   * sorted by label. Mirrors LiftedLogic\Components\ComponentProvider's own discovery
   * (glob + class-name transform) without instantiating theme classes.
   */
  public function getComponentChoices(): array {
    $files = glob( trailingslashit( get_stylesheet_directory() ) . 'components/*/*FieldGroup.php' ) ?: [];

    $choices = [];
    foreach ( $files as $file ) {
      $dirName = basename( dirname( $file ) );
      [ $name, $label ] = $this->deriveNameAndLabel( $dirName );
      $choices[ $name ] = $label;
    }

    asort( $choices );

    return $choices;
  }

  /**
   * Mirrors LiftedLogic\Components\ComponentFieldGroup::setLabels(), which is
   * protected and only runs as a side effect of instantiating a component class.
   */
  private function deriveNameAndLabel( string $componentDirName ): array {
    $spaced = preg_replace( '/(?<!^)[A-Z]/', '_$0', $componentDirName );
    $name   = strtolower( $spaced );
    $label  = str_replace( '_', ' ', $spaced );
    return [ $name, $label ];
  }

  /**
   * Find every page the given layout is placed on, directly or through a Global
   * Component.
   *
   * @return array<int, array{post_id:int,title:string,post_type:string,status:string,path:string,edit_link:string}>
   */
  public function findUsage( string $layout, string $field = 'components' ): array {
    if ( ! function_exists( 'get_field' ) || $layout === '' ) {
      return [];
    }

    $statuses = array_values( array_diff( get_post_stati(), [ 'trash', 'auto-draft' ] ) );

    $candidates = get_posts( [
      'post_type'      => 'any',
      'post_status'    => $statuses,
      'posts_per_page' => -1,
      'fields'         => 'ids',
      'meta_key'       => $field,
      'orderby'        => 'ID',
      'order'          => 'ASC',
    ] );

    $rows = [];
    $seen = [];

    foreach ( $candidates as $post_id ) {
      $visited = [];
      $this->walk( (int) $post_id, $field, $layout, (int) $post_id, [], $visited, $rows, $seen );
    }

    usort( $rows, fn( $a, $b ) => $a['post_id'] <=> $b['post_id'] );

    return $rows;
  }

  private function walk( int $current_post_id, string $field, string $layout, int $origin_post_id, array $chain, array &$visited, array &$rows, array &$seen ): void {
    if ( in_array( $current_post_id, $visited, true ) ) {
      return;
    }
    $visited[] = $current_post_id;

    if ( ! have_rows( $field, $current_post_id ) ) {
      return;
    }

    while ( have_rows( $field, $current_post_id ) ) {
      the_row();
      $row_layout = get_row_layout();

      if ( $row_layout === $layout && ! isset( $seen[ $origin_post_id ] ) ) {
        $post = get_post( $origin_post_id );
        $rows[] = [
          'post_id'   => $origin_post_id,
          'title'     => $post->post_title,
          'post_type' => $post->post_type,
          'status'    => $post->post_status,
          'path'      => $chain ? implode( ' -> ', $chain ) : 'direct',
          'edit_link' => (string) get_edit_post_link( $origin_post_id, '' ),
        ];
        $seen[ $origin_post_id ] = true;
      }

      if ( $row_layout === 'global_component' ) {
        $target_id = (int) get_sub_field( 'global_component_id' );
        if ( $target_id ) {
          $target_post = get_post( $target_id );
          $label       = $target_post ? "{$target_post->post_title} (#{$target_id})" : "#{$target_id}";
          $this->walk( $target_id, $field, $layout, $origin_post_id, array_merge( $chain, [ "via Global Component: {$label}" ] ), $visited, $rows, $seen );
        }
      }
    }
  }
}
