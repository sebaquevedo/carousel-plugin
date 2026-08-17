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