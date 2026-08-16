# Archive Report: Category Card Cover Mode

| | |
|---|---|
| Change | `category-card-cover` |
| Archived at | `openspec/changes/archive/2026-08-16-category-card-cover/` |
| Verdict | **PASS** (verify-report: 15/15 requirements COMPLIANT, 68/68 assertions, 0 CRITICAL / 0 WARNING) |
| Persistence | `both` — this file + Engram `sdd/category-card-cover/archive-report` |
| Prior branch | `feat/cwc-cover-admin` (7 commits, 4 chained slices), merged into `main` at `422c2b6` |

## What Shipped

Opt-in cover mode for `type=category` carousels (7/12 full-bleed cards, dark
overlay, centered white title from `cwc_cat_title` term meta → term name, no
button), `title_align` header alignment for all carousels, product-card full
root→leaf category-path breadcrumbs (deepest path wins, name-ASC tie, CSS
ellipsis), explicit `subcategories` direct-children listing,
`include_children => true` on product `tax_query`, three new config keys
(`cover`, `subcategories`, `title_align` — 17-key contract), admin editor
fields + per-category overlay title term meta, and gettext i18n
(`languages/cwc-carousel.pot` + `cwc-carousel-es_ES.po/.mo`, neutral ES) with
the dev-only `tools/make-mo.php` POMO compile script. `frontend.js`,
`class-plugin.php`, `class-assets.php` and the bootstrap are untouched.

Files changed (10, verified `main..HEAD`): `class-settings.php`,
`class-shortcode.php`, `class-query.php`, `class-renderer.php`,
`class-admin.php`, `assets/css/carousel.css`, `languages/` (.pot/.po/.mo),
`tools/make-mo.php`.

## Stale-Checkbox Reconciliation (exceptional repair)

`tasks.md` Phase 5 (5.1–5.3) was left unchecked in the persisted artifact even
though every task is complete. Per the archive policy, `sdd-apply` owns normal
checkbox completion, but archive performed exceptional mechanical
reconciliation because the orchestrator explicitly launched archive with a
PASS verification report and the report proves each unchecked task:

- **5.1** — `composer phpcs` exit 0, `php -l` clean (verify-report §Build/Tests).
- **5.2** — wp-env acceptance smoke: 68/68 runtime assertions PASS on live site.
- **5.3** — i18n cross-check: 11/11 msgids, runtime es_ES + en_US lookups verified.

The checkboxes in the archived `tasks.md` now read `[x]`; this report records
the exact reconciliation reason and timestamp (2026-08-16). No CRITICAL issues
existed in `verify-report`, so the gate was satisfied before reconciliation.

## Spec Sync Summary

Delta specs merged into base specs under `openspec/specs/` per each delta's
"Archive:" instruction line:

| Domain | Action | Base spec |
|--------|--------|-----------|
| `category-card-cover` | **Created** (new capability; delta is a full spec) | `openspec/specs/category-card-cover/spec.md` — CCC-1..CCC-6 |
| `config-model` | **Updated** (MODIFIED CM-9 14→17-key replaced; ADDED CM-11 appended) | `openspec/specs/config-model/spec.md` — CM-9 now 17-key, +CM-11 |
| `carousel-renderer` | **Updated** (ADDED CR-8..CR-10 appended) | `openspec/specs/carousel-renderer/spec.md` — +CR-8/9/10 |
| `carousel-shortcode` | **Updated** (ADDED SC-10 appended) | `openspec/specs/carousel-shortcode/spec.md` — +SC-10 |
| `admin-settings` | **Updated** (ADDED AS-9/AS-10 appended) | `openspec/specs/admin-settings/spec.md` — +AS-9/10 |
| `product-query` | **Updated** (ADDED PQ-6/PQ-7 appended) | `openspec/specs/product-query/spec.md` — +PQ-6/7 |
| `frontend-assets` | **Updated** (ADDED FA-6/FA-7 appended) | `openspec/specs/frontend-assets/spec.md` — +FA-6/7 |

Merge rules followed: requirements matched by name; requirements not named in
deltas preserved; no REMOVED/RENAMED requirements in this change (nothing
deleted from base specs); MODIFIED CM-9 replaced in place with the 17-key
version, preserving the "Partial instance fills from builtins" scenario and
adding the "New keys present on legacy instances" scenario; delta meta-sections
(Decisions/Notes, Acceptance Criteria) stay in the archived delta, not in the
base specs — matching the `carousel-registry` archive convention.

## Archive Contents

- proposal.md ✅
- specs/ (7 delta specs) ✅
- design.md ✅
- tasks.md ✅ (17/17 tasks complete — incl. reconciled 5.1–5.3)
- verify-report.md ✅ (PASS)
- archive-report.md ✅ (this file)

Active `openspec/changes/` no longer contains `category-card-cover`.

## Notes / Risks Carried Forward

- Pre-existing SUGGESTION (out of scope, informational): `class-plugin.php::register_l10n()`
  passes the plugin path as the *third* argument of `load_plugin_textdomain`
  (correct on WP 7.0.4, but wrong on WP < 7.0 where it was the second
  argument). Project pins WP 7.0.4; a version-agnostic call is a future slice.
- SDD cycle for `category-card-cover` is complete: proposed, specced, designed,
  implemented, verified (PASS), and archived.