<?php
/**
 * Admin view: Migrate from V2
 *
 * Available variables:
 *   $preview  array   V2Migrator::preview()
 *   $log      array   V2Migrator::getLog()
 *   $v3ByV2   array   V2 post ID => V3 post ID
 *   $nonce    string
 *   $action   string  AJAX action
 */

use LiftedLogic\LLBag\Migration\V2Migrator;

defined('ABSPATH') || exit;

$state     = $preview['state'];
$remaining = max(0, $preview['total'] - $preview['migrated']);
$hasRun    = $state !== null;

$levels = [
  V2Migrator::LEVEL_MANUAL => ['label' => __('Needs action', 'll-bag'), 'class' => 'is-manual'],
  V2Migrator::LEVEL_REVIEW => ['label' => __('Review', 'll-bag'),       'class' => 'is-review'],
  V2Migrator::LEVEL_INFO   => ['label' => __('Info', 'll-bag'),         'class' => 'is-info'],
];

$counts = array_fill_keys(array_keys($levels), 0);
foreach ($log as $entry) {
  $counts[$entry['level']] = ($counts[$entry['level']] ?? 0) + 1;
}

// Manual first, then review, then info; original order within each.
$order = array_flip(array_keys($levels));
$sorted = $log;
uksort($sorted, fn($a, $b) => [$order[$log[$a]['level']] ?? 9, $a] <=> [$order[$log[$b]['level']] ?? 9, $b]);

$postLink = function (int $v2Id) use ($v3ByV2): string {
  $v3Id = $v3ByV2[$v2Id] ?? 0;
  $title = get_the_title($v3Id ?: $v2Id) ?: "#{$v2Id}";
  return $v3Id
    ? '<a href="' . esc_url((string) get_edit_post_link($v3Id)) . '">' . esc_html($title) . '</a>'
    : esc_html($title);
};

$statusLabels = [
  'builtin'  => __('Built-in filter', 'll-bag'),
  'existing' => __('Existing filter', 'll-bag'),
  'new'      => __('New filter', 'll-bag'),
];
?>

<div class="wrap ll-bag-migrate"
  data-ll-bag-migrate
  data-ajax-url="<?= esc_url(admin_url('admin-ajax.php')); ?>"
  data-action="<?= esc_attr($action); ?>"
  data-nonce="<?= esc_attr($nonce); ?>"
>
  <h1><?php esc_html_e('Migrate from V2', 'll-bag'); ?></h1>

  <p class="ll-bag-migrate__intro">
    <?php esc_html_e('Copies before & after posts, their terms and the matching settings from the old LL Before After Gallery plugin (V2) into this one. V2 data is only read, never changed, so you can undo the migration and run it again.', 'll-bag'); ?>
  </p>

  <?php if ($preview['v2_active'] && $hasRun && !$remaining) : ?>
    <div class="notice notice-info inline">
      <p><?php esc_html_e('Once you\'ve checked the migrated posts, deactivate the LL Before After Gallery (V2) plugin. Old V2 links will then redirect to the new posts automatically.', 'll-bag'); ?></p>
    </div>
  <?php endif; ?>

  <div class="ll-bag-migrate__stats">
    <div class="ll-bag-migrate__stat">
      <span class="ll-bag-migrate__stat-value"><?= (int) $preview['total']; ?></span>
      <span class="ll-bag-migrate__stat-label"><?php esc_html_e('V2 posts', 'll-bag'); ?></span>
    </div>
    <div class="ll-bag-migrate__stat">
      <span class="ll-bag-migrate__stat-value" data-migrate-count><?= (int) $preview['migrated']; ?></span>
      <span class="ll-bag-migrate__stat-label"><?php esc_html_e('Migrated', 'll-bag'); ?></span>
    </div>
    <div class="ll-bag-migrate__stat">
      <span class="ll-bag-migrate__stat-value"><?= (int) $remaining; ?></span>
      <span class="ll-bag-migrate__stat-label"><?php esc_html_e('Left to migrate', 'll-bag'); ?></span>
    </div>
  </div>

  <?php if ($preview['trashed']) : ?>
    <p class="description">
      <?php printf(esc_html__('%d V2 post(s) in the trash will be skipped.', 'll-bag'), (int) $preview['trashed']); ?>
    </p>
  <?php endif; ?>

  <h2><?php esc_html_e('Taxonomies → Filters', 'll-bag'); ?></h2>
  <table class="wp-list-table widefat striped ll-bag-migrate__table">
    <thead>
      <tr>
        <th><?php esc_html_e('V2 taxonomy', 'll-bag'); ?></th>
        <th><?php esc_html_e('Terms', 'll-bag'); ?></th>
        <th><?php esc_html_e('V3 filter', 'll-bag'); ?></th>
        <th><?php esc_html_e('Status', 'll-bag'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($preview['taxonomies'])) : ?>
        <tr><td colspan="4"><?php esc_html_e('No V2 terms found.', 'll-bag'); ?></td></tr>
      <?php endif; ?>
      <?php foreach ($preview['taxonomies'] as $tax) : ?>
        <tr>
          <td>
            <strong><?= esc_html($tax['v2_label']); ?></strong>
            <p class="ll-bag-meta-key-hint"><?= esc_html($tax['v2_taxonomy']); ?></p>
          </td>
          <td>
            <?php printf(esc_html__('%1$d (%2$d in use)', 'll-bag'), (int) $tax['term_count'], (int) $tax['used_count']); ?>
          </td>
          <td>
            <strong><?= esc_html($tax['label']); ?></strong>
            <p class="ll-bag-meta-key-hint"><?= esc_html($tax['target']); ?></p>
          </td>
          <td>
            <?= $hasRun ? esc_html__('Migrated', 'll-bag') : esc_html($statusLabels[$tax['filter']] ?? ''); ?>
            <?php if ($tax['existing'] && !$hasRun) : ?>
              <br><span class="description"><?php printf(esc_html__('%d term(s) already exist and will be reused', 'll-bag'), (int) $tax['existing']); ?></span>
            <?php endif; ?>
            <?php if (!$tax['known']) : ?>
              <br><span class="description"><?php esc_html_e('Not a standard V2 taxonomy — check the result', 'll-bag'); ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <details class="ll-bag-migrate__details">
    <summary><?php esc_html_e('What else gets copied', 'll-bag'); ?></summary>
    <ul>
      <li><?php esc_html_e('Title, slug, status, date, author and order of every post (trash is skipped).', 'll-bag'); ?></li>
      <li><?php esc_html_e('Treatment Title → Single Page Label.', 'll-bag'); ?></li>
      <li><?php esc_html_e('Patient Details and Treatment Details → Detail Sections.', 'll-bag'); ?></li>
      <li><?php esc_html_e('Photos and Videos → Images. Each image gets the V3 frame shape closest to its own; images that don\'t match a frame closely are shown in full rather than cropped. Posts with no Photos and Videos use their Gallery Image instead.', 'll-bag'); ?></li>
      <li><?php esc_html_e('NSFW → Sensitive Images.', 'll-bag'); ?></li>
      <li><?php esc_html_e('Yoast SEO data, including the primary term.', 'll-bag'); ?></li>
      <li><?php esc_html_e('The most common V2 CTA becomes the global CTA if V3\'s is empty or still "#". Posts with a different CTA are listed below.', 'll-bag'); ?></li>
      <li><?php esc_html_e('V2\'s NSFW popup text and archive banner text, if V3\'s haven\'t been filled in yet.', 'll-bag'); ?></li>
    </ul>
    <p><?php esc_html_e('Anything with no V3 equivalent (image captions, the separate card image, colours) is listed in the log below for you to handle by hand.', 'll-bag'); ?></p>
  </details>

  <div class="ll-bag-migrate__actions">
    <?php if ($remaining || !$hasRun) : ?>
      <button type="button" class="button button-primary button-hero" data-migrate-run>
        <?= $hasRun ? esc_html__('Migrate remaining posts', 'll-bag') : esc_html__('Run migration', 'll-bag'); ?>
      </button>
    <?php endif; ?>

    <?php if ($hasRun) : ?>
      <a class="button" href="<?= esc_url(wp_nonce_url(admin_url('admin-post.php?action=ll_bag_v2_url_map'), \LiftedLogic\LLBag\Admin\MigrationPage::NONCE)); ?>">
        <?php esc_html_e('Download old → new URLs (CSV)', 'll-bag'); ?>
      </a>
      <button type="button" class="button ll-bag-migrate__undo" data-migrate-undo>
        <?php esc_html_e('Undo migration', 'll-bag'); ?>
      </button>
    <?php endif; ?>
  </div>

  <div class="ll-bag-migrate__progress ll-bag-hidden" data-migrate-progress>
    <progress max="<?= max(1, (int) $preview['total']); ?>" value="<?= (int) $preview['migrated']; ?>"></progress>
    <p data-migrate-status></p>
  </div>

  <?php if ($log) : ?>
    <h2><?php esc_html_e('Migration log', 'll-bag'); ?></h2>

    <?php if ($counts[V2Migrator::LEVEL_MANUAL]) : ?>
      <p><?php printf(esc_html__('%d item(s) need to be finished by hand. Everything else was migrated automatically.', 'll-bag'), (int) $counts[V2Migrator::LEVEL_MANUAL]); ?></p>
    <?php endif; ?>

    <div class="ll-bag-migrate__filters" role="group" aria-label="<?php esc_attr_e('Show log entries', 'll-bag'); ?>">
      <?php foreach ($levels as $level => $meta) : ?>
        <label class="ll-bag-migrate__filter <?= esc_attr($meta['class']); ?>">
          <input type="checkbox" value="<?= esc_attr($level); ?>" data-migrate-filter checked>
          <?= esc_html($meta['label']); ?> (<?= (int) $counts[$level]; ?>)
        </label>
      <?php endforeach; ?>
    </div>

    <table class="wp-list-table widefat striped ll-bag-migrate__table ll-bag-migrate__log">
      <thead>
        <tr>
          <th class="ll-bag-migrate__col-level"><?php esc_html_e('Type', 'll-bag'); ?></th>
          <th class="ll-bag-migrate__col-post"><?php esc_html_e('Post', 'll-bag'); ?></th>
          <th><?php esc_html_e('Details', 'll-bag'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sorted as $entry) :
          $level = $levels[$entry['level']] ?? $levels[V2Migrator::LEVEL_INFO];
          $ids   = $entry['v2_ids'] ?? [];
        ?>
          <tr data-level="<?= esc_attr($entry['level']); ?>">
            <td><span class="ll-bag-migrate__badge <?= esc_attr($level['class']); ?>"><?= esc_html($level['label']); ?></span></td>
            <td>
              <?php if (count($ids) === 1) : ?>
                <?= $postLink($ids[0]); ?>
              <?php elseif (count($ids) > 1) : ?>
                <details>
                  <summary><?php printf(esc_html__('%d posts', 'll-bag'), count($ids)); ?></summary>
                  <ul class="ll-bag-migrate__post-list">
                    <?php foreach ($ids as $id) : ?>
                      <li><?= $postLink($id); ?></li>
                    <?php endforeach; ?>
                  </ul>
                </details>
              <?php elseif (!empty($entry['v3_id'])) : ?>
                <a href="<?= esc_url((string) get_edit_post_link((int) $entry['v3_id'])); ?>"><?= esc_html(get_the_title((int) $entry['v3_id'])); ?></a>
              <?php else : ?>
                <span class="description"><?php esc_html_e('Site-wide', 'll-bag'); ?></span>
              <?php endif; ?>
            </td>
            <td><?= esc_html($entry['message']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
