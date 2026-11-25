<?php

namespace LiftedLogic\LLBag\Support;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Delivers updates through the normal WordPress plugin update UI, using the
 * release metadata .github/workflows/release.yml publishes to DigitalOcean Spaces.
 */
class Updater {

  /** Written by .github/workflows/release.yml on every release. Must equal SPACES_PUBLIC_URL + /before-after-gallery/info.json. */
  public const METADATA_URL = 'https://lifted-logic-documents.nyc3.digitaloceanspaces.com/before-after-gallery/info.json';

  public const SLUG = 'before-after-gallery';

  public function register(): void {
    // A git checkout is a dev copy (often symlinked into a site). Updating it
    // would make WordPress delete the folder, i.e. the working source.
    if (is_dir(LL_BAG_PATH . '.git')) {
      return;
    }

    if (!class_exists(PucFactory::class)) {
      return;
    }

    PucFactory::buildUpdateChecker(self::METADATA_URL, LL_BAG_FILE, self::SLUG);
  }
}
