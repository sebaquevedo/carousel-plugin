# Tasks: Panel UX — Categories & Products Management

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 590–820 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 → PR 2 → PR 3 → PR 4 |
| Delivery strategy | force-chained |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Config `products` + query seam + shortcode attr | PR 1 | BC-safe |
| 2 | Gated admin assets + CSS + JS restructure | PR 2 | Inert until picker markup |
| 3 | Categories picker + inline image/title edit | PR 3 | Needs PR 2 |
| 4 | Products picker + gating + CTA + i18n | PR 4 | Needs PR 1 + PR 3 |

Chain base: main or previous PR branch (orchestrator resolves `chain_strategy`).

## Phase 1: Backend Foundation (PR 1)

- [x] 1.1 `includes/class-settings.php`: `builtins()` + `'products' => []`; `normalize()` via `sanitize_ids()`; add to `$known`; 18-key docblock (CM-9)
- [x] 1.2 Seeds `productos`/`categorias` gain `products => []` (CM-10)
- [x] 1.3 `includes/class-query.php`: ids branch adds `type` incl. `variation` (D4)
- [x] 1.4 `includes/class-shortcode.php`: `shortcode_atts` + `'products' => ''`; non-empty → ordered `post__in`, `count` ignored; empty → latest-N BC (SC-11)

**Acceptance**: `php -l` + phpcs; wp-env: legacy identical; `products="12,7,3"` renders in order.

## Phase 2: Admin Assets (PR 2)

- [x] 2.1 `includes/class-assets.php`: gated enqueue (cap `manage_woocommerce`): `wc-enhanced-select`, `woocommerce_admin_styles`, `jquery-ui-sortable`, `admin.css`; `wp_localize_script` `cwcCarouselAdmin` (FA-5)
- [x] 2.2 `assets/css/admin.css` (create): chip list, hidden `.select2-selection__choice`, drag handle, empty-state CTA (D3)
- [x] 2.3 `assets/js/admin.js`: localized strings (drop 2 hardcoded Spanish); uploader reuse; sortable sync + chip rebuild

**Acceptance**: handles + localized object on gated screen only; fields unchanged.

## Phase 3: Categories Picker (PR 3)

- [x] 3.1 `includes/class-admin.php`: categories field → `select.wc-category-search` (`woocommerce_json_search_categories`, min_length 1, `hide_empty=false`), `<option selected>` pre-render in stored order (AS-11)
- [x] 3.2 Replace "Category images" section: per-chip inline upload (`cwc_cat_image`) + overlay title (`cwc_cat_title`, `sanitize_text_field`, empty deletes); keep `save_category_images()` nonce + cap guard (AS-3)
- [x] 3.3 `languages/*`: extract new strings via `__()`; recompile `tools/make-mo.php` (AS-14)

**Acceptance**: search-add works; order persists; upload overrides `thumbnail_id`; es_ES no fallback.

## Phase 4: Products Picker (PR 4)

- [x] 4.1 `includes/class-admin.php`: `select.wc-product-search` (`woocommerce_json_search_products_and_variations`) gated to `type === 'product'`; `<option selected>` pre-render; `sanitize_instance` coerces `products`; create-mode re-render (AS-12, AS-5)
- [x] 4.2 + `assets/js/admin.js`: chip list (thumbnail/name/remove), sortable → option order; "Add products" empty-state CTA (AS-12)
- [x] 4.3 `languages/*`: products-picker strings; final `tools/make-mo.php` recompile (AS-14)

**Acceptance**: variations selectable/render (needs PR 1); hidden for `type=category`; CTA until first selection; order persists.

## Phase 5: Security & Verification

- [x] 5.1 Audit: nonce on every save, cap `manage_woocommerce`, `wp_unslash` + `sanitize_ids` on `[categories][]`/`[products][]`, escape output, term-meta permissions (AS-1/AS-5)
- [ ] 5.2 `php -l` + phpcs + `node --check assets/js/admin.js` per PR; wp-env smoke (create/edit/type-switch/drag/save; legacy BC)