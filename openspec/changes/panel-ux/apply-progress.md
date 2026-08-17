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
| `e3c1b9e` | docs(sdd): mark panel-ux Phase 2 tasks complete and record apply progress |

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