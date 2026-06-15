<?php

namespace LiftedLogic\LLBag\BeforeAfterPostType;

use LiftedLogic\LLBag\Filters\FilterManager;

class Fields {
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
    $filters = (new FilterManager())->getEnabled()
      ->filter(fn($f) => empty($f['builtin']) && !empty($f['meta_key']))
      ->values();

    if ($filters->isEmpty()) {
      echo '<p class="ll-ba-rmb-none">No custom taxonomies are configured as filters.</p>';
      return;
    }

    $saved = (array) (get_post_meta($post->ID, 'll_ba_related_terms', true) ?: []);
    wp_nonce_field('ll_ba_related_terms_save', 'll_ba_related_terms_nonce');
    ?>
    <p class="description" style="margin-bottom:8px">Select specific terms to use when finding related posts. Defaults to the current post's Card Display terms if left empty.</p>
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
            'value'    => 'category',
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
          'instructions'  => 'Shows on single treatment pages where this provider has been selected',
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
          'label' => 'Treatment Label',
          'name' => 'll_ba_title',
          'type' => 'text',
          'wrapper' => [
            'data-tooltip' => 'If left blank, this will default to the value entered at Before & Afters > Settings Single Page > Default Single Page Label. If nothing is entered in either field "Treatments Used" will be the default text',
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
          'instructions' => 'If more than one Detail Section is used, Details will show in tabs.',
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
              'message' => 'When not using multiple images, the image will be used in the ratio / crop it is uploaded with.',
              'wrapper' => [
                'width' => '50%',
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
              'key' => 'field_ll_ba_image_ratio',
              'label' => 'Individual Image Ratio',
              'name' => 'll_ba_image_ratio',
              'type' => 'select',
              'choices' => [
                'wide' => '16/9 (Wide)',
                'square' => '1/1 (Square)',
                'panorama' => '3/1 (Panorama)',
                'vertical' => '4/5 (Vertical)',
              ],
              'default_value' => '',
              'return_format' => 'value',
              'wrapper' => [
                'width' => '50%',
                'data-tooltip' => 'Both images will automatically crop to the selected image ratio then be placed next to one another on the post card',
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
          'label'     => 'Settings',
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
