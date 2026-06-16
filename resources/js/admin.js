import '../css/admin.css';

/**
 * Convert a human label to a snake_case meta key with ll_ba_ prefix.
 * e.g. "Body Area" → "ll_ba_body_area"
 *      "Surgical / Non-Surgical" → "ll_ba_surgical_non_surgical"
 */
function labelToMetaKey(label) {
  return 'll_ba_' + label
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '');
}

function syncSearchable(row) {
  const filterable     = row.querySelector('.ll-bag-filterable-input');
  const searchableWrap = row.querySelector('.ll-bag-searchable-wrap');
  const searchable     = row.querySelector('.ll-bag-searchable-input');
  if (!filterable || !searchableWrap || !searchable) return;

  const enabled = filterable.checked;
  searchableWrap.classList.toggle('is-disabled', !enabled);
  searchable.disabled = !enabled;
  if (!enabled) searchable.checked = false;
}

document.addEventListener('DOMContentLoaded', () => {

  // Convert ACF field instructions stored in data-tooltip into hover tooltips
  document.querySelectorAll('.acf-field[data-tooltip]').forEach((field) => {
    const text = field.dataset.tooltip;
    const label = field.querySelector(':scope > .acf-label label');
    if (!text || !label) return;

    const icon = document.createElement('span');
    icon.className = 'll-bag-field-tooltip dashicons dashicons-editor-help';
    icon.tabIndex = 0;
    icon.setAttribute('aria-label', text);

    const bubble = document.createElement('span');
    bubble.className = 'll-bag-field-tooltip__bubble';
    bubble.textContent = text;
    icon.appendChild(bubble);

    label.appendChild(icon);
  });

  // Init searchable disabled state for all existing rows
  document.querySelectorAll('.ll-bag-filter-row').forEach(syncSearchable);

  document.getElementById('ll-bag-filter-tbody')?.addEventListener('change', (e) => {
    // Keep card display as a radio group (only one selected at a time)
    const checked = e.target.closest('.ll-bag-card-display');
    if (checked?.checked) {
      document.querySelectorAll('.ll-bag-card-display').forEach(cb => {
        if (cb !== checked) cb.checked = false;
      });
    }

    // Sync searchable when filterable changes
    const filterable = e.target.closest('.ll-bag-filterable-input');
    if (filterable) syncSearchable(filterable.closest('.ll-bag-filter-row'));
  });

  // prevent duplicate meta key detection on save
  document.querySelector('#ll-bag-filter-list')?.closest('form')
    ?.addEventListener('submit', (e) => {
      const keys = [...document.querySelectorAll('.ll-bag-meta-key-input')]
        .map(el => el.value.trim())
        .filter(Boolean);

      const seen = new Set();
      for (const key of keys) {
        if (seen.has(key)) {
          e.preventDefault();
          let notice = document.getElementById('ll-bag-duplicate-notice');
          
          if (!notice) {
            notice = document.createElement('div');
            notice.id = 'll-bag-duplicate-notice';
            notice.className = 'notice notice-error';
            document.getElementById('ll-bag-filter-list')?.before(notice);
          }
          notice.innerHTML = `<p>Duplicate filter detected: <strong>${key}</strong>. Each filter must have a unique label.</p>`;
          notice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          return;
        }
        seen.add(key);
      }
    });

  // handles the taxonomy tabs
  document.querySelectorAll('.ll-ba-tax-tabs').forEach(taxTabs => {
    taxTabs.addEventListener('click', (e) => {
      const tab = e.target.closest('.ll-ba-tax-tab');
      if (!tab) return;

      taxTabs.querySelectorAll('.ll-ba-tax-tab').forEach(t => t.classList.remove('is-active'));
      taxTabs.querySelectorAll('.ll-ba-tax-panel').forEach(p => { p.hidden = true; });

      tab.classList.add('is-active');
      const panel = document.getElementById(tab.dataset.target);
      if (panel) panel.hidden = false;
    });
  });

  const tbody  = document.getElementById('ll-bag-filter-tbody');
  const addBtn = document.getElementById('ll-bag-add-filter');

  if (!tbody || !addBtn) return;

  // ── Drag-and-drop row reordering ───────────────────────────────────────────
  let draggedRow = null;

  tbody.addEventListener('dragstart', (e) => {
    draggedRow = e.target.closest('tr');
    setTimeout(() => draggedRow?.classList.add('opacity-50'), 0);
  });

  tbody.addEventListener('dragend', () => {
    draggedRow?.classList.remove('opacity-50');
    draggedRow = null;
  });

  tbody.addEventListener('dragover', (e) => {
    e.preventDefault();
    const target = e.target.closest('tr');
    if (!target || target === draggedRow) return;
    const rect = target.getBoundingClientRect();
    tbody.insertBefore(draggedRow, e.clientY < rect.top + rect.height / 2 ? target : target.nextSibling);
  });

  // REMOVE FILTER ROW
  tbody.addEventListener('click', (e) => {
    const btn = e.target.closest('.ll-bag-remove-filter');
    if (!btn) return;

    btn.closest('.ll-bag-filter-row')?.remove();
  });

  tbody.addEventListener('input', (e) => {
    const labelInput = e.target.closest('.ll-bag-label-input');
    if (!labelInput) return;

    const row = labelInput.closest('.ll-bag-filter-row');
    // skip auto-generating the meta key if it's not a new row
    if (!row?.dataset.isNew) return;

    const metaKeyInput = row.querySelector('.ll-bag-meta-key-input');
    const metaKeyHint  = row.querySelector('.ll-bag-meta-key-hint');
    const key = labelToMetaKey(labelInput.value);

    if (metaKeyInput) metaKeyInput.value = key;
    if (metaKeyHint)  metaKeyHint.textContent = key;
  });

  // NEW FILTER ROW: clone template from filter-settings.php
  addBtn.addEventListener('click', () => {
    const template = document.getElementById('ll-bag-filter-template');
    if (!template) return;

    const id    = `f${Date.now()}`;
    const clone = template.content.cloneNode(true);
    const row   = clone.querySelector('tr');
    if (!row) return;

    row.innerHTML  = row.innerHTML.replaceAll('__ID__', id);
    row.dataset.id  = id;
    row.dataset.isNew = '1'; // flag so meta key auto-generates from label

    tbody.appendChild(row);
    syncSearchable(tbody.lastElementChild);
  });
});
