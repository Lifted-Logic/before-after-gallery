# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
npm run dev        # Vite dev server with HMR (writes a `hot` file; Vite::enqueueFrontendAssets() detects it)
npm run dev:https  # Same, but serves the dev server over HTTPS (self-signed cert) — use when the local WP site itself runs on HTTPS (e.g. a Local site with SSL enabled), since an http:// dev server gets mixed-content blocked on an https:// page
npm run build      # Production build to public/build/ with manifest
composer install   # PHP autoloader (PSR-4 via illuminate/container)
```

While `npm run dev`/`dev:https` is running, `Vite::enqueueHotEntry()` (`src/Support/Vite.php`) serves CSS/JS exclusively from the live Vite dev server — it deliberately does NOT also enqueue the built `public/build/assets` CSS as a "baseline," since a stale built stylesheet can silently outrank live-edited styles in the cascade. Only `enqueueBuiltEntry()` (used when the dev server isn't running) reads from `public/build`.

There are no test commands. No linter is configured.

---

## Architecture Overview

### Plugin Entry Point

`ll-bag-starter.php` defines four constants (`LL_BAG_VERSION`, `LL_BAG_FILE`, `LL_BAG_PATH`, `LL_BAG_URL`), requires `vendor/autoload.php`, then **directly requires** `src/Hooks/functions.php` before class instantiation. This is intentional — global helper functions must exist before any class is resolved from the container.

### Service Container

`src/Plugin.php` uses `illuminate/container` as a lightweight IoC container. All services are registered as singletons. `boot()` calls `->register()` on each service, which is where WordPress `add_action`/`add_filter` calls happen.

### Two-File Hook Pattern

Markup-level WordPress filters live in **two files with different scopes**:

- `src/Hooks/Hooks.php` — namespaced class with static methods. Each method builds markup and passes it through `apply_filters('lifted_logic/bag/{hook_name}', ...)`. Used in templates as `Hooks::method_name()`.
- `src/Hooks/functions.php` — **no namespace declaration**. Contains `bag_include_partial()` and any other global helper functions. This file is required directly from the plugin entry point, not autoloaded.

**Never define global functions in `Hooks.php`** — the namespace makes them inaccessible globally.

### Template Loading

`src/Frontend/TemplateLoader.php` intercepts `template_include` to serve plugin templates. All template resolution checks the active theme first:

```
{theme}/ll-before-after/{file}   ← theme override wins
templates/{file}                  ← plugin fallback
```

Use `TemplateLoader::get('partials/post-card.php', ['post' => $post])` to include a partial with variables extracted into scope. Use `TemplateLoader::render()` when you need the output as a string (e.g. in AJAX handlers). **Do not use `bag_include_partial()` for partials that should be theme-overridable** — it is hardcoded to the plugin directory and bypasses theme resolution.

**Theme components below the archive grid:** `archive-ll_before_after.php` conditionally renders the base theme's `templates/partials/components.php` (ACF flexible content layouts) below the grid. It uses `locate_template()` to check the file exists, then sets the global `$post` to the archive page and calls `setup_postdata()` before `get_template_part()`, followed by `wp_reset_postdata()`. This lets editors add theme-native components (hero banners, text blocks, etc.) below the B&A grid via the archive page's ACF flexible content field without any special integration.

### ACF Fields

All ACF field groups are registered programmatically via `acf_add_local_field_group()` in two places:

- `src/PostType/Fields.php` — fields on the `ll_before_after` post type and on taxonomy terms
- `src/Admin/SettingsPage.php` — fields on the options page (`ll-bag-settings`)

**Category taxonomy fields** (`src/BeforeAfterPostType/Fields.php`, `group_ll_ba_category`):
- `ll_ba_category_bg_image` — background image for the category card grid
- `ll_ba_category_hero_image` — hero image for the category page (future use)

**Category Settings tab** (`SettingsPage.php`):
- `ll_bag_use_category_archive` — master toggle; controls URL routing and field visibility. When enabled, a generated message field shows the resolved URL (`home_url() . '/' . BeforeAfterPostType::getRewriteSlug() . '/categories/'`) — built without relying on `get_post_type_archive_link()` because `acf/init` fires before `register_post_type` on `init`.
- `ll_ba_category_archive_hero` — group with content/link/image for the categories page hero
- `ll_ba_categories_subtitle` — subtitle text above the category grid

**NSFW Popup tab** (`SettingsPage.php`):
- `ll_bag_nsfw_popup_text` — body copy shown inside the NSFW confirmation modal; PHP fallback default is `'This before and after contains sensitive content.'`

**`get_option()` vs `get_field()` in early hooks:** Never call `get_field()` inside a `pre_get_posts` callback. ACF's `get_field()` calls `get_posts()` internally to look up field registration, which fires `pre_get_posts` again → infinite recursion → fatal error. The same `get_option('options_{field_name}')` pattern is also used for `init`-hooked logic that needs an options-page field before templates load — ACF's options fields aren't reliably available that early either. Use `get_option('options_{field_name}')` instead — it reads directly from the options table with no query. Examples: `get_option('options_ll_bag_use_category_archive')` in `registerRewriteRules()` / `scopeCategoryArchive()`, and `get_option('options_' . SettingsPage::FIELD_POSTS_PAGE)` in `BeforeAfterPostType::getRewriteSlug()` (called from `registerPostType()`/`registerRewriteRules()` on `init`). `TemplateLoader::loadTemplate()` (on `template_include`) is safe to use `get_field()` since it fires much later.

**Archive page fields** (`SettingsPage.php`, `registerArchivePageFields()`, hooked on `acf/init`): When a page is assigned via `FIELD_POSTS_PAGE`, the plugin registers ACF fields directly on that page's edit screen under a "Before & After Archive" meta box (`group_ll_ba_archive_page_fields`). Hero banner fields (`ll_ba_hero_banner` group — content/link/image) are included unless the theme has overridden `archive-hero-banner.php`. Themes that need additional fields on the archive page should register their own `acf_add_local_field_group()` in `functions.php` on `acf/init`, using `get_option('options_ll_bag_posts_page')` for the page ID and `'param' => 'post'` location rule. The block editor and classic editor are also disabled on this page: `disableBlockEditorForArchivePage()` filters `use_block_editor_for_post` to return `false`; `disableArchivePageEditor()` runs on `admin_init` and calls `remove_post_type_support('page', 'editor')` when `$_GET['post']` matches the archive page ID. Both use `get_option()` rather than `get_field()` because they fire before ACF options fields are available.

The images repeater field (`ll_ba_images`) is the core data structure for the single post. Each row has:
- `ll_ba_image_options` — `one-image` | `two-images` | `video`
- `ll_ba_image_ratio` — `wide` | `square` | `panorama` | `vertical`
- `ll_ba_single_image` — image ID (one-image + video)
- `ll_ba_before_image` / `ll_ba_after_image` — image IDs (two-images)
- `ll_ba_comparison_slider` — bool (two-images only)
- `ll_ba_video_url` / `ll_ba_video_title` — video fields

Templates map `ll_ba_image_ratio` values to CSS modifier classes (`ll-ba-single__ratio--wide`, etc.). These classes are defined at the top of `single-post.css` outside any nesting block so they work globally (archive cards and the single page gallery both use them).

### Filter System

`src/Filters/FilterManager.php` manages the archive filter sidebar. Filters are stored as a serialized option (`ll_bag_filters`). The "Card Display" taxonomy — the one whose terms appear as pills on post cards — is stored separately in `ll_bag_card_taxonomy` and read via `FilterManager::getCardTaxonomy()`. `PostTerms::forCard($postId)` is the standard way to fetch pill data in templates.

The AJAX filter handler (`src/Frontend/AjaxHandler.php`) handles two actions:
- `ll_bag_filter` — archive grid filtering; returns rendered `post-card.php` HTML
- `ll_bag_related` — related posts for the single page slider; runs three query passes (card taxonomy → override terms → recent fallback)

**Mobile active filter bar:** A second active-filter bar (`#ll-ba-active-bar-mobile`, `#ll-ba-active-tags-mobile`, `#ll-ba-clear-all-mobile`) is rendered in `archive-ll_before_after.php` immediately below `.ll-ba-filter-trigger`. `filters.js`'s `updateActiveTags()` populates and syncs both bars simultaneously. On mobile (≤767px) the sidebar's `#ll-ba-active-bar` is inside the flyout popup; this second bar stays visible in the page flow below the trigger button. On desktop the mobile bar is hidden via `.ll-ba-filters__active--mobile { display: none; }` in `archive.css`. Both bars' tag lists and clear-all buttons delegate to the same shared handler functions (`handleTagRemoval`, `handleClearAll`).

### CSS Architecture

No CSS framework. Plain CSS only — no `theme()` calls, no utility classes, no `@apply`.

CSS is split into:
- `resources/css/frontend.css` — entry point; imports partials
- `resources/css/partials/single-post.css` — BEM styles for the single post page
- `resources/css/partials/archive.css` — BEM styles for the archive, post card, and filter sidebar
- `resources/css/partials/hero-banner.css` — BEM styles for the archive hero banner component
- `resources/css/partials/nsfw-modal.css` — full-screen overlay modal for sensitive single posts
- `resources/css/primitives.css` — raw color values as `--hex-codes-*` custom properties
- `resources/css/ba-colors.css` — semantic UI color tokens (`--general-*`, `--filter-*`, etc.), each defined as `var(--hex-codes-*)`; overrideable from theme

When adding a new component partial, create a matching CSS file in `resources/css/partials/` and import it in `frontend.css`.

Colors come from CSS custom properties defined in `ba-colors.css` (e.g. `var(--general-background)`, `var(--general-body)`), which reference the raw values in `primitives.css`. Use `var(--property-name)` directly — there is no token abstraction layer. `TemplateLoader::enqueueCssOverrides()` enqueues `primitives.css` as a dependency of `ba-colors.css` (in that order); both follow the same theme-override resolution as other CSS partials — if a `var(--general-*)` or `var(--hex-codes-*)` resolves to nothing, check that both files are still wired up this way.

CSS nesting is acceptable. Use it for component-scoped child selectors (e.g. `.ll-ba-card { .ll-ba-card__image { ... } }`). Avoid deep nesting — keep selectors readable.

State toggling classes (`ll-ba-hidden`, `rotate-180`, `is-filtering`) are defined in `archive.css` and toggled by JavaScript. Use `ll-ba-hidden` (not `hidden`) for any show/hide state in plugin-owned elements to avoid polluting the global namespace.

**Empty-state messaging:** Use `.ll-ba-no-posts` (defined in `archive.css`, `grid-column: 1 / -1`) for "no results" text. It's shared between the initial empty archive (`archive-ll_before_after.php`) and the AJAX-filtered empty state (`filters.js`) so both look consistent.

### JavaScript

`resources/js/frontend.js` is the main entry point. It initializes:
- Header height CSS variable (`--ba-header-height`, accounts for `#wpadminbar`)
- Primary + nav Splide sliders (synced)
- Related posts Splide slider
- Comparison slider drag interaction
- Magnific Popup for the single post "Read More" modal

Other JS modules are imported from separate files:

| File | Purpose |
|---|---|
| `card.js` | Card link `ba_ref` tracking |
| `filters.js` | Archive filter sidebar, AJAX filtering, active tags, pagination |
| `pagination.js` | Pagination rendering helper |
| `related-posts.js` | AJAX-loaded related posts slider on single page |
| `nsfw-modal.js` | NSFW confirmation modal on sensitive single posts; exports `initNsfwModal()` |
| `sensitive.js` | Shared sensitive image helpers — `getSensitiveMode()`, `setSensitiveMode()`, `applySensitiveMode(container, mode)`, `updateSensitiveBar(bar, container)`. Queries both `.ll-ba-card`/`.ll-ba-card--sensitive` and `.ll-ba-slider-card`/`.ll-ba-slider-card--sensitive` — add new card class names here when introducing a card type that supports sensitive/blur |
| `cookieUtil.js` | `CookieUtil.getCookie(name)` / `setCookie(name, value, days)` — used by `sensitive.js` |
| `vendor/easy-toggle-state.js` | Declarative toggle library; activated via `data-toggle-*` attributes |

The `llBag` global (set via `wp_localize_script`) provides `ajaxUrl`, `nonce`, `action`, `relatedAction`, `relatedNonce`.

**jQuery plugins** are enqueued via WordPress (`wp_enqueue_script` with `['jquery']` dependency), not bundled through Vite. This avoids CJS/ESM interop issues with jQuery. Current plugins: Magnific Popup (`ll-bag-magnific-popup`, loaded in `TemplateLoader::enqueueMagnificPopup()`). Do not import jQuery-dependent libraries directly in `frontend.js`.

**Vendored assets:** Magnific Popup's CSS/JS are committed to `resources/vendor/magnific-popup/` (copied from `node_modules/magnific-popup/dist/`) rather than referenced from `node_modules/` directly, since `node_modules/` is gitignored and unavailable in production. `magnific-popup` stays in `devDependencies` purely as the source for that copy — Vite never bundles it. If bumping the version, re-copy `magnific-popup.css` and `jquery.magnific-popup.min.js` into `resources/vendor/magnific-popup/`.

**Sensitive image preference** is stored in the `ll-ba-sensitive-mode` cookie (default: `'blur'`). Always use `getSensitiveMode()` / `setSensitiveMode()` from `sensitive.js` — never read or write `localStorage` or a cookie directly for this value.

**NSFW single-post gate:** Posts flagged `ll_ba_is_nsfw` (`true_false` field in `src/BeforeAfterPostType/Fields.php`, Settings tab) show a full-screen confirmation modal on the single post page when the visitor's `ll-ba-sensitive-mode` cookie is not `'unblur'`. The gallery wrapper (`div.ll-ba-single__gallery`) gets the modifier class `ll-ba-single__gallery--sensitive` when the post is NSFW; `initNsfwModal()` in `nsfw-modal.js` adds `is-blurred` to it on load and removes it when the visitor accepts. The `.ll-ba-single__gallery--sensitive.is-blurred` rule in `single-post.css` applies `filter: blur(20px)` to both the main slider and the thumbnail nav. The modal markup is produced by `Hooks::bag_nsfw_modal_markup($message, $archive_url)` (filterable via `lifted_logic/bag/nsfw_modal_markup` — see `README.md`). The popup body copy comes from the `ll_bag_nsfw_popup_text` field (Settings → NSFW Popup tab). Three button actions are wired via `data-nsfw-action`:
- `unblur-once` — removes blur for this page view, cookie untouched
- `unblur-all` — removes blur and sets cookie to `'unblur'` via `setSensitiveMode()`
- `leave` (the × close button) — navigates back using `document.referrer` only if it is same-origin **and** a different URL from the current page (prevents an infinite reload loop that occurs when `document.referrer` equals the current URL on a page refresh); falls back to `data-fallback-url` (the B&A archive URL) for direct/external visits

### Theme Component Injection (`src/Integration/ThemeComponentInjector.php`)

Plugin components can be injected into the LL theme's flexible content field so they appear in the "Add Component" dropdown alongside native theme components. This works on both newer sites (PHP `ComponentProvider`) and older sites (JSON/DB-based ACF) because both use the same FC field key.

**How it works — three hooks per component:**

1. **`acf/load_field`** (`injectLayouts`) — intercepts when ACF loads the FC field (`field_5d0d37adc1475`) and appends the plugin's layout to `$field['layouts']`. This makes the layout appear in the admin dropdown.

2. **`{component-slug}_files`** (`injectRelatedBnaTemplate`) — intercepts the theme's `ll_include_component()` file search. Computes a relative path from the theme directory to the plugin's template using `..` traversal (PHP `file_exists()` resolves `..` at the OS level), so `locate_template()` finds the plugin file without needing theme changes.

3. **`lifted_logic/component/format_data/{layout_name}`** (`formatRelatedBnaData`) — the theme's `ll_format_component_data()` only passes through sub-fields whose key starts with `{layout_name}_`. This filter receives the raw `$data` array and manually maps field values to `$new_data`. Without this, `$component_data` arrives empty in the template.

**Critical layout definition rules** (older ACF Pro versions are strict):
- Every layout must include `'_name'`, `'display'`, `'layout'`, `'min'`, `'max'`
- Every sub_field must include both `'name'` and `'_name'` (set to the same value)
- Sub-field names must follow `{layout_name}_{field_name}` convention so `ll_format_component_data` strips the prefix and delivers them as `$component_data['{field_name}']`
- ACF's `ll_format_component_data` is NOT used for field delivery — the `format_data` filter handles this directly

**`format_data` keys must match template reads:** The `format_data` filter's `$new_data` keys must exactly match the `$component_data['{field_name}']` keys the template reads. A mismatch fails silently — no error, the template just falls back to its default value (e.g. always renders `theme-one` even if a different theme is picked in the editor). When adding or renaming a sub-field, grep the component template for `$component_data[` and confirm every key lines up with the corresponding `format*Data()` method.

**Theme picker sub-field:** If a layout needs a `color_theme` button-group field (matching `ComponentThemePickerFieldGroup`'s choices), use the shared `themePickerSubField(string $key, string $name): ?array` helper instead of duplicating the lookup logic. It returns `null` on sites without `ComponentThemePickerFieldGroup`:
```php
if ( $theme_field = $this->themePickerSubField( 'field_my_component_theme', 'll_ba_my_component_color_theme' ) ) {
  $sub_fields[] = $theme_field;
}
```

**Disabling components (must be in `functions.php`, checked on `after_setup_theme`):**

```php
// Disable all components (master switch)
add_filter( 'll_bag/register_components', '__return_false' );

// Disable one component
add_filter( 'll_bag/register_component/ll_ba_related_bna', '__return_false' );
add_filter( 'll_bag/register_component/ll_ba_grid',        '__return_false' );
add_filter( 'll_bag/register_component/ll_ba_slider',      '__return_false' );

// Disable one component when the theme owns it entirely
add_filter( 'll_bag/inject_component_fields/ll_ba_related_bna', '__return_false' );
add_filter( 'll_bag/inject_component_fields/ll_ba_grid',        '__return_false' );
add_filter( 'll_bag/inject_component_fields/ll_ba_slider',      '__return_false' );
```

`ll_bag/register_component/{layout_name}` and `ll_bag/inject_component_fields/{layout_name}` have identical effects — both fully disable a component (removed from `injectLayouts()`, `maybeRegisterHooks()`, and `registerLocalFields()`; layout, template serving, format_data filter, and AJAX field all gated). Use `inject_component_fields` as the semantic convention when a theme is registering its own version of a component and needs the plugin's copy out of the way.

**Current components** (all in `ThemeComponentInjector`):

| Layout name | Label | Slug (for file filter) |
|---|---|---|
| `ll_ba_related_bna` | Related Before & Afters | `ll-ba-related-bna` |
| `ll_ba_grid` | Before & Afters Grid | `ll-ba-grid` |
| `ll_ba_slider` | Before & After Slider | `ll-ba-slider` |

**Adding a new plugin component:**

1. Create `components/{ComponentName}/` with `.php`, `.css`, `.js` files
2. Import CSS and JS in `frontend.js` under `// Components`
3. Add a `private function {name}Layout(): array` to `ThemeComponentInjector` with all required keys
4. Call it in `injectLayouts()` alongside the existing layouts
5. Add a `{component-slug}_files` filter + inject method for the template
6. Add a `lifted_logic/component/format_data/{layout_name}` filter + format method for data mapping
7. If the component has a relationship field, also register it via `acf_add_local_field()` in `registerLocalFields()` — the AJAX handler needs this to find the field config when populating the selector
8. In the template, read fields via `$component_data['{field_name}']` — NOT `get_sub_field()`

**Why not `get_sub_field()`:** The theme uses `foreach (get_field('components'))`, not `have_rows()`. There is no ACF row context active when the template is included. All data arrives through `$component_data`.

**`ba_grid-cols-container` gotcha:** This theme class creates a 3-column grid (left bleed / content / right bleed). Every direct child that should be in the content column must have `grid-column: 2 / 3` in its CSS. Without it, the element is auto-placed into a bleed column and becomes invisible even though the HTML is correct and JS runs. This applies to pagination containers, sensitive image bars, and all sibling elements inside this wrapper.

**Client-side pagination:** Use `renderPagination(el, totalPages, currentPage, callback)` from `pagination.js`. Count `.ll-ba-card` elements with `querySelectorAll`, compute `Math.ceil(count / PAGE_SIZE)`, and pass a `showPage` function as the callback. The pagination container must have `grid-column: 2 / 3` to be visible inside `ba_grid-cols-container`. See `components/BeforeAndAftersGrid/before-and-afters-grid.js` for the reference implementation.

**Relationship fields in components:** The ACF relationship AJAX handler calls `acf_get_field($key)` to get the field config. If the field is only defined inside a layout's `sub_fields` (in memory via `acf/load_field`), it won't be found and the dropdown returns empty. Register it separately via `acf_add_local_field()` in `registerLocalFields()` to fix this.

### Dynamic CSS Variables

`TemplateLoader::enqueueCssOverrides()` enqueues `ba-colors.css` (checking theme override first), then outputs per-site values as inline CSS via `wp_add_inline_style`. When adding a new ACF color/style option from the settings page, register the field in `SettingsPage.php`, read it in `enqueueCssOverrides()`, and output it as a CSS variable on `:root`. Consume it in CSS via `var(--variable-name)`.

---

## Key Conventions

**Output escaping:** Escape at the point of output, not earlier. Use `esc_html()` for plain text, `esc_url()` for URLs, `esc_attr()` for attribute values, and `wp_kses_post()` for WYSIWYG/rich-text fields that may contain allowed HTML. Don't pre-escape a value into a variable and then escape it again at each output site — that double-encodes entities (e.g. `&amp;` becomes `&amp;amp;`). If a value is used in multiple contexts (e.g. both a `data-` attribute and visible text), keep the variable raw and apply the appropriate escaping function at each individual output point.

**Adding a new markup filter hook:** Add a `public static function` to `src/Hooks/Hooks.php` that builds `$markup` and returns `apply_filters('lifted_logic/bag/{name}', $markup, ...$parts)`. Call it as `Hooks::method_name()` in the template. Document it in `README.md` under the Hooks section. Current hooks: `bag_back_button_markup`, `bag_related_slider_arrows_markup`, `bag_link_card_markup`, `bag_nsfw_modal_markup`, `bag_filter_actions_markup`.

**`ba_ref` back-button behavior:** `card.js` appends `?ba_ref=<current-url>` to every `.ll-ba-card__link` click, including cards rendered in plugin components embedded on non-gallery pages. `Hooks::bag_back_button_markup()` validates that `ba_ref`'s URL path matches the B&A gallery archive path before honoring it — this preserves active filter state (query args) when coming from the gallery, but falls back to the plain unfiltered archive URL when the card was clicked from any other page on the site.

**ACF field tooltips:** To show a hover-tooltip (? icon) instead of always-visible inline instructions on an ACF field, add `'data-tooltip' => 'Your text here'` inside the field's `'wrapper'` array. `admin.js` picks up every `.acf-field[data-tooltip]` on `DOMContentLoaded` and injects a dashicon help button with a CSS tooltip bubble next to the field label. Do not use `'instructions'` and `'data-tooltip'` on the same field — pick one.

**Adding a global helper function:** Add it to `src/Hooks/functions.php` with a `function_exists` guard. Never add global functions to `Hooks.php`.

**Adding a new partial:** Create the file in `templates/partials/`. Include it via `TemplateLoader::get('partials/my-partial.php', $data)` (theme-overridable) or `bag_include_partial('my-partial', $data)` (plugin-only, no theme override). Add the override path to the header docblock.

**Slider card partial (`templates/partials/before-after-slider-post-card.php`):** A fork of `post-card.php` with all BEM classes renamed from `ll-ba-card` to `ll-ba-slider-card`. Used by the Before & After Slider component so its card can be styled and theme-overridden independently from the archive grid card. CSS lives in `components/BeforeAndAfterSlider/before-and-after-slider.css`. Note: `.ll-ba-slider-card__visual` is declared **outside** the nested `.ll-ba-slider-card {}` block — this mirrors the same pattern in `archive.css` where `.ll-ba-card__visual` sits in the "Sensitive card states" section, outside `.ll-ba-card {}`. When forking this card for another component, always copy the `__visual` rule explicitly — without it, images won't render.

**Slider card sensitive overlay:** When a slider card has `ll_ba_is_nsfw = true`, the partial calls `Hooks::bag_slider_card_sensitive_overlay_markup($message)` to render a centered white panel over the blurred card with "Unblur This Only" and "Unblur All" buttons. The overlay is only visible when the card has both `ll-ba-slider-card--sensitive` and `is-blurred` classes (the latter added by `applySensitiveMode()` in `before-and-after-slider.js`). If the visitor's `ll-ba-sensitive-mode` cookie is already `'unblur'`, `applySensitiveMode()` never adds `is-blurred` and the overlay stays hidden. Button click handlers (event-delegated in `before-and-after-slider.js`) use `data-slider-card-action`: `unblur-once` removes `is-blurred` from the single card; `unblur-all` sets the cookie via `setSensitiveMode('unblur')` and removes `is-blurred` from all `.ll-ba-slider-card--sensitive` elements. The overlay markup is filterable via `lifted_logic/bag/slider_card_sensitive_overlay_markup` — see README for full docs.

**BEM class naming in CSS:** All plugin BEM classes use the `ll-ba-` prefix — no exceptions. Examples: `ll-ba-card`, `ll-ba-single`, `ll-ba-single__gallery`, `ll-ba-comparison-slider`. Do not use the bare `ba-` prefix for plugin classes. Do not use bare utility class names (like `hidden`) that could conflict with theme styles — use `ll-ba-hidden` instead.

**What does NOT get the `ll-ba-` prefix:**
- Theme typography/button classes (`ba_hdg-medium`, `ba_btn-primary`) — these use `ba_` with an underscore and belong to the theme, not the plugin
- Splide library classes (`splide__arrow--prev`, `splide__arrows`, etc.)
- Third-party or WordPress classes (`wysiwyg`, `sr-only`, `js-init-video`)
