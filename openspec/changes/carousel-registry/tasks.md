# Tasks: Named Carousel Registry

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~270-340 (4 files) |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR |
| Delivery strategy | ask-always — LOW forecast, single PR is the natural outcome |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

Per file: class-settings.php +60-80; class-shortcode.php +4-6; class-admin.php +180-240; custom-woo-pro-carousel.php +15-20. Under the 400-line budget → one PR; no chain, no size exception.

## Phase 1: Settings Model (CM-7..CM-10)

- [x] 1.1 `class-settings.php`: make `normalize()` public (D5) — shared coerce point for resolve/admin/migration.
- [x] 1.2 Add `registry()`: `get_option('cwc_carousel_registry', array())`, non-array → `array()`, read-only, never writes (CM-7).
- [x] 1.3 Add `seeds()`: `productos` (product, slides 3/2/1, gap 16, count 8, arrows+pagination+buy true) + `categorias` (category, slides 4/2/1, gap 16, count 8, arrows+pagination true), both via `normalize()` (CM-10).
- [x] 1.4 Add `instance_base($name)`: `registry[$name]` → normalize; else `registry['default']` → normalize; else `defaults()`; never fatal (CM-8).
- [x] 1.5 `resolve()` (L66): read `$atts['name']` and `unset()` it BEFORE the empty-string filter (design §4); `$known` built from `instance_base($name)`; rest unchanged (CM-8/CM-9).

## Phase 2: Shortcode Whitelist (SC-8/SC-9)

- [x] 2.1 `class-shortcode.php` (L52-65): add `'name' => ''` to `shortcode_atts()` defaults (SC-8).
- [x] 2.2 Add `'slides_tablet' => ''`, `'slides_mobile' => ''`, `'gap' => ''` — CRITICAL no-op fix; without them per-instance overrides never reach `resolve()` (SC-9).

## Phase 3: Bootstrap Migration (PB-4)

- [x] 3.1 `custom-woo-pro-carousel.php` `cwc_carousel_boot()` (L109): if registry not an array → `add_option('cwc_carousel_registry', array('default' => normalize(legacy cwc_carousel_options | builtins), 'productos' => seeds()['productos'], 'categorias' => seeds()['categorias']), '', false)`; legacy option never deleted; no write when registry present (PB-4).

## Phase 4: Admin List + Editor (AS-5..AS-8)

- [x] 4.1 `register_settings()` (L115): register `cwc_carousel_registry` (type array, autoload false, `sanitize_callback` → `sanitize_registry`); drop `cwc_carousel_options` registration + static per-field section; keep `option_page_capability` → `manage_woocommerce` (AS-5, AS-1).
- [x] 4.2 `render_page()` (L212): router on `$_GET['cwc_action']` (`sanitize_key`) → `render_list()` / `render_editor('edit'|'create')` / `render_delete_confirm()` (AS-6, AS-7).
- [x] 4.3 `render_list()`: rows for slug/type/slides/controls + create/edit/delete controls; `default` listed without delete/rename (AS-6).
- [x] 4.4 Field renderers take a name prefix (`render_slides_field('cwc_carousel_registry[productos]', …)`); add `instance_current($slug)` (registry or `CWC_Settings::defaults()`, class_exists-guarded); editor create + edit forms (AS-7, AS-5).
- [x] 4.5 `sanitize_registry($input)`: per posted slug → slugify key → per-slug replace only; each via `sanitize_instance()` (repurposed `sanitize_options()` L524 body; bounds unchanged: type ∈ {product,category}, slides 1-12, gap 8-64, count ≥ 0, bools, buy_text) (AS-5).
- [x] 4.6 `handle_registry_actions()` on admin_init (D4, mirrors `save_category_images()` L469): create — slug field + `cwc_carousel_registry[__new__]`; reject blank/`default`/duplicate via `add_settings_error`, never overwrites; `slugify()` = `sanitize_title($raw,'','save')` (AS-8); delete — confirm form, refuses `default`, removes slug, redirects to list (AS-7).
- [x] 4.7 `render_delete_confirm()`: confirmation warning that referencing pages fall back to `default` (never fatal) (AS-7).

## Phase 5: Verification

- [x] 5.1 `composer phpcs` (WordPress + WordPress-Extra) green; `php -l` all 4 changed files.
- [x] 5.2 wp-env smoke: no-name BC; `name="productos"` → 3/2/1 + arrows+pagination; `name="categorias"` → 4/2/1; `count="4"` override; `slides_tablet="3"`/`gap="24"` reach resolve; create/edit/delete round-trip; duplicate + reserved rejection; delete-in-use → `default`; migration runs once, idempotent; `delete_option('cwc_carousel_registry')` rollback.

## Out of Scope / Unchanged

`class-renderer.php`, `class-query.php`, `assets/js/frontend.js`, `class-assets.php`, `class-plugin.php`, shortcode `ids` path, category-images block (`render_category_images()`/`save_category_images()`), and the legacy `cwc_carousel_options` option — all unchanged; they consume the same 14-key resolved contract.

## Requirement Map

| Task | Requirements |
|------|--------------|
| Phase 1 (1.1-1.5) | CM-7, CM-8, CM-9, CM-10 |
| Phase 2 (2.1-2.2) | SC-8, SC-9 |
| Phase 3 (3.1) | PB-4 |
| Phase 4 (4.1-4.7) | AS-5, AS-6, AS-7, AS-8 |
| Phase 5 (5.1-5.2) | All acceptance criteria |

## Commit Plan (work units → single PR)

| Unit | Commit | Files |
|------|--------|-------|
| 1 | `feat(settings): registry read, seeds, instance_base, name-aware resolve` | class-settings.php |
| 2 | `feat(shortcode): whitelist name + slides_tablet/slides_mobile/gap` | class-shortcode.php |
| 3 | `feat(bootstrap): lazy idempotent registry migration` | custom-woo-pro-carousel.php |
| 4 | `feat(admin): registry list + per-instance editor, per-slug sanitize` | class-admin.php |
| 5 | `test: phpcs + php -l + wp-env smoke` | — |

Each unit is self-contained with its own `php -l` gate; units 1-4 form the single PR, unit 5 is the lint + smoke gate.
