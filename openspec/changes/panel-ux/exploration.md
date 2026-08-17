# Exploration: Panel UX — AJAX category search, manual product picker, i18n fill

> SDD change: `panel-ux` | artifact: `explore` | date: 2026-08-17
> Scope: (1) replace the `product_cat` multiselect with a Select2 AJAX search input, (2) add a
> Select2 `wc-product-search` picker with a sortable list of assigned products, (3) fill missing
> admin i18n strings. "Arrows outside the carousel" is OUT of scope (separate change).

## Current State

The plugin is a classic single-plugin repo (`custom-woo-pro-carousel.php` at root, module loader
with an explicit require-map, no Composer autoloader, no build step — FA-1 self-contained).
WooCommerce is a hard dependency (auto-deactivate + admin notice when absent). All admin work
lives in `includes/class-admin.php` (CWC_Admin), assets in `includes/class-assets.php` (CWC_Assets).

### Data flow (verified by reading the code)

`[cwc_carousel]` → `CWC_Shortcode::render()` → `shortcode_atts` whitelist → `CWC_Settings::resolve()`
(17-key contract) → `CWC_Query::query()` / `get_categories()` / `get_child_categories()` →
`CWC_Renderer::render()` → escaped HTML. Admin writes land in the registry
(`cwc_carousel_registry`, autoload off) via the Settings API (`cwc_options_group`, per-slug
`sanitize_registry()`); create/delete run on `admin_init` through `handle_registry_actions()`;
per-category image/title overrides run on `admin_init` through `save_category_images()` (nonce
`cwc_save_category_image` + `manage_woocommerce`).

### Exact storage formats

| Data | Where | Format |
|------|-------|--------|
| Named instances | option `cwc_carousel_registry` (autoload off) | `{ slug => 17-key config }` |
| Category selection | `config['categories']` | `int[]` of `product_cat` term IDs, positive, reindexed — coerced by `CWC_Settings::sanitize_ids()` (`array_map('absint')` → filter `> 0`) |
| Category image override | term meta `cwc_cat_image` on `product_cat` | attachment ID `int`; **global per term**, not per instance |
| Category overlay title | term meta `cwc_cat_title` on `product_cat` | `sanitize_text_field` string; empty deletes meta |
| Product selection | **none** — query-based | `type=product` + `count` (limit) + optional `categories`/`category` tax filter, `orderby=date DESC` |
| Legacy manual IDs | shortcode attr `ids` only | `wp_parse_id_list` + `absint`, `include` + `orderby=post__in` (order preserved), `limit` NOT passed → full list renders |

The 17-key contract (`CWC_Settings::builtins()`/`normalize()`, CM-9): `type, title, category,
categories, mix, count, slides, slides_tablet, slides_mobile, gap, arrows, pagination, buy,
buy_text, cover, subcategories, title_align`. **There is no `products` key** — a manual product
list in the admin requires an 18th contract key. The legacy `cwc_carousel_options` option stays
untouched as the rollback path (read only by `defaults()`).

### Current category field (`render_categories_field`)

`<select name="cwc_carousel_registry[slug][categories][]" multiple size="6" class="cwc-categories-select">`
built from `get_terms(product_cat, hide_empty=false)` — every term, Ctrl/Cmd-multiselect, no
search. The `cwc-categories-select` class is inert (no CSS/JS references). Field renders for BOTH
carousel types (for `type=product` the `categories` list acts as a product_cat IN filter, PQ-5).

### Current product handling

No manual picker anywhere in the admin. Product carousels render "latest N products" (`count`,
date DESC). The only manual-ID path is the **legacy shortcode `ids` attribute** (SC-3/PQ-2),
which ignores `count` and ignores the `categories` filter.

### Admin assets today (`CWC_Assets::enqueue_admin_assets`)

Gated to `manage_woocommerce` + screen id containing `cwc-carousel`; enqueues only
`wp_enqueue_media()` + `cwc-carousel-admin` (`assets/js/admin.js`, deps `jquery`). No admin CSS,
no selectWoo, no jQuery UI. `admin.js` is a vanilla-JS IIFE driving the per-category `wp.media`
uploader; it hardcodes two Spanish strings (`Selecciona una imagen para la categoría`,
`Usar esta imagen`) — not translatable.

## Affected Areas

| File | Impact | Why |
|------|--------|-----|
| `includes/class-admin.php` | Modify | `render_categories_field` → `.wc-category-search` AJAX select; new `render_products_field` (`.wc-product-search` + sortable list) gated on `type=product`; `sanitize_instance()` gains `products` via `sanitize_ids()`; ~53 new i18n strings |
| `includes/class-settings.php` | Modify | 18th contract key `products` (default `[]`) in `builtins()`, `normalize()` (reuses `sanitize_ids()`), `$known` whitelist in `resolve()` — spec CM-9 becomes 18-key |
| `includes/class-shortcode.php` | Modify | `shortcode_atts` whitelist gains `products`; when non-empty, pass `products` → `ids` to `CWC_Query::query()` (legacy `ids` attr stays as-is) |
| `includes/class-assets.php` | Modify | Admin enqueue adds `wc-enhanced-select` (JS) + `woocommerce_admin_styles` (CSS), plus `jquery-ui-sortable` (WP core) and a small admin stylesheet; `wp_localize_script` for admin.js i18n strings |
| `assets/js/admin.js` | Modify | Sortable assigned-products list synced to the hidden select/field; read localized strings |
| `assets/css/admin.css` | Create | Chip + sortable-list styles (no admin CSS exists today; `carousel.css` is frontend-only) |
| `languages/cwc-carousel.pot` (+ `.po`, `.mo`) | Modify | Fill the missing admin strings; hand-authored pot (CCC-6), `.mo` compiled via `tools/make-mo.php` in wp-env (POMO) |
| Specs | Modify | `admin-settings` (new fields), `config-model` (CM-9 key count + products coercion), `carousel-shortcode` (whitelist + wiring); `product-query` likely unchanged (PQ-2 already covers `ids`) |

## Select2 Feasibility (both approaches verified against WC trunk + docs)

### Approach A — Categories: WC-native AJAX category search

- Markup: `<select class="wc-category-search" multiple data-action="woocommerce_json_search_categories" data-placeholder="…">` with server-rendered `<option value="term_id" selected>Name</option>` for stored selections (selectWoo initializes from existing selected options — pre-render is REQUIRED, the AJAX search only runs on input).
- Endpoint: `wp_ajax_woocommerce_json_search_categories` → `WC_AJAX::json_search_categories()`: nonce action `search-categories` (`wc_enhanced_select_params.search_categories_nonce`), capability `edit_products`, returns hierarchical labels (`Parent › Child`, with `formatted_name`/count), filter `woocommerce_json_search_found_categories`.
- Enqueue on this custom screen: `wp_enqueue_script('wc-enhanced-select')` + `wp_enqueue_style('woocommerce_admin_styles')`. `wc-enhanced-select` declares `selectWoo` as its own dependency; `wc_enhanced_select_params` (both nonces, ajaxurl) is localized by WC at script registration, and `wc-enhanced-select.js` auto-initializes `.wc-*-search` selects on DOM-ready. **No vendoring needed** — WC is already a hard dependency (FA-1 compatible).
- Storage impact: **zero** — still posts `[categories][]` term IDs, still sanitized by `sanitize_ids()`. Create-form re-render coercion (`array_map('absint')` on posted categories) keeps working.
- Behavior change: the old field listed ALL categories statically; the AJAX field is search-driven with a minimum-input-length (WC default 3; settable via `data-minimum_input_length="1"`). Stored-but-not-typed selections remain visible as pre-rendered chips.

### Approach B — Products: WC-native AJAX product search + sortable list

- Markup: `<select class="wc-product-search" multiple data-action="woocommerce_json_search_products" data-placeholder="…">` with `<option value="ID" selected>` pre-rendered via `wc_get_product($id)->get_formatted_name()` (matches the AJAX labels, includes SKU).
- Endpoint: `wp_ajax_woocommerce_json_search_products` → `WC_AJAX::json_search_products()`: nonce action `search-products`, capability `edit_products`, returns `{ id => formatted_name }`, filter `woocommerce_json_search_found_products`. Use **`woocommerce_json_search_products` (products only), NOT `_and_variations`** — variations would render as cards.
- Sortable list: select2-multiple keeps the underlying `<select>` option order = selection order, but offers NO drag reordering. "Sortable list of assigned products" therefore needs a visible chip list reordered with **`jquery-ui-sortable`** (WP core script handle, no vendoring) and synced back to the field's option order (or a hidden comma-separated input) before submit. The query layer already honors the stored order via `include` + `orderby=post__in` (PQ-2), so stored order == rendered order with no query changes.
- Save: same pattern as today — treat `[products][]` as untrusted, `sanitize_ids()` (absint + drop ≤ 0) — the exact pattern the search results confirm WC itself expects.

### Approach C (rejected) — vanilla Select2 with static full lists

Bundling/initializing a second Select2/selectWoo instance for categories with all terms loaded adds a vendored asset and bypasses WC's tested AJAX + nonce wiring for zero benefit; the requirement explicitly asks for AJAX search. Drop.

## Missing admin i18n strings

`languages/cwc-carousel.pot` is hand-authored (14 entries: cover/subcategories/title-align/overlay-title strings only). **~53 PHP strings in `class-admin.php` + `class-plugin.php` have no pot entry**, including:

- Menu/page: `Carousel`, `Add new carousel`, `Back to carousels`, `New carousel`, `Delete carousel`, `Cancel`
- List: `Name`, `Type`, `Slides (desktop / tablet / mobile)`, `Controls`, `Max items`, `Actions`, `Arrows`, `Pagination`, `None`, `Categories`, `Products`, `Edit`, `Delete`, `(reserved — not deletable or renamable)`, `No carousels registered yet.`, the `[cwc_carousel name="…"]` description
- Editor: `Carousel type`, `Slides`, `Gap (px)`, `Maximum items`, `Buy button`, `Buy text`, `Show the Buy button on product cards`, `Show arrows`, `Show pagination`, `Desktop`, `Tablet`, `Mobile`, `0 renders an empty carousel.`, `Whether the carousel shows products or category cards (product_cat terms).`, the name-description string, `Edit carousel: %s`
- Category images: `Category images`, `Choose image`, `Remove image`, `Select categories above to set a custom image per category.`, `No selected categories were found.`, `Optionally set a custom image that overrides the WooCommerce category thumbnail.`
- Errors: `The requested carousel was not found.`, `The carousel name must contain at least one letter, number, or hyphen.`, `"default" is a reserved name and cannot be used.`, `A carousel with that name already exists.`, `Cannot edit "%s": it does not exist in the registry.`, delete-confirmation strings
- Plugin notice (class-plugin.php): `Custom Woo Pro Carousel requires WooCommerce to be installed and active.`
- **JS (admin.js):** two hardcoded Spanish strings (`Selecciona una imagen para la categoría`, `Usar esta imagen`) — need `wp_localize_script` to become translatable.

Note: `buy_text` default `Comprar` is a stored VALUE (data), not a translation string — do not add to the pot. The `.pot` is compiled by hand; `.po` → `.mo` via `tools/make-mo.php` inside wp-env (POMO), so the fill must update pot + po + recompile mo.

## Open Questions / Decisions for Proposal & Design

1. **New contract key name**: `products` (recommended) — becomes an 18-key contract; CM-9 spec text and scenario updates needed.
2. **`count` vs manual list**: legacy `ids` renders the FULL list (no limit). Recommend manual lists ignore `count` for BC; confirm.
3. **`products` + `categories` both set**: legacy `ids` ignores categories. Recommend ids-only when `products` non-empty (BC); confirm.
4. **Field visibility**: products picker PHP-gated to `type=product` (same pattern as `cover`); no JS toggle on type switch — minor UX wrinkle when switching types, acceptable? (A JS toggle would be a small follow-up.)
5. **JS i18n**: bring admin.js strings into scope via `wp_localize_script`? (The change is titled "fill missing admin i18n strings" — recommended yes.)
6. **`data-minimum_input_length="1"`** on both searches so small catalogs aren't blocked by the WC default of 3.
7. **Sortable storage**: reorder the `<select>` options vs a hidden comma input — pick one in design (both map to `sanitize_ids()` on save).
8. **Category search returns**: endpoint output shape/`hide_empty` for categories varies by WC version (trunk returns enriched term objects with `formatted_name`; wc-enhanced-select handles both shapes) — verify against the actual WC version at implementation; the `.wc-category-search` class contract is stable.

## Risks

- **`edit_products` capability on both WC endpoints** vs the page gate `manage_woocommerce`: admins and Shop Managers hold both by default, so no practical mismatch — but a custom role with only `manage_woocommerce` would see empty search results. Document; no code change needed.
- **WC version drift**: `json_search_categories` output shape and the deprecated `json_search_categories_tree` (removed async-editor field) differ across WC 8/9/10 — the plugin currently pins no WC minimum; consider noting a tested-WC baseline.
- **Pre-render requirement**: forgetting server-side `<option selected>` for stored selections makes the fields look empty after save — must be part of the render contract.
- **Create-form re-render**: the create rejection path re-fills from posted `__new__` values; products array must be coerced like `categories` (absint map, fallback `[]`) to avoid type drift and lost selections.
- **pot/po/mo triple**: missing a .mo recompile silently ships untranslated Spanish admin (the shipped locale is es_ES).
- **Type-switch UX**: switching category→product hides the picker until save (PHP-gated); users may not notice. Acceptable for this change; note in design.
- **No admin CSS exists**: chip/sortable styling is net-new surface — keep it small and WC-styled (`woocommerce_admin_styles` provides the select2 chrome).

## Ready for Proposal

**Yes.** Both Select2 approaches are WC-native and low-risk; storage formats are unchanged or trivially extended; the query layer already supports ordered manual IDs. The main design decisions to lock in proposal: the `products` contract key + its interaction with `count`/`categories`, the sortable-list storage mechanism, and whether JS strings are included in the i18n fill.