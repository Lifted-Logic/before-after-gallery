<?php

namespace LiftedLogic\LLBag;

use Illuminate\Container\Container;
use LiftedLogic\LLBag\Admin\AdminMenu;
use LiftedLogic\LLBag\Admin\PostOrderController;
use LiftedLogic\LLBag\Admin\PostListFilters;
use LiftedLogic\LLBag\Admin\PostListView;
use LiftedLogic\LLBag\Admin\FilterSettingsPage;
use LiftedLogic\LLBag\Admin\HowToPage;
use LiftedLogic\LLBag\Admin\SettingsPage;
use LiftedLogic\LLBag\Filters\FilterManager;
use LiftedLogic\LLBag\Frontend\AjaxHandler;
use LiftedLogic\LLBag\Frontend\TemplateLoader;
use LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType;
use LiftedLogic\LLBag\Hooks\Hooks;
use LiftedLogic\LLBag\BeforeAfterPostType\Fields;
use LiftedLogic\LLBag\BeforeAfterPostType\TaxonomyRegistrar;
use LiftedLogic\LLBag\Integration\ThemeComponentInjector;
use LiftedLogic\LLBag\Integration\YoastListCleanup;
use LiftedLogic\LLBag\Support\RelatedPosts;
use LiftedLogic\LLBag\Support\Updater;
use LiftedLogic\LLBag\Support\Upgrader;

class Plugin {
  private Container $container;

  public function __construct() {
    $this->container = new Container();
    $this->registerBindings();
  }

  private function registerBindings(): void {
    $this->container->singleton(Hooks::class);
    $this->container->singleton(FilterManager::class);
    $this->container->singleton(BeforeAfterPostType::class);
    $this->container->singleton(Fields::class);
    $this->container->singleton(TaxonomyRegistrar::class);
    $this->container->singleton(RelatedPosts::class);
    $this->container->singleton(SettingsPage::class, function () {
      return new SettingsPage($this->container->make(FilterManager::class));
    });
    $this->container->singleton(TemplateLoader::class);
    $this->container->singleton(AjaxHandler::class);
    $this->container->singleton(ThemeComponentInjector::class);

    $this->container->singleton(FilterSettingsPage::class, function () {
      return new FilterSettingsPage($this->container->make(FilterManager::class));
    });

    $this->container->singleton(HowToPage::class);
    $this->container->singleton(PostOrderController::class);
    $this->container->singleton(PostListView::class);
    $this->container->singleton(PostListFilters::class);
    $this->container->singleton(YoastListCleanup::class);
    $this->container->singleton(Updater::class);

    $this->container->singleton(AdminMenu::class, function () {
      return new AdminMenu(
        $this->container->make(FilterSettingsPage::class),
        $this->container->make(HowToPage::class),
      );
    });
  }

  public function boot(): void {
    // Settings steps for updated sites first, before anything reads those settings.
    (new Upgrader())->run();

    $this->container->make(FilterManager::class)->migrateBuiltinCategoryTaxonomy();

    $this->container->make(Hooks::class)->register();
    $this->container->make(BeforeAfterPostType::class)->register();
    $this->container->make(TaxonomyRegistrar::class)->register();
    $this->container->make(Fields::class)->register();
    // Outside is_admin(): saves also happen over REST and cron, and all of them must invalidate.
    $this->container->make(RelatedPosts::class)->register();
    $this->container->make(SettingsPage::class)->register();
    $this->container->make(TemplateLoader::class)->register();
    $this->container->make(AjaxHandler::class)->register();
    $this->container->make(ThemeComponentInjector::class)->register();
    $this->container->make(Updater::class)->register();

    if (is_admin()) {
      $this->container->make(AdminMenu::class)->register();
      $this->container->make(PostOrderController::class)->register();
      $this->container->make(PostListView::class)->register();
      $this->container->make(PostListFilters::class)->register();
      $this->container->make(YoastListCleanup::class)->register();
    }
  }
}
