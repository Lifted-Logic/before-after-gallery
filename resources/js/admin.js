import '../css/admin.css';

/**
 * Convert a human label to a snake_case meta key with ll_ba_ prefix.
 * e.g. "Body Area" → "ll_ba_body_area"
 *      "Surgical / Non-Surgical" → "ll_ba_surgical_non_surgical"
 */
function labelToMetaKey( label ) {
  return 'll_ba_' + label
    .toLowerCase()
    .replace( /[^a-z0-9]+/g, '_' )
    .replace( /^_+|_+$/g, '' );
}

function syncSearchable( row ) {
  const filterable = row.querySelector( '.ll-bag-filterable-input' );
  const searchableWrap = row.querySelector( '.ll-bag-searchable-wrap' );
  const searchable = row.querySelector( '.ll-bag-searchable-input' );
  if ( !filterable || !searchableWrap || !searchable ) return;

  const enabled = filterable.checked;
  searchableWrap.classList.toggle( 'is-disabled', !enabled );
  searchable.disabled = !enabled;
  if ( !enabled ) searchable.checked = false;
}

function syncTagLabel( row ) {
  const cardDisplay = row.querySelector( '.ll-bag-card-display' );
  const tagLabelWrap = row.querySelector( '.ll-bag-tag-label-wrap' );
  if ( !cardDisplay || !tagLabelWrap ) return;

  tagLabelWrap.classList.toggle( 'll-bag-hidden', !cardDisplay.checked );
}

document.addEventListener( 'DOMContentLoaded', () => {

  // Convert ACF field instructions stored in data-tooltip into hover tooltips
  document.querySelectorAll( '.acf-field[data-tooltip]' ).forEach( ( field ) => {
    const text = field.dataset.tooltip;
    const label = field.querySelector( ':scope > .acf-label label' );
    if ( !text || !label ) return;

    const icon = document.createElement( 'span' );
    icon.className = 'll-bag-field-tooltip dashicons dashicons-editor-help';
    icon.tabIndex = 0;
    icon.setAttribute( 'aria-label', text );

    const bubble = document.createElement( 'span' );
    bubble.className = 'll-bag-field-tooltip__bubble';
    bubble.textContent = text;
    icon.appendChild( bubble );

    label.appendChild( icon );
  } );

  // Init searchable disabled state for all existing rows
  document.querySelectorAll( '.ll-bag-filter-row' ).forEach( syncSearchable );

  // Init tag label visibility for all existing rows
  document.querySelectorAll( '.ll-bag-filter-row' ).forEach( syncTagLabel );

  document.getElementById( 'll-bag-filter-tbody' )?.addEventListener( 'change', ( e ) => {
    // Keep card display as a radio group (only one selected at a time)
    const checked = e.target.closest( '.ll-bag-card-display' );
    if ( checked ) {
      if ( checked.checked ) {
        document.querySelectorAll( '.ll-bag-card-display' ).forEach( cb => {
          if ( cb !== checked ) cb.checked = false;
        } );
      }
      document.querySelectorAll( '.ll-bag-filter-row' ).forEach( syncTagLabel );
    }

    // Sync searchable when filterable changes
    const filterable = e.target.closest( '.ll-bag-filterable-input' );
    if ( filterable ) syncSearchable( filterable.closest( '.ll-bag-filter-row' ) );
  } );

  // prevent duplicate meta key detection on save
  document.querySelector( '#ll-bag-filter-list' )?.closest( 'form' )
    ?.addEventListener( 'submit', ( e ) => {
      const keys = [...document.querySelectorAll( '.ll-bag-meta-key-input' )]
        .map( el => el.value.trim() )
        .filter( Boolean );

      const seen = new Set();
      for ( const key of keys ) {
        if ( seen.has( key ) ) {
          e.preventDefault();
          let notice = document.getElementById( 'll-bag-duplicate-notice' );

          if ( !notice ) {
            notice = document.createElement( 'div' );
            notice.id = 'll-bag-duplicate-notice';
            notice.className = 'notice notice-error';
            document.getElementById( 'll-bag-filter-list' )?.before( notice );
          }
          notice.innerHTML = `<p>Duplicate filter detected: <strong>${key}</strong>. Each filter must have a unique label.</p>`;
          notice.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
          return;
        }
        seen.add( key );
      }
    } );

  // handles the taxonomy tabs
  document.querySelectorAll( '.ll-ba-tax-tabs' ).forEach( taxTabs => {
    taxTabs.addEventListener( 'click', ( e ) => {
      const tab = e.target.closest( '.ll-ba-tax-tab' );
      if ( !tab ) return;

      taxTabs.querySelectorAll( '.ll-ba-tax-tab' ).forEach( t => t.classList.remove( 'is-active' ) );
      taxTabs.querySelectorAll( '.ll-ba-tax-panel' ).forEach( p => { p.hidden = true; } );

      tab.classList.add( 'is-active' );
      const panel = document.getElementById( tab.dataset.target );
      if ( panel ) panel.hidden = false;
    } );
  } );

  // ── How To page: active nav link on scroll ────────────────────────────────
  const howToSections = document.querySelectorAll( '.ll-bag-how-to__section' );
  if ( howToSections.length ) {
    const navLinks = document.querySelectorAll( '.ll-bag-how-to__nav-link' );

    const setActive = ( id ) => {
      navLinks.forEach( ( link ) => {
        link.classList.toggle( 'is-active', link.getAttribute( 'href' ) === `#${id}` );
      } );
    };

    // Set first section active on load
    if ( howToSections[0] ) setActive( howToSections[0].id );

    const observer = new IntersectionObserver(
      ( entries ) => {
        entries.forEach( ( entry ) => {
          if ( entry.isIntersecting ) setActive( entry.target.id );
        } );
      },
      { rootMargin: '-20% 0px -70% 0px' }
    );

    howToSections.forEach( ( section ) => observer.observe( section ) );
  }

  const tbody = document.getElementById( 'll-bag-filter-tbody' );
  const addBtn = document.getElementById( 'll-bag-add-filter' );

  if ( !tbody || !addBtn ) return;

  // ── Drag-and-drop row reordering ───────────────────────────────────────────
  let draggedRow = null;

  tbody.addEventListener( 'dragstart', ( e ) => {
    draggedRow = e.target.closest( 'tr' );
    setTimeout( () => draggedRow?.classList.add( 'opacity-50' ), 0 );
  } );

  tbody.addEventListener( 'dragend', () => {
    draggedRow?.classList.remove( 'opacity-50' );
    draggedRow = null;
  } );

  tbody.addEventListener( 'dragover', ( e ) => {
    e.preventDefault();
    const target = e.target.closest( 'tr' );
    if ( !target || target === draggedRow ) return;
    const rect = target.getBoundingClientRect();
    tbody.insertBefore( draggedRow, e.clientY < rect.top + rect.height / 2 ? target : target.nextSibling );
  } );

  // REMOVE FILTER ROW
  tbody.addEventListener( 'click', ( e ) => {
    const btn = e.target.closest( '.ll-bag-remove-filter' );
    if ( !btn ) return;

    btn.closest( '.ll-bag-filter-row' )?.remove();
  } );

  tbody.addEventListener( 'input', ( e ) => {
    const labelInput = e.target.closest( '.ll-bag-label-input' );
    if ( !labelInput ) return;

    const row = labelInput.closest( '.ll-bag-filter-row' );
    // skip auto-generating the meta key if it's not a new row
    if ( !row?.dataset.isNew ) return;

    const metaKeyInput = row.querySelector( '.ll-bag-meta-key-input' );
    const metaKeyHint = row.querySelector( '.ll-bag-meta-key-hint' );
    const key = labelToMetaKey( labelInput.value );

    if ( metaKeyInput ) metaKeyInput.value = key;
    if ( metaKeyHint ) metaKeyHint.textContent = key;
  } );

  // NEW FILTER ROW: clone template from filter-settings.php
  addBtn.addEventListener( 'click', () => {
    const template = document.getElementById( 'll-bag-filter-template' );
    if ( !template ) return;

    const id = `f${Date.now()}`;
    const clone = template.content.cloneNode( true );
    const row = clone.querySelector( 'tr' );
    if ( !row ) return;

    row.innerHTML = row.innerHTML.replaceAll( '__ID__', id );
    row.dataset.id = id;
    row.dataset.isNew = '1'; // flag so meta key auto-generates from label

    tbody.appendChild( row );
    syncSearchable( tbody.lastElementChild );
  } );
} );

( function postListOrder() {
  const cfg = window.llBagOrder;
  const body = document.body;
  if ( !cfg || !cfg.enabled ) return;
  if ( !body.classList.contains( 'edit-php' ) || !body.classList.contains( 'post-type-ll_before_after' ) ) return;

  const list = document.getElementById( 'the-list' );
  if ( !list ) return;

  const rows = () => [...list.querySelectorAll( 'tr[id^="post-"]' )];
  if ( rows().length < 2 ) return;

  rows().forEach( r => r.classList.add( 'll-bag-orderable' ) );

  let dragged = null;
  let before = '';

  list.addEventListener( 'mousedown', ( e ) => {
    const handle = e.target.closest( '.ll-bag-drag-handle' );
    const row = handle?.closest( 'tr[id^="post-"]' );
    if ( row ) row.draggable = true;
  } );
  document.addEventListener( 'mouseup', () => {
    rows().forEach( r => { r.draggable = false; } );
  } );

  list.addEventListener( 'dragstart', ( e ) => {
    dragged = e.target.closest( 'tr[id^="post-"]' );
    before = rows().map( r => r.id ).join();
    setTimeout( () => dragged?.classList.add( 'opacity-50' ), 0 );
  } );

  list.addEventListener( 'dragover', ( e ) => {
    e.preventDefault();
    const target = e.target.closest( 'tr[id^="post-"]' );
    if ( !target || !dragged || target === dragged ) return;
    const rect = target.getBoundingClientRect();
    list.insertBefore( dragged, e.clientY < rect.top + rect.height / 2 ? target : target.nextSibling );
  } );

  list.addEventListener( 'dragend', () => {
    dragged?.classList.remove( 'opacity-50' );
    dragged = null;
    const order = rows().map( r => r.id );
    if ( order.join() === before ) return;

    const fd = new FormData();
    fd.append( 'action', cfg.action );
    fd.append( 'nonce', cfg.nonce );
    fd.append( 'offset', cfg.offset );
    order.forEach( id => fd.append( 'ids[]', id.replace( 'post-', '' ) ) );

    list.style.opacity = '0.5';
    fetch( cfg.ajaxUrl, { method: 'POST', body: fd } )
      .then( r => r.json() )
      .then( d => { if ( !d.success ) throw new Error( 'save rejected' ); } )
      .catch( () => alert( 'Reordering failed to save — reload the page and try again.' ) )
      .finally( () => { list.style.opacity = ''; } );
  } );
} )();

( function cropPanels() {
  const RATIOS = { wide: 16 / 9, square: 1, panorama: 3, vertical: 4 / 5, '': 1 };
  const STACKED = ['wide', 'panorama'];
  const clamp = ( n, lo, hi ) => Math.min( hi, Math.max( lo, n ) );

  let activeStop = null;
  const endGesture = () => {
    const stop = activeStop;
    activeStop = null;
    stop?.();
  };
  ['pointerup', 'pointercancel', 'blur'].forEach(
    evt => window.addEventListener( evt, endGesture ) );
  document.addEventListener( 'visibilitychange',
    () => document.hidden && endGesture() );

  const parse = ( raw ) => {
    const parts = String( raw || '' ).trim().split( /\s+/ ).filter( Boolean ).map( Number );
    const [x, y, z] = parts;
    return {
      x: Number.isFinite( x ) ? clamp( x, 0, 100 ) : 50,
      y: Number.isFinite( y ) ? clamp( y, 0, 100 ) : 50,
      z: Number.isFinite( z ) ? Math.max( 100, z ) : null,   // null = not set yet
    };
  };
  const safe = ( n, fb ) => ( Number.isFinite( n ) ? n : fb );
  const serialise = ( v ) =>
    Math.round( safe( v.x, 50 ) ) + ' ' + Math.round( safe( v.y, 50 ) ) + ' ' + Math.round( safe( v.z, 100 ) );

  // Scale needed to turn a contained image into one that fills its frame.
  const fillZoom = ( img, frameRatio ) => {
    if ( !img?.naturalWidth || !img.naturalHeight ) return 100;
    const imgRatio = img.naturalWidth / img.naturalHeight;
    return Math.ceil( Math.max( imgRatio / frameRatio, frameRatio / imgRatio ) * 100 ) + 1;
  };

  const panBounds = ( img, fw, fh, z ) => {
    const full = { minX: 0, maxX: 100, minY: 0, maxY: 100 };
    if ( !img?.naturalWidth || !img.naturalHeight || z <= 1 || !fw || !fh ) return full;

    const a = img.naturalWidth / img.naturalHeight;
    const fa = fw / fh;
    const cw = a >= fa ? fw : fh * a;
    const ch = a >= fa ? fw / a : fh;

    const axis = ( f, c ) => {
      if ( z * c < f ) return [50, 50];            // cannot fill this axis — centre it
      const lo = ( ( ( f - c ) / 2 ) * z ) / ( z - 1 );
      const hi = ( ( ( f + c ) / 2 ) * z - f ) / ( z - 1 );
      return [( lo / f ) * 100, ( hi / f ) * 100];
    };

    const [minX, maxX] = axis( fw, cw );
    const [minY, maxY] = axis( fh, ch );
    return { minX, maxX, minY, maxY };
  };

  const fieldIn = ( row, key ) => row.querySelector( '.acf-field[data-key="' + key + '"]' );
  const imgUrl = ( field ) =>
    field?.querySelector( '.acf-image-uploader img' )?.getAttribute( 'src' ) || '';

  const SIDE_SETS = {
    'two-images': [
      { key: 'before', label: 'Before', focal: 'field_ll_ba_before_focal', image: 'field_ll_ba_before_image' },
      { key: 'after', label: 'After', focal: 'field_ll_ba_after_focal', image: 'field_ll_ba_after_image' },
    ],
    'one-image': [
      { key: 'single', label: '', focal: 'field_ll_ba_single_focal', image: 'field_ll_ba_single_image' },
    ],
  };

  const build = ( row ) => {
    const optionSel = fieldIn( row, 'field_ll_ba_image_options' )?.querySelector( 'select' );
    const option = optionSel?.value || '';
    const spec = SIDE_SETS[option];
    if ( !spec ) return;                       // video rows have nothing to crop

    const sides = spec.map( s => {
      const field = fieldIn( row, s.focal );
      return {
        ...s,
        field,
        input: field?.querySelector( 'input[type="text"]' ),
        url: imgUrl( fieldIn( row, s.image ) ),
      };
    } );
    if ( sides.some( s => !s.field || !s.input ) ) return;

    const solo = sides.length === 1;
    sides.forEach( s => s.field.classList.add( 'll-ba-crop-input--stowed' ) );
    const anchor = sides[0].field;

    const croppedChk = solo
      ? fieldIn( row, 'field_ll_ba_single_fill' )?.querySelector( 'input[type="checkbox"]' )
      : null;
    // Until the editor touches it, a single image sits at 100% and stays uncommitted.
    const defaultZoom = ( side ) =>
      ( solo && !croppedChk?.checked ) ? 100 : fillFor( side );
    const markCropped = () => {
      if ( !croppedChk || croppedChk.checked ) return;
      croppedChk.checked = true;
      croppedChk.dispatchEvent( new Event( 'change', { bubbles: true } ) );

      panel.querySelector( '[data-uncropped-note]' )?.remove();
    };

    let panel = row.querySelector( '.ll-ba-crop' );
    if ( !panel ) {
      panel = document.createElement( 'div' );
      panel.className = 'll-ba-crop';
    }
    panel.classList.toggle( 'll-ba-crop--single', solo );
    if ( panel.parentElement !== anchor.parentElement ) {
      anchor.parentElement.insertBefore( panel, anchor );
    }

    if ( !sides.some( s => s.url ) ) {
      panel.innerHTML = '<p class="ll-ba-crop__empty">' +
        ( solo ? 'Choose an image to set the crop.' : 'Choose a before and after image to set the crop.' ) +
        '</p>';
      panel.dataset.sig = '';
      return;
    }

    const ratioSel = fieldIn( row, 'field_ll_ba_image_ratio' )?.querySelector( 'select' );
    const sliderIn = fieldIn( row, 'field_ll_ba_comparison_slider' )?.querySelector( 'input[type="checkbox"]' );
    const ratioKey = ratioSel?.value || '';
    const ratio = RATIOS[ratioKey] ?? 1;
    const sliderOn = !!fieldIn( row, 'field_ll_ba_comparison_slider' )
      ?.querySelector( 'input[type="checkbox"]' )?.checked;
    const stacked = !solo && !sliderOn && STACKED.includes( ratioKey );

    const sig = [option, ...sides.map( s => s.url ), ratioKey, stacked, sliderOn].join( '|' );
    if ( panel.dataset.sig === sig ) return;
    panel.dataset.sig = sig;

    const frame = ( s ) =>
      '<div class="ll-ba-crop__cell' + ( solo ? ' ll-ba-crop__cell--solo' : '' ) +
      '" data-side="' + s.key + '" style="aspect-ratio:' + ratio + '">' +
      ( s.url ? '<div class="ll-ba-crop__ghost" aria-hidden="true"><img alt="" draggable="false" src="' + s.url + '"></div>' : '' ) +
      '<div class="ll-ba-crop__frame" data-side="' + s.key + '">' +
      ( s.url ? '<img alt="" draggable="false" src="' + s.url + '">' : '<span class="ll-ba-crop__missing">No image</span>' ) +
      ( s.label ? '<span class="ll-ba-crop__tag">' + s.label + '</span>' : '' ) +
      '</div>' +
      '<span class="ll-ba-crop__boundary" aria-hidden="true"></span>' +
      '</div>';

    panel.innerHTML =
      '<div class="ll-ba-crop__stage' + ( stacked ? ' is-stacked' : '' ) + '">' +
      sides.map( frame ).join( '' ) +
      '</div>' +
      '<p class="ll-ba-crop__hint">' +
      ( sliderOn
        ? 'With the comparison slider on, both images share one frame at runtime — each is shown here separately so you can crop them. '
        : '' ) +
      ( solo && !croppedChk?.checked
        ? '<span data-uncropped-note>Not cropped yet — this renders at its own size. ' +
        'Zoom or drag to crop it into the frame. </span>'
        : '' ) +
      '100% shows the whole image; zoom in to fill the frame, then drag to reposition.</p>' +
      '<div class="ll-ba-crop__controls"><div class="ll-ba-crop__control">' +
      '<label>Zoom' + ( solo ? '' : ' <b data-active-label>before</b>' ) + '</label>' +
      '<input type="range" min="100" step="1">' +
      '<output></output>' +
      '<button type="button" class="button button-small" data-fill>Fill frame</button>' +
      '<button type="button" class="button button-small" data-reset>Reset</button>' +
      '</div></div>';

    const inputFor = ( side ) => sides.find( s => s.key === side ).input;
    const imgFor = ( side ) => panel.querySelector( '.ll-ba-crop__frame[data-side="' + side + '"] img' );
    const fillFor = ( side ) => fillZoom( imgFor( side ), ratio );

    let active = ( sides.find( s => s.key === panel.dataset.activeSide ) ||
      sides.find( s => s.url ) || sides[0] ).key;

    const paint = ( side ) => {
      const v = parse( inputFor( side ).value );
      const z = v.z ?? defaultZoom( side );
      const layers = [imgFor( side ), panel.querySelector( '.ll-ba-crop__cell[data-side="' + side + '"] .ll-ba-crop__ghost img' )].filter( Boolean );
      layers.forEach( img => {
        img.style.objectPosition = '50% 50%';
        img.style.transform = 'scale(' + ( z / 100 ) + ')';
        img.style.transformOrigin = v.x + '% ' + v.y + '%';
      } );
      if ( side !== active ) return;
      const ctl = panel.querySelector( '.ll-ba-crop__control' );
      const range = ctl.querySelector( 'input[type="range"]' );
      range.max = Math.max( 300, fillFor( side ) + 50 );
      range.value = z;
      ctl.querySelector( 'output' ).textContent = Math.round( z ) + '%';
      updateLocks();
    };

    const updateLocks = () => {
      panel.querySelectorAll( '.ll-ba-crop__frame' ).forEach( f => {
        const v = parse( inputFor( f.dataset.side ).value );
        const z = v.z ?? defaultZoom( f.dataset.side );
        f.classList.toggle( 'is-locked', z <= 101 );
      } );
    };

    const write = ( side, next ) => {
      const input = inputFor( side );
      const frameEl = panel.querySelector( '.ll-ba-crop__frame[data-side="' + side + '"]' );
      const b = panBounds( imgFor( side ), frameEl?.clientWidth, frameEl?.clientHeight,
        ( next.z ?? 100 ) / 100 );
      next = {
        x: clamp( Math.round( next.x ), Math.ceil( b.minX ), Math.floor( b.maxX ) ),
        y: clamp( Math.round( next.y ), Math.ceil( b.minY ), Math.floor( b.maxY ) ),
        z: next.z,
      };
      input.value = serialise( next );
      input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
      markCropped();
      paint( side );
    };

    const setActive = ( side ) => {
      active = side;
      panel.dataset.activeSide = side;
      panel.querySelectorAll( '.ll-ba-crop__frame' ).forEach( f =>
        f.classList.toggle( 'is-active', f.dataset.side === side ) );
      const lbl = panel.querySelector( '[data-active-label]' );
      if ( lbl ) lbl.textContent = side;
      paint( side );
    };

    const previousRatio = panel.dataset.ratioKey;
    const ratioChanged = previousRatio !== undefined && previousRatio !== ratioKey;
    panel.dataset.ratioKey = ratioKey;

    const seed = ( side ) => {
      const img = imgFor( side );
      if ( !img ) return;
      const go = () => {
        const v = parse( inputFor( side ).value );

        if ( solo && !croppedChk?.checked ) {
          paint( side );
          return;
        }

        if ( v.z === null ) {
          write( side, { x: v.x, y: v.y, z: fillFor( side ) } );
          return;
        }
        const frameEl = panel.querySelector( '.ll-ba-crop__frame[data-side="' + side + '"]' );
        const b = panBounds( img, frameEl?.clientWidth, frameEl?.clientHeight, ( v.z ?? 100 ) / 100 );
        const outOfBounds = v.x < Math.floor( b.minX ) || v.x > Math.ceil( b.maxX ) ||
          v.y < Math.floor( b.minY ) || v.y > Math.ceil( b.maxY );

        if ( ratioChanged ) {
          const oldFill = fillZoom( img, RATIOS[previousRatio] ?? 1 );
          const newFill = fillFor( side );
          if ( oldFill > 0 ) {
            write( side, { x: v.x, y: v.y, z: Math.round( v.z * ( newFill / oldFill ) ) } );
            return;
          }
        }
        if ( outOfBounds ) {
          write( side, { x: v.x, y: v.y, z: v.z } );
          return;
        }
        paint( side );
      };
      img.complete && img.naturalWidth ? go() : img.addEventListener( 'load', go, { once: true } );
    };

    sides.forEach( s => seed( s.key ) );
    setActive( active );

    panel.querySelectorAll( '.ll-ba-crop__frame' ).forEach( frameEl => {
      const side = frameEl.dataset.side;
      if ( !frameEl.querySelector( 'img' ) ) return;
      let from = null;

      frameEl.addEventListener( 'pointerdown', e => {
        e.preventDefault();                       // stops the browser's native image drag
        setActive( side );
        frameEl.setPointerCapture( e.pointerId );
        const v = parse( inputFor( side ).value );
        if ( !frameEl.clientWidth || !frameEl.clientHeight ) return;
        from = {
          px: e.clientX, py: e.clientY, x: v.x, y: v.y,
          z: v.z ?? defaultZoom( side ), w: frameEl.clientWidth, h: frameEl.clientHeight
        };
        activeStop = stop;
        frameEl.closest( '.ll-ba-crop__cell' )?.classList.add( 'is-panning' );
      } );

      frameEl.addEventListener( 'pointermove', e => {
        if ( !from ) return;
        if ( !( e.buttons & 1 ) ) return stop();
        e.preventDefault();
        const travel = ( from.z / 100 ) - 1;
        if ( travel <= 0.01 ) return;
        write( side, {
          x: clamp( from.x - ( ( e.clientX - from.px ) / from.w ) * 100 / travel, 0, 100 ),
          y: clamp( from.y - ( ( e.clientY - from.py ) / from.h ) * 100 / travel, 0, 100 ),
          z: from.z,
        } );
      } );

      const stop = () => {
        if ( activeStop === stop ) activeStop = null;
        from = null;
        frameEl.closest( '.ll-ba-crop__cell' )?.classList.remove( 'is-panning' );
      };
      frameEl.addEventListener( 'pointerup', stop );
      frameEl.addEventListener( 'pointercancel', stop );
      frameEl.addEventListener( 'lostpointercapture', stop );
    } );

    const ctl = panel.querySelector( '.ll-ba-crop__control' );
    ctl.querySelector( 'input[type="range"]' ).addEventListener( 'input', e => {
      const v = parse( inputFor( active ).value );
      write( active, { x: v.x, y: v.y, z: Number( e.target.value ) } );
    } );
    ctl.querySelector( '[data-fill]' ).addEventListener( 'click', () => {
      const v = parse( inputFor( active ).value );
      write( active, { x: v.x, y: v.y, z: fillFor( active ) } );
    } );
    ctl.querySelector( '[data-reset]' ).addEventListener( 'click', () => {
      const input = inputFor( active );
      input.value = '';
      input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
      if ( croppedChk?.checked ) {
        croppedChk.checked = false;
        croppedChk.dispatchEvent( new Event( 'change', { bubbles: true } ) );
      }
      panel.dataset.sig = '';
      build( row );
    } );

    const sliderChk = fieldIn( row, 'field_ll_ba_comparison_slider' )?.querySelector( 'input[type="checkbox"]' );
    [ratioSel, sliderChk, optionSel].forEach( el =>
      el?.addEventListener( 'change', () => { panel.dataset.sig = ''; build( row ); } ) );
  };

  const scan = () => document.querySelectorAll( '.acf-row' ).forEach( build );

  document.addEventListener( 'DOMContentLoaded', scan );
  if ( window.acf ) {
    window.acf.addAction( 'append', scan );
    window.acf.addAction( 'ready', scan );
    window.acf.addAction( 'change', () => setTimeout( scan, 120 ) );
  }

  const watched = document.querySelector( '#post' ) || document.querySelector( 'form' );
  if ( watched && window.MutationObserver ) {
    let pending;
    const observer = new MutationObserver( records => {
      const touchedImage = records.some( r =>
        ( r.target.nodeType === 1 && r.target.closest?.( '.acf-image-uploader' ) ) ||
        [...r.addedNodes].some( n => n.nodeType === 1 && (
          n.matches?.( '.acf-image-uploader, .acf-image-uploader img' ) ||
          n.querySelector?.( '.acf-image-uploader img' ) ) ) );
      if ( !touchedImage ) return;
      clearTimeout( pending );
      pending = setTimeout( scan, 150 );
    } );
    observer.observe( watched, {
      subtree: true, childList: true,
      attributes: true, attributeFilter: ['src']
    } );
  }
} )();
