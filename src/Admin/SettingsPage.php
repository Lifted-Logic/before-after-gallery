<?php

namespace LiftedLogic\LLBag\Admin;

use LiftedLogic\LLBag\Frontend\TemplateLoader;
use LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType;
use LiftedLogic\LLBag\Filters\FilterManager;
use WP_Taxonomy;

class SettingsPage {
  public const FIELD_POSTS_PAGE = 'll_bag_posts_page';

  public function __construct(private readonly FilterManager $filterManager) {}

  public function register(): void {
    add_action('acf/init',     [$this, 'registerOptionsPage']);
    add_action('acf/init',     [$this, 'registerFields']);
    add_action('acf/init',     [$this, 'registerArchivePageFields']);
    add_action('acf/init',     [$this, 'registerTermCtaFields']);
    add_action('acf/save_post', [$this, 'maybeFlushRewriteRules']);
    add_action('admin_init',   [$this, 'disableArchivePageEditor']);
    add_filter('use_block_editor_for_post', [$this, 'disableBlockEditorForArchivePage'], 10, 2);
    add_filter('acf/load_field/key=field_ll_bag_category_taxonomy',          [$this, 'populateCategoryTaxonomyChoices']);
    add_filter('acf/load_field/key=field_ll_bag_category_archive_url_message', [$this, 'updateCategoryArchiveUrlMessage']);
  }

  public function disableArchivePageEditor(): void {
    $page_id = (int) get_option('options_' . self::FIELD_POSTS_PAGE);
    if (!$page_id) return;

    $current = isset($_GET['post']) ? (int) $_GET['post'] : 0;
    if ($current !== $page_id) return;

    remove_post_type_support('page', 'editor');
  }

  public function disableBlockEditorForArchivePage(bool $use, \WP_Post $post): bool {
    $page_id = (int) get_option('options_' . self::FIELD_POSTS_PAGE);
    if ($page_id && $post->ID === $page_id) return false;
    return $use;
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
    $categoriesHeroBannerOverridden = TemplateLoader::resolve( 'partials/categories-hero-banner.php' )
      !== LL_BAG_PATH . 'templates/partials/categories-hero-banner.php';

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
      ],
    ];

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
        'key'  => 'field_ll_bag_global_back_label',
        'label' => 'Back to Gallery Label',
        'name' => 'll_ba_global_back_label',
        'type' => 'text',
        'wrapper' => [
          'data-tooltip' => 'If left empty the link will default to "Back to Gallery"',
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
        'key'       => 'field_ll_bag_category_settings_tab',
        'label'     => 'Taxonomy Archive Settings',
        'type'      => 'tab',
        'placement' => 'left',
        'endpoint'  => 0,
      ],
      [
        'key'           => 'field_ll_bag_use_category_archive',
        'label'         => 'Use Taxonomy Archive?',
        'name'          => 'll_bag_use_category_archive',
        'type'          => 'true_false',
        'default_value' => 1,
        'ui'            => 1,
        'ui_on_text'    => 'Yes',
        'ui_off_text'   => 'No',
      ],
      [
        'key'           => 'field_ll_bag_category_taxonomy',
        'label'         => 'Featured Taxonomy',
        'name'          => 'll_bag_category_taxonomy',
        'type'          => 'select',
        'choices'       => [],
        'allow_null'    => 1,
        'multiple'      => 0,
        'ui'            => 1,
        'return_format' => 'value',
        'placeholder'   => 'Select a taxonomy…',
        'instructions'  => 'The taxonomy treated as the post\'s category. Its terms fill out the Taxonomy Archive page, and each term can carry its own CTA that overrides the global one below.',
      ],
      [
        'key'           => 'field_ll_bag_category_archive_slug',
        'label'         => 'Taxonomy Archive Slug',
        'name'          => 'll_bag_category_archive_slug',
        'type'          => 'text',
        'default_value' => 'categories',
        'instructions'  => 'The URL segment for the category archive page (e.g. "treatments" → /before-afters/treatments/). After changing this field you must go to Settings > Permalinks > Save in order for the change to take effect',
        'conditional_logic' => [
          [ [ 'field' => 'field_ll_bag_use_category_archive', 'operator' => '==', 'value' => '1' ] ],
        ],
      ],
      [
        'key'     => 'field_ll_bag_category_archive_url_message',
        'type'    => 'message',
        'message' => '',
        'conditional_logic' => [
          [ [ 'field' => 'field_ll_bag_use_category_archive', 'operator' => '==', 'value' => '1' ] ],
        ],
      ],
    ] );

    if ( !$categoriesHeroBannerOverridden ) {
      $fields[] = [
        'key'    => 'field_ll_ba_category_archive_hero',
        'label'  => 'Taxonomy Archive Hero',
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
      ];
    }

    $fields[] = [
      'key'           => 'field_ll_ba_categories_subtitle',
      'label'         => 'Taxonomy Archive Subtitle',
      'name'          => 'll_ba_categories_subtitle',
      'type'          => 'text',
      'default_value' => 'Select a category below to start exploring.',
      'instructions'  => 'Text shown above the category grid.',
      'conditional_logic' => [
        [ [ 'field' => 'field_ll_bag_use_category_archive', 'operator' => '==', 'value' => '1' ] ],
      ],
    ];

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
   * Populate the Category Taxonomy select with all taxonomies registered to the BAG post type.
   * Hooked to acf/load_field — fires at render time, well after init, so all custom taxonomies exist.
   *
   * @param array<string, mixed> $field
   * @return array<string, mixed>
   */
  /**
   * Recompute the category archive URL message at render time so it uses get_post_type_archive_link(),
   * which is unavailable at acf/init because registerPostType() hasn't run yet.
   *
   * @param array<string, mixed> $field
   * @return array<string, mixed>
   */
  public function updateCategoryArchiveUrlMessage(array $field): array {
    $url = BeforeAfterPostType::getCategoriesArchiveUrl();
    if ($url) {
      $field['message'] = 'The category archive page will live at: <a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($url) . '</a>';
    }
    return $field;
  }

  public function populateCategoryTaxonomyChoices(array $field): array {
    $field['choices'] = [];

    foreach ($this->filterManager->all() as $filter) {
      $slug = $filter['meta_key'] ?? '';
      if ($slug === '') continue;

      $taxonomy = get_taxonomy($slug);
      if ($taxonomy instanceof WP_Taxonomy) {
        $field['choices'][$slug] = $taxonomy->label;
      }
    }

    return $field;
  }

  public function registerTermCtaFields(): void {
    $taxonomy = (string) ( get_option('options_ll_bag_category_taxonomy') ?: 'category' );
    if ($taxonomy === '') return;

    acf_add_local_field_group( [
      'key'    => 'group_ll_ba_term_cta',
      'title'  => 'Before & After CTA',
      'fields' => [
        [
          'key'     => 'field_ll_ba_term_cta_message',
          'type'    => 'message',
          'message' => 'Overrides the global CTA for posts whose primary term is this one. Leave the link blank to keep using the global CTA.',
        ],
        [
          'key'     => 'field_ll_ba_term_cta_title',
          'label'   => 'CTA Title',
          'name'    => 'll_ba_term_cta_title',
          'type'    => 'text',
          'wrapper' => [ 'width' => '50%' ],
        ],
        [
          'key'           => 'field_ll_ba_term_cta_link',
          'label'         => 'CTA Link',
          'name'          => 'll_ba_term_cta_link',
          'type'          => 'link',
          'return_format' => 'array',
          'wrapper'       => [ 'width' => '50%' ],
        ],
      ],
      'location' => [
        [
          [
            'param'    => 'taxonomy',
            'operator' => '==',
            'value'    => $taxonomy,
          ],
        ],
      ],
    ] );
  }

  public function registerArchivePageFields(): void {
    $page_id = (int) get_option('options_' . self::FIELD_POSTS_PAGE);
    if (!$page_id) return;

    $heroBannerOverridden    = TemplateLoader::resolve( 'partials/archive-hero-banner.php' )
      !== LL_BAG_PATH . 'templates/partials/archive-hero-banner.php';
    $heroBannerFieldsEnabled = apply_filters( 'll_bag/hero_banner_fields_enabled', !$heroBannerOverridden );

    $fields = [];

    if ($heroBannerFieldsEnabled) {
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

    if (empty($fields)) return;

    acf_add_local_field_group([
      'key'      => 'group_ll_ba_archive_page_fields',
      'title'    => 'Before & After Archive',
      'fields'   => $fields,
      'location' => [
        [
          [
            'param'    => 'post',
            'operator' => '==',
            'value'    => $page_id,
          ],
        ],
      ],
    ]);
  }

  /**
   * Return the configured "all posts" page URL, or empty string if not set.
   */
  public static function getPostsPageUrl(): string {
    $pageId = (int) get_field(self::FIELD_POSTS_PAGE, 'option');
    return $pageId ? (string) get_permalink($pageId) : '';
  }

  public static function getCategoryTaxonomy(): string {
    return (string) get_field('ll_bag_category_taxonomy', 'option');
  }

  public static function getCategoryArchiveSlug(): string {
    return sanitize_title(get_option('options_ll_bag_category_archive_slug') ?: 'categories');
  }
}
