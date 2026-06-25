<?php

namespace LiftedLogic\LLBag\Admin;

use LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType;
use LiftedLogic\LLBag\Support\Vite;

class AdminMenu {
  public function __construct(
    private readonly FilterSettingsPage $filterSettingsPage,
    private readonly HowToPage $howToPage,
  ) {}

  public function register(): void {
    add_action('admin_menu', [$this, 'registerMenu']);
    add_action('admin_menu', [$this, 'registerHowToMenu'], 99);
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

  public function registerHowToMenu(): void {
    add_submenu_page(
      'edit.php?post_type=' . BeforeAfterPostType::SLUG,
      __('How To', 'll-bag'),
      __('How To', 'll-bag'),
      'manage_options',
      'll-bag-how-to',
      [$this->howToPage, 'render']
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
      $hook !== 'll_before_after_page_ll-bag-filters' &&
      $hook !== 'll_before_after_page_ll-bag-how-to'
    ) {
      return;
    }

    Vite::enqueueAdminAssets();
  }

  public function handleFilterSave(): void {
    $this->filterSettingsPage->handleSave();
  }
}
