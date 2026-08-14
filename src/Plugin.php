<?php

namespace LiftedLogic\LLBag;

use Illuminate\Container\Container;
use LiftedLogic\LLBag\Admin\AdminMenu;
use LiftedLogic\LLBag\Admin\PostOrderController;
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

    $this->container->singleton(AdminMenu::class, function () {
      return new AdminMenu(
        $this->container->make(FilterSettingsPage::class),
        $this->container->make(HowToPage::class),
      );
    });
  }

  public function boot(): void {
    $this->container->make(Hooks::class)->register();
    $this->container->make(BeforeAfterPostType::class)->register();
    $this->container->make(TaxonomyRegistrar::class)->register();
    $this->container->make(Fields::class)->register();
    $this->container->make(SettingsPage::class)->register();
    $this->container->make(TemplateLoader::class)->register();
    $this->container->make(AjaxHandler::class)->register();
    $this->container->make(ThemeComponentInjector::class)->register();

    if (is_admin()) {
      $this->container->make(AdminMenu::class)->register();
      $this->container->make(PostOrderController::class)->register();
    }
  }
}
