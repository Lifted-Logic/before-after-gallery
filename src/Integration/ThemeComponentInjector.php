<?php

namespace LiftedLogic\LLBag\Integration;

class ThemeComponentInjector {

  // The LL theme's 'components' flexible content field key.
  // Identical across PHP-ComponentProvider sites and older JSON/DB sites.
  const COMPONENTS_FC_KEY = 'field_5d0d37adc1475';

  public function register(): void {
    add_action( 'after_setup_theme', [$this, 'maybeRegisterHooks'] );
  }

  public function maybeRegisterHooks(): void {
    if ( !apply_filters( 'll_bag/register_components', true ) ) {
      return;
    }

    $this->registerLocalFields();

    add_filter( 'acf/load_field', [$this, 'injectLayouts'] );

    if ( apply_filters( 'll_bag/register_component/ll_ba_related_bna', true ) && apply_filters( 'll_bag/inject_component_fields/ll_ba_related_bna', true ) ) {
      add_filter( 'll-ba-related-bna_files',                              [$this, 'injectRelatedBnaTemplate'] );
      add_filter( 'lifted_logic/component/format_data/ll_ba_related_bna', [$this, 'formatRelatedBnaData'], 10, 3 );
    }

    if ( apply_filters( 'll_bag/register_component/ll_ba_grid', true ) && apply_filters( 'll_bag/inject_component_fields/ll_ba_grid', true ) ) {
      add_filter( 'll-ba-grid_files',                              [$this, 'injectBeforeAndAftersGridTemplate'] );
      add_filter( 'lifted_logic/component/format_data/ll_ba_grid', [$this, 'formatBeforeAndAftersGridData'], 10, 3 );
    }

    if ( apply_filters( 'll_bag/register_component/ll_ba_slider', true ) && apply_filters( 'll_bag/inject_component_fields/ll_ba_slider', true ) ) {
      add_filter( 'll-ba-slider_files',                              [$this, 'injectBeforeAndAfterSliderTemplate'] );
      add_filter( 'lifted_logic/component/format_data/ll_ba_slider', [$this, 'formatBeforeAndAfterSliderData'], 10, 3 );
    }
  }

  public function registerLocalFields(): void {
    if ( !function_exists( 'acf_add_local_field' ) ) return;

    // Relationship fields need to be independently registered in ACF's local
    // field store so that the AJAX handler can find the field config (post_type
    // etc.) via acf_get_field(). The sub_fields definition in the layout handles
    // admin form rendering; this handles the AJAX query.

    if ( apply_filters( 'll_bag/register_component/ll_ba_related_bna', true ) && apply_filters( 'll_bag/inject_component_fields/ll_ba_related_bna', true ) ) {
      acf_add_local_field( [
        'key'           => 'field_ll_ba_rba_posts',
        'label'         => 'Before & After Posts',
        'name'          => 'll_ba_related_bna_posts',
        '_name'         => 'll_ba_related_bna_posts',
        'type'          => 'relationship',
        'post_type'     => [ 'll_before_after' ],
        'filters'       => [ 'search' ],
        'elements'      => [],
        'return_format' => 'object',
        'min'           => '',
        'max'           => '3',
        'parent'        => 'layout_ll_ba_related_bna',
      ] );
    }

    if ( apply_filters( 'll_bag/register_component/ll_ba_grid', true ) && apply_filters( 'll_bag/inject_component_fields/ll_ba_grid', true ) ) {
      acf_add_local_field( [
        'key'           => 'field_ll_ba_bag_grid_posts',
        'label'         => 'Before & After Posts',
        'name'          => 'll_ba_grid_posts',
        '_name'         => 'll_ba_grid_posts',
        'type'          => 'relationship',
        'post_type'     => [ 'll_before_after' ],
        'filters'       => [ 'search' ],
        'elements'      => [],
        'return_format' => 'object',
        'min'           => '',
        'max'           => '',
        'parent'        => 'layout_ll_ba_grid',
      ] );
    }

    if ( apply_filters( 'll_bag/register_component/ll_ba_slider', true ) && apply_filters( 'll_bag/inject_component_fields/ll_ba_slider', true ) ) {
      acf_add_local_field( [
        'key'           => 'field_ll_ba_slider_posts',
        'label'         => 'Before & After Posts',
        'name'          => 'll_ba_slider_posts',
        '_name'         => 'll_ba_slider_posts',
        'type'          => 'relationship',
        'post_type'     => [ 'll_before_after' ],
        'filters'       => [ 'search' ],
        'elements'      => [],
        'return_format' => 'object',
        'min'           => '',
        'max'           => '',
        'parent'        => 'layout_ll_ba_slider',
      ] );
    }
  }

  public function formatRelatedBnaData( array $new_data, string $component_name, array $data ): array {
    // Map our content field. When ACF properly identifies it the key is
    // 'll_ba_related_bna_content'; when not (empty-string fallback), use $data[''].
    $new_data['content'] = $data['ll_ba_related_bna_content'] ?? $data[''] ?? '';
    $new_data['link']    = $data['ll_ba_related_bna_link']    ?? null;
    $new_data['posts']   = $data['ll_ba_related_bna_posts']   ?? [];
    $new_data['color_theme'] = $data['ll_ba_related_bna_color_theme'] ?? 'theme-one';
    return $new_data;
  }

  public function injectRelatedBnaTemplate( array $files ): array {
    $plugin_file = LL_BAG_PATH . 'components/RelatedBeforeAndAfters/related-before-and-afters.php';
    $files[] = $this->relativePathFromTheme( $plugin_file );
    return $files;
  }

  private function relativePathFromTheme( string $absolute_target ): string {
    $from_parts = explode( '/', trim( get_stylesheet_directory(), '/' ) );
    $to_parts   = explode( '/', trim( $absolute_target, '/' ) );

    while ( count( $from_parts ) && count( $to_parts ) && $from_parts[0] === $to_parts[0] ) {
      array_shift( $from_parts );
      array_shift( $to_parts );
    }

    return str_repeat( '../', count( $from_parts ) ) . implode( '/', $to_parts );
  }

  // Theme picker sub-field — only on sites that have ComponentThemePickerFieldGroup
  private function themePickerSubField( string $key, string $name ): ?array {
    if ( !class_exists( 'LiftedLogic\\Components\\UtilityComponents\\ComponentThemePickerFieldGroup' ) ) {
      return null;
    }

    $picker_class = 'LiftedLogic\\Components\\UtilityComponents\\ComponentThemePickerFieldGroup';
    $picker_field = acf_get_local_field( 'field_5f592y688ra43' );
    if ( !$picker_field ) {
      ( new $picker_class() )->boot();
      $picker_field = acf_get_local_field( 'field_5f592y688ra43' );
    }

    $choices = $picker_field['choices'] ?? [ 'theme-one' => 'Theme One' ];

    return [
      'key'           => $key,
      'label'         => 'Theme',
      'name'          => $name,
      '_name'         => $name,
      'type'          => 'button_group',
      'choices'       => $choices,
      'default_value' => array_key_first( $choices ),
      'layout'        => 'horizontal',
      'return_format' => 'value',
    ];
  }

  public function injectLayouts( array $field ): array {
    if ( $field['key'] !== self::COMPONENTS_FC_KEY ) {
      return $field;
    }

    if ( apply_filters( 'll_bag/register_component/ll_ba_related_bna', true ) && apply_filters( 'll_bag/inject_component_fields/ll_ba_related_bna', true ) ) {
      $field['layouts']['layout_ll_ba_related_bna'] = $this->relatedBeforeAndAftersLayout();
    }
    if ( apply_filters( 'll_bag/register_component/ll_ba_grid', true ) && apply_filters( 'll_bag/inject_component_fields/ll_ba_grid', true ) ) {
      $field['layouts']['layout_ll_ba_grid'] = $this->beforeAndAftersGridLayout();
    }
    if ( apply_filters( 'll_bag/register_component/ll_ba_slider', true ) && apply_filters( 'll_bag/inject_component_fields/ll_ba_slider', true ) ) {
      $field['layouts']['layout_ll_ba_slider'] = $this->beforeAndAfterSliderLayout();
    }

    usort( $field['layouts'], fn( $a, $b ) => strcmp( $a['label'], $b['label'] ) );

    return $field;
  }

  private function relatedBeforeAndAftersLayout(): array {
    $sub_fields = [];

    // Theme picker first — only on sites that have ComponentThemePickerFieldGroup
    if ( $theme_field = $this->themePickerSubField( 'field_ll_ba_rba_theme', 'll_ba_related_bna_color_theme' ) ) {
      $sub_fields[] = $theme_field;
    }

    $sub_fields[] = [
      'key'   => 'field_ll_ba_rba_content',
      'label' => 'Content',
      'name'  => 'll_ba_related_bna_content',
      '_name' => 'll_ba_related_bna_content',
      'type'  => 'wysiwyg',
    ];

    $sub_fields[] = [
      'key'           => 'field_ll_ba_rba_link',
      'label'         => 'Link',
      'name'          => 'll_ba_related_bna_link',
      '_name'         => 'll_ba_related_bna_link',
      'type'          => 'link',
      'return_format' => 'array',
    ];

    $sub_fields[] = [
      'key'           => 'field_ll_ba_rba_posts',
      'label'         => 'Before & After Posts',
      'name'          => 'll_ba_related_bna_posts',
      '_name'         => 'll_ba_related_bna_posts',
      'type'          => 'relationship',
      'post_type'     => [ 'll_before_after' ],
      'filters'       => [ 'search' ],
      'elements'      => [],
      'return_format' => 'object',
      'min'           => '',
      'max'           => '3',
    ];

    return [
      'key'        => 'layout_ll_ba_related_bna',
      'name'       => 'll_ba_related_bna',
      '_name'      => 'll_ba_related_bna',
      'label'      => 'Related Before & Afters',
      'display'    => 'block',
      'layout'     => 'block',
      'min'        => '',
      'max'        => '',
      'sub_fields' => $sub_fields,
    ];
  }

  public function injectBeforeAndAftersGridTemplate( array $files ): array {
    $plugin_file = LL_BAG_PATH . 'components/BeforeAndAftersGrid/before-and-afters-grid.php';
    $files[] = $this->relativePathFromTheme( $plugin_file );
    return $files;
  }

  public function formatBeforeAndAftersGridData( array $new_data, string $component_name, array $data ): array {
    $new_data['posts']       = $data['ll_ba_grid_posts']       ?? [];
    $new_data['color_theme'] = $data['ll_ba_grid_color_theme'] ?? 'theme-one';
    return $new_data;
  }

  public function injectBeforeAndAfterSliderTemplate( array $files ): array {
    $plugin_file = LL_BAG_PATH . 'components/BeforeAndAfterSlider/before-and-after-slider.php';
    $files[] = $this->relativePathFromTheme( $plugin_file );
    return $files;
  }

  public function formatBeforeAndAfterSliderData( array $new_data, string $component_name, array $data ): array {
    $new_data['color_theme'] = $data['ll_ba_slider_color_theme'] ?? 'theme-one';
    $new_data['layout']      = $data['ll_ba_slider_layout']      ?? 'content-image';
    $new_data['content']     = $data['ll_ba_slider_content']     ?? '';
    $new_data['posts']       = $data['ll_ba_slider_posts']       ?? [];
    return $new_data;
  }

  private function beforeAndAfterSliderLayout(): array {
    $sub_fields = [];

    if ( $theme_field = $this->themePickerSubField( 'field_ll_ba_slider_theme', 'll_ba_slider_color_theme' ) ) {
      $sub_fields[] = $theme_field;
    }

    $sub_fields[] = [
      'key' => 'field_ll_ba_slider_layout',
      'label' => 'Layout',
      'name' => 'll_ba_slider_layout',
      '_name' => 'll_ba_slider_layout',
      'type' => 'button_group',
      'choices' => [
        'image-content' => '<i class="far fa-image"></i> <i class="fas fa-align-left"></i>',
        'content-image' => '<i class="fas fa-align-left"></i> <i class="far fa-image"></i>',
      ],
      'return_format' => 'value',
      'allow_null' => 0,
      'layout' => 'horizontal',
    ];

    $sub_fields[] = [
      'key'   => 'field_ll_ba_slider_content',
      'label' => 'Content',
      'name'  => 'll_ba_slider_content',
      '_name' => 'll_ba_slider_content',
      'type'  => 'wysiwyg',
    ];

    $sub_fields[] = [
      'key'           => 'field_ll_ba_slider_posts',
      'label'         => 'Before & After Posts',
      'name'          => 'll_ba_slider_posts',
      '_name'         => 'll_ba_slider_posts',
      'type'          => 'relationship',
      'post_type'     => [ 'll_before_after' ],
      'filters'       => [ 'search' ],
      'elements'      => [],
      'return_format' => 'object',
      'min'           => '',
      'max'           => '',
    ];

    return [
      'key'        => 'layout_ll_ba_slider',
      'name'       => 'll_ba_slider',
      '_name'      => 'll_ba_slider',
      'label'      => 'Before & After Slider',
      'display'    => 'block',
      'layout'     => 'block',
      'min'        => '',
      'max'        => '',
      'sub_fields' => $sub_fields,
    ];
  }

  private function beforeAndAftersGridLayout(): array {
    $sub_fields = [];

    if ( $theme_field = $this->themePickerSubField( 'field_ll_ba_grid_theme', 'll_ba_grid_color_theme' ) ) {
      $sub_fields[] = $theme_field;
    }

    $sub_fields[] = [
      'key'           => 'field_ll_ba_bag_grid_posts',
      'label'         => 'Before & After Posts',
      'name'          => 'll_ba_grid_posts',
      '_name'         => 'll_ba_grid_posts',
      'type'          => 'relationship',
      'post_type'     => [ 'll_before_after' ],
      'filters'       => [ 'search' ],
      'elements'      => [],
      'return_format' => 'object',
      'min'           => '',
      'max'           => '',
    ];

    return [
      'key'        => 'layout_ll_ba_grid',
      'name'       => 'll_ba_grid',
      '_name'      => 'll_ba_grid',
      'label'      => 'Before & Afters Grid',
      'display'    => 'block',
      'layout'     => 'block',
      'min'        => '',
      'max'        => '',
      'sub_fields' => $sub_fields,
    ];
  }
}
