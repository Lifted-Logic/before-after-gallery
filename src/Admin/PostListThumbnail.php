<?php

namespace LiftedLogic\LLBag\Admin;

/**
 * Grid view tile image for the B&A posts list. Always the first entry of the
 * Images repeater, chosen by that entry's type. Raw meta only (no get_field()).
 */
class PostListThumbnail {
  /** @var array<int, array{id: int, focal: string, video: bool}> */
  private static array $cache = [];

  /** @return array{id: int, focal: string, video: bool} */
  public static function resolve(int $postId): array {
    if (isset(self::$cache[$postId])) {
      return self::$cache[$postId];
    }

    $thumb = self::candidate($postId);

    // Cache-only once PostListView has primed the attachments (see primeCaches()).
    if ($thumb['id'] && !wp_attachment_is_image($thumb['id'])) {
      $thumb = ['id' => 0, 'focal' => '', 'video' => false];
    }

    return self::$cache[$postId] = $thumb;
  }

  /**
   * Raw-meta pick of the tile image, before checking that the attachment is a real image.
   * Touches only post meta, so it is safe to call before the attachment caches are primed.
   *
   * @return array{id: int, focal: string, video: bool}
   */
  public static function candidate(int $postId): array {
    return self::compute($postId);
  }

  public static function render(int $postId): string {
    $thumb   = self::resolve($postId);
    $canEdit = current_user_can('edit_post', $postId);

    if (!$thumb['id']) {
      return '<span class="ll-bag-thumb ll-bag-thumb--empty" aria-hidden="true"></span>';
    }

    $image = wp_get_attachment_image($thumb['id'], 'medium', false, [
      'alt'     => '',
      'loading' => 'lazy',
      'sizes'   => '(max-width: 782px) 50vw, 240px',
      'style'   => 'object-position:' . self::objectPosition($thumb['focal']),
    ]);

    if ($image === '') {
      return '<span class="ll-bag-thumb ll-bag-thumb--empty" aria-hidden="true"></span>';
    }

    $class = 'll-bag-thumb' . ($thumb['video'] ? ' ll-bag-thumb--video' : '');
    $play  = $thumb['video']
      ? '<span class="ll-bag-thumb__play dashicons dashicons-controls-play" aria-hidden="true"></span>'
      : '';

    if ($canEdit) {
      $href = get_edit_post_link($postId, 'raw');
      if ($href) {
        return '<a class="' . esc_attr($class) . '" href="' . esc_url($href) . '" tabindex="-1" aria-hidden="true">'
          . $image . $play . '</a>';
      }
    }

    return '<span class="' . esc_attr($class) . '">' . $image . $play . '</span>';
  }

  /** @return array{id: int, focal: string, video: bool} */
  private static function compute(int $postId): array {
    $none = ['id' => 0, 'focal' => '', 'video' => false];

    if ((int) get_post_meta($postId, 'll_ba_images', true) < 1) {
      return $none;
    }

    $p      = 'll_ba_images_0_';
    $option = (string) get_post_meta($postId, $p . 'll_ba_image_options', true);

    [$imageKey, $focal, $video] = match ($option) {
      'one-image'  => [
        'll_ba_single_image',
        get_post_meta($postId, $p . 'll_ba_single_fill', true)
          ? (string) get_post_meta($postId, $p . 'll_ba_single_focal', true) : '',
        false,
      ],
      'two-images' => ['ll_ba_before_image', (string) get_post_meta($postId, $p . 'll_ba_before_focal', true), false],
      'video'      => ['ll_ba_single_image', '', true],
      default      => [null, '', false],
    };

    if (!$imageKey) {
      return $none;
    }

    $id = (int) get_post_meta($postId, $p . $imageKey, true);
    if (!$id) {
      return $none;
    }

    return ['id' => $id, 'focal' => $focal, 'video' => $video];
  }

  /** "X Y Z" or legacy "X Y": first two numbers, clamped 0-100. Invalid gives centre. */
  private static function objectPosition(string $focal): string {
    $parts = preg_split('/\s+/', trim($focal), -1, PREG_SPLIT_NO_EMPTY) ?: [];

    // Either number missing or non-numeric: centre the whole value.
    if (!isset($parts[1]) || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
      return '50% 50%';
    }

    $x = max(0.0, min(100.0, (float) $parts[0]));
    $y = max(0.0, min(100.0, (float) $parts[1]));

    return round($x, 2) . '% ' . round($y, 2) . '%';
  }
}
