# Design: Panel UX — Categories & Products Management

## Technical Approach

Rebuild the admin category picker and add a product picker in the carousel settings panel, backed by the existing 17-key registry (adding `products`, 18 keys total). Selection is stored as ordered `int[]` (term IDs / product IDs); order = drag order. Both pickers reuse WooCommerce's own `wc-enhanced-select` (selectWoo) AJAX search — no custom endpoints or nonces needed, because WC localizes `wc_enhanced_select_params` (with `search_products_nonce` / `search_categories_nonce`) unconditionally in `register_scripts()` on `admin_init`, and registers `woocommerce_admin_styles` on every admin request (verified in WC trunk; only the *enqueue* is screen-gated, so our page enqueues the handles itself). The products picker supports variations (AS-12), which needs one contained addition in `CWC_Query::query()`. Field visibility is gated server-side on `type === 'product'`, mirroring the existing `cover` gate.

## Architecture Decisions

| # | Decision | Options | Tradeoffs | Decision |
|---|----------|---------|-----------|----------|
| D1 | Sortable storage | (a) int[] registry; (b) custom meta | (a) array order = manual order; `sanitize_ids` preserves order; no schema change; (b) new table/meta + migration | **int[]** — server pre-renders `<option selected>` in stored order; drag reorders `<select>` options (native submit serializes DOM order); selectWoo only appends new options, never reorders |
| D2 | Field visibility on type switch | (a) PHP gate; (b) JS toggle | (a) mirrors existing `cover` gate; products wiped on switch via sanitizer fallback `[]` (AS-5) — acceptable, avoids hidden stale ids; (b) extra client state + re-sanitization | **PHP gate** — render products field only when `type === 'product'`; categories field renders for both types (product_cat filter applies to product carousels, PQ-5) |
| D3 | Chip UI | (a) selectWoo chips + sortable companion; (b) fully custom list | (a) AJAX search for free; hide native chips via `.select2-selection__choice { display:none }` (keeps inline search input); companion sortable list is the visible source of truth; (b) reimplements search/select | **(a)** — wc-enhanced-select auto-init handles search; our JS renders the sortable list, syncs on `change`, reorders `<option>`s on `sortstop` |
| D4 | Variations | (a) products only; (b) products + variations | AS-12 mandates `_and_variations`; WC default `type` (from `wc_get_product_types()`) excludes `variation`, so `include` of a variation returns nothing (verified, data store post_type branch) | **(b)** — in the CWC_Query ids branch pass `'type' => array_merge( array_keys( wc_get_product_types() ), ['variation'] )` → data store emits `post_type ['product_variation','product']` + OR tax_query (verified) |
| D5 | Image/title controls | (a) edit-only; (b) create+edit | (a) reuses existing nonce `cwc_save_category_image` + `manage_woocommerce` gate; create-mode nonce would `wp_die` on save (BC); (b) new nonce flow | **(a)** — sortable chips render in both modes; upload/title inputs edit-only, POSTed as `cwc_cat_images[term_id]` / `cwc_cat_titles[term_id]` → `save_category_images()` unchanged |

## Data Flow

```
Admin save:
  render_*_field (PHP pre-renders <option selected> in stored order)
    → wc-enhanced-select auto-init (AJAX search, add/remove)
    → sortable chip list (JS) ⇄ <select> option order (sortstop sync)
    → native POST [categories][]/[products][] in DOM order
    → sanitize_registry → sanitize_ids → stored registry int[]

Frontend render:
  [cwc_carousel name="x"] → shortcode_atts (products default '')
    → resolve() 18-key whitelist → products non-empty
    → CWC_Query::query(['ids' => products]) → include + orderby=post__in + type(incl. variation)
    → ordered WC_Product[] → CWC_Renderer (CR-9/CR-10 unchanged)
    → products empty → latest-N fallback (BC, count honored)
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `includes/class-settings.php` | Modify | `builtins()` + `'products' => []`; `normalize()` sanitizes via `sanitize_ids`; `resolve()` `$known` + products; 18-key docblock |
| `includes/class-admin.php` | Modify | categories field → `select.wc-category-search` (AJAX, pre-render, `hide_empty=false`); new products field (gated, `select.wc-product-search`, pre-render); chip section replaces `render_category_images` output (edit-only nonce); `sanitize_instance` + products; create re-render coercion; ~53 i18n strings |
| `includes/class-shortcode.php` | Modify | `shortcode_atts` + `'products' => ''`; render maps non-empty products → `['ids' => ...]`; precedence: ids attr > products > latest-N |
| `includes/class-query.php` | Modify | ids branch adds `type` incl. `variation` (only functional change outside config) |
| `includes/class-assets.php` | Modify | admin enqueue: `wc-enhanced-select`, `woocommerce_admin_styles`, `jquery-ui-sortable`, admin.css; `wp_localize_script` `cwcCarouselAdmin` (JS strings) |
| `assets/js/admin.js` | Modify | localized strings (removes 2 hardcoded Spanish); sortable init + option reorder sync; chip-list rebuild on `change`; dynamic rows for newly added terms/products; wp.media uploader reuse |
| `assets/css/admin.css` | Create | chip list, hidden `.select2-selection__choice`, drag handle, empty-state "Add products" CTA |
| `languages/cwc-carousel.pot`, `cwc-carousel-es_ES.po`, `.mo` | Modify | new strings (incl. JS strings via PHP `__()`); recompile via `tools/make-mo.php` |

## Interfaces / Contracts

```php
// Registry (18 keys): products added
'products' => array(), // int[] ordered product/variation IDs

// CWC_Query::query() ids branch — the only functional change outside config
$query_args['type'] = array_merge( array_keys( wc_get_product_types() ), array( 'variation' ) );

// JS contract (admin.js): .cwc-chip-list li[data-id] ⇄ <select> <option>;
// sortstop → reorder options; change → rebuild list; submit → native serialization.
```

## Testing Strategy

| Layer | What | Approach |
|-------|------|----------|
| Manual (wp-env) | AS-1…AS-6, AS-11…AS-13, CM-10 | create/edit carousel; drag, add/remove, type switch, save → order persists; variations selectable and render |
| Manual | AS-9/AS-10/CR-9 BC | legacy instance: categories + images unchanged; legacy `ids` attr unchanged; products empty → latest-N |
| i18n | AS-14 | site in es_ES; verify new strings incl. JS; `make-mo.php` recompile |
| Security | nonce/caps | edit-only nonce: create-mode POST rejected; capability check unchanged |

## Migration / Rollout

No DB migration — `normalize()` backfills `products=[]` on next save (CM-10). Rollback: drop the `products` key + picker; older versions ignore unknown keys. JS strings previously hardcoded — `.mo` recompile must ship in the same change.

## Open Questions

- None blocking. Verify at apply: WC version drift of `.wc-category-search` auto-init and `json_search_categories` response shape (WC handles both); placeholder thumbnails for freshly added items until reload (accepted).