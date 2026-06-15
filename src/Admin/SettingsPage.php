<?php

namespace LiftedLogic\LLBag\Admin;

use LiftedLogic\LLBag\Frontend\TemplateLoader;
use LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType;

class SettingsPage {
  public const FIELD_POSTS_PAGE = 'll_bag_posts_page';

  public function register(): void {
    add_action('acf/init',     [$this, 'registerOptionsPage']);
    add_action('acf/init',     [$this, 'registerFields']);
    add_action('acf/save_post', [$this, 'maybeFlushRewriteRules']);
  }

  public function maybeFlushRewriteRules(mixed $postId): void {
    if ($postId === 'options') {
      flush_rewrite_rules();
    }
  }

  public function registerOptionsPage(): void {
    if (!function_exists('acf_add_options_sub_page')) {
      return;
    }

    acf_add_options_sub_page([
      'page_title'  => __('Settings', 'll-bag'),
      'menu_title'  => __('Settings', 'll-bag'),
      'parent_slug' => 'edit.php?post_type=' . BeforeAfterPostType::SLUG,
      'capability'  => 'manage_options',
      'menu_slug'   => 'll-bag-settings',
    ]);
  }

  public function registerFields(): void {
    $heroBannerOverridden    = TemplateLoader::resolve( 'partials/archive-hero-banner.php' )
      !== LL_BAG_PATH . 'templates/partials/archive-hero-banner.php';
    $heroBannerFieldsEnabled = apply_filters( 'll_bag/hero_banner_fields_enabled', !$heroBannerOverridden );

    $categoriesArchiveUrl = home_url( '/' . BeforeAfterPostType::getRewriteSlug() . '/categories/' );


    $fields = [
      [
        'key'       => 'field_ll_bag_archive_settings_tab',
        'label'     => 'Archive Settings',
        'type'      => 'tab',
        'placement' => 'left',
        'endpoint'  => 0,
      ],
      [
        'key'           => 'field_ll_bag_posts_page',
        'label'         => 'All Posts Archive Page',
        'name'          => self::FIELD_POSTS_PAGE,
        'type'          => 'post_object',
        'post_type'     => ['page'],
        'return_format' => 'id',
        'allow_null'    => 1,
        'instructions'  => 'The page used for the "View All Before & Afters" link on the category archive.',
      ],
    ];


    // Themes can inject additional fields into the Archive Settings tab via this filter.
    // Fields are inserted after the plugin's own archive fields and before the next tab.
    $fields = apply_filters( 'll_bag/settings_archive_fields', $fields );

    if ( $heroBannerFieldsEnabled ) {
      $fields[] = [
        'key'        => 'field_ll_ba_hero_banner',
        'label'      => 'Hero Banner',
        'name'       => 'll_ba_hero_banner',
        'type'       => 'group',
        'layout'     => 'block',
        'sub_fields' => [
          [
            'key'     => 'field_ll_ba_hero_banner_content',
            'label'   => 'Content',
            'name'    => 'content',
            'type'    => 'wysiwyg',
            'wrapper' => [ 'class' => 'll-ba-hero-banner-preview' ],
          ],
          [
            'key'           => 'field_ll_ba_hero_banner_link',
            'label'         => 'Link',
            'name'          => 'link',
            'type'          => 'link',
            'return_format' => 'array',
          ],
          [
            'key'           => 'field_ll_ba_hero_banner_image',
            'label'         => 'Image',
            'name'          => 'image',
            'type'          => 'image',
            'return_format' => 'id',
          ],
        ],
      ];
    }

    $fields = array_merge( $fields, [
      [
        'key'       => 'field_ll_bag_global_options_tab',
        'label'     => 'Single Page',
        'type'      => 'tab',
        'placement' => 'left',
        'endpoint'  => 0,
      ],
      [
        'key'  => 'field_ll_bag_global_default_single_page_label',
        'label' => 'Default Single Page Label',
        'name' => 'll_ba_global_default_single_page_label',
        'type' => 'text',
        'wrapper' => [
          'data-tooltip' => 'If left empty the label will default to "Treatments Used"',
        ],
      ],
      [
        'key'     => 'field_ll_bag_cta_message',
        'type'    => 'message',
        'message' => 'Leave CTA fields blank to omit CTA on single pages',
      ],
      [
        'key'     => 'field_ll_ba_global_cta_title',
        'label'   => 'CTA Title',
        'name'    => 'll_ba_global_cta_title',
        'type'    => 'text',
        'wrapper' => [ 'width' => '50%' ],
      ],
      [
        'key'           => 'field_ll_ba_global_cta_link',
        'label'         => 'CTA Link',
        'name'          => 'll_ba_global_cta_link',
        'type'          => 'link',
        'return_format' => 'array',
        'wrapper'       => [ 'width' => '50%' ],
      ],
      [
        'key'   => 'field_ll_bag_related_treatments_slider_title',
        'label' => 'Related Treatments Slider Title',
        'name'  => 'll_bag_related_treatments_slider_title',
        'type'  => 'text',
      ],
      [
        'key'       => 'field_ll_bag_nsfw_popup_tab',
        'label'     => 'NSFW Popup',
        'type'      => 'tab',
        'placement' => 'left',
        'endpoint'  => 0,
      ],
      [
        'key'           => 'field_ll_bag_nsfw_popup_text',
        'label'         => 'NSFW Popup Text',
        'name'          => 'll_bag_nsfw_popup_text',
        'type'          => 'text',
        'default_value' => 'This before and after contains sensitive content.',
      ],
      [
        'key'       => 'field_ll_bag_archive_settings_tab_2',
        'label'     => 'Card Background Color',
        'type'      => 'tab',
        'placement' => 'left',
        'endpoint'  => 0,
      ],
      [
        'key'           => 'field_ll_ba_card_bg_color',
        'label'         => 'Card Background Color',
        'name'          => 'll_ba_card_bg_color',
        'type'          => 'color_picker',
        'default_value' => '#B8C2B0',
        'instructions'  => 'Background color shown behind the Before and After post cards.',
      ],
      [
        'key'       => 'field_ll_bag_category_settings_tab',
        'label'     => 'Category Settings',
        'type'      => 'tab',
        'placement' => 'left',
        'endpoint'  => 0,
      ],
      [
        'key'           => 'field_ll_bag_use_category_archive',
        'label'         => 'Use category archive?',
        'name'          => 'll_bag_use_category_archive',
        'type'          => 'true_false',
        'default_value' => 1,
        'ui'            => 1,
        'ui_on_text'    => 'Yes',
        'ui_off_text'   => 'No',
      ],
      [
        'key'     => 'field_ll_bag_category_archive_url_message',
        'type'    => 'message',
        'message' => 'The category archive page will live at: <a href="' . esc_url( $categoriesArchiveUrl ) . '" target="_blank" rel="noopener">' . esc_html( $categoriesArchiveUrl ) . '</a>',
        'conditional_logic' => [
          [ [ 'field' => 'field_ll_bag_use_category_archive', 'operator' => '==', 'value' => '1' ] ],
        ],
      ],
      [
        'key'    => 'field_ll_ba_category_archive_hero',
        'label'  => 'Category Archive Hero',
        'name'   => 'll_ba_category_archive_hero',
        'type'   => 'group',
        'layout' => 'block',
        'conditional_logic' => [
          [ [ 'field' => 'field_ll_bag_use_category_archive', 'operator' => '==', 'value' => '1' ] ],
        ],
        'sub_fields' => [
          [
            'key'     => 'field_ll_ba_category_archive_hero_content',
            'label'   => 'Content',
            'name'    => 'content',
            'type'    => 'wysiwyg',
            'wrapper' => [ 'class' => 'll-ba-hero-banner-preview' ],
          ],
          [
            'key'           => 'field_ll_ba_category_archive_hero_link',
            'label'         => 'Link',
            'name'          => 'link',
            'type'          => 'link',
            'return_format' => 'array',
          ],
          [
            'key'           => 'field_ll_ba_category_archive_hero_image',
            'label'         => 'Image',
            'name'          => 'image',
            'type'          => 'image',
            'return_format' => 'id',
          ],
        ],
      ],
      [
        'key'           => 'field_ll_ba_categories_subtitle',
        'label'         => 'Categories Subtitle',
        'name'          => 'll_ba_categories_subtitle',
        'type'          => 'text',
        'default_value' => 'Select a category below to start exploring.',
        'instructions'  => 'Text shown above the category grid.',
        'conditional_logic' => [
          [ [ 'field' => 'field_ll_bag_use_category_archive', 'operator' => '==', 'value' => '1' ] ],
        ],
      ],
    ] );

    acf_add_local_field_group( [
      'key'      => 'group_ll_ba_settings',
      'title'    => 'Before & After Settings',
      'fields'   => $fields,
      'location' => [
        [
          [
            'param'    => 'options_page',
            'operator' => '==',
            'value'    => 'll-bag-settings',
          ],
        ],
      ],
    ] );
  }

  /**
   * Return the configured "all posts" page URL, or empty string if not set.
   */
  public static function getPostsPageUrl(): string {
    $pageId = (int) get_field(self::FIELD_POSTS_PAGE, 'option');
    return $pageId ? (string) get_permalink($pageId) : '';
  }
}
