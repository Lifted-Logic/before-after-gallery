<?php

namespace LiftedLogic\LLBag\Admin;

use LiftedLogic\LLBag\BeforeAfterPostType\BeforeAfterPostType;

class PostOrderController {
  public const ACTION = 'll_bag_post_order';

  public function register(): void {
    add_action('pre_get_posts', [$this, 'orderAdminList']);
    add_action('admin_enqueue_scripts', [$this, 'localizeConfig'], 20);
    add_action('wp_ajax_' . self::ACTION, [$this, 'handleSave']);
    add_filter('manage_' . BeforeAfterPostType::SLUG . '_posts_columns', [$this, 'addHandleColumn']);
    add_action('manage_' . BeforeAfterPostType::SLUG . '_posts_custom_column', [$this, 'renderHandleColumn'], 10, 2);
  }

  /**
   * @param array<string, string> $columns
   * @return array<string, string>
   */
  public function addHandleColumn(array $columns): array {
    $ordered = [];
    foreach ($columns as $key => $label) {
      $ordered[$key] = $label;
      if ($key === 'cb') {
        $ordered['ll_bag_order_handle'] = '';
      }
    }
    return $ordered;
  }

  public function renderHandleColumn(string $column, int $postId): void {
    if ($column !== 'll_bag_order_handle') return;
    echo '<span class="ll-bag-drag-handle" aria-hidden="true" title="Drag to reorder">'
      . '<svg viewBox="0 0 10 16" width="10" height="16" fill="currentColor">'
      . '<circle cx="2" cy="2" r="1.5"/><circle cx="8" cy="2" r="1.5"/>'
      . '<circle cx="2" cy="8" r="1.5"/><circle cx="8" cy="8" r="1.5"/>'
      . '<circle cx="2" cy="14" r="1.5"/><circle cx="8" cy="14" r="1.5"/>'
      . '</svg></span>';
  }

  public function orderAdminList(\WP_Query $query): void {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== BeforeAfterPostType::SLUG) return;
    if (!empty($_GET['orderby'])) return;

    $query->set('orderby', ['menu_order' => 'ASC', 'date' => 'DESC']);
  }

  public function localizeConfig(string $hook): void {
    if ($hook !== 'edit.php') return;
    if ((get_current_screen()?->post_type ?? '') !== BeforeAfterPostType::SLUG) return;

    // Saving offset + index renumbers only the visible posts, so reordering is
    // off whenever the list is a subset: search, sort, month, a taxonomy filter
    // a status tab other than All or the Mine view. (empty() treats the dropdown's "All" 0 as no filter.)
    $taxonomyFiltered = false;
    foreach (get_object_taxonomies(BeforeAfterPostType::SLUG, 'objects') as $tax) {
      if ($tax->query_var && !empty($_GET[$tax->query_var])) {
        $taxonomyFiltered = true;
        break;
      }
    }

    $rawStatus      = $_GET['post_status'] ?? '';
    $status         = is_string($rawStatus) ? sanitize_key(wp_unslash($rawStatus)) : '';
    $statusFiltered = $status !== '' && $status !== 'all';

    $enabled = empty($_GET['s']) && empty($_GET['orderby']) && empty($_GET['m']) && empty($_GET['author'])
      && !$taxonomyFiltered && !$statusFiltered;

    $paged   = max(1, (int) ($_GET['paged'] ?? 1));
    $perPage = (int) (get_user_option('edit_' . BeforeAfterPostType::SLUG . '_per_page') ?: 20);

    wp_localize_script('ll-bag-admin', 'llBagOrder', [
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'action'  => self::ACTION,
      'nonce'   => wp_create_nonce(self::ACTION),
      'enabled' => $enabled,
      'offset'  => ($paged - 1) * $perPage,
    ]);
  }

  public function handleSave(): void {
    check_ajax_referer(self::ACTION, 'nonce');

    if (!current_user_can('edit_others_posts')) {
      wp_send_json_error('insufficient permissions', 403);
    }

    $ids    = array_map('intval', (array) ($_POST['ids'] ?? []));
    $offset = max(0, (int) ($_POST['offset'] ?? 0));

    foreach ($ids as $i => $id) {
      if (get_post_type($id) !== BeforeAfterPostType::SLUG) continue;
      wp_update_post(['ID' => $id, 'menu_order' => $offset + $i]);
    }

    // Gallery order feeds related-post tie-breaks; see Support\RelatedPosts.
    do_action('ll_bag/post_order_saved');

    wp_send_json_success(['count' => count($ids)]);
  }
}
