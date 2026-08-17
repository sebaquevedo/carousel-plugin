# Verify Report — panel-ux

> Written 2026-08-17 on `feat/panel-ux` @ `7c2d6da` (PRs #13–#16 merged, working tree clean).
> **Re-verified 2026-08-17 on `feat/panel-ux` @ `6f851a0`** (AS-14 corrective fix, PR #17 merged) — see [Re-verification (AS-14 fix)](#re-verification-as-14-fix). Verdict upgraded **FAIL → PASS**.
> **Post-verify additions re-verified 2026-08-17 on `feat/panel-ux` @ `483647e`** (commits `850fe12`, `6c99d5b`, `7a9d475`; working tree has the uncommitted AS-15 spec text) — see [Post-verify additions re-verification](#post-verify-additions-re-verification). Verdict extended **PASS → PASS (19/19)**.

## Verification Report

**Change**: panel-ux
**Version**: delta specs (admin-settings, carousel-shortcode, config-model, frontend-assets)
**Mode**: Standard (strict_tdd = false)

### Completeness

| Metric | Value |
|--------|-------|
| Tasks total | 15 |
| Tasks complete | 14 |
| Tasks incomplete | 1 (5.2 — this verification run itself; all 14 implementation tasks are `[x]`) |

### Build & Tests Execution

**Build/static**: ✅ Passed
```text
php -l  (9 files: custom-woo-pro-carousel.php, includes/class-{admin,assets,plugin,query,renderer,settings,shortcode}.php, tools/make-mo.php)
  → "No syntax errors detected" on every file
node --check assets/js/admin.js → OK
composer phpcs --standard=phpcs.xml.dist includes custom-woo-pro-carousel.php tools
  → exit 0, no violations (temp verification scripts were removed before this final run)
```

**Runtime assertions** (wp-env dev site, es_ES, WooCommerce 11.0.0 active): ✅ 77/77 plugin-behavior assertions passed across 5 suites, plus HTTP page checks and two real `options.php` save round-trips.

| Suite | Scope | Result |
|-------|-------|--------|
| `verify-panel-ux-cm.php` | CM-9/CM-10 config model (18-key contract, legacy fills, malformed coercion, seeds, order preservation, wp_json_encode) | ✅ 31/31 |
| `verify-panel-ux-sc.php` | SC-11 + legacy BC (manual order, count ignored, 10-item list renders fully — bf49167 regression, attr override, `ids` precedence, empty→latest-N, registry restore) | ✅ 16/16 |
| `verify-panel-ux-d4.php` | D4 variations (contrast: default `type` misses a variation; `CWC_Query` ids branch returns it; full shortcode render; pre-render path) | ✅ 10/10 |
| `verify-panel-ux-as3.php` | AS-3 renderer (placeholder → WC thumbnail → custom upload overrides → fallback; overlay title round-trip; empty deletes; admin pre-render priority) | ✅ 11/11 |
| `verify-panel-ux-as5.php` | AS-5 guards (unknown-slug rejection, scoped per-slug save, capability filter, autoload off) | ✅ 9/9 (1 harness artifact — restore write passed through the edit-only sanitizer after `do_action('admin_init')`; registry restored via the dedicated restore script) |
| HTTP checks | AS-11/AS-12 editor markup, AS-3 nonce field, FA-5 asset gating (5 pages) | ✅ all plugin behaviors verified (5 initial markers corrected: single-quote hidden fields, `multiple` attr, full-URL form action, WC's own `admin.css` on the products list page, JS-generated chip fields) |
| Real saves | AS-3 via `options.php` POST (set + clear term meta); AS-5 scoped save via full editor form POST | ✅ both persisted correctly; registry restored afterwards |

**Coverage**: ➖ Not available (no coverage tooling configured for this plugin).

### Spec Compliance Matrix

| Requirement | Scenario | Test | Result |
|-------------|----------|------|--------|
| CM-9 | 18-key contract with `products`; legacy instances backfilled; malformed coerced | `verify-panel-ux-cm.php` (31 assertions) | ✅ COMPLIANT |
| CM-10 | Seeds exact defaults | `verify-panel-ux-cm.php` | ✅ COMPLIANT |
| SC-11 | Manual products render in order, `count` ignored; attr override; `ids` precedence | `verify-panel-ux-sc.php` (16 assertions) | ✅ COMPLIANT |
| SC-11 | Empty products → latest-N BC | `verify-panel-ux-sc.php` | ✅ COMPLIANT |
| SC-11 | List longer than 8 renders fully (bf49167) | `verify-panel-ux-sc.php` | ✅ COMPLIANT |
| D4 | Variations selectable and renderable | `verify-panel-ux-d4.php` (10 assertions) | ✅ COMPLIANT |
| AS-11 | Search-to-add (`wc-category-search`, `data-action="woocommerce_json_search_categories"`, `data-minimum_input_length="1"`) | HTTP editor page + source | ✅ COMPLIANT |
| AS-11 | Pre-rendered chips keep stored order without AJAX | HTTP page: `<option value="109" selected …>…<option value="115" selected …>` with `data-thumb`/`data-image-id`/`data-title` | ✅ COMPLIANT |
| AS-12 | Products picker (`wc-product-search`, `data-action="woocommerce_json_search_products_and_variations"`, `multiple`, `min_length=1`), empty → "Add products" CTA, chips sortable | HTTP editor page (product-test) | ✅ COMPLIANT |
| AS-12 | Must NOT render for `type=category` | HTTP page: no `wc-product-search` on cover-test editor | ✅ COMPLIANT |
| AS-3 | Upload overrides thumbnail; no custom image → thumbnail/placeholder; title save/clear; empty deletes meta; renderer falls back to term name | `verify-panel-ux-as3.php` (11 assertions) + real `options.php` round-trip (set → meta persisted; empty → meta deleted) | ✅ COMPLIANT |
| AS-5 | Save scoped per slug (only `registry[slug]` replaced, others untouched) | `verify-panel-ux-as5.php` + real editor-form POST (product-test edited, cover-test untouched, stored fields preserved) | ✅ COMPLIANT |
| AS-5 | Capability gate holds; unknown slugs never created | `verify-panel-ux-as5.php` (capability filter returns `manage_woocommerce`; `nonexistent` rejected) + source (`option_page_capability_cwc_options_group` filter, nonce+cap-first handlers) | ✅ COMPLIANT |
| AS-14 | es_ES translated — **no admin string falls back to English** | i18n cross-check + runtime `__()` probes | ✅ COMPLIANT (72/72 msgids translated, 0 missing, 0 fuzzy; runtime: `WP_Translations` 72 entries, 21/21 samples, 71/72 sweep + intentional plugin-name identity — see Re-verification) |
| FA-5 | Admin assets only on the plugin admin page; never frontend/other admin; JS strings localized | HTTP checks: plugin page loads `wc-enhanced-select`, `woocommerce_admin_styles`, `jquery-ui-sortable`, `admin.css`, `admin.js`, `cwcCarouselAdmin`; products list page and frontend load none of the plugin's assets | ✅ COMPLIANT |
| FA-5 | JS strings localized (no hardcoded literals) | Source scan: no Spanish literals in `admin.js`; all 6 `cwcCarouselAdmin` keys consumed via `strings.*`; runtime page shows translated values | ✅ COMPLIANT |

**Compliance summary**: 18/18 scenarios compliant.

### Correctness (Static Evidence)

| Requirement | Status | Notes |
|------------|--------|-------|
| CM-9/CM-10 config model | ✅ Implemented | `builtins()` 18 keys with `products => []`; `normalize()` coerces via `sanitize_ids()`; `seeds()` matches spec defaults |
| SC-11 shortcode | ✅ Implemented | `shortcode_atts` + `products => ''`; `orderby=post__in`; `count` ignored for manual lists; `ids` attr wins |
| D4 query | ✅ Implemented | ids branch passes `type = array_keys(wc_get_product_types()) + 'variation'`, `limit = count($ids)` (bf49167) |
| AS-11/AS-12 pickers | ✅ Implemented | PHP pre-renders `<option selected>` in stored order with meta data attrs; `enhanced` marker skips WC auto-init; `data-return_id="id"` |
| AS-3 chip controls + save | ✅ Implemented | Edit-mode nonce `cwc_category_image_nonce` + `manage_woocommerce`; `save_category_images()` persists `cwc_cat_images`/`cwc_cat_titles`, empty deletes, non-image ids ignored |
| AS-5 registry + sanitizer | ✅ Implemented | `register_setting(…, autoload: false)`; edit-only per-slug `sanitize_registry()`; `option_page_capability_cwc_options_group` → `manage_woocommerce` |
| AS-14 i18n plumbing | ✅ Implemented | Translatability + `wp_localize_script` + `tools/make-mo.php` all in place; catalog complete (72/72 msgids translated, 0 missing, 0 fuzzy) after the corrective fix |
| FA-5 assets | ✅ Implemented | Screen-ID gate (`strpos($screen->id, 'cwc-carousel')`) + capability; wp.media primed; localized object |

### Coherence (Design)

| Decision | Followed? | Notes |
|----------|-----------|-------|
| D1 — int[] ordered storage, server pre-render, drag reorders options | ✅ Yes | Order round-trips proven (118,121,123 rendered in order; drag list ⇄ options sync in `admin.js`) |
| D2 — PHP gate for products field; categories field for both types | ✅ Yes | Server-side `type === 'product'` gate; verified absent on category editor |
| D3 — selectWoo + sortable chip companion | ✅ Yes | `enhanced` class, `cwc-chip-list`, `jquery-ui-sortable`, hidden native `.select2-selection__choice` |
| D4 — variations via type array in ids branch | ✅ Yes | `array_merge(…, ['variation'])`; contrast test proves the default misses variations |
| D5 — image/title controls edit-only, existing nonce | ✅ Yes | Nonce only in edit mode; save path unchanged and verified via real POST |
| File/asset plan | ✅ Yes | `class-assets.php` enqueues WC handles + `jquery-ui-sortable` + `admin.css` + localized object; no custom endpoints/nonces |

### Issues Found

**CRITICAL**: None.

**WARNING**: None.

**SUGGESTION**
- FA-5 asset set (selectWoo + localized object) also loads on the plugin's list page (`admin.php?page=cwc-carousel` without `cwc_action`). Compliant with FA-5's page-level gate (`woocommerce_page_cwc-carousel` screen ID covers list and editor), but the selectWoo payload is only needed on the editor — consider narrowing the enqueue to `cwc_action === 'edit'|'create'` to trim page weight.
- The Categories picker row renders on product-type editors (D2 explicitly allows it; the products row is the gated one). Harmless, but a JS row-toggle on type switch would reduce confusion — not required by any spec.
- Demo-site state changed during verification (ephemeral wp-env): term 103 now has `thumbnail_id = 19`; terms 109/115 carry image/title demo meta from prior verification; the seeded D4 variable product was deleted. Reset with `npx @wordpress/env stop && npx @wordpress/env start` if a pristine site is needed.

### Verdict

**PASS** — 18/18 scenarios compliant. All functional, security, and BC requirements (CM-9/10, SC-11, D4, AS-3/5/11/12, AS-14, FA-5) pass with runtime evidence.

---

## Re-verification (AS-14 fix)

> Executed 2026-08-17 on `feat/panel-ux` @ `6f851a0` (commit `6f851a0` — `i18n(admin): complete es_ES catalog (AS-14) (#17)`, working tree clean). Focused re-check of the single failing scenario; all prior evidence above remains intact.

### Catalog completeness (static)

`languages/cwc-carousel.pot` vs `languages/cwc-carousel-es_ES.po` (gettext parse, header excluded):

| Metric | Value |
|--------|-------|
| POT msgids | 72 |
| PO entries | 72 |
| Translated (non-empty, non-fuzzy msgstr) | **72/72** |
| Missing (no entry / empty msgstr) | 0 |
| Fuzzy | 0 |

The only msgstr identical to its msgid is the plugin **name** `Custom Woo Pro Carousel` — intentionally kept as a proper noun (same convention WordPress.org uses); its msgstr is present and non-empty, so it is NOT a gap. `languages/cwc-carousel-es_ES.mo` recompiled via `tools/make-mo.php` (POMO, magic `0x950412de`, 6090 bytes).

### Runtime probe (es_ES site, `npx @wordpress/env run cli wp eval-file`)

Re-created a temp probe (`tools/probe-i18n-reverify.php`, removed after the run — `tools/` ships only `make-mo.php` as before):

- **Catalog loads**: `WP_Translations` class, **72 entries** under `switch_to_locale('es_ES')` — not `NOOP_Translations`, no English fallback. First probe `__('Name','cwc-carousel')` → `Nombre`.
- **Sample probes: 21/21 matched catalog values** — all previously-failing strings now translate, including the five named in the original FAIL (`Name`→`Nombre`, `Show arrows`→`Mostrar flechas`, `Maximum items`→`Máximo de elementos`, `Cover mode`→`Modo portada`, `Edit`→`Editar`) plus `Carousels`, `Delete carousel`, `New carousel`, `Show pagination`, `Buy text`, `Buy button`, `Slides (desktop / tablet / mobile)`, `Title alignment`, `Left`/`Center`/`Right`, `Show cover cards`, `Show subcategories`, the duplicate-name error, the unknown-slug error (`Cannot edit "%s"…`), and the plugin header description.
- **Full sweep of all 72 .pot msgids**: 71/72 resolve to translated text; the sole identical-to-source msgid is the intentional proper noun `Custom Woo Pro Carousel`. **MISS COUNT: 0**.
- Result: **PASS (24 assertion groups green, exit 0)**.

### Outcome

AS-14 "es_ES translated" is now **COMPLIANT**: the es_ES catalog is complete (72/72 msgids, 0 missing, 0 fuzzy) and the Spanish admin loads translations at runtime with no English fallback. Verdict upgraded **FAIL → PASS (18/18)**.

**Informational** (no action): the regenerated `.pot`/`.po` use CRLF line endings (Windows) — harmless, but the next regeneration may normalize to LF depending on the writing environment.

---

## Post-verify additions re-verification

> Executed 2026-08-17 on `feat/panel-ux` @ `483647e` (working tree clean except the **uncommitted AS-15 spec text** in `openspec/changes/panel-ux/specs/admin-settings/spec.md` — the delta spec gained AS-15 after the last verify; commit `7a9d475` implemented it). Three commits landed after the last verify: `850fe12` (picker selection fix), `6c99d5b` (title CSS tokens at `:root`), `7a9d475` (editable display title + products-picker hint + i18n). Focused re-check of what changed; all prior evidence above remains intact.

### AS-15 — Editable display title: **COMPLIANT** (runtime evidence)

Run in wp-env (`wp eval-file` probe, 15 assertions, exit 0; probe removed after the run — `tools/` ships only `make-mo.php` as before):

| Check | Result |
|-------|--------|
| Editor renders `[title]` input (`name="cwc_carousel_registry[cover-test][title]"`, `maxlength="100"`) via `render_editor()` → `render_title_field()` | ✅ |
| Description renders **translated** on the es_ES editor (`Título que se muestra encima del carrusel. Déjalo vacío para ocultarlo.`) — bonus AS-14 signal from the same render | ✅ |
| `sanitize_registry()` (the registered `sanitize_callback`, i.e. the exact options.php save path) coerces hostile `title` (`<script>…</script>Spring Collection` + whitespace) → `Spring Collection` via `clean_text()` = `sanitize_text_field` | ✅ |
| Blank title (`"   \t "`) → `''`; missing `title` key → `''` (fallback empty, AS-15/AS-5) | ✅ |
| Non-empty title → shortcode render emits `<h2 class="cwc-carousel__title cwc-carousel__title--center">Spring Collection</h2>` (CR-1; `--center` because `cover-test` uses `title_align=center`) | ✅ |
| Empty title → heading omitted; carousel still renders (2764 chars, no `cwc-carousel__title`, no stray title text) | ✅ |
| Registry restored to its pre-probe state (`cover-test` back to `Cover Test`) | ✅ |

Round-trip proven end-to-end: sanitize (editor save path) → `update_option` → `do_shortcode` render. `Title round-trips` and `Empty title hides heading` both **COMPLIANT**.

### i18n: catalog 72 → 74, still complete

`languages/cwc-carousel.pot` vs `languages/cwc-carousel-es_ES.po` (gettext parse, header excluded; `.pot`/`.po` use CRLF — parser tolerant):

| Metric | Value |
|--------|-------|
| POT msgids | **74** (72 prior + `Title` + `Display title shown above the carousel. Leave empty to hide it.`; the products-picker hint string was **replaced**, not added, so the net delta is +2) |
| PO entries | **74** |
| Translated (non-empty, non-fuzzy msgstr) | **74/74** |
| Missing / extra-in-PO | 0 / 0 |
| Fuzzy | 0 |
| `.mo` recompiled | ✅ via commit `7a9d475` (6527 bytes, newer than the `.po`) |

Runtime on the es_ES site (same probe): `Title` → `Título`, the display-title description → `Título que se muestra encima del carrusel. Déjalo vacío para ocultarlo.`, and the new products-picker hint → `Añadir productos muestra exactamente estos artículos en este orden e ignora el filtro de categorías de arriba. Si se deja vacío, se muestran automáticamente los productos más recientes de las categorías seleccionadas.` Full sweep of all 74 `.pot` msgids: **73/74 resolve**; the sole identical-to-source msgid remains the intentional proper noun `Custom Woo Pro Carousel`. AS-14 remains **COMPLIANT**.

### Static checks

| Command | Result |
|---------|--------|
| `php -l includes/class-admin.php` | ✅ No syntax errors |
| `node --check assets/js/admin.js` | ✅ OK |
| `composer phpcs` (full repo, WPCS) | ✅ exit 0, no violations (after removing the temp probe; the probe itself is not part of the change) |

### Manual verification note (no automated claim)

- `850fe12` picker selection fix (selectWoo `select2:select`/`select2:unselect` → `rebuildChipList`, `width: 100%` on `.select2-container`/`.select2-search__field`, `String( item.term_id )` id coercion) — **browser behavior the user verified manually**; recorded as manual verification, **no automated coverage claimed**.
- `6c99d5b` title-margin CSS fix (carousel title tokens moved to `:root` so the `<h2>` sibling resolves `--cwc-title-gap`/`--cwc-title-font-size`/`--cwc-color-title`) — **browser behavior the user verified manually**; recorded as manual verification, **no automated coverage claimed**. Static sanity: the CSS change is scoped to `assets/css/carousel.css` and the renderer's h2 markup (verified above) matches the `.cwc-carousel__title` selector.

### Issues Found (additions)

**CRITICAL**: None.

**WARNING**: None.

**SUGGESTION**
- `tasks.md` has no task row for AS-15 (the requirement was added to the spec after apply and implemented by commit `7a9d475`). Consider adding a `[x]` row (e.g. under Phase 4/5) for traceability before archive.
- The AS-15 spec text in `openspec/changes/panel-ux/specs/admin-settings/spec.md` is **uncommitted** — sdd-archive's spec sync will need this working-tree delta committed (or re-applied) so the archived spec matches the reviewed delta.

### Verdict (post-verify additions)

**PASS — 19/19 requirements compliant** (18 prior + AS-15 with both scenarios). AS-15 round-trip, CR-1 heading behavior, i18n 74/74 completeness, and all static checks pass with runtime evidence; the two browser-behavior fixes are covered by manual verification.
