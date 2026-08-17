# Archive Report: Panel UX — Categories & Products Management

| | |
|---|---|
| Change | `panel-ux` |
| Archived at | `openspec/changes/archive/2026-08-17-panel-ux/` |
| Verdict | **PASS** (verify-report: 19/19 requirements COMPLIANT, 18/18 scenarios, 74/74 msgids, 77/77 runtime assertions, 0 CRITICAL / 0 WARNING) |
| Persistence | `both` — this file + Engram `sdd/panel-ux/archive-report` |
| Prior branch | `feat/panel-ux` (PRs #13–#17: 4 chained slices + AS-14 corrective fix + post-verify additions), merged into `main` at `d81004e` |

## What Shipped

Rebuilt the admin panel UX with WC-native Select2 AJAX search for categories and
products, sortable manual product order, chip-based category editing, and a
complete es_ES admin i18n fill.

- **Categories picker (AS-11)**: `.wc-category-search` Select2 AJAX select
  (`woocommerce_json_search_categories`, `data-minimum_input_length="1"`,
  `hide_empty=false`), server pre-rendered `<option selected>` in stored order,
  sortable chips synced to field option order pre-submit.
- **Products picker (AS-12)**: `wc-product-search` Select2
  (`woocommerce_json_search_products_and_variations`, products + variations),
  gated server-side to `type === 'product'`, stores contract key `products`
  (default `[]`), "Add products" empty-state CTA, sortable chips.
- **18-key config contract (CM-9/CM-10)**: `products` added to
  `builtins()`/`normalize()` (via `sanitize_ids()`)/`$known` whitelist; seeds
  define `products => []`.
- **Shortcode wiring (SC-11)**: `products` attr whitelisted; non-empty resolved
  `products` → ordered `post__in` manual-ID query (count ignored, full list
  renders — bf49167 limit fix); empty → latest-N BC fallback; legacy `ids` attr
  untouched.
- **Chip inline image/title (AS-3, AS-10 merged)**: per-chip upload
  (`cwc_cat_image`) + overlay title (`cwc_cat_title`, `sanitize_text_field`,
  empty deletes) replaces the separate "Category images" section;
  `save_category_images()` nonce + cap guard kept.
- **Per-slug sanitizer extension (AS-5)**: additionally coerces `products` via
  `sanitize_ids()`; `title` via `clean_text`.
- **Admin assets (FA-5)**: gated admin enqueue adds `wc-enhanced-select`,
  `woocommerce_admin_styles`, `jquery-ui-sortable`, `admin.css`; admin.js
  strings localized via `wp_localize_script` (`cwcCarouselAdmin`).
- **i18n (AS-14)**: full es_ES admin catalog — 74/74 msgids translated,
  0 missing, 0 fuzzy, `.mo` recompiled via `tools/make-mo.php`.
- **Editable display title (AS-15)**: editor "Title" field edits `title` config
  key (`clean_text`, fallback empty); non-empty → carousel heading, empty →
  heading omitted (CR-1).

## Stale-Checkbox Reconciliation

None required — `tasks.md` had all implementation tasks marked `[x]` in the
persisted artifact before archive (19/19 rows, Phases 1–6, incl. post-verify
additions 6.1–6.4). No exceptional repair performed.

## Spec Sync Summary

Delta specs merged into base specs under `openspec/specs/`:

| Domain | Action | Base spec |
|--------|--------|-----------|
| `admin-settings` | **Updated** — ADDED AS-11/AS-12/AS-14/AS-15 appended; MODIFIED AS-3 (chip inline image/title) and AS-5 (products coercion + title) replaced in place; REMOVED AS-10 deleted (Reason/Migration recorded in delta: merged into AS-3) | `openspec/specs/admin-settings/spec.md` — AS-1..AS-9, +AS-11/12/14/15, −AS-10 |
| `config-model` | **Updated** — MODIFIED CM-9 replaced (17→18-key contract, +`products` coercion scenarios) and CM-10 replaced (seeds gain `products => []`) | `openspec/specs/config-model/spec.md` — CM-9 18-key, CM-10 seeds updated |
| `carousel-shortcode` | **Updated** — ADDED SC-11 appended | `openspec/specs/carousel-shortcode/spec.md` — +SC-11 |
| `frontend-assets` | **Updated** — MODIFIED FA-5 replaced (enqueue list + localized-strings scenario) | `openspec/specs/frontend-assets/spec.md` — FA-5 with 3 scenarios |

Merge rules followed: requirements matched by name; requirements not named in
the deltas preserved; MODIFIED blocks replaced in full (including all scenarios
per delta); REMOVED AS-10 deleted from the base spec after recording
Reason/Migration in the delta; delta meta-notes (`(Previously: ...)`) stay in the
archived delta, not in the base specs — matching the `carousel-registry` and
`category-card-cover` archive conventions.

## Archive Contents

- proposal.md ✅
- exploration.md ✅
- specs/ (4 delta specs: admin-settings, config-model, carousel-shortcode, frontend-assets) ✅
- design.md ✅
- tasks.md ✅ (19/19 tasks complete)
- apply-progress.md ✅ (4 slices + AS-14 corrective fix)
- verify-report.md ✅ (PASS 19/19, incl. AS-14 fix + post-verify additions)
- archive-report.md ✅ (this file)

Active `openspec/changes/` no longer contains `panel-ux`.

## Notes / Risks Carried Forward

- Verify-report SUGGESTIONs (informational, no action): FA-5 asset set also
  loads on the plugin list page (`admin.php?page=cwc-carousel` without
  `cwc_action`) — compliant, but enqueue could be narrowed to editor actions;
  categories picker row renders on product-type editors (D2 explicitly allows
  it); demo wp-env site state changed during verification (term 103
  `thumbnail_id = 19`; terms 109/115 carry demo meta; seeded D4 variable product
  deleted) — reset with `npx @wordpress/env stop && npx @wordpress/env start`.
- The regenerated `.pot`/`.po` use CRLF line endings (Windows) — harmless; a
  future regeneration may normalize to LF.
- WC endpoint capability note: `woocommerce_json_search_categories` /
  `_and_variations` gate on `edit_products` vs the page gate
  `manage_woocommerce` — default roles hold both; a custom role with only
  `manage_woocommerce` would see empty search results (documented in
  exploration.md).
- SDD cycle for `panel-ux` is complete: explored, proposed, specced, designed,
  implemented (4-PR chain), verified (PASS 19/19), and archived.
