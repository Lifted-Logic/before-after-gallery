<?php
/**
 * Partial: Categories hero navigation pills
 *
 * One pill per Hero Navigation row (Settings → Taxonomy Archive Settings), plus an
 * optional leading "All" pill that returns to the unscoped categories page.
 *
 * Available variables:
 *   $items  array<int, array{label: string, url: string, active: bool}>
 *           (filterable via lifted_logic/bag/categories_nav_items)
 *
 * Override: place this file at {theme}/ll-before-after/partials/categories-nav.php
 */

defined('ABSPATH') || exit;

if ( empty( $items ) ) return;
?>

<nav class="ll-ba-categories-nav" aria-label="Browse before &amp; afters">
  <ul class="ll-ba-categories-nav__list">
    <?php foreach ( $items as $item ) : ?>
      <li class="ll-ba-categories-nav__item">
        <a
          class="ll-ba-categories-nav__link<?= $item['active'] ? ' ll-ba-categories-nav__link--active' : '' ?>"
          href="<?= esc_url( $item['url'] ) ?>"
          <?= $item['active'] ? 'aria-current="page"' : '' ?>
        >
          <?php if ( $item['active'] ) : ?>
            <svg class='icon icon-check-mark' aria-hidden='true'><use xlink:href='#icon-check-mark'></use></svg>
          <?php endif; ?>
          <span><?= esc_html( $item['label'] ) ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</nav>
