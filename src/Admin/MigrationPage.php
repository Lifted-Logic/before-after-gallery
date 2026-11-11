<?php

namespace LiftedLogic\LLBag\Admin;

use LiftedLogic\LLBag\Migration\V2Migrator;

/**
 * Before & Afters → Migrate from V2. Only shown when V2 posts exist in the database
 * or a migration has already been run.
 */
class MigrationPage {
  public const SLUG   = 'll-bag-migrate';
  public const ACTION = 'll_bag_v2_migrate';
  public const NONCE  = 'll_bag_v2_migrate';

  public function __construct(private readonly V2Migrator $migrator) {}

  public function register(): void {
    add_action('wp_ajax_' . self::ACTION, [$this, 'handleAjax']);
    add_action('admin_post_ll_bag_v2_url_map', [$this, 'downloadUrlMap']);
  }

  public function shouldShow(): bool {
    return V2Migrator::hasV2Data() || $this->migrator->getState() !== null;
  }

  public function render(): void {
    $preview = $this->migrator->preview();
    $log     = $this->migrator->getLog();
    $v3ByV2  = $this->migrator->migratedIdMap();
    $nonce   = wp_create_nonce(self::NONCE);
    $action  = self::ACTION;
    require LL_BAG_PATH . 'views/admin/migration.php';
  }

  public function handleAjax(): void {
    check_ajax_referer(self::NONCE, 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error(['message' => __('You do not have permission to do this.', 'll-bag')], 403);
    }

    if (function_exists('set_time_limit')) {
      @set_time_limit(120);
    }

    $step = sanitize_key($_POST['step'] ?? '');

    try {
      switch ($step) {
        case 'setup':
          $this->migrator->setup();
          wp_send_json_success([
            'total'     => $this->migrator->countV2Posts(),
            'migrated'  => $this->migrator->countMigratedPosts(),
          ]);

        case 'posts':
          $result = $this->migrator->migrateBatch();
          wp_send_json_success($result + ['migrated' => $this->migrator->countMigratedPosts()]);

        case 'finish':
          $this->migrator->finish();
          wp_send_json_success();

        case 'rollback':
          wp_send_json_success($this->migrator->rollbackBatch());
      }
    } catch (\Throwable $e) {
      wp_send_json_error(['message' => $e->getMessage()], 500);
    }

    wp_send_json_error(['message' => 'Unknown step.'], 400);
  }

  public function downloadUrlMap(): void {
    check_admin_referer(self::NONCE);

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to do this.', 'll-bag'));
    }

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="before-after-v2-redirects.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Old URL', 'New URL']);
    foreach ($this->migrator->urlMap() as $old => $new) {
      fputcsv($out, [$old, $new]);
    }
    fclose($out);
    exit;
  }
}
