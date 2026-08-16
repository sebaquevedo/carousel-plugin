# Verification Report: Category Card Cover Mode

| | |
|---|---|
| Change | `category-card-cover` |
| Persistence | `both` (openspec file + Engram `sdd/category-card-cover/verify-report`) |
| Branch | `feat/cwc-cover-admin` (7 commits, 4 chained slices) |
| Diff `main..HEAD` | 10 files: 6 PHP, 1 CSS, 3 languages, 1 dev tool |
| Verdict | **PASS** |

## Completeness

| Task phase | Status |
|---|---|
| 1–4 (implementation) | ✅ All checked in `tasks.md` |
| 5.1 Static checks | ✅ Verified (`composer phpcs` exit 0, `php -l` clean) |
| 5.2 Runtime acceptance | ✅ Verified (68/68 assertions, live wp-env site) |
| 5.3 i18n cross-check | ✅ Verified (11/11 msgids, runtime es_ES + en_US lookups) |

## Build / Tests / Coverage Evidence

| Command | Result |
|---|---|
| `composer phpcs` (after temp-script cleanup) | exit 0, zero findings across the changed source |
| `php -l` on 6 changed PHP files | clean (no syntax errors) |
| Runtime acceptance (`wp eval-file` in wp-env CLI) | **PASS=68 FAIL=0** |
| Environment | WP 7.0.4, WooCommerce 11.0.0, WPLANG=es_ES, PHP 8.3.30, wp-env container |
| Coverage | No PHPUnit/xdebug coverage tooling in repo; spec scenarios are covered 1:1 by the 68 runtime assertions below (behavioral coverage) |
| Temp scripts | `tools/verify-cover.php` + debug scripts were dev-only and are **deleted**; `main..HEAD` remains exactly the 10 intended files |

## Spec Compliance Matrix

Fixture: term tree A(108) › B(109) › C(110) › D(111), X(112) › Y(113), W(114), NoImg(115), EmptyChild(116), EscTerm(117); products P1–P10 (128–138); shared image attachment on B (thumbnail_id) + C (cwc_cat_image).

| Requirement | Status | Runtime evidence (assertions) |
|---|---|---|
| CM-11 config contract (17 keys, defaults, coercion, legacy BC) | ✅ COMPLIANT | 6/6 — 17 keys incl. cover/subcategories/title_align; defaults false/false/left; explicit values; invalid title_align→left; legacy 14-key instance resolves to 17 with BC defaults |
| SC-10 shortcode whitelist | ✅ COMPLIANT | 5/5 — cover/subcategories/title_align reach resolve; cover renders `cwc-carousel--cover`; omitted atts keep defaults |
| CR-8 title alignment | ✅ COMPLIANT | 4/4 — center/right modifiers; left emits legacy markup; empty title omits h2 |
| CR-9 cover card | ✅ COMPLIANT | 7/7 — cover markup (overlay + cover-title); meta title `Verano`; fallback to term name; custom `cwc_cat_image` used (no placeholder); archive link correct; no button/caption |
| CR-10 breadcrumb | ✅ COMPLIANT | 8/8 — 4-level (A › B › C › D); 3-level; root-only; deepest wins; tie resolved name-ASC (A › B); unscoped carousel still shows path; scoped carousel keeps own path; `Zap & Co` escaped once (no `&amp;amp;`); `title` attr keeps full path |
| CCC-1 cover off / products | ✅ COMPLIANT | 2/2 — cover off keeps legacy card; `cover="1"` on product carousel ignored (no cover container, buy button + custom text preserved) |
| CCC-2 gap passthrough | ✅ COMPLIANT | 1/1 — `gap="36"` serializes in config |
| CCC-4 thumbnailless category | ✅ COMPLIANT | 1/1 — NoImg renders placeholder image + cover card + kept title |
| CCC-6 i18n | ✅ COMPLIANT | 3/3 — `__( 'Cover mode' )` → `Modo portada` under site es_ES; editor renders `Modo portada` for category instances; English fallback `Cover mode` |
| PQ-6 subcategory listing | ✅ COMPLIANT | 8/8 — depth-1 children only; `hide_empty` omits EmptyChild; invalid/zero parent → []; shortcode lists children; config subcategories=true; empty/nonexistent parent → empty carousel |
| PQ-7 include_children | ✅ COMPLIANT | 5/5 — `include_children => true` in single-term AND IN-list tax_query; grandchild included via parent scope; outside subtree excluded; list mode includes children |
| AS-9 editor fields | ✅ COMPLIANT | 9/9 — cover checkbox + hidden-0 companion + pre-filled checked; subcategories checkbox; title_align select + center pre-selected; cover hidden for product instances; sanitize round-trip (true/false/center); invalid title_align coerced |
| AS-10 overlay title save | ✅ COMPLIANT | 4/4 — title saved via `save_category_images()`; pre-filled on edit; empty clears meta; capability gate holds (user 0 write rejected) |
| FA-6 cover CSS | ✅ COMPLIANT | 4/4 — `--cwc-cover-overlay-opacity: 0.45`; `aspect-ratio: 7 / 12`; scoped under `.cwc-carousel--cover`; `.cwc-carousel__title--center/--right` |
| FA-7 breadcrumb ellipsis | ✅ COMPLIANT | 1/1 — `text-overflow: ellipsis` + `white-space: nowrap` + `overflow: hidden` |

15/15 requirements COMPLIANT, 68/68 assertions passing. No `UNTESTED` or `FAILING` scenarios.

## Correctness

- Nonce handling (AS-10): `check_admin_referer( 'cwc_save_category_image', 'cwc_category_image_nonce' )` verified end-to-end. Note: in WP-CLI `eval-file`, `request_order` is empty so `$_REQUEST` never merges `$_POST` — the harness must seed `$_REQUEST` directly (this was a harness bug, not a plugin bug; a browser POST populates `$_REQUEST` normally).
- i18n runtime (CCC-6): WP 7.0.4 loads plugin catalogs just-in-time. The catalog loads once per request; `unload_textdomain()` + reload under the *same* locale lands the domain in `NOOP_Translations` (harness artifact — the shipped plugin path loads es_ES correctly at first use).
- Sanitization/escaping: `sanitize_text_field` on titles, `esc_html`/`esc_attr` on breadcrumb names verified by the `Zap & Co` assertion; `absint` on image ids; `get_term()` re-validation before term-meta writes.

## Design Coherence

| Design decision | Coherence |
|---|---|
| D1 17-key contract for all types; cover inert for products | ✅ Verified — legacy product carousel serializes 17 keys; `cover=1` ignored for `type=product` |
| D2 title_align whitelist coercion | ✅ Verified — single coerce point in `normalize()`, invalid → `left` |
| D3 `get_terms(parent=)` depth-1 listing | ✅ Verified — PQ-6 assertions incl. invalid/zero parent → [] |
| D4 pinned breadcrumb order (deepest wins, first on tie) | ✅ Verified — CR-10 assertions |
| D5 extend `save_category_images()` (same nonce + cap gate) | ✅ Verified — nonce field/action constants, capability first, AS-10 assertions |
| D6 hand-authored .pot/.po + POMO compile | ✅ Verified — `tools/make-mo.php` compiled `cwc-carousel-es_ES.mo`; runtime lookup returns `Modo portada` |
| Not changed: `frontend.js`, `class-plugin.php`, `class-assets.php` | ✅ Verified — `git diff main..HEAD` contains none of them |

## Issues

### CRITICAL
None.

### WARNING
None.

### SUGGESTION
- **Pre-existing, out of scope (informational):** `class-plugin.php::register_l10n()` (PB-3 slice, untouched by this change) passes the path as the *third* argument of `load_plugin_textdomain`. This is correct on WP 7.0.4 (`$domain, $deprecated, $plugin_rel_path` — verified against `wp-includes/l10n.php:999`) but on WP < 7.0 the path was the *second* argument, so translations would not load on older core. The project pins WP 7.0.4 (`wp-env`); consider a version-agnostic call in a future slice.
- **Harness learnings for future verify slices** (documented, no product impact): (1) WP-CLI `eval-file` has `request_order=""` — `$_REQUEST` does not include `$_POST`; seed nonce fields into `$_REQUEST` directly. (2) WP 7.0.4 JIT textdomain loading: never `unload_textdomain()` + reload under the same locale; assert catalog state instead, or switch to a different locale first. (3) `hide_empty` default in `get_terms()` silently drops count-0 fixture terms — give fixture terms products.

## Final Verdict

**PASS** — implementation matches proposal, specs, design, and tasks; all 68 runtime assertions pass on the live site; static checks and i18n cross-checks green; `main..HEAD` contains exactly the 10 planned files with no stray changes.