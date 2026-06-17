// Component: Before & After Slider
import Splide from '@splidejs/splide';
import { getSensitiveMode, setSensitiveMode, applySensitiveMode } from '../../resources/js/sensitive.js';

document.addEventListener( 'DOMContentLoaded', () => {
  const mode = getSensitiveMode();

  document.querySelectorAll( '.ll-ba-before-after-slider__splide' ).forEach( el => {
    const slider = new Splide( el, {
      type:       'loop',
      perPage:    1,
      gap:        '2rem',
      pagination: false,
    } ).mount();

    applySensitiveMode( el, mode );
  } );

  document.addEventListener( 'click', e => {
    const btn = e.target.closest( '[data-slider-card-action]' );
    if ( !btn ) return;

    e.preventDefault();
    const action = btn.dataset.sliderCardAction;
    const card   = btn.closest( '.ll-ba-slider-card' );

    if ( action === 'unblur-once' && card ) {
      card.classList.remove( 'is-blurred' );
    } else if ( action === 'unblur-all' ) {
      setSensitiveMode( 'unblur' );
      document.querySelectorAll( '.ll-ba-slider-card--sensitive' ).forEach( c => c.classList.remove( 'is-blurred' ) );
    }
  } );
} );
