<?php

namespace LiftedLogic\LLBag\Migration;

/**
 * After a V2 migration, sends old V2 URLs that now 404 (once the V2 plugin is deactivated)
 * to their V3 equivalents with a 301:
 *
 *   /{v2-slug}/                      → V3 archive
 *   /{v2-slug}/{post}/               → migrated V3 post
 *   /treatment-category/{term}/ etc. → V3 archive filtered to that term
 *
 * Does nothing unless a migration has run, and only looks at requests that would otherwise 404.
 */
class V2Redirects {
  public function __construct(private readonly V2Migrator $migrator) {}

  public function register(): void {
    add_action('template_redirect', [$this, 'maybeRedirect'], 1);
  }

  public function maybeRedirect(): void {
    if (!is_404()) {
      return;
    }

    $state = $this->migrator->getState();
    if (!$state) {
      return;
    }

    $path     = trim((string) wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
    $homePath = trim((string) wp_parse_url(home_url(), PHP_URL_PATH), '/');
    if ($homePath !== '' && str_starts_with($path, $homePath . '/')) {
      $path = substr($path, strlen($homePath) + 1);
    }

    $path = urldecode($path);
    if ($path === '') {
      return;
    }

    // Only build the (comparatively heavy) URL map for paths that could be old V2 URLs.
    $prefixes = array_filter(array_merge(
      [$state['v2_base'] ?? ''],
      array_column($state['taxonomies'] ?? [], 'rewrite')
    ));
    if (!in_array(strtok($path, '/'), $prefixes, true)) {
      return;
    }

    $map = $this->migrator->urlMap();
    $key = home_url('/' . $path . '/');

    if (isset($map[$key]) && $map[$key] !== $key) {
      wp_safe_redirect($map[$key], 301, 'LL Before & After');
      exit;
    }
  }
}
