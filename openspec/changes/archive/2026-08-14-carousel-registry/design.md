# Design: Named Carousel Registry

## Technical Approach

Replace single-global editing with a registry of named carousel instances. `CWC_Settings` becomes registry-aware: `resolve()` consumes a `name` attribute to select one instance config as the merge base, preserving today's precedence (att > instance > builtin, CM-2). `CWC_Shortcode` whitelists `name` plus the three missing attributes (`slides_tablet`, `slides_mobile`, `gap`, SC-9). `CWC_Admin` becomes a list + per-instance editor over one keyed option (`cwc_carousel_registry`, autoload off). Bootstrap gains a lazy, idempotent migration that seeds `default` from the legacy option (or builtins) plus the two seeds; `cwc_carousel_options` is never deleted. Renderer, query, and `frontend.js` are untouched — they already consume the same 14-key resolved contract per container via `data-cwc-config`.

## 1. Architecture Decisions

| # | Decision | Options | Choice / Rationale |
|---|----------|---------|---------------------|
| D1 | Registry storage | per-name options / CPT / keyed array | **One option `cwc_carousel_registry` {slug => full_config}** (confirmed 2, AS-5 D1) — single sanitize/migrate surface |
| D2 | `name` into `resolve()` | extract before `shortcode_atts` / whitelist it | **Whitelist `name` (SC-8)**; `resolve()` pops it before the merge so it never serializes into the 14-key contract (CM-9) — `normalize()` is the hard exclusion boundary |
| D3 | Unknown/no-name base | fatal / `default` instance | **`registry['default']` → else legacy `defaults()`** — never fatal (CM-8); preserves an edited default over stale legacy |
| D4 | Create/delete writes | all through Settings API / separate handler | **`handle_registry_actions()` on admin_init** for create+delete, mirroring `save_category_images()` (D7) — Settings API stays pure for per-slug edit |
| D5 | `normalize()` visibility | keep private, duplicate logic / make public | **Make public** — single coerce point shared by resolve, migration, admin (no logic drift) |
| D6 | Rename UI | dedicated action / none this slice | **None** — create-new + delete-old is the rename path; duplicate + reserved guards still enforced (AS-8) |

## 2. Data Model

```php
// Option: cwc_carousel_registry (autoload off). { slug => full 14-key config }.
$registry = array(
    'default'    => array( /* from legacy cwc_carousel_options, normalized; else builtins */ ),
    'productos'  => array( /* product, slides 3/2/1, gap 16, count 8, arrows+pagination+buy true */ ),
    'categorias' => array( /* category, slides 4/2/1, gap 16, count 8, arrows+pagination true */ ),
    '<slug>'     => array( /* user-created, same 14-key shape */ ),
);
```

Each value stores the full 14-key config (`type, title, category, categories, mix, count, slides, slides_tablet, slides_mobile, gap, arrows, pagination, buy, buy_text`), normalized as the resolved contract (CM-9, CM-10). Full keys keep migrate/sanitize/resolve free of per-key special-casing.

## 3. Component Breakdown

`CWC_Settings` (`includes/class-settings.php`):
- `resolve( array $atts ): array` — pop `name`, select base via `instance_base()`, then existing empty-string filter + `$known` map built from the base + `normalize( wp_parse_args( $atts, $known ) )`.
- `registry(): array` — `get_option('cwc_carousel_registry', array())`, non-array → `array()`; read-only, never writes (CM-7).
- `instance_base( string $name ): array` — `registry[$name]` → normalize; else `registry['default']` → normalize; else `defaults()` (CM-8).
- `seeds(): array` — `['productos' => normalize([...]), 'categorias' => normalize([...])]` per CM-10; consumed only by migration.
- `normalize()` public (D5). `defaults()`, `builtins()`, `parse_bool()`, `sanitize_ids()` unchanged.

`CWC_Shortcode` (`includes/class-shortcode.php`): whitelist gains `'name' => ''`, `'slides_tablet' => ''`, `'slides_mobile' => ''`, `'gap' => ''` (SC-8/SC-9). `ids` path, query dispatch, and renderer call unchanged.

`CWC_Admin` (`includes/class-admin.php`):
- `register_settings()`: `register_setting('cwc_options_group','cwc_carousel_registry', array('type'=>'array','autoload'=>false,'sanitize_callback'=>[sanitize_registry]))`; drop the `cwc_carousel_options` registration and static per-field section (fields render inline, prefixed per instance); keep `option_page_capability_{group}` → `manage_woocommerce`.
- `render_page()`: router on `$_GET['cwc_action']` (sanitize_key) → `render_list()` / `render_editor('edit'|'create')` / `render_delete_confirm()`.
- `sanitize_registry( $input )`: per posted slug → slugify key → per-slug replace only (AS-5), each instance via `sanitize_instance()` (repurposed `sanitize_options()` body; bounds unchanged: type ∈ {product,category}, slides 1–12, gap 8–64, count ≥ 0, bools, buy_text).
- `handle_registry_actions()` (admin_init, D7 pattern: capability → `check_admin_referer` → write): create (slug text field + `cwc_carousel_registry[__new__]` fields; reject blank / reserved / duplicate via `add_settings_error`, AS-8) and delete (confirm form, refuses `default`, removes slug, redirects to list, AS-7).
- Field renderers take a name prefix (`render_slides_field('cwc_carousel_registry[productos]', $current)`); `slugify()` = `sanitize_title($raw, '', 'save')`; `instance_current( $slug )` reads registry or `CWC_Settings::defaults()` (class_exists-guarded like today's `current()`).

`custom-woo-pro-carousel.php` — `cwc_carousel_boot()` gains the lazy migration (PB-4), before `CWC_Plugin`:

```php
$registry = get_option( 'cwc_carousel_registry' );
if ( ! is_array( $registry ) ) {        // absent ⇒ seed exactly once; add_option is a no-op if raced
    $settings = new CWC_Settings();
    $default  = get_option( 'cwc_carousel_options', array() );
    add_option( 'cwc_carousel_registry', array(
        'default'    => $settings->normalize( is_array( $default ) ? $default : array() ),
        'productos'  => $settings->seeds()['productos'],
        'categorias' => $settings->seeds()['categorias'],
    ), '', false );                     // autoload off; legacy option never deleted
}
```

## 4. The `name` Mechanism (SC-8 residual, resolved)

1. Whitelist includes `'name' => ''` → `[cwc_carousel name="productos"]` survives `shortcode_atts()` (SC-8).
2. `resolve()` reads `$atts['name']` and `unset()`s it **before** the empty-string filter and `$known` merge: `name` selects the base via `instance_base()` and never participates in attribute layering.
3. Defense in depth: even if it leaked, `normalize()` emits only the 14 contract keys, so `name` cannot enter `data-cwc-config` / `wp_json_encode` (CM-9, CR-7).
4. Empty/absent `name` → slug `default` → today's output unchanged (BC, CM-8).

## 5. Migration / Edge Cases

| Case | Behavior |
|------|----------|
| Legacy + no registry | Seed `default` (normalized legacy) + both seeds; legacy untouched (PB-4) |
| Clean install | Same; no legacy option created |
| Registry present | No write; user edits preserved (idempotent) |
| Rollback (delete registry) | `resolve()` → `defaults()` → builtins; legacy still restorable |
| Unknown `name` | `registry['default']`, else legacy `defaults()`; never fatal (CM-8) |
| No-name shortcode | `default` instance → identical output (BC) |
| Duplicate / `default` on create | Rejected with admin error; never overwrites (AS-8) |
| Delete in use | Confirm warns; afterwards falls back to `default` (AS-7, CM-8) |
| Non-array registry | Reads as empty map (CM-7) |

## 6. NOT Changing (confirmed in code)

`CWC_Renderer::render()` (single `data-cwc-config` emit, CR-7), `CWC_Query::query()`/`get_categories()`, `frontend.js` (per-container init, slides/gap/controls already read, FA-4), `class-assets.php`, `class-plugin.php`, shortcode `ids` path (PQ-2), and the legacy option itself.

## 7. File Changes

| File | Action | Description |
|------|--------|-------------|
| `includes/class-settings.php` | Modify | `registry()`, `seeds()`, `instance_base()`; `name` pop in `resolve()`; `normalize()` public |
| `includes/class-shortcode.php` | Modify | whitelist `name` + `slides_tablet`/`slides_mobile`/`gap` |
| `includes/class-admin.php` | Modify | list/editor/delete router; registry sanitize; create+delete handler; prefixed field renderers |
| `custom-woo-pro-carousel.php` | Modify | lazy idempotent migration at boot |

## 8. Testing Strategy

| Layer | What | Approach |
|-------|------|----------|
| Static | All changed PHP | `composer phpcs` (WordPress + WordPress-Extra) always-green |
| Manual | name / no-name / unknown name; att override (`count="4"`); slides/gap atts reach resolve; create/edit/delete round-trip; duplicate + reserved rejection; delete-in-use fallback; migration once; rollback | wp-env smoke |

## 9. Risks

| Risk | Mitigation |
|------|------------|
| Edited `default` ignored after deleting a named instance | `instance_base()` prefers `registry['default']` before legacy `defaults()` |
| Two write paths (options.php edit; admin_init create/delete) diverge | Both funnel through `sanitize_instance()` + shared helpers (D7 precedent) |
| phpcs failures in new admin markup | Reuse existing escaped renderers/helpers |

## Open Questions

- [ ] None blocking.

## Archive Note (from verify-report, 2026-08-14)

Verified deviations recorded at archive time, per verify-report SUGGESTION notes:

1. **Corrupt-registry repair**: `cwc_carousel_boot()` repairs a present-but-corrupt
   non-array `cwc_carousel_registry` with `update_option()` (design §5 showed only
   the absent case). Strictly an improvement — prevents a broken value being
   re-attempted every request; consistent with CM-7's non-array handling and PB-4
   idempotency.
2. **`name` normalization in `resolve()`**: `resolve()` normalizes the shortcode
   `name` via `sanitize_title` + underscore folding (parity with admin `slugify()`,
   AS-8), slightly beyond the literal "pop name" text but matching its intent;
   unknown names still fall back to `default` (verified R4).