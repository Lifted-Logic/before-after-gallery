/**
 * @param {HTMLElement|null} el
 * @param {number}           totalPages
 * @param {number}           page
 * @param {(n: number) => void} onPageChange
 */
export function renderPagination(el, totalPages, page, onPageChange) {
  if (!el) return;

  if (totalPages <= 1) {
    el.innerHTML = '';
    return;
  }

  const items = [];

  if (page > 1) {
    items.push(`<a class="ll-ba-prev-link ll-ba-pagination-button" data-page="${page - 1}" href="#"><svg class='icon icon-arrow-left' aria-hidden='true'><use xlink:href='#icon-arrow-left'></use></svg></a>`);
  }

  const start = Math.max(1, page - 2);
  const end   = Math.min(totalPages, page + 2);

  if (start > 1) {
    items.push(`<a class="ll-ba-page-link" data-page="1" href="#">1</a>`);
    if (start > 2) items.push(`<span class="ll-ba-page-ellipsis">&hellip;</span>`);
  }

  for (let i = start; i <= end; i++) {
    if (i === page) {
      items.push(`<span class="ll-ba-page-link ll-ba-page-current">${i}</span>`);
    } else {
      items.push(`<a class="ll-ba-page-link" data-page="${i}" href="#">${i}</a>`);
    }
  }

  if (end < totalPages) {
    if (end < totalPages - 1) items.push(`<span class="ll-ba-page-ellipsis">&hellip;</span>`);
    items.push(`<a class="ll-ba-page-link" data-page="${totalPages}" href="#">${totalPages}</a>`);
  }

  if (page < totalPages) {
    items.push(`<a class="ll-ba-next-link ll-ba-pagination-button" data-page="${page + 1}" href="#"><svg class='icon icon-arrow-right' aria-hidden='true'><use xlink:href='#icon-arrow-right'></use></svg></a>`);
  }

  el.innerHTML = `<nav class="ll-ba-pagination">${items.join('')}</nav>`;

  el.querySelectorAll('[data-page]').forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      onPageChange(parseInt(link.dataset.page, 10));
    });
  });
}

/**
 * Render initial pagination from data attributes on the container element.
 * Returns the current page read from the element (so filters.js can sync its state).
 *
 * @param {HTMLElement|null} el
 * @param {(n: number) => void} onPageChange
 * @returns {number}
 */
export function initPagination(el, onPageChange) {
  const totalPages  = parseInt(el?.dataset.totalPages  ?? '1', 10);
  const currentPage = parseInt(el?.dataset.currentPage ?? '1', 10);
  renderPagination(el, totalPages, currentPage, onPageChange);
  return currentPage;
}
