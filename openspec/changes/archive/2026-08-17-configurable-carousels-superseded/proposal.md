# Proposal: Configurable Carousels

## Intent

Today every presentation value of the plugin is hardcoded in one of two places: the
`[cwc_carousel]` default atts (`title/count/ids`) and the Swiper options in
`assets/js/frontend.js` (`slidesPerView 1/2/3`, breakpoints `768/1024`,
`spaceBetween:16`). This change converts those values into a **configurable system**:
a global-defaults admin panel plus **per-shortcode overrides** (confirmed Option B),
supporting **two distinct carousel types** — category carousels and per-category
product carousels. The same shortcode must be able to produce different setups per
instance on the same page.

Base: `woo-pro-carousel-bootstrap` (slice 1, complete). This change **revises the
slice-2 plan**: a Settings API page replaces the planned CPT as the config surface
(CPT stays a later "saved presets" enhancement only if named presets become a real
need).

## Scope

### In Scope
1. **Admin settings page** — submenu "Carousel" under the WooCommerce menu,
   capability `manage_woocommerce`, Settings API (`register_setting` +
   `add_settings_section/field`), one sanitized `cwc_carousel_options` array
   (autoload off; rollback = `delete_option`).
2. **`CWC_Settings` model** — pure config: `defaults()` + `resolve($atts)` merging
   `admin defaults → explicit shortcode atts`; shared by shortcode/renderer/assets.
3. **Two card types** in the renderer: category card (image + link to category
   archive) and enriched product card (image + product's category/subcategory term +
   Buy button).
4. **Category carousel** (type A): desktop `slidesPerView 4`, explicitly chosen
   `product_cat` terms, category image = WC category thumbnail (`thumbnail_id`) by
   default **with per-category custom image override** uploadable from the panel.
5. **Product carousel** (type B): desktop `slidesPerView 3`; **primary mode scoped to
   ONE category/subcategory** (`category="4"`); secondary **mix mode**
   (`categories="4,7,9"` + `mix="yes"`); products filtered via `tax_query` on
   `product_cat` term ids (no raw SQL).
6. **Buy button**: rendered only when `type=product`, buy enabled, and
   `$product->is_purchasable() && ($product->is_in_stock() || $product->backorders_allowed())`;
   links to `get_permalink()`.
7. **Per-instance config channel**: renderer emits one JSON `data-` attribute per
   container (`esc_attr`); `frontend.js` builds Swiper options from it (dynamic
   slides/breakpoints/gap), replacing the hardcoded 1/2/3.
8. New delta specs mirroring the bootstrap conventions (`config-model`, `admin-settings`
   new; `product-query`, `carousel-renderer`, `carousel-shortcode`, `frontend-assets`
   modified).

### Out of Scope (explicit)
- **Gutenberg block** (future slice; placement stays via shortcode).
- **CPT / saved named presets** (deferred; only if a real need appears later).
- **`count="all"`** — count is **numeric only**; `count=0` stays empty (PQ-1).
- Pagination; AJAX add-to-cart / cart integration (slice-5 concern); Buy button only
  links to the product page.
- Custom product-image uploads (only *category* carousel gets custom category images).
- Theme/Customizer theming of card colors beyond existing tokens.

## Approach

1. `CWC_Settings::defaults()` defines the global defaults; `CWC_Settings::resolve($atts)`
   merges admin defaults → explicit shortcode atts (unknown atts dropped by
   `shortcode_atts`). One resolved config object shared by the query builder, the
   renderer, and the container's `data-` attribute.
2. Admin page (new `includes/class-admin.php`) exposes the defaults via Settings API,
   one sanitize callback, capability `manage_woocommerce`.
3. `CWC_Query` gains category filtering (`tax_query` on `product_cat` term ids) and
   keeps the pure arg-builder contract; numeric-only `count` (0 ⇒ empty).
4. Renderer becomes type-aware: category card vs product card; emits per-container
   `data-cwc-config` JSON (escaped); Buy button gated per product.
5. `frontend.js` reads the `data-` config and constructs Swiper options dynamically
   (slides desktop/tablet/mobile, breakpoints, gap), keeping `resizeObserver:false`.
6. CSS gains category card, product-card category line, and Buy button styles,
   themeable via existing tokens on `.cwc-carousel`.

## Key Decisions (confirmed by product owner)

1. **Admin location**: submenu "Carousel" under **WooCommerce** (`manage_woocommerce`).
2. **Category image**: WC category thumbnail by default, **with per-category custom
   image upload from the panel** that overrides it.
3. **Product carousel category handling**: primary = scoped to **one**
   category/subcategory (`category`); secondary = **mix** of several (`categories` +
   `mix`). Both supported.
4. **`count` is numeric only** — no `all`; `0 ⇒ empty` (consistent with PQ-1).
5. **Buy button** is a button (not a text link) with editable default text "Comprar"
   linking to the product page; shown only for purchasable + available products.
6. **Config flow**: global defaults (panel) + per-shortcode overrides; per-instance
   channel via per-container `data-` JSON attribute (a single `wp_localize_script`
   global cannot represent two setups of the same shortcode on one page).

## Capabilities

### New Capabilities
- `config-model` — `CWC_Settings` defaults + resolve/merge semantics.
- `admin-settings` — Settings API page, sanitize, capability, per-category image.

### Modified Capabilities
- `product-query` — category `tax_query` + numeric-only count.
- `carousel-renderer` — category card + enriched product card + Buy gating + data-attr.
- `carousel-shortcode` — new atts (`type`, `category`, `categories`, `mix`, `count`,
  `slides`, `buy`, `buy_text`) + override merging.
- `frontend-assets` — dynamic Swiper options from config; buy/category styles.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `includes/class-settings.php` | New | Config model: defaults + resolve |
| `includes/class-admin.php` | New | Settings API page + sanitize + capability + per-category image |
| `includes/class-shortcode.php` | Modify | New atts + override merge via `CWC_Settings::resolve` |
| `includes/class-query.php` | Modify | Category `tax_query`; numeric-only count |
| `includes/class-renderer.php` | Modify | Type-aware cards; Buy gating; `data-cwc-config` attr |
| `includes/class-assets.php` | Modify | Admin enqueue guard (only on our page) |
| `includes/class-plugin.php` | Modify | Instantiate CWC_Settings + CWC_Admin |
| `assets/js/frontend.js` | Modify | Read `data-` config → dynamic Swiper options |
| `assets/css/carousel.css` | Modify | Category card, category line, Buy button styles |
| `openspec/specs/*` | Modify/Create | Delta specs per capability above |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Review budget (>400 lines: admin + renderer + JS) | High | Chained PRs / split on apply; gate at tasks phase |
| First admin-state write (options) | Med | Autoload off; one sanitize callback; rollback = delete option |
| `tax_query` vs `category` slugs | Low | Decide in design: `tax_query` term_ids preferred (exact, one call) |
| Renderer grows two card types | Med | Keep card builders small; escape discipline (CR) |
| WPCS/escape discipline on data-attr JSON | Med | `wp_json_encode` + `esc_attr`, one emit point |
| Revises slice-2 CPT plan | Low | Confirmed with product owner; CPT deferred |

## Rollback Plan

Remove the admin page + delete the `cwc_carousel_options` option
(`delete_option`) → plugin falls back to defaults; no schema. Frontend unaffected for
existing shortcodes (defaults equal today's hardcoded values: 1/2/3 ramp, gap 16).

## Dependencies

- WooCommerce active (already required by the plugin).
- Existing slice-1 pipeline (`CWC_Query`, `CWC_Renderer`, `CWC_Shortcode`,
  `CWC_Assets`, `CWC_Plugin`).
- Swiper v14.0.7 vendored (unchanged).
- WP media library for the per-category image override (attachment id stored per
  term id).

## Success Criteria

- [ ] Admin page "Carousel" under WooCommerce shows global defaults (type, slides
      desktop/tablet/mobile, gap, categories, count, buy toggle/text).
- [ ] Per-category custom image upload overrides the category thumbnail in category
      carousels.
- [ ] `[cwc_carousel type="category" categories="1,2,3"]` renders category cards
      linking to category archives.
- [ ] `[cwc_carousel type="product" category="4" count="6"]` renders product cards
      (image + category term + Buy button) linking to product pages; Buy appears only
      for purchasable + available products.
- [ ] `[cwc_carousel type="product" categories="4,7,9" mix="yes"]` mixes products.
- [ ] Two instances of the same shortcode on one page render different setups via
      per-container config.
- [ ] `count=0` renders empty; numeric counts respected; no "all".
- [ ] phpcs passes (WordPress + WordPress-Extra).

## Resolved Decisions (from proposal Q&A)

1. Admin location: submenu under WooCommerce (`manage_woocommerce`).
2. Category image: category thumbnail default + per-category custom upload override.
3. Product carousel: scoped-per-category primary, mix secondary — both supported.
4. `count` numeric-only; no "all"; `0 ⇒ empty` preserved.
5. Buy button: button element, default text "Comprar" (editable), links to product page.
6. Panel location / placement remains via shortcode (engine under the form UX).
