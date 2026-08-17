# Proposal: Panel UX — AJAX category search, manual product picker, i18n fill

## Intent

The editor's category multiselect is a raw `<select multiple>` with no search, and product carousels only support "latest N" — manual curation is impossible. Redesign the panel UX with WC-native Select2 AJAX search for categories and products, sortable manual product order, chip-based category editing, and fill the ~53 untranslated admin strings (incl. 2 hardcoded Spanish strings in `admin.js`). Frontend "arrows outside" is a separate change.

## Scope

### In Scope
- **Categories**: Select2 AJAX (`woocommerce_json_search_categories`, `data-minimum_input_length="1"`), server-rendered `<option selected>`; storage/sanitize UNCHANGED (`int[]` via `CWC_Settings::sanitize_ids()`); chips sortable via drag & drop.
- **Products**: NEW 18th contract key `products` (default `[]`); Select2 `wc-product-search` (`woocommerce_json_search_products_and_variations`, variations selectable); sortable chips synced to field order.
- **Render semantics**: `products` non-empty → render exactly those IDs in manual order (`orderby=post__in`, `count` ignored); empty → BC "latest N" fallback.
- **Category chips**: thumbnail + name with inline per-category image/overlay-title controls (replaces separate "Category images" section; term-meta storage unchanged).
- **Empty state**: "Add products" placeholder CTA when no products assigned.
- **i18n**: ~53 PHP strings + 2 JS strings via `wp_localize_script`; es_ES in `languages/cwc-carousel-*` recompiled via `tools/make-mo.php`.

### Out of Scope
- Frontend "arrows outside" (separate change).
- JS type-switch toggle for field visibility (PHP-gated only).
- Legacy `ids` shortcode attr and `cwc_carousel_options` (rollback path) — untouched.

## Capabilities

### New Capabilities
None.

### Modified Capabilities
- `admin-settings`: AJAX category select, products picker (type=product only), chip inline image/title, empty state, `products` in `sanitize_instance()`, admin i18n strings.
- `config-model`: CM-9 → 18-key contract; `products` in `builtins()`/`normalize()` (via `sanitize_ids()`)/`$known` whitelist.
- `carousel-shortcode`: `products` in `shortcode_atts` whitelist; non-empty → `ids` passthrough to query; empty → BC fallback.
- `frontend-assets`: FA-5 admin enqueue adds `wc-enhanced-select` + `woocommerce_admin_styles` + `jquery-ui-sortable` + `admin.css` + `wp_localize_script`.

## Approach

WC-native throughout — enqueue `wc-enhanced-select` + `woocommerce_admin_styles` on the custom screen (no vendoring; WC is a hard dependency). Stored selections MUST render server-side as `<option selected>` (selectWoo initializes from them; AJAX runs on input only). Sortable chip list reorders the field options pre-submit. `product-query` unchanged (PQ-2 already covers `post__in` ordering).

## Open Questions

- Sortable storage: reorder `<select>` options vs hidden comma input — design decides; both map to `sanitize_ids()`.
- Verify `json_search_categories` output shape against deployed WC version (drift risk).

## Affected Areas

| File | Impact |
|------|--------|
| `includes/class-admin.php` | Modify — fields, chips, i18n |
| `includes/class-settings.php` | Modify — 18th key |
| `includes/class-shortcode.php` | Modify — whitelist + wiring |
| `includes/class-assets.php` | Modify — enqueues + localization |
| `assets/js/admin.js` | Modify — sortable, localized strings |
| `assets/css/admin.css` | Create |
| `languages/cwc-carousel.pot/.po/.mo` | Modify — recompile via make-mo.php |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| WC AJAX endpoints gate on `edit_products` vs page `manage_woocommerce` | Low | Default roles hold both; document only |
| WC version drift in `json_search_categories` shape | Med | Verify against deployed WC; class contract stable |
| Missing `.mo` recompile → untranslated es_ES | Med | make-mo.php step in verify |
| Forgetting `<option selected>` pre-render → empty after save | Low | Render contract; spec scenario |

## Rollback Plan

`cwc_carousel_options` untouched; removing the `products` key from stored instances restores old UX on redeploy. Chip edits reuse existing term-meta handlers (reversible). Frontend query path is unchanged for empty `products` (BC).

## Dependencies

WooCommerce (hard dep); WP/WC core asset handles `wc-enhanced-select`, `woocommerce_admin_styles`, `jquery-ui-sortable`.

## Success Criteria

- [ ] Category/product selections save and re-render as selected chips (no lost selections)
- [ ] Drag-sorted order persists through save and render (`post__in`)
- [ ] `products` empty renders identically to today (BC)
- [ ] es_ES admin shows no untranslated strings; pot/po/mo in sync