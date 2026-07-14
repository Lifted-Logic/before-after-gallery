<?php

namespace LiftedLogic\LLBag\Support;

class Vite {
  private static string $hotFile  = LL_BAG_PATH . 'hot';
  private static string $buildDir = LL_BAG_PATH . 'public/build';
  private static string $buildUrl = LL_BAG_URL  . 'public/build';

  /** @var array<string, mixed>|null */
  private static ?array $manifest = null;

  /**
   * Enqueue admin JS and CSS. Call from admin_enqueue_scripts.
   */
  public static function enqueueAdminAssets(): void {
    if (self::isHot()) {
      self::enqueueHotEntry('resources/js/admin.js', 'll-bag-admin');
    } else {
      self::enqueueBuiltEntry('resources/js/admin.js', 'll-bag-admin');
    }
  }

  /**
   * Enqueue frontend JS and CSS. Call from wp_enqueue_scripts.
   */
  public static function enqueueFrontendAssets(): void {
    if (self::isHot()) {
      self::enqueueHotEntry('resources/js/frontend.js', 'll-bag-frontend');
    } else {
      self::enqueueBuiltEntry('resources/js/frontend.js', 'll-bag-frontend');
    }
  }

  private static function enqueueHotEntry(string $entry, string $handle): void {
    // No baseline built CSS here on purpose: a static <link> to public/build/assets
    // can outrank Vite's live-injected styles in the cascade for rules that changed
    // since the last build, silently showing stale CSS. Styles come from Vite's
    // live module graph only while the dev server is running.
    $base = rtrim((string) file_get_contents(self::$hotFile), "\n");

    wp_enqueue_script($handle . '-vite', $base . '/@vite/client', [], null, false);
    wp_enqueue_script($handle, $base . '/' . $entry, [$handle . '-vite', 'jquery'], null, true);

    add_filter('script_loader_tag', static function (string $tag, string $tagHandle) use ($handle): string {
      if ($tagHandle === $handle . '-vite' || $tagHandle === $handle) {
        $tag = str_replace('<script ', '<script type="module" ', $tag);
      }
      return $tag;
    }, 10, 2);
  }

  private static function enqueueBuiltEntry(string $entry, string $handle): void {
    $data = self::manifestEntry($entry);

    if (!empty($data['css'])) {
      foreach ($data['css'] as $i => $cssFile) {
        wp_enqueue_style(
          $handle . '-css-' . $i,
          self::$buildUrl . '/' . $cssFile,
          [],
          LL_BAG_VERSION
        );
      }
    }

    wp_enqueue_script(
      $handle,
      self::$buildUrl . '/' . $data['file'],
      ['jquery'],
      LL_BAG_VERSION,
      true
    );
  }

  private static function isHot(): bool {
    return file_exists(self::$hotFile);
  }

  /**
   * @return array<string, mixed>
   */
  private static function manifestEntry(string $entry): array {
    $manifest = self::getManifest();

    if (!isset($manifest[$entry])) {
      throw new \RuntimeException(
        "Vite manifest entry not found: \"{$entry}\". Run `npm run build`."
      );
    }

    return $manifest[$entry];
  }

  /**
   * @return array<string, mixed>
   */
  private static function getManifest(): array {
    if (self::$manifest !== null) {
      return self::$manifest;
    }

    $path = self::$buildDir . '/.vite/manifest.json';

    if (!file_exists($path)) {
      throw new \RuntimeException(
        'Vite manifest not found. Run `npm run build` or `npm run dev`.'
      );
    }

    self::$manifest = (array) json_decode((string) file_get_contents($path), true);

    return self::$manifest;
  }
}
