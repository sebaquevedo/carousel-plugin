# Apply Progress: Named Carousel Registry

- **Change**: `carousel-registry`
- **Status**: Complete — 17/17 tasks done
- **Store**: OpenSpec (tasks.md marked `[x]`) + Engram (`sdd/carousel-registry/apply-progress`)
- **Mode**: Standard (strict_tdd: false, no configured test runner)
- **Date**: 2026-08-14

## Summary

Implemented the named-instance carousel registry per design.md: `CWC_Settings` becomes
registry-aware (`registry()`, `seeds()`, `instance_base()`, public `normalize()`, and a
`name`-aware `resolve()`), the shortcode whitelists `name` + `slides_tablet` /
`slides_mobile` / `gap`, bootstrap lazily seeds the registry once (idempotent, legacy
option preserved), and the admin becomes a list + per-instance editor over one keyed
option with per-slug sanitize and guarded create/delete. Judgment Day review: APPROVED
(0 CRITICAL, 0 real code warnings).

## Tasks Completed (17/17)

Phase 1 — Settings Model (1.1–1.5): `normalize()` public; `registry()` read-only;
`seeds()` for `productos`/`categorias`; `instance_base()` with default fallback;
`resolve()` pops `name` before the empty-string filter.

Phase 2 — Shortcode Whitelist (2.1–2.2): `name`, `slides_tablet`, `slides_mobile`,
`gap` added to `shortcode_atts()` defaults.

Phase 3 — Bootstrap Migration (3.1): lazy, race-safe, idempotent seed of
`cwc_carousel_registry`; legacy `cwc_carousel_options` never deleted.

Phase 4 — Admin List + Editor (4.1–4.7): registry registered via Settings API
(autoload off, `sanitize_registry`); `render_page()` router; list view; prefixed
field renderers + `instance_current()`; per-slug `sanitize_registry()` with
edit-only guard; `handle_registry_actions()` (create/delete, nonce + capability,
slugify, reject blank/reserved/duplicate); delete confirmation with default
fallback warning.

Phase 5 — Verification (5.1–5.2): phpcs green (exit 0) + `php -l` clean on all 4
files; wp-env smoke executed and passed in the earlier apply session (no-name BC,
named instances, att overrides, create/edit/delete round-trip, duplicate/reserved
rejection, delete-in-use fallback, idempotent migration, rollback).

## Files Changed

| File | Action | What Was Done |
|------|--------|---------------|
| `includes/class-settings.php` | Modified | `registry()`, `seeds()`, `instance_base()`; `name` pop in `resolve()`; `normalize()` public |
| `includes/class-shortcode.php` | Modified | whitelist `name` + `slides_tablet`/`slides_mobile`/`gap` |
| `includes/class-admin.php` | Modified | list/editor/delete router; registry sanitize; create+delete handler; prefixed field renderers |
| `custom-woo-pro-carousel.php` | Modified | lazy idempotent registry migration at boot |
| `.gitattributes` | Created | `*.php` / `*.css` / `*.js` → `text eol=lf` (phpcs EOL gate) |

## Review Iterations

- R3 — create-form re-fill: rejected create submissions re-fill the form from posted
  `__new__` values instead of silently reverting to the default instance.
- R4 — coerce + categories: numeric fields coerced with the same clamp bounds used on
  save before re-render; categories fall back to empty when the multi-select posts no
  key.
- R5 — bound-clamp + guards: `bound()` shared by sanitize and re-render; sanitizer is
  strictly edit-only (unknown slugs and `__new__` never written).

Judgment Day: 0 CRITICAL, 0 real code warnings, verdict APPROVED.

## Verification Notes

- `composer phpcs` (WordPress + WordPress-Extra): exit 0 after EOL fix.
- `php -l` on the 4 changed files: no syntax errors.
- EOL approach: `.gitattributes` (`.php`/`.css`/`.js` → `text eol=lf`) +
  `git add --renormalize` + working-tree LF conversion. Renormalize touched exactly
  the 4 modified files — the phpcs.xml.dist exclusion fallback was NOT needed.
- wp-env smoke (earlier apply session): passed per orchestrator-verified state.

## PR Plan

Feature-branch chain (resolved by orchestrator; actual diff ≈ 845 insertions /
183 deletions exceeded the 400-line forecast, so chained delivery applies):

- `feat/cwc-carousel-registry` — tracker branch (docs + EOL commit only; no tracker PR).
- `feat/cwc-registry-settings` — PR #1 → base tracker: settings model, shortcode
  whitelist, bootstrap migration.
- `feat/cwc-registry-admin` — PR #2 → base PR #1 branch: admin list + per-instance editor.

## Deviations from Design

None — implementation matches design.md (the `name` mechanism, migration shape,
admin surface, and the 14-key contract are as designed). The fix iterations R3–R5
addressed review findings without changing the design contract.
