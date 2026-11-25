<?php

namespace LiftedLogic\LLBag\Support;

use LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType;

/**
 * One-time settings steps that run on the first request after an update, so an
 * update never changes how an existing site behaves. Each step only fills in
 * values a site never saved; it never overwrites a saved choice.
 *
 * Runs from Plugin::boot(), before `init`, so rewrite rules registered on `init`
 * already see the result. BeforeAfterPostType flushes rewrite rules on the same
 * request because LL_BAG_VERSION changed.
 */
class Upgrader {

  public const OPTION = 'll_bag_settings_version';

  public function run(): void {
    $from = (string) get_option(self::OPTION, '0');
    if (version_compare($from, LL_BAG_VERSION, '>=')) return;

    if (version_compare($from, '3.4.0', '<')) {
      $this->pinArchiveMode();
    }

    update_option(self::OPTION, LL_BAG_VERSION);
  }

  /**
   * Before 3.3.0 the gallery URL always showed the posts archive; the old "Use
   * Taxonomy Archive?" toggle (default on) only added an extra categories page.
   * 3.3.0 read that toggle as "Categories" and switched existing sites' gallery
   * to the categories page on update. Sites that never saved Main Gallery View
   * get "Posts archive" saved explicitly, so no later default can flip them.
   */
  private function pinArchiveMode(): void {
    if (get_option('options_ll_bag_archive_mode') !== false) return;

    update_option('options_ll_bag_archive_mode', BeforeAfterPostType::MODE_POSTS);
    // ACF's field reference, so get_field() and the settings screen read the saved value.
    update_option('_options_ll_bag_archive_mode', 'field_ll_bag_archive_mode', false);
  }
}
