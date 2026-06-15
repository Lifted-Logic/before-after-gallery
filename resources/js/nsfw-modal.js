import { getSensitiveMode, setSensitiveMode } from './sensitive.js';

export function initNsfwModal() {
  const modal = document.getElementById('ll-ba-nsfw-modal');
  const gallery = document.querySelector('.ll-ba-single__gallery--sensitive');
  if (!modal || !gallery) return;

  if (getSensitiveMode() === 'unblur') return;

  gallery.classList.add('is-blurred');
  modal.classList.remove('ll-ba-hidden');

  modal.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-nsfw-action]');
    if (!trigger) return;
    const action = trigger.dataset.nsfwAction;

    if (action === 'unblur-all') {
      setSensitiveMode('unblur');
    }

    if (action === 'unblur-once' || action === 'unblur-all') {
      gallery.classList.remove('is-blurred');
      modal.classList.add('ll-ba-hidden');
      return;
    }

    if (action === 'leave') {
      const ref = document.referrer;
      const currentUrl = window.location.href.split('#')[0];
      const isUsableReferrer = ref
        && ref !== currentUrl
        && new URL(ref).origin === window.location.origin;

      if (isUsableReferrer) {
        window.location.href = ref;
      } else if (trigger.dataset.fallbackUrl) {
        window.location.href = trigger.dataset.fallbackUrl;
      } else {
        window.history.back();
      }
    }
  });
}
