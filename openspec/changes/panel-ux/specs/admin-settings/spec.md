# Delta for admin-settings

## ADDED Requirements

### Requirement: AS-11 — AJAX category search field

The categories field MUST be a Select2 AJAX search select (`.wc-category-search`, `data-action="woocommerce_json_search_categories"`, `data-minimum_input_length="1"`). Stored selections MUST pre-render as `<option value="term_id" selected>`; posted `[categories][]` MUST keep `sanitize_ids()`; chips MUST be sortable, reordering field options pre-submit.

#### Scenario: Search-to-add

- GIVEN a product instance editor
- WHEN the user types in the search
- THEN matching `product_cat` terms load from the WC AJAX endpoint
- AND choosing one adds its term ID

#### Scenario: Pre-rendered chips keep order

- GIVEN an instance with categories dragged from `[7, 3]` to `[3, 7]`
- WHEN the editor re-renders
- THEN options 3 and 7 carry `selected` in that order, without AJAX

### Requirement: AS-12 — Products picker (manual curation)

For `type=product` instances, the editor MUST render a `wc-product-search` Select2 field (`data-action="woocommerce_json_search_products_and_variations"`, products and variations selectable) storing the contract key `products` (default `[]`). Stored IDs MUST pre-render as `<option selected>`; assigned chips MUST be sortable and synced to the field option order pre-submit. It MUST NOT render for `type=category`; empty list shows an "Add products" CTA.

#### Scenario: Search-add + CTA

- GIVEN a product instance with `products = []`
- WHEN the user searches and selects
- THEN an "Add products" CTA shows until a selection exists
- AND selecting appends its ID to `products`

### Requirement: AS-14 — Admin i18n strings

All admin-facing strings MUST be translatable with the plugin text domain; the two hardcoded Spanish strings in `admin.js` MUST come from `wp_localize_script`; es_ES `.po`/`.mo` MUST be recompiled via `tools/make-mo.php`.

#### Scenario: es_ES translated

- GIVEN a Spanish site
- WHEN the admin page renders
- THEN no admin string falls back to English

## MODIFIED Requirements

### Requirement: AS-3 — Per-category image and overlay title (chip inline)

For each chosen `product_cat` term, the category chip MUST offer inline controls to upload a custom image (term meta `cwc_cat_image`) and set an overlay title (term meta `cwc_cat_title`, `sanitize_text_field`, empty deletes), replacing the "Category images" section. Carousels MUST use the custom image over the WC `thumbnail_id` when present; the renderer reads `cwc_cat_title` for the cover overlay title (CR-9). Saving MUST keep the `manage_woocommerce` + nonce guard of `save_category_images()`.
(Previously: separate "Category images" section; AS-3/AS-10 merged.)

#### Scenario: Upload overrides thumbnail

- GIVEN a term with both `thumbnail_id` and a custom upload
- WHEN the category card renders
- THEN the custom image is shown

#### Scenario: No custom image

- GIVEN a term with no custom upload
- WHEN the category card renders
- THEN the WC thumbnail (or a placeholder) is used

#### Scenario: Title save/clear

- GIVEN a chip with title "Verano", later cleared
- WHEN the save handler runs
- THEN `cwc_cat_title` stores "Verano", deletes on empty, and the renderer falls back to the term name (CR-9)

### Requirement: AS-5 — Registry option with per-slug sanitizer

The plugin MUST store named carousels in one registered keyed array `cwc_carousel_registry` (`{ slug => full_config }`, autoload off). Its sanitize callback MUST run per slug, scoped as `cwc_carousel_registry[slug][key]`, reusing today's field bounds (type within {product,category}, slides 1-12, gap 8-64, count ≥ 0, bools via `parse_bool`, `buy_text` via `clean_text`), and MUST additionally coerce `products` via `sanitize_ids()` (absint, values ≤ 0 dropped, fallback `[]`). The page and all saves MUST stay gated by `manage_woocommerce` (AS-1).
(Previously: 17-key instances, no `products`.)

#### Scenario: Save scoped per slug

- GIVEN an authorized user edits `productos`
- WHEN the registry sanitizer runs
- THEN only `registry['productos']` is replaced, with every value coerced
- AND other slugs are untouched

#### Scenario: Capability gate holds

- GIVEN a user without `manage_woocommerce`
- WHEN they submit the editor
- THEN the save is rejected, no registry key written

## REMOVED Requirements

### Requirement: AS-10 — Per-category overlay title term meta

(Reason: merged into AS-3 — per-chip inline editing.)
(Migration: `cwc_cat_title` term meta + CR-9 renderer contract unchanged.)