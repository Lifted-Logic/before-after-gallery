<?php

namespace LiftedLogic\LLBag\BeforeAfterPostType;

use LiftedLogic\LLBag\Filters\FilterManager;
use LiftedLogic\LLBag\Support\RelatedPosts;

class Fields {
  public function __construct(
    private readonly FilterManager $filterManager,
    private readonly RelatedPosts $relatedPosts,
  ) {}

  public function register(): void {
    add_action('acf/init',           [$this, 'registerFields']);
    add_action('add_meta_boxes',     [$this, 'addRelatedMetaBox']);
    add_action('save_post_' . BeforeAfterPostType::SLUG, [$this, 'saveRelatedTerms']);
  }

  public function addRelatedMetaBox(): void {
    add_meta_box(
      'll-ba-related-terms',
      'Related Before & After Posts',
      [$this, 'renderRelatedMetaBox'],
      BeforeAfterPostType::SLUG,
      'normal',
      'low'
    );
  }

  public function renderRelatedMetaBox(\WP_Post $post): void {
    $filters = $this->filterManager->getEnabled()
      ->filter(fn($f) => empty($f['builtin']) && !empty($f['meta_key']))
      ->values();

    ?>
    <p class="description" style="margin-bottom:8px"><?= esc_html__('Related posts are chosen automatically: the gallery shows the posts that share the most specific terms with this one. A term that only a few posts have counts for more than one that most posts have. When posts are tied, the ones nearest to this post in the gallery order come first. If fewer than 6 posts share any term, the rest are filled with the newest posts.', 'll-bag'); ?></p>
    <p class="description" style="margin-bottom:8px"><?= esc_html__('To choose yourself, check terms below. Posts with those terms are shown first, and the automatic matches fill any remaining spots. Leave everything unchecked to stay fully automatic.', 'll-bag'); ?></p>
    <?php $this->renderRelatedPreview($post); ?>
    <?php

    if ($filters->isEmpty()) {
      echo '<p class="ll-ba-rmb-none">No custom taxonomies are configured as filters.</p>';
      return;
    }

    $saved = (array) (get_post_meta($post->ID, 'll_ba_related_terms', true) ?: []);
    wp_nonce_field('ll_ba_related_terms_save', 'll_ba_related_terms_nonce');
    ?>
    <div class="ll-ba-tax-tabs">
      <ul class="ll-ba-tax-tab-list">
        <?php foreach ($filters as $i => $f) : ?>
          <li>
            <button
              type="button"
              class="ll-ba-tax-tab <?= $i === 0 ? 'is-active' : ''; ?>"
              data-target="ll-ba-related-panel-<?= esc_attr($f['meta_key']); ?>"
            ><?= esc_html($f['label']); ?></button>
          </li>
        <?php endforeach; ?>
      </ul>

      <div class="ll-ba-tax-panels">
        <?php foreach ($filters as $i => $f) :
          $taxonomy = $f['meta_key'];
          $terms    = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        ?>
          <div
            id="ll-ba-related-panel-<?= esc_attr($taxonomy); ?>"
            class="ll-ba-tax-panel"
            <?= $i !== 0 ? 'hidden' : ''; ?>
          >
            <?php if (is_wp_error($terms) || empty($terms)) : ?>
              <p class="description">No terms found.</p>
            <?php else : ?>
              <ul class="ll-ba-related-checklist">
                <?php foreach ($terms as $term) :
                  $value = $taxonomy . ':' . $term->slug;
                ?>
                  <li>
                    <label>
                      <input type="checkbox" name="ll_ba_related_terms[]" value="<?= esc_attr($value); ?>" <?= checked(in_array($value, $saved, true), true, false); ?>>
                      <?= esc_html($term->name); ?>
                    </label>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
  }

  /**
   * "Currently showing" list: the saved state, as the front end would build it right now.
   */
  /**
   * "Category: Nose · Treatment Areas: Nose". Plain text; escape at output.
   *
   * @param array<int, array{taxonomy:string, name:string}> $shared
   */
  public static function termLabels(array $shared): string {
    $parts = [];
    foreach ($shared as $term) {
      $taxonomy = get_taxonomy($term['taxonomy']);
      $label    = $taxonomy && isset($taxonomy->labels->singular_name) && $taxonomy->labels->singular_name !== ''
                  ? $taxonomy->labels->singular_name
                  : $term['taxonomy'];
      $parts[]  = $label . ': ' . $term['name'];
    }
    return implode(' · ', $parts);
  }

  private function renderRelatedPreview(\WP_Post $post): void {
    echo '<h4 style="margin:12px 0 4px">' . esc_html__('Currently showing', 'll-bag') . '</h4>';

    if ($post->post_status === 'auto-draft') {
      echo '<p class="description">' . esc_html__('Save this post to see its related posts.', 'll-bag') . '</p>';
      return;
    }

    $picks = $this->relatedPosts->explain($post->ID, RelatedPosts::SLOTS, true);

    if (!$picks) {
      echo '<p class="description">' . esc_html__('No other published posts yet.', 'll-bag') . '</p>';
      return;
    }

    $labels = [
      'manual' => __('From the terms checked below', 'll-bag'),
      'newest' => __('Newest post, no specific terms in common', 'll-bag'),
    ];

    echo '<ol class="ll-ba-related-preview" style="margin:0 0 8px 20px">';
    foreach ($picks as $pick) {
      $title = get_the_title($pick['id']);
      $link  = get_edit_post_link($pick['id']);
      $terms = self::termLabels($pick['shared']);
      ?>
      <li style="margin-bottom:4px">
        <?php if ($link) : ?>
          <a href="<?= esc_url($link); ?>"><?= esc_html($title !== '' ? $title : __('(no title)', 'll-bag')); ?></a>
        <?php else : ?>
          <?= esc_html($title); ?>
        <?php endif; ?>
        <?php if (isset($labels[$pick['source']])) : ?>
          <em style="font-size:11px">(<?= esc_html($labels[$pick['source']]); ?>)</em>
        <?php endif; ?>
        <?php if ($terms) : ?>
          <br><small style="color:#646970"><?= esc_html($terms); ?></small>
        <?php endif; ?>
      </li>
      <?php
    }
    echo '</ol>';
    echo '<p class="description" style="margin-bottom:12px">' . esc_html__('This list shows the saved post. Changes to the terms below show after you click Update.', 'll-bag') . '</p>';
  }

  public function saveRelatedTerms(int $postId): void {
    if (
      !isset($_POST['ll_ba_related_terms_nonce']) ||
      !wp_verify_nonce($_POST['ll_ba_related_terms_nonce'], 'll_ba_related_terms_save')
    ) {
      return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $postId)) return;

    $raw   = isset($_POST['ll_ba_related_terms']) && is_array($_POST['ll_ba_related_terms'])
             ? $_POST['ll_ba_related_terms']
             : [];
    $valid = array_values(array_filter(
      array_map('sanitize_text_field', $raw),
      fn($v) => (bool) preg_match('/^[a-z0-9_-]+:[a-z0-9_-]+$/', $v)
    ));

    if (empty($valid)) {
      delete_post_meta($postId, 'll_ba_related_terms');
    } else {
      update_post_meta($postId, 'll_ba_related_terms', $valid);
    }
  }

  public function registerFields(): void {
    $categoryTaxonomy = get_option('options_ll_bag_category_taxonomy') ?: 'll_ba_category';

    // Category Fields
    acf_add_local_field_group([
      'key'    => 'group_ll_ba_category',
      'title'  => 'Before & After Category Settings',
      'fields' => [
        [
          'key'           => 'field_ll_ba_category_bg_image',
          'label'         => 'Background Image',
          'name'          => 'll_ba_category_bg_image',
          'type'          => 'image',
          'return_format' => 'id',
          'instructions'  => 'Used as the background image for this category card on the Before & After Category Archive.',
        ],
      ],
      'location' => [
        [
          [
            'param'    => 'taxonomy',
            'operator' => '==',
            'value'    => $categoryTaxonomy,
          ],
        ],
      ],
    ]);
    // Provider Fields
    acf_add_local_field_group([
      'key'    => 'group_ll_ba_provider',
      'title'  => 'Before & After Provider Settings',
      'fields' => [
        [
          'key'           => 'field_ll_ba_provider_link',
          'label'         => 'Link',
          'name'          => 'll_ba_provider_link',
          'type'          => 'link',
          'return_format' => 'array',
          'instructions'  => 'Shows on single before & after pages where this provider has been selected',
        ],
        [
          'key'           => 'field_ll_ba_provider_image',
          'label'         => 'Image',
          'name'          => 'll_ba_provider_image',
          'type'          => 'image',
          'return_format' => 'id',
        ],
      ],
      'location' => [
        [
          [
            'param'    => 'taxonomy',
            'operator' => '==',
            'value'    => 'll_ba_provider',
          ],
        ],
      ],
    ]);

    // Single Post Fields
    acf_add_local_field_group([
      'key'    => 'group_ll_before_after',
      'title'  => 'Before & After Details',
      'fields' => [
        [
          'key' => 'field_ll_details_tab',
          'label' => 'Details',
          'type' => 'tab',
          'placement' => 'left',
          'endpoint' => 0,
        ],
        [
          'key' => 'field_ll_ba_title',
          'label' => 'Single Page Label',
          'name' => 'll_ba_title',
          'type' => 'text',
          'wrapper' => [
            'data-tooltip' => 'The heading shown above this post\'s categories. Leave blank to use Before & Afters > Settings > Single Pages > Default Page Label, or to show no heading if that is empty too',
          ],
        ],
        [
          'label' => '',
          'name' => '',
          'type' => 'message',
          'message' => 'The first image or set of images will show in the card for this post',
        ],
        [
          'key' => 'field_ll_ba_detail_sections',
          'label' => 'Detail Sections',
          'name' => 'll_ba_detail_sections',
          'type' => 'repeater',
          'layout' => 'block',
          'button_label' => 'Add Detail Section',
          'instructions' => 'If more than one Detail Section is used, Details will show in tabs. If only one detail field is used, the Detail Title will not show.',
          'max' => 3,
          'sub_fields' => [
            [
              'key' => 'field_ll_ba_detail_title',
              'label' => 'Title',
              'name' => 'll_ba_detail_title',
              'type' => 'text',
            ],
            [
              'key' => 'field_ll_ba_detail_content',
              'label' => 'Content',
              'name' => 'll_ba_detail_content',
              'type' => 'wysiwyg',
            ],
            [
              'key' => 'field_ll_ba_detail_read_more_content',
              'label' => 'Read More Content',
              'name' => 'll_ba_detail_read_more_content',
              'type' => 'wysiwyg',
            ],
          ],
        ],
        [
          'key' => 'field_ll_images_tab',
          'label' => 'Images',
          'type' => 'tab',
          'placement' => 'left',
          'endpoint' => 0,
        ],
        [
          'key' => 'field_ll_ba_images',
          'label' => 'Images',
          'name' => 'll_ba_images',
          'type' => 'repeater',
          'layout' => 'block',
          'button_label' => 'Add Image',
          'sub_fields' => [
            [
              'key' => 'field_ll_ba_image_options',
              'label' => 'Image Options',
              'name' => 'll_ba_image_options',
              'type' => 'select',
              'choices' => [
                'one-image' => 'One Image',
                'two-images' => 'Two Images',
                'video' => 'Video',
              ],
              'default_value' => '',
              'return_format' => 'value',
              'wrapper' => [
                'width' => '50%',
              ],
            ],
            [
              'key' => 'field_ll_ba_image_ratio_message',
              'label' => 'Image Ratio',
              'name' => 'll_ba_image_ratio_message',
              'type' => 'message',
              'message' => 'The video thumbnail is used in the ratio / crop it is uploaded with.',
              'wrapper' => [
                'width' => '50%',
              ],
              'conditional_logic' => [
                [
                  [
                    'field' => 'field_ll_ba_image_options',
                    'operator' => '==',
                    'value' => 'video',
                  ],
                ],
              ],
            ],
            [
              'key' => 'field_ll_ba_image_ratio',
              'label' => 'Individual Image Ratio',
              'name' => 'll_ba_image_ratio',
              'type' => 'select',
              'choices' => [
                'default' => 'Site default', // label gets the current default appended (SettingsPage::labelDefaultRatioChoice)
                'wide' => '16/9 (Wide)',
                'square' => '1/1 (Square)',
                'panorama' => '3/1 (Panorama)',
                'vertical' => '4/5 (Vertical)',
              ],
              'default_value' => 'default',
              'return_format' => 'value',
              'wrapper' => [
                'width' => '50%',
                'data-tooltip' => 'The frame images are cropped to.',
              ],
              'conditional_logic' => [
                [
                  [
                    'field' => 'field_ll_ba_image_options',
                    'operator' => '==',
                    'value' => 'two-images',
                  ],
                ],
                [
                  [
                    'field' => 'field_ll_ba_image_options',
                    'operator' => '==',
                    'value' => 'one-image',
                  ],
                ],
              ],
            ],
            [
              'key' => 'field_ll_ba_single_image',
              'label' => 'Before and After Image',
              'name' => 'll_ba_single_image',
              'type' => 'image',
              'return_format' => 'id',
              'conditional_logic' => [
                [
                  [
                    'field' => 'field_ll_ba_image_options',
                    'operator' => '!=',
                    'value' => 'two-images',
                  ],
                ],
              ],
            ],
            [
              'key' => 'field_ll_ba_single_crop_accordion',
              'label' => 'Crop & Zoom',
              'type' => 'accordion',
              'open' => 0,
              'multi_expand' => 1,
              'endpoint' => 0,
              'conditional_logic' => [
                [
                  [
                    'field' => 'field_ll_ba_image_options',
                    'operator' => '!=',
                    'value' => 'two-images',
                  ],
                ],
              ],
            ],
            [
              'key' => 'field_ll_ba_single_fill',
              'label' => 'Cropped',
              'name' => 'll_ba_single_fill',
              'type' => 'true_false',
              'ui' => 1,
              'default_value' => 0,
              'instructions' => '',
              'wrapper' => [
                'class' => 'll-ba-internal-field',
              ],
              'conditional_logic' => [
                [
                  [
                    'field' => 'field_ll_ba_image_options',
                    'operator' => '!=',
                    'value' => 'two-images',
                  ],
                ],
              ],
            ],
            [
              'key' => 'field_ll_ba_single_focal',
              'label' => 'Focal Point',
              'name' => 'll_ba_single_focal',
              'type' => 'text',
              'default_value' => '',
              'instructions' => '',
              'wrapper' => [ 'class' => 'll-ba-crop-input' ],
              'conditional_logic' => [
                [
                  [
                    'field' => 'field_ll_ba_image_options',
                    'operator' => '!=',
                    'value' => 'two-images',
                  ],
                ],
              ],
            ],
            [
              'key' => 'field_ll_ba_single_crop_accordion_end',
              'label' => '',
              'type' => 'accordion',
              'endpoint' => 1,
            ],
            [
              'key' => 'field_ll_ba_before_image',
              'label' => 'Before Image',
              'name' => 'll_ba_before_image',
              'type' => 'image',
              'return_format' => 'id',
              'conditional_logic' => [
                [
                  [
                    'field' => 'field_ll_ba_image_options',
                    'operator' => '==',
                    'value' => 'two-images',
                  ],
                ],
              ],
              'wrapper' => [
                'width' => '50%',
              ],
            ],
            [
              'key' => 'field_ll_ba_after_image',
              'label' => 'After Image',
              'name' => 'll_ba_after_image',
              'type' => 'image',
              'return_format' => 'id',
              'conditional_logic' => [
                [
                  [
                    'field' => 'field_ll_ba_image_options',
                    'operator' => '==',
                    'value' => 'two-images',
                  ],
                ],
              ],
              'wrapper' => [
                'width' => '50%',
              ],
            ],
            [
              'key' => 'field_ll_ba_crop_accordion',
              'label' => 'Crop & Zoom',
              'type' => 'accordion',
              'open' => 0,
              'multi_expand' => 1,
              'endpoint' => 0,
              'instructions' => '',
              'conditional_logic' => [
                [
                  [
                    'field' => 'field_ll_ba_image_options',
                    'operator' => '==',
                    'value' => 'two-images',
                  ],
                ],
              ],
            ],
            [
              'key' => 'field_ll_ba_before_focal',
              'label' => 'Before Crop',
              'name' => 'll_ba_before_focal',
              'type' => 'text',
              'default_value' => '',
              'instructions' => '',
              'wrapper' => [ 'class' => 'll-ba-crop-input' ],
              'conditional_logic' => [
                [
                  [
                    'field' => 'field_ll_ba_image_options',
                    'operator' => '==',
                    'value' => 'two-images',
                  ],
                ],
              ],
            ],
            [
              'key' => 'field_ll_ba_after_focal',
              'label' => 'After Crop',
              'name' => 'll_ba_after_focal',
              'type' => 'text',
              'default_value' => '',
              'instructions' => '',
              'wrapper' => [ 'class' => 'll-ba-crop-input' ],
              'conditional_logic' => [
                [
                  [
                    'field' => 'field_ll_ba_image_options',
                    'operator' => '==',
                    'value' => 'two-images',
                  ],
                ],
              ],
            ],
            [
              'key' => 'field_ll_ba_crop_accordion_end',
              'label' => '',
              'type' => 'accordion',
              'endpoint' => 1,
            ],
            [
              'key' => 'field_ll_ba_comparison_slider',
              'label' => 'Use Comparison Slider?',
              'name' => 'll_ba_comparison_slider',
              'type' => 'true_false',
              'default_value' => 0,
              'ui' => 1,
              'wrapper' => [
                'data-tooltip' => 'For best results use two images that are cropped identically with the subject in the same area of the image. Image comparison slider will only show on the single page. The images will be placed side by side for on the post card',
              ],
              'conditional_logic' => [
                [
                  [
                    'field' => 'field_ll_ba_image_options',
                    'operator' => '==',
                    'value' => 'two-images',
                  ],
                ],
              ],
            ],
            [
              'key' => 'field_ll_ba_video_url',
              'label' => 'Video URL',
              'name' => 'll_ba_video_url',
              'type' => 'url',
              'conditional_logic' => [
                [
                  [
                    'field' => 'field_ll_ba_image_options',
                    'operator' => '==',
                    'value' => 'video',
                  ],
                ],
              ],
            ],
            [
              'key' => 'field_ll_ba_video_title',
              'label' => 'Video Title (For Screen Readers)',
              'name' => 'll_ba_video_title',
              'type' => 'text',
              'conditional_logic' => [
                [
                  [
                    'field' => 'field_ll_ba_image_options',
                    'operator' => '==',
                    'value' => 'video',
                  ],
                ],
              ],
            ],
          ],
        ],
        [
          'key'       => 'field_ll_settings_tab',
          'label'     => 'NSFW Settings',
          'type'      => 'tab',
          'placement' => 'left',
          'endpoint'  => 0,
        ],
        [
          'key'           => 'field_ll_ba_is_nsfw',
          'label'         => 'Sensitive Images',
          'name'          => 'll_ba_is_nsfw',
          'type'          => 'true_false',
          'instructions'  => 'Mark this post as containing sensitive images. Visitors will be given blur/hide options.',
          'default_value' => 0,
          'ui'            => 1,
          'ui_on_text'    => 'Yes',
          'ui_off_text'   => 'No',
        ],
      ],
      'location' => [
        [
          [
            'param' => 'post_type',
            'operator' => '==',
            'value' => BeforeAfterPostType::SLUG,
          ],
        ],
      ],
    ]);

  }
}
