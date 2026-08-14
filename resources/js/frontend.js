import '../css/frontend.css';
import '../css/theme-before-after.css';
import './vendor/easy-toggle-state.js';

// Components
import '../../components/RelatedBeforeAndAfters/related-before-and-afters.css';
import '../../components/RelatedBeforeAndAfters/related-before-and-afters.js';
import '../../components/BeforeAndAftersGrid/before-and-afters-grid.css';
import '../../components/BeforeAndAftersGrid/before-and-afters-grid.js';
import '../../components/BeforeAndAfterSlider/before-and-after-slider.css';
import '../../components/BeforeAndAfterSlider/before-and-after-slider.js';

// Splide — import only if not already provided by the theme
import Splide from '@splidejs/splide';
import '@splidejs/splide/css';

( function setHeaderHeight() {
  const run = () => {
    const header = document.querySelector( 'header' );
    if ( !header ) return;
    const adminBar = document.getElementById( 'wpadminbar' );
    const update = () => {
      const height = header.offsetHeight + ( adminBar ? adminBar.offsetHeight : 0 );
      document.documentElement.style.setProperty( '--ba-header-height', height + 'px' );
    };
    update();
    window.addEventListener( 'resize', update );
    window.addEventListener( 'load', update );
    window.addEventListener( 'pageshow', update );

    if ( document.fonts && document.fonts.ready ) {
      document.fonts.ready.then( update );
    }

    if ( 'ResizeObserver' in window ) {
      const observer = new ResizeObserver( update );
      observer.observe( header );
      if ( adminBar ) observer.observe( adminBar );
    }
  };

  if ( document.readyState === 'loading' ) {
    document.addEventListener( 'DOMContentLoaded', run );
  } else {
    run();
  }
} )();

import { initCardLinks } from './card.js';
import { initRelatedSlider } from './related-posts.js';
import { initFilters } from './filters.js';
import { initNsfwModal } from './nsfw-modal.js';

// ── Splide: single post gallery + thumbnails ───────────────────────────────────

document.querySelectorAll( '.ll-ba-single-page-slider' ).forEach( el => {
  const navEl = el.nextElementSibling?.classList.contains( 'll-ba-single-page-slider-nav' ) ? el.nextElementSibling : null;

  const slideCount = el.querySelectorAll( '.splide__slide' ).length;

  if ( slideCount <= 1 ) {
    if ( navEl ) navEl.style.display = 'none';
    new Splide( el, { drag: false, arrows: false, pagination: false } ).mount();
    return;
  }

  const primary = new Splide( el, {
    type: 'loop',
    perPage: 1,
    pagination: false,
    arrows: false,
    gap: '24px',
    focus: 'center',
    drag: false,
  } );

  if ( navEl ) {
    const nav = new Splide( navEl, {
      isNavigation: true,
      gap: '6px',
      pagination: false,
      arrows: slideCount > 5,
      arrowPath: 'M0.221889 0.203398C0.517741 -0.0677994 0.997411 -0.0677994 1.29326 0.203398L11.4448 9.50895C11.7406 9.78015 11.7406 10.2198 11.4448 10.491L1.29326 19.7966C0.997411 20.0678 0.517741 20.0678 0.221889 19.7966C-0.073963 19.5254 -0.073963 19.0857 0.221889 18.8145L9.83772 10L0.221889 1.18549C-0.073963 0.914293 -0.073963 0.474596 0.221889 0.203398Z',
      fixedWidth: '80px',
      focus: 'center',
    } );
    primary.sync( nav );
    primary.mount();
    nav.mount();
  } else {
    primary.mount();
  }
} );

// ── Splide: static related sliders (dynamic ones are mounted in related-posts.js) ──

document.querySelectorAll( '.ll-ba-related-slider' ).forEach( el => {
  if ( el.dataset.postId ) return;

  new Splide( el, {
    type: 'loop',
    perPage: 2,
    gap: '32px',
    pagination: false,
    breakpoints: {
      768: {
        gap: '12px',
      },
    },
  } ).mount();
} );

// ── Splide: comparison slider ──────────────────────────────────────────────────

document.querySelectorAll( '.ll-ba-comparison-slider' ).forEach( el => {
  const after = el.querySelector( '.ll-ba-comparison-slider__after' );
  const divider = el.querySelector( '.ll-ba-comparison-slider__divider' );
  let dragging = false;

  const setPosition = ( pct ) => {
    after.style.clipPath = `inset(0 ${( 1 - pct ) * 100}% 0 0)`;
    divider.style.left = `${pct * 100}%`;
  };

  setPosition( 0.5 );

  const getPct = ( x ) => {
    const rect = el.getBoundingClientRect();
    return Math.min( Math.max( ( x - rect.left ) / rect.width, 0 ), 1 );
  };

  el.addEventListener( 'pointerdown', ( e ) => {
    dragging = true;
    e.stopPropagation();
    el.setPointerCapture( e.pointerId );
    setPosition( getPct( e.clientX ) );
  } );
  el.addEventListener( 'pointermove', ( e ) => {
    if ( dragging ) setPosition( getPct( e.clientX ) );
  } );
  el.addEventListener( 'pointerup', () => { dragging = false; } );
} );

// ── Feature init ───────────────────────────────────────────────────────────────

initCardLinks();
initRelatedSlider();

document.addEventListener( 'DOMContentLoaded', () => {
  const $ = window.jQuery;
  if ( $ && $.magnificPopup ) {
    const openLightbox = ( trigger ) => {
      const host = trigger.closest( '[data-ba-gallery]' );
      if ( !host ) return;

      let items;
      try {
        items = JSON.parse( host.getAttribute( 'data-ba-gallery' ) || '[]' );
      } catch ( err ) {
        return;
      }
      if ( !items.length ) return;

      const index = Math.min( parseInt( trigger.dataset.baIndex || '0', 10 ) || 0, items.length - 1 );

      $.magnificPopup.open( {
        items,
        type: 'image',
        gallery: {
          enabled: items.length > 1,
          navigateByImgClick: false,
          arrowMarkup: '<button title="%title%" type="button" class="mfp-arrow mfp-arrow-%dir% ll-ba-mfp-arrow ll-ba-mfp-arrow--%dir% mfp-prevent-close">' +
            '<svg class="ll-ba-mfp-arrow-icon icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>' +
            '</button>',
        },
        mainClass: 'll-ba-mfp',
        closeBtnInside: true,
        image: { titleSrc: 'title' },
      }, index );
    };

    document.addEventListener( 'click', e => {
      const trigger = e.target.closest( '[data-ba-open]' );
      if ( !trigger ) return;
      e.preventDefault();
      openLightbox( trigger );
    } );

    document.addEventListener( 'keydown', e => {
      if ( e.key !== 'Enter' && e.key !== ' ' ) return;
      const trigger = e.target.closest( '[data-ba-open]' );
      if ( !trigger || trigger.tagName === 'BUTTON' ) return;
      e.preventDefault();
      openLightbox( trigger );
    } );
  }

  if ( $ && $.fn.magnificPopup ) {
    $( document ).on( 'click', '.ll-ba-single__detail-read-more-trigger', function ( e ) {
      e.preventDefault();
      $.magnificPopup.open( {
        items: { src: $( this ).data( 'mfp-src' ), type: 'inline' },
        closeBtnInside: true,
      } );
    } );
  }

  initFilters();
  initNsfwModal();
} );

( function clampFocalOrigins() {
  const apply = () => {
    document.querySelectorAll( '.fit-image' ).forEach( box => {
      const img = box.querySelector( 'img' );
      if ( !img || !img.naturalWidth || !img.naturalHeight ) return;

      const style = img.getAttribute( 'style' ) || '';
      const scale = style.match( /scale\(([\d.]+)\)/ );
      if ( !scale ) return;
      const z = parseFloat( scale[1] );
      if ( !( z > 1 ) ) return;

      if ( !img.dataset.baOrigin ) {
        const m = style.match( /transform-origin:\s*([\d.]+)%\s+([\d.]+)%/ );
        img.dataset.baOrigin = m ? m[1] + ' ' + m[2] : '50 50';
      }
      const [wantX, wantY] = img.dataset.baOrigin.split( ' ' ).map( Number );

      const fw = box.clientWidth, fh = box.clientHeight;
      if ( !fw || !fh ) return;

      const a = img.naturalWidth / img.naturalHeight;
      const fa = fw / fh;
      const cw = a >= fa ? fw : fh * a;
      const ch = a >= fa ? fw / a : fh;

      const axis = ( len, content, want ) => {
        if ( z * content < len ) return 50;
        const lo = ( ( ( len - content ) / 2 ) * z ) / ( z - 1 );
        const hi = ( ( ( len + content ) / 2 ) * z - len ) / ( z - 1 );
        return Math.min( Math.max( want, ( lo / len ) * 100 ), ( hi / len ) * 100 );
      };

      img.style.transformOrigin =
        axis( fw, cw, wantX ).toFixed( 2 ) + '% ' + axis( fh, ch, wantY ).toFixed( 2 ) + '%';
    } );
  };

  const run = () => { apply(); requestAnimationFrame( apply ); };

  if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', run );
  else run();
  window.addEventListener( 'load', run );
  window.addEventListener( 'resize', run );
  document.addEventListener( 'load', e => {
    if ( e.target?.tagName === 'IMG' ) apply();
  }, true );
} )();
