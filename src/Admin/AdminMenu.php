<?php

namespace LiftedLogic\LLBag\Admin;

use LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType;
use LiftedLogic\LLBag\Support\Vite;

class AdminMenu {
  public function __construct(private readonly FilterSettingsPage $filterSettingsPage) {}

  public function register(): void {
    add_action('admin_menu', [$this, 'registerMenu']);
    add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    add_action('admin_post_ll_bag_save_filters', [$this, 'handleFilterSave']);
  }

  public function registerMenu(): void {
    add_submenu_page(
      'edit.php?post_type=' . BeforeAfterPostType::SLUG,
      __('Filter Settings', 'll-bag'),
      __('Filter Settings', 'll-bag'),
      'manage_options',
      'll-bag-filters',
      [$this->filterSettingsPage, 'render']
    );
  }

  public function enqueueAssets(string $hook): void {
    // make sure styles aren't messing up term pages
    if (in_array($hook, ['edit-tags.php', 'term.php'], true)) {
      return;
    }

    $screen = get_current_screen();

    if (
      $screen?->post_type !== BeforeAfterPostType::SLUG &&
      $hook !== 'll_before_after_page_ll-bag-filters'
    ) {
      return;
    }

    Vite::enqueueAdminAssets();
  }

  public function handleFilterSave(): void {
    $this->filterSettingsPage->handleSave();
  }
}
