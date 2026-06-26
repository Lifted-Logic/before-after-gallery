# LL Before & After

WordPress plugin for the LL Before & After plugin.

## Requirements

- PHP 8.0+
- Node.js
- A local WordPress install

## Setup

**1. Install dependencies**

```bash
composer install
npm install
```

**2. Symlink into your local WordPress install plugin folder**

```bash
ln -s "/path/to/ll-bag" "/path/to/wordpress/wp-content/plugins/ll-bag"
```

**3. Activate the plugin**

Go to WP Admin → Plugins and activate **LL Before & After**.

## Development

Start the Vite dev server with hot module reloading:

```bash
npm run dev
```

## Production

Build assets for production:

```bash
npm run build
```

## Theme Overrides

Files in the plugin can be overridden from your theme by placing files at the corresponding path under `your-theme/ll-before-after/`. The plugin checks the theme location first and falls back to the plugin file — no configuration required.

### Templates

Copy any template from `templates/` into `your-theme/ll-before-after/` and the theme version will be used instead:

```
your-theme/
└── ll-before-after/
    ├── single-ll_before_after.php
    ├── archive-ll_before_after.php
    └── archive-ll_before_after_categories.php
```

### Partials

Any partial included via `TemplateLoader::get()` can also be overridden. Place the file at `your-theme/ll-before-after/partials/{filename}`:

```
your-theme/
└── ll-before-after/
    └── partials/
        ├── archive-hero-banner.php       # Archive page hero banner
        ├── categories-hero-banner.php    # Categories listing page hero banner
        ├── category-card.php             # Individual category card
        ├── post-card.php                        # Grid card for archive and related slider
        ├── before-after-slider-post-card.php    # Card used inside the Before & After Slider component
        └── filters.php                          # Filter sidebar
```

> **Note:** Partials included via `bag_include_partial()` (e.g. `fit-image`) are hardcoded to the plugin directory and cannot be overridden from the theme.

#### Categories archive page

The categories listing lives at `/{archive-slug}/categories/` and is controlled via **B&A Posts → Settings → Category Settings**:

- **Use category archive?** — master toggle. When off, the URL returns 404 and all fields below hide.
- **Category Archive Hero** — content/link/image for the hero banner on that page.
- **Categories Subtitle** — text shown above the category grid (defaults to "Select a category below to start exploring.").

Category cards link directly to the main archive pre-filtered by category (`?category={slug}`). There are no individual per-category archive pages — the archive's filter/restore URL logic handles the rest.

#### Adding fields to the Taxonomy Archive Settings tab

Register your own field group on `acf/init` targeting the `'ll-bag-settings'` options page slug. Multiple groups can coexist on the same page.

```php
// In your theme's functions.php
add_action( 'acf/init', function() {
    acf_add_local_field_group( [
        'key'      => 'group_my_bag_settings',   // must be unique to your theme
        'title'    => 'My Custom Settings',
        'fields'   => [
            [
                'key'   => 'field_my_custom_field',
                'label' => 'My Custom Field',
                'name'  => 'my_custom_field',
                'type'  => 'text',
            ],
        ],
        'location' => [
            [ [ 'param' => 'options_page', 'operator' => '==', 'value' => 'll-bag-settings' ] ],
        ],
    ] );
} );
```

Read the value in any template with `get_field( 'my_custom_field', 'option' )`.

#### Independent hero banners

The archive page (`archive-ll_before_after.php`) and the categories page (`archive-ll_before_after_categories.php`) each have their own hero banner partial:

| Partial | Page | ACF field |
|---|---|---|
| `archive-hero-banner.php` | Main archive | `ll_ba_hero_banner` (Archive Settings tab) |
| `categories-hero-banner.php` | Categories listing | `ll_ba_category_archive_hero` (Category Settings tab) |

A theme can override either file independently. `categories-hero-banner.php` is not a delegate — it reads from its own distinct ACF field.

#### Hero Banner ACF fields

The plugin registers ACF fields for `archive-hero-banner.php` directly on the page set as the archive in **B&A Posts → Settings → Archive Settings → All Posts Archive Page**. They appear as a "Before & After Archive" meta box on that page's edit screen. When a theme overrides that partial, those fields are automatically removed from the admin — the plugin detects the override at registration time and skips them.

When you override the partial, register your own fields on the archive page using the approach described in [Archive page ACF fields](#archive-page-acf-fields) below. Read values in your template with `get_field( 'my_theme_hero_heading', $page_id )`, where `$page_id = (int) get_field( 'll_bag_posts_page', 'option' )`.

To force the plugin's default hero banner fields to register even when your override file is present:

```php
add_filter( 'll_bag/hero_banner_fields_enabled', '__return_true' );
```

#### Archive page ACF fields

Register your own field group on the archive page by reading the archive page ID from the plugin's stored option and passing it as a `post` location rule.

```php
// In your theme's functions.php
add_action( 'acf/init', function() {
    $page_id = (int) get_option( 'options_ll_bag_posts_page' );
    if ( !$page_id ) return;

    acf_add_local_field_group( [
        'key'      => 'group_my_archive_fields',   // must be unique to your theme
        'title'    => 'My Custom Archive Fields',
        'fields'   => [
            [
                'key'   => 'field_my_archive_custom_field',
                'label' => 'My Custom Field',
                'name'  => 'my_archive_custom_field',
                'type'  => 'text',
            ],
        ],
        'location' => [
            [ [ 'param' => 'post', 'operator' => '==', 'value' => $page_id ] ],
        ],
    ] );
} );
```

Read values in your template with `get_field( 'my_archive_custom_field', $page_id )`, where `$page_id = (int) get_field( 'll_bag_posts_page', 'option' )`.

### CSS

Override plugin CSS files by placing them at `your-theme/ll-before-after/css/{filename}`:

```
your-theme/
└── ll-before-after/
    └── css/
        └── ba-colors.css   # Override plugin color variables
```

Overrideable CSS files:

| File | Purpose |
|------|---------|
| `ba-colors.css` | CSS custom properties for UI colors |

### Header & Footer

By default the archive templates call `get_header()` and `get_footer()`. Use these filters to swap the template name or skip them entirely:

```php
// Load a named variant — calls get_header('minimal') which loads header-minimal.php
add_filter( 'll_bag/header_template', fn() => 'minimal' );
add_filter( 'll_bag/footer_template', fn() => 'minimal' );

// Skip header and/or footer completely
add_filter( 'll_bag/header_template', fn() => false );
add_filter( 'll_bag/footer_template', fn() => false );
```

Returning `''` (the default) calls `get_header()`/`get_footer()` with no argument — standard theme behavior.

---

## Hooks

The plugin exposes WordPress filters so themes can override specific pieces of markup without copying full templates. Each hook is a static method on `Hooks` — search the codebase for the method name to find its definition and usage.

---

### `bag_back_button_markup`

Filter: `lifted_logic/bag/bag_back_button_markup`

Overrides the back-to-gallery link at the top of the single post sidebar. The `$href` defaults to the post type archive URL, falling back to `site_url('/')` if no archive is configured. If a `ba_ref` query param is present **and its path matches the gallery archive page**, it is used instead (preserves filtered archive state — e.g. active filter query args). If `ba_ref` points to some other page on the site (e.g. a B&A card embedded outside the gallery via a plugin component), it is ignored and the plain archive URL is used.

**Default markup:**

```html
<a href="{archive-url}" class="bag_back-text bag-inline-block">Back to Gallery</a>
```

**Parameters passed to the filter:**
| # | Variable | Type | Description |
|---|----------|------|-------------|
| 1 | `$markup` | `string` | Full anchor tag HTML |
| 2 | `$classes` | `string` | CSS classes on the anchor |
| 3 | `$text` | `string` | Link text |
| 4 | `$href` | `string` | Link URL |

The example below reproduces the plugin default exactly — copy, paste into your theme, then modify:

```php
add_filter( 'lifted_logic/bag/bag_back_button_markup', function( $markup, $classes, $text, $href ) {
    return '<a href="' . $href . '" class="' . $classes . '">' . $text . '</a>';
}, 10, 4 );
```

---

### `bag_related_slider_arrows_markup`

Filter: `lifted_logic/bag/related_slider_arrows_markup`

Overrides the previous/next arrow buttons on the related posts slider. The buttons **must keep** `splide__arrow--prev` and `splide__arrow--next` classes — Splide.js uses these to wire up navigation.

The filter receives `$prev` and `$next` as separate strings so you can replace only one button while keeping the other, or rearrange them within a custom wrapper.

**Default markup:**

```html
<div class="ll-ba-single__related-arrows splide__arrows">
  <button class="ll-ba-single__related-arrow ll-ba-single__related-arrow--prev splide__arrow--prev">
    <svg class="ll-ba-single__related-arrow-icon icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
    <span class="sr-only">Previous Slide</span>
  </button>
  <button class="ll-ba-single__related-arrow ll-ba-single__related-arrow--next splide__arrow--next">
    <svg class="ll-ba-single__related-arrow-icon icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
    <span class="sr-only">Next Slide</span>
  </button>
</div>
```

**Parameters passed to the filter:**
| # | Variable | Type | Description |
|---|----------|------|-------------|
| 1 | `$markup` | `string` | Full wrapper `<div>` containing both buttons |
| 2 | `$prev` | `string` | Previous button HTML only |
| 3 | `$next` | `string` | Next button HTML only |

The example below reproduces the plugin default exactly — copy, paste into your theme, then modify:

```php
add_filter( 'lifted_logic/bag/related_slider_arrows_markup', function( $markup, $prev, $next ) {
    return '
      <div class="ll-ba-single__related-arrows splide__arrows">
        <button class="ll-ba-single__related-arrow ll-ba-single__related-arrow--prev splide__arrow--prev">
          <svg class="ll-ba-single__related-arrow-icon icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
          <span class="sr-only">Previous Slide</span>
        </button>
        <button class="ll-ba-single__related-arrow ll-ba-single__related-arrow--next splide__arrow--next">
          <svg class="ll-ba-single__related-arrow-icon icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
          <span class="sr-only">Next Slide</span>
        </button>
      </div>
    ';
}, 10, 3 );
```

---

### `bag_link_card_markup`

Filter: `lifted_logic/bag/link_card_markup`

Overrides the CTA link card in the single post sidebar. The card only renders when a CTA link is configured on the **B&A Posts → Settings** options page (Global Single Page Options tab) — it is skipped entirely when the link field is empty.

`$title` and `$link` come from the `ll_ba_global_cta_title` and `ll_ba_global_cta_link` ACF options fields.

**Default markup:**

```html
<div class="ll-ba-single__cta-card">
  <p class="ll-ba-single__cta-title ba_hdg-small">{title}</p>
  <a class="ll-ba-single__cta-button ba_btn-primary" href="{url}">{link title}</a>
</div>
```

**Parameters passed to the filter:**
| # | Variable | Type | Description |
|---|----------|------|-------------|
| 1 | `$markup` | `string` | Full card HTML |
| 2 | `$title` | `string` | Card heading text (from options page) |
| 3 | `$link` | `array` | ACF link array — keys: `url`, `title`, `target` |

The example below reproduces the plugin default exactly — copy, paste into your theme, then modify:

```php
add_filter( 'lifted_logic/bag/link_card_markup', function( $markup, $title, $link ) {
    $href      = $link['url'] ?? '';
    $link_text = $link['title'] ?? '';
    $target    = $link['target'] ? 'target="' . $link['target'] . '"' : '';
    $sr_text   = $link['target'] === '_blank' ? '<span class="sr-only"> (opens in new tab)</span>' : '';

    return '
      <div class="ll-ba-single__cta-card">
        <p class="ll-ba-single__cta-title ba_hdg-small">' . $title . '</p>
        <a class="ll-ba-single__cta-button ba_btn-primary" href="' . $href . '" ' . $target . '>' . $link_text . ' ' . $sr_text . '</a>
      </div>
    ';
}, 10, 3 );
```

---

### `bag_nsfw_modal_markup`

Filter: `lifted_logic/bag/nsfw_modal_markup`

Overrides the sensitive-content confirmation modal shown on single posts where the "Sensitive Images" (`ll_ba_is_nsfw`) field is enabled, unless the visitor's `ll-ba-sensitive-mode` cookie is already `unblur`. The modal markup must keep the `#ll-ba-nsfw-modal` id and `ll-ba-hidden` class — `resources/js/nsfw-modal.js` looks up the modal by id and toggles `ll-ba-hidden`. Buttons are wired up via `data-nsfw-action` values (`unblur-once`, `unblur-all`, `leave`); the close button additionally needs `data-fallback-url` for same-origin-referrer-less visits.

`$message` comes from the **NSFW Popup → NSFW Popup Text** field on the **B&A Posts → Settings** options page (defaults to "This before and after contains sensitive content." if empty). `$archive_url` is the Before & After post type archive link, used as the close button's fallback URL.

The filter also receives `$actions` — the `.ll-ba-nsfw-modal__actions` button group HTML — as its own string, since themes will most often want to swap out just the buttons while leaving the rest of the modal untouched.

**Parameters passed to the filter:**
| # | Variable | Type | Description |
|---|----------|------|-------------|
| 1 | `$markup` | `string` | Full modal HTML |
| 2 | `$message` | `string` | Popup message text (from options page, unescaped) |
| 3 | `$archive_url` | `string` | B&A archive URL, used as the close button's fallback |
| 4 | `$actions` | `string` | `.ll-ba-nsfw-modal__actions` button group HTML only |

The example below reproduces the plugin default exactly — copy, paste into your theme, then modify:

```php
add_filter( 'lifted_logic/bag/nsfw_modal_markup', function( $markup, $message, $archive_url, $actions ) {
    $fallback_url = esc_url( $archive_url );
    $message_html = esc_html( $message );

    $actions = '
      <div class="ll-ba-nsfw-modal__actions">
          <button type="button" class="ll-ba-nsfw-modal__btn ba_btn-primary" data-nsfw-action="unblur-once">Unblur This Only</button>
          <button type="button" class="ll-ba-nsfw-modal__btn ll-ba-nsfw-modal__btn--secondary" data-nsfw-action="unblur-all">
            <svg class="icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
            Unblur All
            <svg class="icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
          </button>
      </div>
    ';

    return '
      <div class="ll-ba-nsfw-modal ll-ba-hidden" id="ll-ba-nsfw-modal" role="dialog" aria-modal="true" aria-label="Sensitive content">
          <div class="ll-ba-nsfw-modal__panel ll-ba-popup-modal">
              <button type="button" class="ll-ba-nsfw-modal__close" data-nsfw-action="leave" data-fallback-url="' . $fallback_url . '" aria-label="Go back">
                  <svg class="icon icon-exit" aria-hidden="true"><use xlink:href="#icon-exit"></use></svg>
              </button>
              <p class="ll-ba-nsfw-modal__message">' . $message_html . '</p>
              ' . $actions . '
          </div>
      </div>
    ';
}, 10, 4 );
```

To replace only the action buttons while keeping the rest of the modal untouched:

```php
add_filter( 'lifted_logic/bag/nsfw_modal_markup', function( $markup, $message, $archive_url, $actions ) {
    $custom_actions = '
      <div class="ll-ba-nsfw-modal__actions">
          <button type="button" class="ll-ba-nsfw-modal__btn ba_btn-primary" data-nsfw-action="unblur-once">Unblur This Only</button>
          <button type="button" class="ll-ba-nsfw-modal__btn ll-ba-nsfw-modal__btn--secondary" data-nsfw-action="unblur-all">
            <svg class="icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
            Unblur All
            <svg class="icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
          </button>
      </div>
    ';

    return str_replace( $actions, $custom_actions, $markup );
}, 10, 4 );
```

---

### `bag_filter_actions_markup`

Filter: `lifted_logic/bag/filter_actions_markup`

Overrides the Clear / Apply button bar rendered at the bottom of the mobile filter flyout. This bar is only visible on viewports narrower than 768px — on desktop it is hidden via CSS. The markup must keep `id="ll-ba-filter-clear-mobile"` on the Clear button and `id="ll-ba-filter-apply"` on the Apply button; `resources/js/filters.js` wires the open/close and clear logic to those ids.

**Default markup:**

```html
<div class="ll-ba-filter-actions">
  <button type="button" id="ll-ba-filter-clear-mobile" class="ll-ba-filter-clear-mobile">Clear</button>
  <button type="button" id="ll-ba-filter-apply" class="ll-ba-filter-apply">Apply</button>
</div>
```

**Parameters passed to the filter:**
| # | Variable | Type | Description |
|---|----------|------|-------------|
| 1 | `$markup` | `string` | Full action bar HTML |

```php
add_filter( 'lifted_logic/bag/filter_actions_markup', function( $markup ) {
    return '
      <div class="ll-ba-filter-actions">
        <button type="button" id="ll-ba-filter-clear-mobile" class="ll-ba-filter-clear-mobile">Reset</button>
        <button type="button" id="ll-ba-filter-apply" class="ll-ba-filter-apply">Show Results</button>
      </div>
    ';
} );
```

---

### `bag_slider_card_sensitive_overlay_markup`

Filter: `lifted_logic/bag/slider_card_sensitive_overlay_markup`

Overrides the sensitive-content overlay panel shown on slider cards (Before & After Slider component) when a post has the "Sensitive Images" (`ll_ba_is_nsfw`) field enabled. The overlay is only shown when the visitor's `ll-ba-sensitive-mode` cookie is not `unblur` — if the cookie is already `unblur`, `applySensitiveMode()` never adds `is-blurred` to the card, so the overlay remains hidden.

`$message` is the same text from **B&A Posts → Settings → NSFW Popup → NSFW Popup Text** (defaults to "This before and after contains sensitive content." if empty).

The two buttons in the overlay are wired via `data-slider-card-action` attributes handled by `before-and-after-slider.js`:
- `unblur-once` — removes `is-blurred` from the single card only; cookie is untouched
- `unblur-all` — sets cookie to `unblur` via `setSensitiveMode()` and removes `is-blurred` from all `.ll-ba-slider-card--sensitive` elements on the page

The filter also receives `$actions` — the `.ll-ba-slider-card__sensitive-actions` button group HTML — so themes can swap just the buttons while keeping the rest of the panel.

**Default markup:**

```html
<div class="ll-ba-slider-card__sensitive-overlay" aria-label="Sensitive content">
  <div class="ll-ba-slider-card__sensitive-panel">
    <p class="ll-ba-slider-card__sensitive-message">{message}</p>
    <div class="ll-ba-slider-card__sensitive-actions">
      <button type="button" class="ll-ba-slider-card__sensitive-btn ll-ba-slider-card__sensitive-btn--primary" data-slider-card-action="unblur-once">Unblur This Only</button>
      <button type="button" class="ll-ba-slider-card__sensitive-btn ll-ba-slider-card__sensitive-btn--secondary" data-slider-card-action="unblur-all">
        Unblur All
        <svg class="icon icon-arrow-right" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
      </button>
    </div>
  </div>
</div>
```

**Parameters passed to the filter:**
| # | Variable | Type | Description |
|---|----------|------|-------------|
| 1 | `$markup` | `string` | Full overlay HTML |
| 2 | `$message` | `string` | Popup message text (from options page, unescaped) |
| 3 | `$actions` | `string` | `.ll-ba-slider-card__sensitive-actions` button group HTML only |

The example below replaces only the action buttons, reusing the rest of the default panel:

```php
add_filter( 'lifted_logic/bag/slider_card_sensitive_overlay_markup', function( $markup, $message, $actions ) {
    $custom_actions = '
      <div class="ll-ba-slider-card__sensitive-actions">
        <button type="button" class="ll-ba-slider-card__sensitive-btn ll-ba-slider-card__sensitive-btn--primary" data-slider-card-action="unblur-all">Show Content</button>
      </div>
    ';

    return str_replace( $actions, $custom_actions, $markup );
}, 10, 3 );
```

---

### `single_sidebar_classes`

Filter: `lifted_logic/bag/single_sidebar_classes`

Injects additional CSS classes onto the `.ll-ba-single__sidebar` element on the single post page. Each value is passed through `sanitize_html_class()` before output.

**Parameters passed to the filter:**
| # | Variable | Type | Description |
|---|----------|------|-------------|
| 1 | `$classes` | `array` | Classes to add (empty by default) |

```php
add_filter( 'lifted_logic/bag/single_sidebar_classes', function ( $classes ) {
    $classes[] = 'my-custom-class';
    return $classes;
} );
```

---

### `archive_inner_classes`

Filter: `lifted_logic/bag/archive_inner_classes`

Injects additional CSS classes onto the `.ll-ba-archive__inner` wrapper on the main archive page. Each value is passed through `sanitize_html_class()` before output.

**Parameters passed to the filter:**
| # | Variable | Type | Description |
|---|----------|------|-------------|
| 1 | `$classes` | `array` | Classes to add (empty by default) |

```php
add_filter( 'lifted_logic/bag/archive_inner_classes', function ( $classes ) {
    $classes[] = 'has-sidebar';
    return $classes;
} );
```

---

### `grid_classes`

Filter: `lifted_logic/bag/grid_classes`

Injects additional CSS classes onto the Before & Afters Grid wrapper element (`.ll-ba-bag-grid`). Each value is passed through `sanitize_html_class()` before output.

**Parameters passed to the filter:**
| # | Variable | Type | Description |
|---|----------|------|-------------|
| 1 | `$classes` | `array` | Classes to add (empty by default) |

```php
add_filter( 'lifted_logic/bag/grid_classes', function ( $classes ) {
    $classes[] = 'theme-two';
    $classes[] = 'component-spacing';
    return $classes;
} );
```

---

## Plugin Components

Plugin components appear in the LL theme's "Add Component" flexible content dropdown alongside native theme components. They work on both newer PHP-`ComponentProvider` sites and older JSON/DB-based sites — both share the same FC field key.

### Current components

| Component | Label in admin | Layout name | Folder |
|---|---|---|---|
| Related Before & Afters | Related Before & Afters | `ll_ba_related_bna` | `components/RelatedBeforeAndAfters/` |
| Before & Afters Grid | Before & Afters Grid | `ll_ba_grid` | `components/BeforeAndAftersGrid/` |
| Before & After Slider | Before & After Slider | `ll_ba_slider` | `components/BeforeAndAfterSlider/` |

### File structure

Each component lives under `components/{ComponentName}/`:

```
components/RelatedBeforeAndAfters/
├── related-before-and-afters.php    # Template (rendered by the theme's component system)
├── related-before-and-afters.css    # Scoped styles (imported in frontend.js)
└── related-before-and-afters.js     # Behavior (imported in frontend.js)
```

CSS and JS are imported in `resources/js/frontend.js` under `// Components`. The PHP template is served to the theme via a computed relative path — no files need to be copied into the theme.

### Client-side pagination

The Before & Afters Grid uses client-side pagination via `renderPagination()` from `pagination.js`. Add a `.{component}__pagination` container div to the template, then in the component JS:

```js
import { renderPagination } from '../../resources/js/pagination.js';

const PAGE_SIZE = 12;
const cards      = [...grid.querySelectorAll('.ll-ba-card')];
const totalPages = Math.ceil(cards.length / PAGE_SIZE);

const showPage = (page) => {
    const start = (page - 1) * PAGE_SIZE;
    cards.forEach((card, i) => {
        card.style.display = (i >= start && i < start + PAGE_SIZE) ? '' : 'none';
    });
    renderPagination(paginationEl, totalPages, page, showPage);
};

if (totalPages > 1 && paginationEl) showPage(1);
```

`renderPagination` is reused from the archive — same UI, different callback. The archive uses an AJAX callback; components use the client-side `showPage` function.

### `ba_grid-cols-container` gotcha

The LL theme's `ba_grid-cols-container` class creates a 3-column CSS grid (left bleed / content / right bleed). Every direct child that should appear in the visible content area must have `grid-column: 2 / 3` in its CSS. Without it, the element is auto-placed into a bleed column and becomes invisible — even though the HTML is correct and the JS runs. This affects pagination containers, sensitive image bars, and any other sibling elements of the main content area.

### Reading field data in the template

The theme uses `foreach (get_field('components'))`, **not** `have_rows()`. There is no ACF row context, so `get_sub_field()` always returns null. All field data arrives through `$component_data`:

```php
// In your component template:
$content = $component_data['content'] ?? '';
$link    = $component_data['link']    ?? [];
```

### Field naming convention

Sub-fields must be named `{layout_name}_{field_name}` so the theme's `ll_format_component_data()` strips the prefix and delivers them as `$component_data['{field_name}']`:

- Layout name: `ll_ba_related_bna`
- Sub-field name: `ll_ba_related_bna_content` → arrives as `$component_data['content']`

### Adding a new plugin component

All injection logic lives in `src/Integration/ThemeComponentInjector.php`. For each new component, add:

1. A `private function {name}Layout(): array` with the layout definition
2. A call to it inside `injectLayouts()`
3. A `{component-slug}_files` filter + inject method (serves the template file)
4. A `lifted_logic/component/format_data/{layout_name}` filter + format method (maps field data to `$component_data`)

**Required keys on every layout definition** (older ACF Pro versions are strict):

```php
[
    'key'        => 'layout_my_component',
    'name'       => 'my_component',
    '_name'      => 'my_component',      // required by older ACF Pro
    'label'      => 'My Component',
    'display'    => 'block',             // required by older ACF Pro
    'layout'     => 'block',
    'min'        => '',                  // required by older ACF Pro
    'max'        => '',                  // required by older ACF Pro
    'sub_fields' => [
        [
            'key'   => 'field_my_component_content',
            'label' => 'Content',
            'name'  => 'my_component_content',
            '_name' => 'my_component_content',   // required by older ACF Pro
            'type'  => 'wysiwyg',
        ],
    ],
]
```

### Disabling plugin components on a specific theme

All filters below must be added to the theme's `functions.php`. They are checked on `after_setup_theme`, so they must be registered before that hook fires.

**Disable all components (master switch):**

```php
add_filter( 'll_bag/register_components', '__return_false' );
```

**Disable one component:**

```php
add_filter( 'll_bag/register_component/ll_ba_related_bna', '__return_false' );
add_filter( 'll_bag/register_component/ll_ba_grid',        '__return_false' );
add_filter( 'll_bag/register_component/ll_ba_slider',      '__return_false' );
```

**Disable a component and take full ownership from the theme:**

```php
add_filter( 'll_bag/inject_component_fields/ll_ba_related_bna', '__return_false' );
add_filter( 'll_bag/inject_component_fields/ll_ba_grid',        '__return_false' );
add_filter( 'll_bag/inject_component_fields/ll_ba_slider',      '__return_false' );
```

`ll_bag/inject_component_fields/{layout_name}` is a semantic alias for per-component disable — it has the identical effect as `ll_bag/register_component/{layout_name}`. Use it when the theme is registering its own version of a component (layout definition, ACF fields, and template) and needs the plugin's copy out of the way entirely. When this filter returns false, the plugin does not inject the layout into the "Add Component" dropdown, does not register any ACF sub-fields, does not hook into the `_files` template lookup, and does not register a `format_data` mapping.

---

## Structure

```
ll-bag/
├── src/
│   ├── Plugin.php                    # Boots container, registers hooks
│   ├── PostType/
│   │   └── BeforeAfterPostType.php   # CPT registration
│   ├── Admin/
│   │   └── AdminMenu.php             # Asset enqueuing
│   └── Support/
│       └── Vite.php                  # HMR + manifest helper
└── resources/
    ├── js/admin.js                   # Admin JS entry point
    └── css/admin.css                 # Admin CSS entry point (Tailwind)
```
