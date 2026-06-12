import { renderPagination, initPagination } from './pagination.js';
import { getSensitiveMode, setSensitiveMode, applySensitiveMode, updateSensitiveBar } from './sensitive.js';

export function initFilters() {
  const filtersEl      = document.getElementById('ll-ba-filters');
  const filterGroupsEl = document.getElementById('ll-ba-filter-groups');
  const grid           = document.getElementById('ll-ba-grid');
  const paginationEl   = document.getElementById('ll-ba-pagination');

  if (!filtersEl || !grid) return;

  let currentPage          = 1;
  let currentSensitiveMode = getSensitiveMode();

  // ── Accordion toggles ────────────────────────────────────────────────────────

  filtersEl.addEventListener('click', (e) => {
    const toggle = e.target.closest('.ll-ba-filter-toggle');
    if (!toggle) return;

    const group   = toggle.closest('.ll-ba-filter-group');
    const content = group?.querySelector('.ll-ba-filter-content');
    const arrow   = toggle.querySelector('.ll-ba-filter-arrow');
    if (!content) return;

    const isOpen = !content.classList.contains('ll-ba-hidden');

    // Close all other open groups before opening this one
    if ( !isOpen ) {
      filtersEl.querySelectorAll( '.ll-ba-filter-content' ).forEach( other => {
        if ( other === content || other.classList.contains( 'll-ba-hidden' ) ) return;
        other.classList.add( 'll-ba-hidden' );
        const otherGroup  = other.closest( '.ll-ba-filter-group' );
        const otherToggle = otherGroup?.querySelector( '.ll-ba-filter-toggle' );
        otherToggle?.setAttribute( 'aria-expanded', 'false' );
        otherToggle?.querySelector( '.ll-ba-filter-arrow' )?.classList.remove( 'rotate-180' );
      } );
    }

    content.classList.toggle('ll-ba-hidden', isOpen);
    toggle.setAttribute('aria-expanded', String(!isOpen));
    arrow?.classList.toggle('rotate-180', !isOpen);
  });

  // ── Option search (client-side, filters visible checkboxes only) ─────────────

  filtersEl.addEventListener('input', (e) => {
    const search = e.target.closest('.ll-ba-option-search');
    if (!search) return;

    const term = search.value.toLowerCase();
    const list = search.closest('.ll-ba-filter-content')?.querySelector('.ll-ba-checkbox-list');
    if (!list) return;

    list.querySelectorAll('.ll-ba-checkbox-option').forEach(opt => {
      const text = opt.textContent.toLowerCase();
      opt.style.display = text.includes(term) ? '' : 'none';
    });
  });

  // ── Filter change events ─────────────────────────────────────────────────────

  filtersEl.addEventListener('change', (e) => {
    if (
      e.target.matches('.ll-ba-checkbox-filter') ||
      e.target.matches('.ll-ba-dropdown-filter')
    ) {
      currentPage = 1;
      applyFilters();
    }
  });

  // ── Active tag removal ───────────────────────────────────────────────────────

  document.getElementById('ll-ba-active-tags')?.addEventListener('click', (e) => {
    const btn = e.target.closest('.ll-ba-tag-remove');
    if (!btn) return;

    const { metaKey, value } = btn.dataset;
    const group = filterGroupsEl.querySelector(`[data-meta-key="${metaKey}"]`);
    if (!group) return;

    const checkbox = [...group.querySelectorAll('.ll-ba-checkbox-filter')]
      .find(el => el.value === value);
    if (checkbox) checkbox.checked = false;

    currentPage = 1;
    applyFilters();
  });

  // ── Sensitive images mode ────────────────────────────────────────────────────

  document.getElementById('ll-ba-sensitive-bar')?.addEventListener('click', (e) => {
    const btn = e.target.closest('.ll-ba-sensitive-btn');
    if (!btn) return;

    currentSensitiveMode = btn.dataset.mode;
    setSensitiveMode( currentSensitiveMode );
    applySensitiveMode( grid, currentSensitiveMode );

    document.querySelectorAll('.ll-ba-sensitive-btn').forEach(b =>
      b.classList.toggle('is-active', b === btn)
    );
  });

  // ── Clear all ────────────────────────────────────────────────────────────────

  document.getElementById('ll-ba-clear-all')?.addEventListener('click', () => {
    filtersEl.querySelectorAll('.ll-ba-checkbox-filter').forEach(el => { el.checked = false; });
    filtersEl.querySelectorAll('.ll-ba-dropdown-filter').forEach(el => { el.value = ''; });
    currentPage = 1;
    applyFilters();
  });

  // ── Core ─────────────────────────────────────────────────────────────────────

  function collectFilters() {
    const active = {};

    filtersEl.querySelectorAll('.ll-ba-filter-group').forEach(group => {
      const key = group.dataset.metaKey;

      const checked = [...group.querySelectorAll('.ll-ba-checkbox-filter:checked')]
        .map(el => el.value);
      if (checked.length) active[key] = checked;
    });

    return active;
  }

  async function applyFilters(page = currentPage) {
    currentPage = page;

    const active   = collectFilters();
    const formData = new FormData();

    formData.append('action', llBag.action);
    formData.append('nonce',  llBag.nonce);
    formData.append('paged',  page);

    for (const [key, value] of Object.entries(active)) {
      if (Array.isArray(value)) {
        value.forEach(v => formData.append(`filters[${key}][]`, v));
      } else {
        formData.append(`filters[${key}]`, value);
      }
    }

    grid.classList.add('is-filtering');

    try {
      const res  = await fetch(llBag.ajaxUrl, { method: 'POST', body: formData });
      const data = await res.json();

      if (data.success) {
        grid.innerHTML = data.data.html || `<p class="ll-ba-no-posts">${llBag.noResults ?? 'No results found.'}</p>`;
        renderPagination(paginationEl, data.data.total_pages, data.data.current_page, applyFilters);
        updateSensitiveBar( document.getElementById( 'll-ba-sensitive-bar' ), grid );
        applySensitiveMode( grid, currentSensitiveMode );
      }
    } finally {
      grid.classList.remove('is-filtering');
    }

    updateActiveTags(active);
    updateUrl(active);
  }

  function updateActiveTags(active) {
    const bar  = document.getElementById('ll-ba-active-bar');
    const tags = document.getElementById('ll-ba-active-tags');
    if (!bar || !tags) return;

    tags.innerHTML = '';

    for (const [key, value] of Object.entries(active)) {
      const group  = filtersEl.querySelector(`[data-meta-key="${key}"]`);
      const label  = group?.dataset.label ?? key;
      const values = Array.isArray(value) ? value : [value];

      values.forEach(v => {
        const checkbox    = group?.querySelector(`.ll-ba-checkbox-filter[value="${v}"]`);
        const displayName = checkbox?.dataset.termName ?? v;

        const tag = document.createElement('li');
        tag.className = 'll-ba-tag';
        tag.innerHTML = `<button type="button" class="ll-ba-tag-remove" data-meta-key="${escAttr(key)}" data-value="${escAttr(v)}" aria-label="Remove filter">${escHtml(displayName)}<svg class='icon icon-exit' aria-hidden='true'><use xlink:href='#icon-exit'></use></svg></button>`;
        tags.appendChild(tag);
      });
    }

    bar.classList.toggle('ll-ba-hidden', tags.children.length === 0);
  }

  function updateUrl(active) {
    const params = new URLSearchParams();

    for (const [key, value] of Object.entries(active)) {
      if (Array.isArray(value)) {
        value.forEach(v => params.append(key, v));
      } else {
        params.set(key, value);
      }
    }

    if (currentPage > 1) {
      params.set('paged', currentPage);
    }

    // Strip WP permalink pagination (/page/N/) so it doesn't conflict with JS state
    const basePath = location.pathname.replace(/\/page\/\d+\/?$/, '/');
    const search   = params.toString();
    history.pushState({}, '', search ? `${basePath}?${search}` : basePath);
  }

  function restoreFromUrl() {
    const params = new URLSearchParams(location.search);

    const pagedParam = params.get('paged');
    if (pagedParam) {
      currentPage = Math.max(1, parseInt(pagedParam, 10));
    }

    params.forEach((value, key) => {
      if (key === 'paged' || key === 'ba_ref') return;

      const group = filtersEl.querySelector(`[data-meta-key="${key}"]`);
      if (!group) return;

      if (group.dataset.display === 'checkbox') {
        const checkbox = [...group.querySelectorAll('.ll-ba-checkbox-filter')]
          .find(el => el.value === value);
        if (checkbox) checkbox.checked = true;
      } else {
        const dropdown = group.querySelector('.ll-ba-dropdown-filter');
        if (dropdown) dropdown.value = value;
      }
    });

    applyFilters(currentPage);
  }

  // ── Helpers ──────────────────────────────────────────────────────────────────

  function escHtml(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function escAttr(str) {
    return str.replace(/"/g, '&quot;');
  }

  // ── Init ─────────────────────────────────────────────────────────────────────

  const initParams = new URLSearchParams(location.search);

  let hasFilterParams = false;
  initParams.forEach((_, key) => {
    if (key !== 'paged' && key !== 'ba_ref') hasFilterParams = true;
  });

  const initPaged = initParams.get('paged');
  if (initPaged) {
    currentPage = Math.max(1, parseInt(initPaged, 10));
  }

  if (hasFilterParams) {
    restoreFromUrl();
  } else {
    if (!initPaged) {
      currentPage = initPagination(paginationEl, applyFilters);
    } else {
      const totalPages = parseInt(paginationEl?.dataset.totalPages ?? '1', 10);
      renderPagination(paginationEl, totalPages, currentPage, applyFilters);
    }
  }

  // ── Sensitive images init ─────────────────────────────────────────────────────

  document.querySelectorAll('.ll-ba-sensitive-btn').forEach(btn =>
    btn.classList.toggle('is-active', btn.dataset.mode === currentSensitiveMode)
  );
  updateSensitiveBar( document.getElementById( 'll-ba-sensitive-bar' ), grid );
  applySensitiveMode( grid, currentSensitiveMode );
}