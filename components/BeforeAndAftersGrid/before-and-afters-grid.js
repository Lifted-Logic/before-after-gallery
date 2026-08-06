// Component: Before & Afters Grid
import { renderPagination } from '../../resources/js/pagination.js';
import { getSensitiveMode, setSensitiveMode, applySensitiveMode, updateSensitiveBar } from '../../resources/js/sensitive.js';

const PAGE_SIZE = 12;

document.addEventListener( 'DOMContentLoaded', () => {
  const components = document.querySelectorAll( '.ll-ba-bag-grid' );

  components.forEach( component => {
    const grid         = component.querySelector( '.ll-ba-bag-grid__card-grid' );
    const bar          = component.querySelector( '.ll-ba-bag-grid__sensitive-bar' );
    const paginationEl = component.querySelector( '.ll-ba-bag-grid__pagination' );
    if ( !grid ) return;

    const cards      = [ ...grid.querySelectorAll( '.ll-ba-card' ) ];
    const totalPages = Math.ceil( cards.length / PAGE_SIZE );

    // ── Sensitive mode ────────────────────────────────────────────────────────

    let mode = getSensitiveMode();

    component.querySelectorAll( '.ll-ba-sensitive-btn' ).forEach( btn => {
      btn.classList.toggle( 'is-active', btn.dataset.mode === mode );
    } );

    applySensitiveMode( grid, mode );
    updateSensitiveBar( bar, grid );

    bar?.addEventListener( 'click', e => {
      const btn = e.target.closest( '.ll-ba-sensitive-btn' );
      if ( !btn ) return;
      mode = btn.dataset.mode;
      setSensitiveMode( mode );
      applySensitiveMode( grid, mode );
      component.querySelectorAll( '.ll-ba-sensitive-btn' ).forEach( b => {
        b.classList.toggle( 'is-active', b === btn );
      } );
    } );

    // ── Pagination ────────────────────────────────────────────────────────────

    if ( totalPages <= 1 || !paginationEl ) {
      return;
    }

    const showPage = ( page ) => {
      const start = ( page - 1 ) * PAGE_SIZE;
      cards.forEach( ( card, i ) => {
        card.style.display = ( i >= start && i < start + PAGE_SIZE ) ? '' : 'none';
      } );
      applySensitiveMode( grid, mode );
      updateSensitiveBar( bar, grid );
      renderPagination( paginationEl, totalPages, page, showPage );
    };

    showPage( 1 );
  } );
} );
