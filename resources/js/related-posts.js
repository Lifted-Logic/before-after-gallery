import Splide from '@splidejs/splide';
import { getSensitiveMode, applySensitiveMode } from './sensitive.js';

export function initRelatedSlider() {
  const relatedEl = document.querySelector('.ll-ba-related-slider[data-post-id]');
  if (!relatedEl || typeof llBag === 'undefined') return;

  const formData = new FormData();
  formData.append('action',     llBag.relatedAction);
  formData.append('nonce',      llBag.relatedNonce);
  formData.append('exclude_id', relatedEl.dataset.postId);

  fetch(llBag.ajaxUrl, { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
      if (!data.success) return;

      const count = data.data.count ?? 0;

      if (count < 2) {
        relatedEl.closest('.ll-ba-single__related')?.style.setProperty('display', 'none');
        return;
      }

      if (data.data.html) {
        relatedEl.querySelector('.splide__list').innerHTML = data.data.html;
      }

      const list = relatedEl.querySelector('.splide__list');
      const mode = getSensitiveMode();

      applySensitiveMode( list, mode );

      if (mode === 'hide') {
        list.querySelectorAll('.splide__slide').forEach(slide => {
          if (slide.querySelector('.ll-ba-card--sensitive')) slide.remove();
        });
      }

      const visibleCount = list.querySelectorAll('.splide__slide').length;

      if (visibleCount < 2) {
        relatedEl.closest('.ll-ba-single__related')?.style.setProperty('display', 'none');
        return;
      }

      new Splide(relatedEl, {
        type:       'slide',
        perPage:    2,
        gap:        '32px',
        pagination: false,
        arrows:     visibleCount >= 3,
        breakpoints: {
          1023: {
            perPage: 1,
            gap:     '16px',
            arrows:  visibleCount >= 2,
          },
        },
      }).mount();
    })
    .catch(() => {});
}
