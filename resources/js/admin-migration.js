/**
 * Before & Afters → Migrate from V2.
 *
 * Runs the migration as a series of small AJAX requests (setup, posts in batches, finish)
 * so big sites don't hit the server's time limit, then reloads to show the log.
 */
export function initMigrationPage() {
  const root = document.querySelector( '[data-ll-bag-migrate]' );
  if ( !root ) return;

  const { ajaxUrl, action, nonce } = root.dataset;
  const progressWrap = root.querySelector( '[data-migrate-progress]' );
  const progress = progressWrap?.querySelector( 'progress' );
  const status = root.querySelector( '[data-migrate-status]' );
  const buttons = root.querySelectorAll( '[data-migrate-run], [data-migrate-undo]' );

  const post = async ( step ) => {
    const body = new FormData();
    body.append( 'action', action );
    body.append( 'nonce', nonce );
    body.append( 'step', step );

    const response = await fetch( ajaxUrl, { method: 'POST', body, credentials: 'same-origin' } );
    let json = null;
    try {
      json = await response.json();
    } catch {
      throw new Error( `The server returned an unexpected response (HTTP ${response.status}).` );
    }
    if ( !json?.success ) {
      throw new Error( json?.data?.message || `Request failed (HTTP ${response.status}).` );
    }
    return json.data ?? {};
  };

  const setStatus = ( text ) => {
    if ( status ) status.textContent = text;
  };

  const start = () => {
    buttons.forEach( ( b ) => { b.disabled = true; } );
    progressWrap?.classList.remove( 'll-bag-hidden' );
  };

  const fail = ( error ) => {
    setStatus( `Stopped: ${error.message} Nothing has been lost. Reload the page and run it again to pick up where it left off.` );
    buttons.forEach( ( b ) => { b.disabled = false; } );
  };

  root.querySelector( '[data-migrate-run]' )?.addEventListener( 'click', async () => {
    start();
    try {
      setStatus( 'Setting up filters, terms and settings…' );
      const setup = await post( 'setup' );
      if ( progress ) {
        progress.max = Math.max( 1, setup.total );
        progress.value = setup.migrated;
      }

      let remaining = setup.total - setup.migrated;
      while ( remaining > 0 ) {
        setStatus( `Migrating posts… ${remaining} left` );
        const batch = await post( 'posts' );
        if ( progress ) progress.value = batch.migrated;
        remaining = batch.remaining;
        if ( !batch.processed ) break;
      }

      setStatus( 'Finishing up…' );
      await post( 'finish' );
      setStatus( 'Done. Loading the log…' );
      window.location.reload();
    } catch ( error ) {
      fail( error );
    }
  } );

  root.querySelector( '[data-migrate-undo]' )?.addEventListener( 'click', async () => {
    // eslint-disable-next-line no-alert
    const ok = window.confirm(
      'Undo the migration?\n\nThis permanently deletes every migrated V3 post (including any edits made to them since), the terms and filters the migration created, and puts changed settings back. V2 data is not affected.'
    );
    if ( !ok ) return;

    start();
    try {
      let deleted = 0;
      for ( ;; ) {
        setStatus( `Removing migrated posts… ${deleted} removed` );
        const result = await post( 'rollback' );
        deleted += result.deleted;
        if ( result.done ) break;
      }
      setStatus( 'Undone. Reloading…' );
      window.location.reload();
    } catch ( error ) {
      fail( error );
    }
  } );

  // Log level checkboxes
  root.querySelectorAll( '[data-migrate-filter]' ).forEach( ( checkbox ) => {
    checkbox.addEventListener( 'change', () => {
      root.querySelectorAll( `.ll-bag-migrate__log tr[data-level="${checkbox.value}"]` ).forEach( ( row ) => {
        row.classList.toggle( 'll-bag-hidden', !checkbox.checked );
      } );
    } );
  } );
}
