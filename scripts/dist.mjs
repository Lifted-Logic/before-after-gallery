#!/usr/bin/env node
/**
 * Build a release zip: npm run dist [patch|minor|major|x.y.z] [--skip-build] [--skip-composer]
 *
 * Bumps the version in all three places it lives, builds assets, strips dev
 * composer deps, zips everything .distignore doesn't exclude, then VERIFIES the
 * zip — canary files that must be present and ones that must not. The verify
 * step exists because a hand-rolled zip once shipped without resources/css and
 * took down the pills and sensitive bar on staging.
 *
 * Does not touch git: commit the bump yourself when the zip checks out.
 */
import { execSync } from 'node:child_process';
import { readFileSync, writeFileSync, existsSync, mkdirSync, statSync } from 'node:fs';
import { resolve, basename } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve( fileURLToPath( new URL( '..', import.meta.url ) ) );
const sh = ( cmd, opts = {} ) => execSync( cmd, { cwd: root, stdio: 'pipe', encoding: 'utf8', ...opts } );
const die = ( msg ) => { console.error( '\n✗ ' + msg ); process.exit( 1 ); };
const ok = ( msg ) => console.log( '✓ ' + msg );

const args = process.argv.slice( 2 );
const skipBuild = args.includes( '--skip-build' );
const skipComposer = args.includes( '--skip-composer' );
const bumpArg = args.find( a => !a.startsWith( '--' ) );

// ── Version ──────────────────────────────────────────────────────────────────
const mainFile = resolve( root, 'll-bag.php' );
let main = readFileSync( mainFile, 'utf8' );
const current = main.match( /define\('LL_BAG_VERSION',\s*'([\d.]+)'\)/ )?.[1];
if ( !current ) die( 'could not read LL_BAG_VERSION from ll-bag.php' );

let next = current;
if ( bumpArg ) {
  const [maj, min, pat] = current.split( '.' ).map( Number );
  next = bumpArg === 'patch' ? `${maj}.${min}.${pat + 1}`
    : bumpArg === 'minor' ? `${maj}.${min + 1}.0`
      : bumpArg === 'major' ? `${maj + 1}.0.0`
        : /^\d+\.\d+\.\d+$/.test( bumpArg ) ? bumpArg
          : die( `unrecognised bump "${bumpArg}" — use patch, minor, major, or x.y.z` );
}

if ( next !== current ) {
  // All three homes of the version. A count!=1 means the file drifted — stop.
  const edits = [
    [mainFile, `Version:      ${current}`, `Version:      ${next}`],
    [mainFile, `define('LL_BAG_VERSION', '${current}')`, `define('LL_BAG_VERSION', '${next}')`],
    [resolve( root, 'package.json' ), `"version": "${current}"`, `"version": "${next}"`],
  ];
  for ( const [file, from, to] of edits ) {
    const src = readFileSync( file, 'utf8' );
    if ( src.split( from ).length !== 2 ) die( `expected exactly one "${from}" in ${basename( file )}` );
    writeFileSync( file, src.replace( from, to ) );
  }
  ok( `version ${current} → ${next}` );
} else {
  ok( `packaging current version ${next} (no bump requested)` );
}

// ── Build ────────────────────────────────────────────────────────────────────
if ( !skipBuild ) { sh( 'npm run build', { stdio: 'inherit' } ); }
if ( !existsSync( resolve( root, 'public/build/.vite/manifest.json' ) ) )
  die( 'public/build/.vite/manifest.json missing — the plugin fatals without it. Run npm run build.' );
ok( 'vite manifest present' );

if ( !skipComposer ) { sh( 'composer install --no-dev --optimize-autoloader --quiet', { stdio: 'inherit' } ); }
if ( !existsSync( resolve( root, 'vendor/autoload.php' ) ) )
  die( 'vendor/autoload.php missing — run composer install --no-dev --optimize-autoloader' );
ok( 'composer autoloader present' );

// ── Stage + zip ──────────────────────────────────────────────────────────────
const slug = basename( root );
const dist = process.env.DIST_DIR ? resolve( process.env.DIST_DIR ) : resolve( root, 'dist' );
const stage = resolve( dist, slug );
const zipPath = resolve( dist, `${slug}-${next}.zip` );
mkdirSync( dist, { recursive: true } );
sh( `rm -rf "${stage}" "${zipPath}"` );
sh( `rsync -a --exclude-from=.distignore ./ "${stage}/"` );
sh( `cd "${dist}" && zip -qr "${zipPath}" "${slug}"` );
sh( `rm -rf "${stage}"` );

// ── Verify ───────────────────────────────────────────────────────────────────
const listing = sh( `unzip -l "${zipPath}"` );
const mustShip = [
  'll-bag.php',
  'public/build/.vite/manifest.json',
  'vendor/autoload.php',
  'resources/css/primitives.css',        // runtime-read, not Vite-bundled —
  'resources/css/ba-colors.css',         // stripping these broke staging once
  'resources/img/symbol-defs.svg',
  'resources/vendor/magnific-popup/magnific-popup.css',
  'resources/vendor/magnific-popup/jquery.magnific-popup.min.js',
];
const mustNotShip = ['/hot', 'node_modules/', '.git/', 'vendor/php-stubs', 'package.json', '.distignore'];
for ( const f of mustShip ) if ( !listing.includes( f ) ) die( `zip is missing ${f}` );
for ( const f of mustNotShip ) if ( listing.includes( f ) ) die( `zip must not contain ${f}` );
const shippedMain = sh( `unzip -p "${zipPath}" "${slug}/ll-bag.php"` );
if ( !shippedMain.includes( `'${next}'` ) ) die( 'zip contains the wrong plugin version' );
ok( 'zip verified: runtime files present, dev files absent, version correct' );

if ( !skipComposer ) { sh( 'composer install --quiet', { stdio: 'inherit' } ); ok( 'dev composer deps restored' ); }

const mb = ( statSync( zipPath ).size / 1048576 ).toFixed( 1 );
console.log( `\n→ ${zipPath} (${mb} MB)\nCommit the version bump when ready: git commit -am "bump version to ${next}"` );
