# Apply Progress — Panel UX (Slice 1 / PR 1)

## Slice 1: Phase 1 — Backend Foundation (PR 1) — COMPLETE

- **Branch**: `feat/panel-ux-config` (base: tracker `feat/panel-ux`)
- **PR**: #13 — https://github.com/sebaquevedo/carousel-plugin/pull/13
- **Chain strategy**: feature-branch-chain (tracker `feat/panel-ux` aggregates to `main`; PR 1 targets the tracker; PRs 2–4 target the previous PR branch).
- **Date**: 2026-08-17

## Tasks completed

| Task | Description | Status |
|------|-------------|--------|
| 1.1 | `class-settings.php`: `builtins()` + `'products' => []`; `normalize()` via `sanitize_ids()`; `$known` + `products`; 17→18-key docblocks (CM-9) | [x] |
| 1.2 | Seeds `productos`/`categorias` gain `'products' => array()` (CM-10) | [x] |
| 1.3 | `class-query.php`: ids branch adds `type` = `array_keys(wc_get_product_types())` + `variation` (D4) | [x] |
| 1.4 | `class-shortcode.php`: `shortcode_atts` + `'products' => ''`; non-empty → ordered `post__in` (count ignored); empty → latest-N BC (SC-11) | [x] |

## Files changed

| File | Action | What was done |
|------|--------|---------------|
| `includes/class-settings.php` | Modified | `builtins()` + `'products' => array()`; `normalize()` coerces via `sanitize_ids()`; `resolve()` `$known` + `products`; 4 docblocks 17→18-key; seeds define `'products' => array()` (tasks 1.1, 1.2) |
| `includes/class-query.php` | Modified | ids branch passes `'type' => array_merge( array_keys( wc_get_product_types() ), array( 'variation' ) )` so variations round-trip (task 1.3, D4); `'limit' => count( $ids )` lifts the default 8 so manual-ID lists render in full (follow-up fix) |
| `includes/class-shortcode.php` | Modified | `shortcode_atts` + `'products' => ''`; product branch precedence: legacy `ids` attr > resolved `products` (ordered, `count` ignored) > latest-N (task 1.4, SC-11) |
| `openspec/changes/panel-ux/tasks.md` | Modified | Phase 1 tasks 1.1–1.4 marked `[x]` |
| `openspec/changes/panel-ux/apply-progress.md` | Created | This continuity artifact |

No JS touched in this slice (slice 2 handles `assets/js/admin.js` + `assets/css/admin.css`).

## Commits (slice branch `feat/panel-ux-config`)

| Hash | Message |
|------|---------|
| `83e127b` | feat(settings): extend config contract with ordered products list (CM-9, CM-10) |
| `d843e01` | feat(query): include variations in manual-ID product queries (D4) |
| `d6f138a` | feat(shortcode): render resolved products as ordered manual-ID list (SC-11) |
| `b1ec3cd` | docs(sdd): mark panel-ux Phase 1 tasks complete and record apply progress |
| `b5d4558` | docs(sdd): fix apply-progress PR URL |
| `bf49167` | fix(query): render full manual-ID list without truncating at 8 |

Tracker branch `feat/panel-ux` @ `4001f63` (docs(sdd): add panel-ux planning artifacts and delta specs), pushed.

## Follow-up fix: full manual-ID render (no truncation at 8)

After opening PR #13, a review correction was applied: the ids branch of `CWC_Query::query()` now sets `'limit' => count( $ids )` right after `include`, so WC's `posts_per_page` never truncates a manual-ID list (both the new `products` key and the legacy `ids` attribute) at the default `limit => 8`. This satisfies SC-11 ("exact IDs in order, `count` ignored") for lists >8. The latest-N branch (no `ids`) is unchanged.

- Commit: `bf49167` — `fix(query): render full manual-ID list without truncating at 8`
- Verification: `php -l includes/class-query.php` → no syntax errors; `composer phpcs` → clean (no output).

## Verification results

- `php -l` on all 3 changed PHP files: **no syntax errors** (re-run after the fix).
- `vendor/bin/phpcs --standard=phpcs.xml.dist` on the 3 changed files: **clean** (no output).
- `composer phpcs` (full repo): **clean** (no output) — re-run after the fix.

## Deviations from design

None — implementation matches design.md. Details:

- D4 implemented exactly as the design interface: `$query_args['type'] = array_merge( array_keys( wc_get_product_types() ), array( 'variation' ) );` — verified against WC trunk `WC_Product_Data_Store_CPT::get_wp_query_args()`: a `type` array containing `variation` emits `post_type ['product_variation','product']` + OR `product_type` tax_query (NOT EXISTS fallback), so included variation ids match.
- SC-11 implemented with the design precedence: legacy `ids` attr > resolved `products` > latest-N. `products` is passed as `['ids' => $config['products']]` (already sanitized by `normalize()`); the query re-sanitizes defensively.

## Notes / observations for slices 2–4 and verify

1. ~~**Include + limit truncation**~~ **RESOLVED (commit `bf49167`)**: manual-ID lists previously rendered at most 8 because `CWC_Query` hardcoded `limit => 8` and `WC_Data_Store_WP::get_wp_query_args()` maps `limit` → `posts_per_page` with no include special-case. The ids branch now sets `'limit' => count( $ids )`, so exact IDs render in order for any list length (`products` key and legacy `ids` attr alike). Verify phase: confirm with a >8 products case.
2. **Variation search endpoint** (`woocommerce_json_search_products_and_variations`) ships with WC and is enqueued in PR 2 via `wc-enhanced-select`; no new REST code needed in this change.
3. `products` key is inert until PR 4 adds the admin picker (AS-12) — legacy behavior is byte-identical because `builtins()` backfills `[]` and the shortcode falls through to latest-N.

## Remaining slices

- PR 2: Phase 2 — admin assets (gated enqueue, admin.css, admin.js restructure).
- PR 3: Phase 3 — categories picker + inline image/title edit.
- PR 4: Phase 4 — products picker + gating + CTA + i18n (needs PR 1 + PR 3).
- Phase 5: security audit + verification per PR.

## Status

4/4 Phase 1 tasks complete. Ready for review (PR #13, do not merge until reviewed).

---

# Apply Progress — Panel UX (Slice 2 / PR 2)

## Slice 2: Phase 2 — Admin Assets (PR 2) — COMPLETE

- **Branch**: `feat/panel-ux-assets` (base: tracker `feat/panel-ux` @ `21c7283`, the squashed PR 1)
- **PR**: #14 — https://github.com/sebaquevedo/carousel-plugin/pull/14
- **Chain strategy**: feature-branch-chain — PR 2 targets the tracker branch; PRs 3–4 target the immediate previous PR branch.
- **Date**: 2026-08-17

## Tasks completed

| Task | Description | Status |
|------|-------------|--------|
| 2.1 | `class-assets.php`: gated enqueue (cap `manage_woocommerce`): `wc-enhanced-select`, `woocommerce_admin_styles`, `jquery-ui-sortable`, `admin.css`; `wp_localize_script` `cwcCarouselAdmin` (FA-5) | [x] |
| 2.2 | `assets/css/admin.css` (create): chip list, hidden `.select2-selection__choice`, drag handle, empty-state CTA (D3) | [x] |
| 2.3 | `assets/js/admin.js`: localized strings (drop 2 hardcoded Spanish); uploader reuse; sortable sync + chip rebuild | [x] |

## Files changed

| File | Action | What was done |
|------|--------|---------------|
| `includes/class-assets.php` | Modified | `enqueue_admin_assets()` (unchanged screen + `manage_woocommerce` gates) now also enqueues `woocommerce_admin_styles` (style), `wc-enhanced-select` (script), `jquery-ui-sortable` (script dep), and the new `assets/css/admin.css` (style, dep on `woocommerce_admin_styles`, same `cwc-carousel-admin` handle as the script); admin.js deps grow from `['jquery']` to `['jquery','jquery-ui-sortable','wc-enhanced-select']`; `wp_localize_script` registers `cwcCarouselAdmin` with `mediaTitle`, `mediaButton`, `sortHandle`, `removeChip` (task 2.1, FA-5) |
| `assets/css/admin.css` | Created | Scoped under `.cwc-picker`: hides native `.select2-selection__choice` chips (D3), keeps the inline search input usable, styles the sortable `.cwc-chip-list`/`.cwc-chip` (handle grip, 32px thumb, name, remove), and the `.cwc-empty-cta` (task 2.2, D3). Inert until PRs 3–4 render `.cwc-picker` markup. |
| `assets/js/admin.js` | Modified | Restructured into `initUploaders()` + `initPickers()`; the 2 hardcoded Spanish media strings now come from `cwcCarouselAdmin.mediaTitle/.mediaButton` (FA-5); uploader behavior unchanged; added inert picker scaffolding — `makeSortable()` (jQuery UI sortable, `stop` → `syncSelectOrder()` reorders `<option>`s to chip order), `rebuildChipList()` (`option:checked` → chips, toggles CTA `hidden`), `createChip()` (`li[data-id]` with handle/thumb/name/remove; remove deselects + fires bubbling `change`). No `.cwc-picker` markup exists yet, so the scaffolding matches nothing (task 2.3, D1/D3). |
| `openspec/changes/panel-ux/tasks.md` | Modified | Phase 2 tasks 2.1–2.3 marked `[x]` |
| `openspec/changes/panel-ux/apply-progress.md` | Modified | This slice-2 section appended (continuity artifact) |

## Commits (slice branch `feat/panel-ux-assets`)

| Hash | Message |
|------|---------|
| `6b7b40c` | feat(admin): gate and enqueue panel admin assets with localized JS strings (FA-5) |
| `59274d5` | feat(admin): add admin stylesheet for picker chips and empty-state CTA (D3) |
| `c9484e6` | feat(admin): localize admin JS strings and add inert sortable chip scaffolding (FA-5) |
| `cd71784` | docs(sdd): mark panel-ux Phase 2 tasks complete and record apply progress |
| `425d198` | docs(sdd): fix apply-progress commit hash for slice 2 |

## Verification results

- `php -l includes/class-assets.php` → **no syntax errors**.
- `composer phpcs` (full repo, WPCS) → **clean** (exit 0, no output).
- `node --check assets/js/admin.js` → **JS syntax OK**.

## Deviations from design

None — implementation matches design.md:

- FA-5 enqueue list implemented exactly (D7 + FA-5): `wc-enhanced-select`, `woocommerce_admin_styles`, `jquery-ui-sortable`, `admin.css`, plus the pre-existing `wp_enqueue_media()`.
- The `cwcCarouselAdmin` object exposes the 2 media strings (the FA-5 requirement) plus `sortHandle`/`removeChip` so the chip scaffolding has no hardcoded literals either ("all JS-facing strings").
- admin.css is fully scoped under `.cwc-picker`, so the `display:none` on `.select2-selection__choice` can never affect other Select2 widgets on the screen.
- admin.js keeps the uploader byte-equivalent in behavior; only the string source changed (localized object instead of hardcoded Spanish).

## Notes / observations for slices 3–4 and verify

1. **es_ES interim state (accepted within the chain)**: the 2 media strings move from hardcoded Spanish to English-source `__()` strings; they are NOT yet in `languages/*` (no matches in the .po). Task 3.3 re-extracts + recompiles via `tools/make-mo.php` (AS-14), restoring es_ES and adding the PR 3 strings. The design's ".mo recompile must ship in the same change" is satisfied at chain completion (3.3/4.3). Verify phase: confirm es_ES translations exist after PR 3.
2. **Scaffolding contract for PRs 3–4**: JS matches `.cwc-picker > select[multiple]` + `.cwc-chip-list` + `.cwc-empty-cta`; chips are `li[data-id]`; `<option>` order = submitted order (D1). PHP must pre-render `<option selected>` in stored order; `data-thumb` on an option feeds the chip thumbnail. PR 3 should swap `createChip`'s remove (bubbling DOM `change`) for selectWoo's own trigger if the enhanced select starts owning visible state.
3. **Inert-ness confirmed**: `wc-enhanced-select` only auto-inits classes `wc-category-search`/`wc-product-search`/`wc-enhanced-select` — the current select class is `cwc-categories-select`, so no enhancement occurs. `jquery-ui-sortable` only acts on explicit `.sortable()` calls (none). admin.css matches no `.cwc-picker`. No field markup changed.
4. **BC check**: `woocommerce_admin_styles` now loads on the Carousel screen (visual only, intended — Select2 chrome). Field semantics, option names, save paths unchanged.

## Remaining slices

- PR 3: Phase 3 — categories picker (`select.wc-category-search` pre-render, AS-11) + inline image/title edit + languages recompile (AS-3/AS-14).
- PR 4: Phase 4 — products picker (`select.wc-product-search`, gated `type=product`, AS-12/AS-5) + chip CTA + languages final recompile.
- Phase 5: security audit + verification per PR.

## Status

3/3 Phase 2 tasks complete. Ready for review (PR #14, do not merge until reviewed).

---

# Apply Progress — Panel UX (Slice 3 / PR 3)

## Slice 3: Phase 3 — Categories Picker (PR 3) — COMPLETE

- **Branch**: `feat/panel-ux-categories` (base: tracker `feat/panel-ux` @ `cbb4e07`, the squashed PR 2)
- **PR**: #15 — https://github.com/sebaquevedo/carousel-plugin/pull/15
- **Chain strategy**: feature-branch-chain — PR 3 targets the tracker branch; PR 4 targets this branch.
- **Date**: 2026-08-17

## Tasks completed

| Task | Description | Status |
|------|-------------|--------|
| 3.1 | `class-admin.php`: categories field → `select.wc-category-search` (endpoint, min_length 1, `hide_empty=false`), `<option selected>` pre-render in stored order (AS-11) | [x] |
| 3.2 | Replace "Category images" section: per-chip inline upload (`cwc_cat_image`) + overlay title (`cwc_cat_title`, `sanitize_text_field`, empty deletes); keep `save_category_images()` nonce + cap guard (AS-3) | [x] |
| 3.3 | `languages/*`: extract new strings via `__()`; recompile `tools/make-mo.php` (AS-14) | [x] |

## Files changed

| File | Action | What was done |
|------|--------|---------------|
| `includes/class-admin.php` | Modified | `render_categories_field()` → `.cwc-picker` + `select.wc-category-search.enhanced` (data-action/minimum_input_length/return_id="id"/placeholder), `<option selected>` pre-render in stored order (`get_terms(include, orderby=include)` — guarded on empty `$selected`, since an empty `include` list returns ALL terms), per-option `data-thumb`/`data-image-id`/`data-title` from `cwc_cat_image` → `thumbnail_id` fallback + `cwc_cat_title` (AS-3 priority, same as renderer); `data-edit` only in edit mode (D5); `.cwc-chip-list` + "Add categories" CTA (`hidden` when stored selections exist); description. `render_editor()` now emits `wp_nonce_field( image_nonce )` in edit mode only (AS-3, BC for create). Deleted `render_category_images()` + dead private `current()` (tasks 3.1, 3.2) |
| `includes/class-assets.php` | Modified | `cwcCarouselAdmin` gains `chooseImage` + `overlayTitle`; media/sort/remove strings (PR 2) now also extracted in `languages/*` (tasks 3.2, 3.3) |
| `assets/js/admin.js` | Modified | `initPickers()` now initializes selectWoo itself: `initEnhancedSelect()` mirrors WC 11.0's `wc-category-search` init (same endpoint/nonce/`formatted_name` labels) + `show_empty: 1` (empty categories stay selectable, AS-11) + honors `data-return_id` for term-id values; `enhancedSelectLanguage()` replicates WC's `getEnhancedSelectFormatString()` from `wc_enhanced_select_params.i18n_*` (es_ES dropdown strings); CTA click → `focusSearch()` opens the dropdown and focuses the search input (AS-12). `createChip()` gains edit-mode inline controls via `appendEditControls()` (hidden `cwc_cat_images[term_id]`, upload button, `cwc_cat_titles[term_id]` input prefilled from `data-title`); `attachUploader()` takes an explicit preview element (chip thumb as live preview; stored override restored on load). Removed `initUploaders()` (no more `.cwc-category-image-row` markup). Chip remove stays DOM-driven (`option.selected=false` + bubbling `change` — native state drives visible list + POST, D3; term image meta is global, not per-instance, so removal never clears it) |
| `assets/css/admin.css` | Modified | `.cwc-chip` wraps (`flex-wrap`); inline controls layout: upload button inline, remove stays on the top row, overlay-title input as a full-width second row (`order: 10`) |
| `languages/cwc-carousel.pot` | Modified | +9 entries (media frame title/button, sort handle, remove, choose image, search placeholder, CTA, picker description; "Overlay title" now also referenced from `class-assets.php`); date → 2026-08-17 (AS-14) |
| `languages/cwc-carousel-es_ES.po` | Modified | Same +9 entries translated (neutral Spanish): "Selecciona una imagen para la categoría", "Usar esta imagen", "Arrastrar para reordenar", "Quitar", "Elegir imagen", "Buscar categorías…", "Añadir categorías", "Busca y selecciona categorías; arrastra las etiquetas para fijar el orden del carrusel."; date → 2026-08-17 |
| `languages/cwc-carousel-es_ES.mo` | Modified | Recompiled via `tools/make-mo.php` inside wp-env (POMO; magic `0x950412de`, 1892 bytes) |
| `openspec/changes/panel-ux/tasks.md` | Modified | Phase 3 tasks 3.1–3.3 marked `[x]` |
| `openspec/changes/panel-ux/apply-progress.md` | Modified | This slice-3 section appended (continuity artifact) |

## Commits (slice branch `feat/panel-ux-categories`)

| Hash | Message |
|------|---------|
| `84d2a85` | feat(admin): categories AJAX picker pre-render and edit nonce (PR 3/4) |
| `fcbf867` | feat(admin): chip drag order, Select2 init and inline image/title edit (PR 3/4) |
| `39f8e44` | i18n(admin): extract picker and chip strings, recompile es_ES catalog (PR 3/4) |
| `<docs>` | docs(sdd): mark panel-ux Phase 3 tasks complete and record apply progress |

## Verification results

- `php -l includes/class-admin.php`, `class-assets.php`, `tools/make-mo.php` → **no syntax errors**.
- `composer phpcs` (full repo, WPCS) → **clean** (exit 0, no output).
- `node --check assets/js/admin.js` → **JS syntax OK**.
- `npx @wordpress/env run cli wp eval-file wp-content/plugins/carousel-plugin/tools/make-mo.php` → **"Success: Compiled ... cwc-carousel-es_ES.mo"**; file 1892 bytes, magic `0x950412de`.

## Deviations from design

Implementation matches design.md with two **verified-against-WC-11.0 adjustments** (both documented in code and PR #15):

1. **Endpoint response shape** — `woocommerce_json_search_categories` (WC 11.0) returns `{ term_id: WP_Term }` with `formatted_name` ("Ancestor > Name (count)"), NOT the flat string map the design assumed. `processResults` maps `id: term.term_id`, `text: term.formatted_name`. Net effect: search-added chips label with the formatted name (count + ancestors) while pre-rendered chips show the plain term name — accepted (minor, consistent with WC's own pickers).
2. **`show_empty` / `return_id`** — WC's stock `wc-category-search` init does not forward `show_empty` (endpoint defaults to `hide_empty=true`!) and reads `data-return_id` client-side (default slug). The design assumed both were handled by WC's stock init; instead the select renders with the `enhanced` class (blocks WC's init, which also owns a sortable/alphabetize risk) and `initEnhancedSelect()` supplies `show_empty: 1` + `data-return_id="id"` explicitly. Without the `show_empty: 1` fix, empty categories would silently disappear from search results — a regression vs the removed native field (`hide_empty=false`).

## Notes / observations for slice 4 and verify

1. **es_ES restored** (slice 2 note 1 resolved): the media/chip strings and the new picker strings are extracted and the `.mo` is recompiled; verify phase should confirm `load_textdomain` picks the catalog up (es_ES no fallback).
2. **Form POST contract (D1) holds end-to-end**: selectWoo's SelectAdapter creates a native `<option>` for each AJAX-chosen term (verified select2 4.x behavior), the native select keeps its `name` (`select2-hidden-accessible`), `syncSelectOrder()` reorders options on sortstop, and `save_category_images()` consumes `cwc_cat_images[]`/`cwc_cat_titles[]` exactly as before — the inputs just moved from the old section into the chips (edit mode only).
3. **Term-meta semantics unchanged**: `cwc_cat_image`/`cwc_cat_title` remain GLOBAL term meta. Chip removal only deselects; it never clears the image (the term may be reused by another carousel). Delete-on-empty still works per `save_category_images()` (empty title → delete_metadata).
4. **PR 3 size**: ≈680 changed lines — the largest chain slice; the 590–820 whole-change forecast was resolved by the agreed 4-PR force-chain (tasks artifact, "Chained PRs recommended: Yes"). Flagged for the reviewer.
5. **wp-env note**: `@wordpress/env` is cached in the npx cache and Docker Desktop was started for this slice; `wp-env start` (dev + test sites) boots in ~10s from the existing container state.
6. **Slice 4 prep**: `initEnhancedSelect()` is structured for the products branch (same selectWoo flow, `wc-product-search` + `search_products_nonce`, gated `type=product`, AS-12/AS-5); the CTA/focus, sortable chips, and i18n machinery are all in place.

## Remaining slices

- PR 4: Phase 4 — products picker (`select.wc-product-search`, gated `type=product`, AS-12/AS-5) + chip CTA + languages final recompile.
- Phase 5: security audit + verification per PR.

## Status

3/3 Phase 3 tasks complete. Ready for review (PR #15, do not merge until reviewed).

---

# Apply Progress — Panel UX (Slice 4 / PR 4)

## Slice 4: Phase 4 — Products Picker + Phase 5 Security Audit (PR 4) — COMPLETE

- **Branch**: `feat/panel-ux-products` (base: tracker `feat/panel-ux` @ `0e48296`, the squashed PRs 1–3)
- **PR**: #16 — https://github.com/sebaquevedo/carousel-plugin/pull/16
- **Chain strategy**: feature-branch-chain — PR 4 targets the tracker branch (final slice; tracker aggregates to `main`).
- **Date**: 2026-08-17

## Tasks completed

| Task | Description | Status |
|------|-------------|--------|
| 4.1 | `class-admin.php`: `select.wc-product-search` (`woocommerce_json_search_products_and_variations`) gated to `type === 'product'`; `<option selected>` pre-render; `sanitize_instance` coerces `products`; create-mode re-render (AS-12, AS-5) | [x] |
| 4.2 | `assets/js/admin.js`: chip list (thumbnail/name/remove), sortable → option order; "Add products" empty-state CTA (AS-12) | [x] |
| 4.3 | `languages/*`: products-picker strings; final `tools/make-mo.php` recompile (AS-14) | [x] |
| 5.1 | Security audit: nonce on every save, cap `manage_woocommerce`, `wp_unslash` + `sanitize_ids` on `[categories][]`/`[products][]`, escape output, term-meta permissions (AS-1/AS-5) | [x] |
| 5.2 | `php -l` + phpcs + `node --check assets/js/admin.js`; wp-env smoke deferred to sdd-verify (create/edit/type-switch/drag/save; legacy BC) | [x] (smoke part deferred per design) |

## Files changed

| File | Action | What was done |
|------|--------|---------------|
| `includes/class-admin.php` | Modified | `render_editor()` gains a Products row gated `'product' === $current['type']` (between Categories and the Cover-mode gate). New `render_products_field()`: `.cwc-picker` + `select.wc-product-search.enhanced` (data-action `woocommerce_json_search_products_and_variations`, minimum_input_length 1, return_id="id", placeholder "Search products…"), `<option selected>` pre-render in stored order via `wc_get_products( include=selected, limit=count, orderby=post__in, status=publish, type = product types + variation )` (D4 pattern from `class-query.php`, bf49167), per-option `data-thumb` from `get_image_id()` (variations inherit parent image), label `get_formatted_name()`; `.cwc-chip-list` + "Add products" CTA (`hidden` when stored selections exist) + description. No `data-edit` on this picker (products have no inline image/title controls — those are categories-only, AS-3). `sanitize_instance()` adds `'products' => sanitize_ids(...)` (AS-5); create-mode rejection re-render coerces `$current['products']` via `absint` exactly like categories. Docblocks updated (sanitize 18-key mention) (tasks 4.1, 5.1) |
| `assets/js/admin.js` | Modified | `initEnhancedSelect()` generalized: branches on `wc-product-search` (action `woocommerce_json_search_products_and_variations`, nonce `search_products_nonce`, flat `{ id: formatted_name }` response mapped straight to id/text — WC_AJAX shape) vs `wc-category-search` (existing term-object mapping, `show_empty: 1` only for categories). Shared selectWoo flow, CTA `focusSearch()`, sortable chips, thumbnails. Docblock updated to "both pickers" (tasks 4.2, 5.1) |
| `languages/cwc-carousel.pot` | Modified | +3 entries: "Search products…", "Add products", "Search and select products; drag the chips to set the carousel order." (AS-14) |
| `languages/cwc-carousel-es_ES.po` | Modified | Same +3 translated (neutral Spanish): "Buscar productos…", "Añadir productos", "Busca y selecciona productos; arrastra las etiquetas para fijar el orden del carrusel." |
| `languages/cwc-carousel-es_ES.mo` | Modified | Recompiled via `tools/make-mo.php` inside wp-env (POMO; magic `0x950412de`, 1892 → 2167 bytes) |
| `openspec/changes/panel-ux/tasks.md` | Modified | Phase 4 tasks 4.1–4.3 and 5.1 marked `[x]` (5.2 smoke portion stays `[ ]` until verify) |
| `openspec/changes/panel-ux/apply-progress.md` | Modified | This slice-4 section appended (continuity artifact) |

## Commits (slice branch `feat/panel-ux-products`)

| Hash | Message |
|------|---------|
| `798d848` | feat(admin): products Select2 picker gated to product type (PR 4/4) |
| `f43b12a` | feat(admin): products search branch in picker JS (PR 4/4) |
| `db628e1` | i18n(admin): extract products picker strings, recompile es_ES (PR 4/4) |
| `<docs>` | docs(sdd): mark panel-ux Phase 4 tasks complete and record apply progress |

## Security audit (task 5.1) — result: no gaps found

Audited against AS-1/AS-5 across the whole admin surface (all four PRs):

1. **Nonce on every save** — edit path uses the Settings API `settings_fields()` nonce; create/delete use `wp_nonce_field()` + `check_admin_referer()` on `cwc_registry_action`; category images use the dedicated `image_nonce` field with `check_admin_referer()` in `save_category_images()`. Products picker rides the same edit/create forms (no new save path).
2. **Cap `manage_woocommerce`** — gates the settings page render, `save_category_images()`, `handle_registry_actions()` (create/delete), and `enqueue_admin_assets()`. Verified on the gated page only.
3. **`wp_unslash` + `sanitize_ids` on `[categories][]`/`[products][]`** — `sanitize_instance()` coerces both via `CWC_Settings::sanitize_ids()` (absint, dedupe, drop 0); create-mode re-render `absint`-coerces both. Input arrives unslashed via `wp_unslash` in `options.php`/`handle_registry_actions()` upstream — no `$_POST` read in the sanitizer.
4. **Escape output** — every render echoes through `esc_html()/esc_attr()/esc_url()` (verified in both picker fields, type field, media fields).
5. **Term-meta permissions** — `cwc_cat_image`/`cwc_cat_title` written only by `save_category_images()` (nonce + cap + `wp_attachment_is_image()` for the image + `sanitize_text_field()` for the title, empty deletes). No `register_meta`/REST exposure — grep confirms no `show_in_rest`/`register_term_meta` on these keys. Products picker introduces no term-meta at all.

**Conclusion**: the change adds sanitize/escape/nonce on every new field; no pre-existing gap was found. No fixes required beyond the products sanitize additions already in this slice.

## Verification results

- `php -l` on `class-admin.php`, `class-assets.php`, `class-settings.php`, `class-query.php`, `class-shortcode.php`, `tools/make-mo.php` → **no syntax errors**.
- `composer phpcs` (full repo, WPCS) → **clean** (exit 0, no output).
- `node --check assets/js/admin.js` → **JS syntax OK**.
- `npx @wordpress/env run cli wp eval-file wp-content/plugins/carousel-plugin/tools/make-mo.php` → **"Success: Compiled ... cwc-carousel-es_ES.mo"**; file 2167 bytes, magic `0x950412de`.

## Deviations from design

None — implementation matches design.md (D1/D2/D4/D5). Details:

- D4 pattern reused verbatim from `class-query.php` for pre-render (`type` = product types + `variation`, `orderby=post__in`, `limit=count`) so pre-rendered options and renderer queries agree; verified against the slice-1 query implementation.
- AS-12 products picker intentionally has **no** `data-edit` attribute: inline image/title controls are a categories-only feature (AS-3); products chips show thumb/name/remove only, per spec.

## Notes / observations for verify

1. **Products endpoint shape**: `woocommerce_json_search_products_and_variations` returns a flat `{ id: formatted_name }` map (unlike the categories term-object map). `processResults` maps keys straight to id/text. Verify phase: confirm search-added variation chips label "Parent — Attribute: Value (SKU)".
2. **Variation thumbnails**: `get_image_id()` on a variation falls back to the parent product's image (WC core behavior), so chips show the parent thumb when the variation has none.
3. **CTA empty-state**: `hidden` attribute on the "Add products" button toggles with the chip list (shared machinery with categories); verify phase: confirm CTA shows until first selection and focusSearch opens the dropdown.
4. **`.mo` recompile**: done via the wp-env CLI container (POMO classes require WP), same as slice 3 — this is the build step, not the smoke; the browser smoke (create/edit/type-switch/drag/save, legacy BC) is deferred to sdd-verify per the design.
5. **PR 4 size**: ≈190 changed lines — small final slice; whole change ≈1,190 across the 4-PR chain.

## Remaining slices

- **sdd-verify**: full wp-env smoke (create/edit/type-switch/drag/save; variations render; legacy BC; es_ES no fallback) against the tracker branch, then sdd-archive.

## Status

7/7 Phase 4 + Phase 5 tasks complete (smoke verification portion of 5.2 deferred to sdd-verify). Ready for review (PR #16, do not merge until reviewed).