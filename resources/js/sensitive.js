import { CookieUtil } from './cookieUtil.js';

const COOKIE_KEY = 'll-ba-sensitive-mode';
const DEFAULT_MODE = 'blur';

export function getSensitiveMode() {
  return CookieUtil.getCookie( COOKIE_KEY ) || DEFAULT_MODE;
}

export function setSensitiveMode( mode ) {
  CookieUtil.setCookie( COOKIE_KEY, mode );
}

/**
 * Apply blur/hide state to all sensitive cards within a container.
 * @param {Element} container - Any element containing .ll-ba-card elements
 * @param {string}  mode      - 'blur' | 'hide' | anything else = show all
 */
export function applySensitiveMode( container, mode ) {
  container.querySelectorAll( '.ll-ba-card, .ll-ba-slider-card' ).forEach( card => {
    card.classList.remove( 'is-blurred', 'is-hidden' );
  } );
  if ( mode === 'blur' ) {
    container.querySelectorAll( '.ll-ba-card--sensitive, .ll-ba-slider-card--sensitive' ).forEach( c => c.classList.add( 'is-blurred' ) );
  } else if ( mode === 'hide' ) {
    container.querySelectorAll( '.ll-ba-card--sensitive, .ll-ba-slider-card--sensitive' ).forEach( c => c.classList.add( 'is-hidden' ) );
  }
}

/**
 * Show or hide the sensitive bar based on whether sensitive cards exist in the container.
 * @param {Element|null} bar       - #ll-ba-sensitive-bar element
 * @param {Element}      container - Grid/list containing cards
 */
export function updateSensitiveBar( bar, container ) {
  if ( !bar ) return;
  const hasSensitive = [...container.querySelectorAll( '.ll-ba-card--sensitive, .ll-ba-slider-card--sensitive' )]
    .some( card => card.style.display !== 'none' );
  bar.classList.toggle( 'll-ba-hidden', !hasSensitive );
}
