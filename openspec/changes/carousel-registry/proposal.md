# Proposal: Named Carousel Registry

## Intent

Today the plugin has ONE global config (`cwc_carousel_options`, class-settings.php L40) applied to every `[cwc_carousel]`. Multiple carousels on a page differ only via per-shortcode attributes — unmanageable beyond a couple, and there is no place to edit a "carousel" as a named, reusable unit. This change replaces the single editing model with a registry of NAMED carousel instances, each holding its own full config, editable individually in the admin panel. Shortcode becomes `[cwc_carousel name="..."]` with optional attribute overrides layered on top (panel-driven config as the primary surface; shortcode atts as per-page overrides).

**Confirmed product decisions (authoritative):**
1. **BC**: `[cwc_carousel]` without `name` resolves to a reserved `default` instance. Legacy `cwc_carousel_options` migrates into it; existing pages keep working unchanged.
2. **Persistence**: ONE keyed option `cwc_carousel_registry = { slug => full_config }` — not per-name options, not a CPT. Single option to sanitize/migrate.
3. **Seeds**: `productos` (product, slides 3/2/1, arrows+pagination ON) and `categorias` (category, slides 4/2/1, arrows ON; category cards already link to archives via `get_term_link`, renderer L231).
4. **Admin**: list view + per-carousel editor (create/edit/delete), reusing today's options form shape scoped per instance. No CPT, no tabs.
5. Resolved config key set = current 12 keys + `arrows`/`pagination` (14 keys, builtins defaults). Per-instance contract mirrors today's resolved shape exactly (CM-4).

## Scope

### In Scope
- Registry storage: one sanitized `cwc_carousel_registry` option (autoload off).
- `CWC_Settings` gains registry read + resolve-by-name: instance config becomes the merge base, atts layered via existing `$known`/`normalize()` (class-settings.php L66-98).
- BC shortcode: `name` attribute whitelisted; absent name → `default`.
- Migration: lazy + version-gated at boot (PB-2 forbids activation writes): legacy option present + no registry → seed `default` from it plus `productos`/`categorias`; idempotent; legacy option never deleted.
- Admin: list screen + per-instance editor reusing field/sanitize/current patterns (class-admin.php L115-140, L524, L561); create/edit/delete; capability `manage_woocommerce` (AS-1).
- Whitelist gap fix: add `slides_tablet`/`slides_mobile`/`gap` to `shortcode_atts` (currently NOT whitelisted, class-shortcode.php L51-68). **In scope** — the `categorias` seed needs per-instance `slides_tablet`/`slides_mobile` overrides to work; without them resolve never receives them (SC-6-style no-op failure).

### Out of Scope
- Renderer/query/frontend rewrites; per-card rendering; uploader/category images; CPT; tabs-per-carousel; advanced per-instance styling (later slice).

## Capabilities

### New Capabilities
- `carousel-registry` — registry storage, resolve-by-name, lazy migration, admin list + per-instance editor.

### Modified Capabilities
- `config-model` — resolve() base becomes the named instance config (CM-1..CM-6 preserved; `default` fallback = today's behavior).
- `carousel-shortcode` — `name` att; whitelist gains `slides_tablet`/`slides_mobile`/`gap` (SC-1..SC-7 preserved).
- `admin-settings` — list + per-instance editor replaces single-global editing (AS-1..AS-4 preserved).
- `plugin-bootstrap` — version-gated lazy migration at boot (PB-1..PB-3 preserved).

## Approach

1. **Settings registry**: `CWC_Settings` reads `cwc_carousel_registry`; `resolve($atts)` uses `name` (or `default`) → instance config as defaults base, then the existing empty-string filter + `$known` whitelist layer atts (class-settings.php L66-98 unchanged mechanics).
2. **Shortcode**: whitelist `name` + missing `slides_*`/`gap` (class-shortcode.php L51-68).
3. **Admin**: list view (existing page becomes the list) + per-instance editor reusing today's fields scoped as `cwc_carousel_registry[slug][key]`; delete removes the slug.
4. **Migration**: in `cwc_carousel_boot` (main file L109) — if `cwc_carousel_options` exists and registry absent, seed `default` (copied + normalized) and the two seeds once; never writes on clean installs.

## Alternatives Considered

- **Per-name options** (`cwc_carousel_options_productos`, …) — no single sanitize/migrate surface, option sprawl, harder atomic saves. Rejected: ONE keyed option keeps sanitize/migration in one place (confirmed decision 2).
- **CPT** — overkill for simple named config blobs; adds schema, rewrite, capability surface for no benefit. Rejected (confirmed 4; PB-2 favors no activation schema).
- **Tabs-per-carousel on one page** — unbounded config, no list grouping, poor with many carousels. Rejected for a list + separate editor-per-instance (confirmed 4).

## Impacts / Implications

- **BC**: no-name shortcode → `default` → identical output; legacy pages unchanged.
- **Stable contract**: renderer/query/frontend consume the same 14-key resolved config — `data-cwc-config`, `frontend.js`, `CWC_Query` untouched (CR-7, FA-4, PQ unchanged).
- **Seed migration**: existing legacy option maps into `default`; seeds appear only when a registry is first created (ever-fresh install or first upgrade).

## First-Slice Scope

**Must ship**: registry storage + `resolve()`-by-name + `default` BC fallback + seeds + admin list/editor (create/edit/delete) + version-gated lazy migration + whitelist `slides_tablet`/`slides_mobile`/`gap` + capability/sanitize.
**Later**: per-instance advanced styling, name cloning/duplication UI, import/export, block support.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `includes/class-settings.php` | Modify | Registry read + resolve-by-name + seed defaults |
| `includes/class-shortcode.php` | Modify | `name` att + whitelist `slides_tablet/slides_mobile/gap` |
| `includes/class-admin.php` | Modify | List + per-instance editor; registry sanitize |
| `custom-woo-pro-carousel.php` | Modify | Lazy migration at boot |
| `openspec/specs/*` | Modify/Create | Delta specs per capabilities above |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Name sanitization/duplicate slugs (admin) | Med | Slug via `sanitize_title`; unique-slug check on create/rename |
| Page references a deleted named instance | Med | Resolve falls back to `default` (never fatal); delete warns |
| Missing registry (fresh install / migration skipped) | Low | `default` falls back to builtins; migration re-runnable/idempotent |
| Option merge conflicts (atts vs instance) | Low | Existing `$known`/normalize order guarantees att wins |
| Whitelist gap breaks seeds | Med | Explicitly in scope; spec scenario proves `slides_tablet` override reaches resolve |
| Migration overwrites user edits | Low | Runs only when registry absent; legacy option untouched |

## Rollback Plan

Delete `cwc_carousel_registry` → `default` resolves from builtins (today's defaults); legacy `cwc_carousel_options` is never modified, so restoring it as the single source is a config copy. Revert the four files; no schema, no data loss.

## Dependencies

- WooCommerce active (already required).
- Merged `carousel-navigation-controls` — `arrows`/`pagination` flow through per-instance config unchanged (CM-5/6, SC-6/7, CR-8, FA-6).

## Success Criteria

- [ ] `[cwc_carousel]` (no name) renders today's output via migrated `default`.
- [ ] `[cwc_carousel name="productos"]` renders 3/2/1 product carousel, arrows+pagination ON.
- [ ] `[cwc_carousel name="categorias"]` renders 4/2/1 category cards linking to archives.
- [ ] `[cwc_carousel name="productos" count="4"]` overrides count only.
- [ ] Admin list shows seeds; create/edit/delete round-trips; deleting an in-use name falls back to `default`.
- [ ] `slides_tablet="3"` / `slides_mobile="2"` / `gap="24"` reach resolve and apply.
- [ ] Migration runs once (idempotent); legacy option untouched; clean install seeds without legacy option.
- [ ] phpcs passes (WordPress + WordPress-Extra).
