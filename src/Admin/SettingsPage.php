<?php

namespace LiftedLogic\LLBag\Admin;

use LiftedLogic\LLBag\Frontend\TemplateLoader;
use LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType;
use LiftedLogic\LLBag\Filters\FilterManager;
use LiftedLogic\LLBag\Support\CategoryScope;
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
    add_filter('acf/load_field/key=field_ll_bag_archive_mode_urls', [$this, 'updateArchiveModeUrlsMessage']);
    add_filter('acf/load_value/key=field_ll_bag_archive_mode', [$this, 'loadArchiveModeValue']);
    add_filter('acf/load_field/key=field_ll_ba_image_ratio', [$this, 'labelDefaultRatioChoice']);
    add_filter('acf/validate_value/key=field_ll_bag_default_image_ratio_custom', [$this, 'validateCustomRatio'], 10, 2);
    add_action('admin_enqueue_scripts', [$this, 'exposeDefaultRatio'], 20);
    add_filter('acf/load_field/key=field_ll_ba_category_nav_taxonomy', [$this, 'populateCategoryNavTaxonomyChoices']);
    add_filter('acf/load_field/key=field_ll_ba_category_nav_term',     [$this, 'populateCategoryNavTermChoices']);
    add_filter('acf/validate_value/key=field_ll_ba_category_nav',      [$this, 'validateCategoryNav'], 10, 2);
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

  // Flushed on the next request (BeforeAfterPostType::maybeFlushRewriteRules): this save's
  // init already registered rules for the previous mode/slugs.
  public function maybeFlushRewriteRules(mixed $postId): void {
    if ($postId === 'options') {
      update_option(BeforeAfterPostType::FLUSH_FLAG, 1, false);
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
      [
        'key'           => 'field_ll_bag_archive_mode',
        'label'         => 'Main Gallery View',
        'name'          => 'll_bag_archive_mode',
        'type'          => 'button_group',
        'choices'       => [
          BeforeAfterPostType::MODE_POSTS      => 'Posts archive',
          BeforeAfterPostType::MODE_CATEGORIES => 'Categories',
        ],
        'default_value' => BeforeAfterPostType::MODE_POSTS,
        'return_format' => 'value',
        'layout'        => 'horizontal',
        'wrapper'       => [
          'data-tooltip' => 'What the gallery URL shows. Posts archive: every before & after with filters (no categories page). Categories: category cards with hero navigation; the posts archive moves to its own URL below and category cards link into it pre-filtered.',
        ],
      ],
      [
        'key'           => 'field_ll_bag_posts_archive_slug',
        'label'         => 'Posts Archive Slug',
        'name'          => 'll_bag_posts_archive_slug',
        'type'          => 'text',
        'default_value' => 'all',
        'wrapper'       => [
          'width'        => '50%',
          'data-tooltip' => 'URL segment for the posts archive in Categories mode, e.g. "all" → /before-afters/all/.',
        ],
        'conditional_logic' => [
          [ [ 'field' => 'field_ll_bag_archive_mode', 'operator' => '==', 'value' => BeforeAfterPostType::MODE_CATEGORIES ] ],
        ],
      ],
      [
        'key'     => 'field_ll_bag_archive_mode_urls',
        'label'   => 'Gallery URLs',
        'type'    => 'message',
        'message' => '',
        'wrapper' => [ 'width' => '50%' ],
      ],
      [
        'key'           => 'field_ll_bag_default_image_ratio',
        'label'         => 'Default Image Ratio',
        'name'          => 'll_bag_default_image_ratio',
        'type'          => 'select',
        'choices'       => [
          'wide'     => '16/9 (Wide)',
          'square'   => '1/1 (Square)',
          'panorama' => '3/1 (Panorama)',
          'vertical' => '4/5 (Vertical)',
          'custom'   => 'Custom',
        ],
        'default_value' => 'wide',
        'return_format' => 'value',
        'wrapper'       => [
          'width'        => '50%',
          'data-tooltip' => 'Used by every post image set to "Site default" (the default for new images). Images with their own ratio keep it.',
        ],
      ],
      [
        'key'           => 'field_ll_bag_default_image_ratio_custom',
        'label'         => 'Custom Ratio',
        'name'          => 'll_bag_default_image_ratio_custom',
        'type'          => 'text',
        'placeholder'   => '4:3',
        'wrapper'       => [
          'width'        => '50%',
          'data-tooltip' => 'Width:height, e.g. 4:3 or 3:2.',
        ],
        'conditional_logic' => [
          [ [ 'field' => 'field_ll_bag_default_image_ratio', 'operator' => '==', 'value' => 'custom' ] ],
        ],
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
          'data-tooltip' => 'The heading shown above each post\'s categories, for posts that do not set their own Single Page Label. Leave empty to show no heading',
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
        'key'     => 'field_ll_bag_posts_mode_notice',
        'type'    => 'message',
        'message' => 'The categories page is off because Archive Settings → Main Gallery View is set to Posts archive. Switch it to Categories to use the settings below.',
        'conditional_logic' => [
          [ [ 'field' => 'field_ll_bag_archive_mode', 'operator' => '!=', 'value' => BeforeAfterPostType::MODE_CATEGORIES ] ],
        ],
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
        'label'         => 'Scoped Pages Slug',
        'name'          => 'll_bag_category_archive_slug',
        'type'          => 'text',
        'default_value' => 'categories',
        'instructions'  => 'URL segment for the Hero Navigation pages, e.g. "categories" → /before-afters/categories/breast/.',
        'conditional_logic' => [
          [ [ 'field' => 'field_ll_bag_archive_mode', 'operator' => '==', 'value' => BeforeAfterPostType::MODE_CATEGORIES ] ],
        ],
      ],
      [
        'key'     => 'field_ll_bag_category_archive_url_message',
        'type'    => 'message',
        'message' => '',
        'conditional_logic' => [
          [ [ 'field' => 'field_ll_bag_archive_mode', 'operator' => '==', 'value' => BeforeAfterPostType::MODE_CATEGORIES ] ],
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
          [ [ 'field' => 'field_ll_bag_archive_mode', 'operator' => '==', 'value' => BeforeAfterPostType::MODE_CATEGORIES ] ],
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
            'wrapper' => ['width' => '50%'],
          ],
          [
            'label' => 'Image Focus Point',
            'key' => 'field_ll_ba_category_archive_hero_image_focus',
            'name' => 'image_focus_point',
            'type' => 'button_group',
            'wrapper' => [
              'width' => '50%',
              'class' => 'button-grid',
            ],
            'choices' => [
              'object-left-top' => '<div class="w-5 h-5"></div>',
              'object-top' => '<div class="w-5 h-5"></div>',
              'object-right-top' => '<div class="w-5 h-5"></div>',
              'object-left' => '<div class="w-5 h-5"></div>',
              'object-center' => '<div class="w-5 h-5"></div>',
              'object-right' => '<div class="w-5 h-5"></div>',
              'object-left-bottom' => '<div class="w-5 h-5"></div>',
              'object-bottom' => '<div class="w-5 h-5"></div>',
              'object-right-bottom' => '<div class="w-5 h-5"></div>',
            ],
            'allow_null' => 0,
            'default_value' => 'object-center',
            'layout' => 'horizontal',
            'return_format' => 'value',
          ],
        ],
      ];
    }

    $archiveOn = [ 'field' => 'field_ll_bag_archive_mode', 'operator' => '==', 'value' => BeforeAfterPostType::MODE_CATEGORIES ];

    $fields[] = [
      'key'           => 'field_ll_ba_category_nav_show_all',
      'label'         => 'Show "All" Pill',
      'name'          => CategoryScope::FIELD_SHOW_ALL,
      'type'          => 'true_false',
      'default_value' => 1,
      'ui'            => 1,
      'wrapper'       => [ 'width' => '50%' ],
      'conditional_logic' => [ [ $archiveOn ] ],
    ];

    $fields[] = [
      'key'           => 'field_ll_ba_category_nav_all_label',
      'label'         => '"All" Pill Label',
      'name'          => CategoryScope::FIELD_ALL_LABEL,
      'type'          => 'text',
      'default_value' => 'All',
      'wrapper'       => [ 'width' => '50%' ],
      'conditional_logic' => [ [ $archiveOn, [ 'field' => 'field_ll_ba_category_nav_show_all', 'operator' => '==', 'value' => '1' ] ] ],
    ];

    $fields[] = [
      'key'          => 'field_ll_ba_category_nav',
      'label'        => 'Hero Navigation',
      'name'         => CategoryScope::FIELD_NAV,
      'type'         => 'repeater',
      'layout'       => 'table',
      'button_label' => 'Add Row',
      'conditional_logic' => [ [ $archiveOn ] ],
      'sub_fields'   => [
        [
          'key'   => 'field_ll_ba_category_nav_label',
          'label' => 'Label',
          'name'  => 'label',
          'type'  => 'text',
        ],
        [
          'key'           => 'field_ll_ba_category_nav_taxonomy',
          'label'         => 'Taxonomy',
          'name'          => 'taxonomy',
          'type'          => 'select',
          'choices'       => [],
          'allow_null'    => 1,
          'ui'            => 0,
          'return_format' => 'value',
        ],
        [
          // Choices are every term of every enabled filter taxonomy, grouped by taxonomy.
          // admin.js hides the groups that don't match the row's Taxonomy select.
          'key'           => 'field_ll_ba_category_nav_term',
          'label'         => 'Term',
          'name'          => 'term',
          'type'          => 'select',
          'choices'       => [],
          'allow_null'    => 1,
          'ui'            => 0,
          'return_format' => 'value',
        ],
      ],
    ];

    $fields[] = [
      'key'           => 'field_ll_ba_categories_subtitle',
      'label'         => 'Taxonomy Archive Subtitle',
      'name'          => 'll_ba_categories_subtitle',
      'type'          => 'text',
      'default_value' => 'Select a category below to start exploring.',
      'instructions'  => 'Text shown above the category grid.',
      'conditional_logic' => [
        [ [ 'field' => 'field_ll_bag_archive_mode', 'operator' => '==', 'value' => BeforeAfterPostType::MODE_CATEGORIES ] ],
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
      $field['message'] = 'The categories page lives at: <a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($url) . '</a>';
    }
    return $field;
  }

  /** Current gallery URLs for the saved mode (they change after saving a different mode). */
  public function updateArchiveModeUrlsMessage(array $field): array {
    $link = fn(string $url) => '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($url) . '</a>';
    $rows = BeforeAfterPostType::isCategoriesMode()
      ? [
          'Categories page' => BeforeAfterPostType::getCategoriesArchiveUrl(),
          'Posts archive'   => BeforeAfterPostType::getPostsArchiveUrl(),
        ]
      : [ 'Posts archive' => BeforeAfterPostType::getPostsArchiveUrl() ];

    $field['message'] = implode('<br>', array_map(
      fn($label, $url) => esc_html($label) . ': ' . ($url ? $link($url) : '—'),
      array_keys($rows), $rows
    ));
    return $field;
  }

  /** Sites that never saved the mode show what they're actually running (see BeforeAfterPostType::archiveMode()). */
  public function loadArchiveModeValue($value) {
    // ACF has already swapped in default_value by now, so check whether it was ever saved.
    return get_option('options_ll_bag_archive_mode') === false ? BeforeAfterPostType::archiveMode() : $value;
  }

  /**
   * Hero Navigation taxonomy choices: enabled sidebar filters only. The archive only
   * restores a URL param that has a matching sidebar group, so a scope taxonomy that
   * isn't an enabled filter would be silently dropped on click-through.
   *
   * @param array<string, mixed> $field
   * @return array<string, mixed>
   */
  public function populateCategoryNavTaxonomyChoices(array $field): array {
    $field['choices'] = [];

    foreach ($this->enabledFilterTaxonomies() as $slug => $taxonomy) {
      $field['choices'][$slug] = $taxonomy->label;
    }

    return $field;
  }

  /**
   * Hero Navigation term choices, grouped by taxonomy label (rendered as <optgroup>s).
   *
   * @param array<string, mixed> $field
   * @return array<string, mixed>
   */
  public function populateCategoryNavTermChoices(array $field): array {
    $field['choices'] = [];

    foreach ($this->enabledFilterTaxonomies() as $slug => $taxonomy) {
      $terms = get_terms(['taxonomy' => $slug, 'hide_empty' => false]);
      if (is_wp_error($terms) || empty($terms)) continue;

      foreach ($terms as $term) {
        $field['choices'][$taxonomy->label][$term->term_id] = $term->name;
      }
    }

    return $field;
  }

  /**
   * Each row needs a term that belongs to its taxonomy, and term slugs must be unique
   * across rows because the slug is the scoped page's URL segment.
   */
  public function validateCategoryNav(mixed $valid, mixed $value): mixed {
    if ($valid !== true || !is_array($value)) return $valid;

    $slugs = [];

    foreach ($value as $key => $row) {
      if ($key === 'acfcloneindex' || !is_array($row)) continue;

      $taxonomy = (string) ($row['field_ll_ba_category_nav_taxonomy'] ?? '');
      $termId   = (int) ($row['field_ll_ba_category_nav_term'] ?? 0);

      if ($taxonomy === '' || !$termId) {
        return 'Each Hero Navigation row needs a taxonomy and a term.';
      }

      $term = get_term($termId, $taxonomy);
      if (!$term instanceof \WP_Term) {
        return 'A Hero Navigation term doesn\'t belong to the taxonomy picked in its row.';
      }

      if (isset($slugs[$term->slug])) {
        return sprintf('Two Hero Navigation rows use terms with the slug "%s". Each row needs a unique slug, since it becomes that page\'s URL.', $term->slug);
      }
      $slugs[$term->slug] = true;
    }

    return $valid;
  }

  /** @return array<string, WP_Taxonomy> Enabled filter taxonomies keyed by slug. */
  private function enabledFilterTaxonomies(): array {
    $out = [];

    foreach ($this->filterManager->getEnabled() as $filter) {
      $slug     = $filter['meta_key'] ?? '';
      $taxonomy = $slug !== '' ? get_taxonomy($slug) : false;
      if ($taxonomy instanceof WP_Taxonomy) {
        $out[$slug] = $taxonomy;
      }
    }

    return $out;
  }

  /** Show which ratio "Site default" currently means, e.g. "Site default (16/9 Wide)". */
  public function labelDefaultRatioChoice(array $field): array {
    $default = bag_default_image_ratio();
    $labels  = ['wide' => '16/9 Wide', 'square' => '1/1 Square', 'panorama' => '3/1 Panorama', 'vertical' => '4/5 Vertical'];
    $label   = $default['key'] === 'custom' ? str_replace(' / ', '/', $default['css']) : ($labels[$default['key']] ?? '');
    if (isset($field['choices']['default'])) {
      $field['choices']['default'] = 'Site default' . ($label ? " ($label)" : '');
    }
    return $field;
  }

  public function validateCustomRatio(mixed $valid, mixed $value): mixed {
    if ($valid !== true || (string) $value === '') return $valid;
    return preg_match('/^\s*\d+(\.\d+)?\s*[:\/x]\s*\d+(\.\d+)?\s*$/i', (string) $value) ? $valid : 'Use width:height, e.g. 4:3.';
  }

  /** The admin crop preview needs the default ratio to frame "Site default" rows. */
  public function exposeDefaultRatio(): void {
    if (!wp_script_is('ll-bag-admin', 'enqueued')) return;
    $default = bag_default_image_ratio();
    wp_add_inline_script('ll-bag-admin', 'window.llBagDefaultRatio = ' . wp_json_encode([
      'value'   => $default['value'],
      'stacked' => $default['key'] === 'custom' ? $default['value'] >= 1.7 : in_array($default['key'], ['wide', 'panorama'], true),
    ]) . ';', 'before');
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
    $taxonomy = (string) ( get_option('options_ll_bag_category_taxonomy') ?: 'll_ba_category' );
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
            'wrapper' => [
              'width' => '50%',
            ],
          ],
          [
            'label' => 'Image Focus Point',
            'key' => 'field_ll_ba_hero_banner_image_focus',
            'name' => 'image_focus_point',
            'type' => 'button_group',
            'wrapper' => [
              'width' => '50%',
              'class' => 'button-grid',
            ],
            'choices' => [
              'object-left-top' => '<div class="w-5 h-5"></div>',
              'object-top' => '<div class="w-5 h-5"></div>',
              'object-right-top' => '<div class="w-5 h-5"></div>',
              'object-left' => '<div class="w-5 h-5"></div>',
              'object-center' => '<div class="w-5 h-5"></div>',
              'object-right' => '<div class="w-5 h-5"></div>',
              'object-left-bottom' => '<div class="w-5 h-5"></div>',
              'object-bottom' => '<div class="w-5 h-5"></div>',
              'object-right-bottom' => '<div class="w-5 h-5"></div>',
            ],
            'allow_null' => 0,
            'default_value' => 'object-center',
            'layout' => 'horizontal',
            'return_format' => 'value',
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

  /** URL segment of the posts archive in Categories mode (/{archive}/{slug}/). */
  public static function getPostsArchiveSlug(): string {
    return sanitize_title(get_option('options_ll_bag_posts_archive_slug') ?: 'all');
  }

  public static function getCategoryArchiveSlug(): string {
    return sanitize_title(get_option('options_ll_bag_category_archive_slug') ?: 'categories');
  }
}
