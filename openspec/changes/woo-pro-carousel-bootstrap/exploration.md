# Exploration: Custom Woo Pro Carousel — Bootstrap & Architecture

> SDD change: `woo-pro-carousel-bootstrap` | artifact: `explore` | date: 2026-08-04

## Current State

The repository is **empty of plugin code** (only `.claude/`, `.cursor/`, `.gitignore`, `openspec/`
exist; git has 0 commits). SDD init already registered:

- **Stack**: WordPress plugin (PHP) + WooCommerce + Swiper.js + Gutenberg block (shortcode + block).
- **Tooling**: PHP 8.3.30, Composer 2.9.7, Node v24.15.0, npm 11.12.1, Docker 29.2.1. **WP-CLI NOT installed**. Git remote `origin` → github.com/sebaquevedo/carousel-plugin (empty).
- **Testing**: no runner configured (`strict_tdd: false`); PHPUnit + wp-env feasible to add later.
- **Conventions**: WPCS, sanitize/validate input, escape output, nonces + capabilities, Settings API, i18n.
- **Persistence**: hybrid — OpenSpec files + Engram (`sdd/{change-name}/...`).

So this exploration is **research of approach**, not codebase mapping. The product goal is the "Instrucción de Rol" PRD: a modular, secure, lightweight "Custom Woo Pro Carousel" plugin that replicates/improves the PRO features of "Product Carousel Slider for WooCommerce", Astra-optimized, with shortcode + Gutenberg block, multi-carousel admin, Swiper-based frontend, AJAX add-to-cart, quick view, and quantity selector.

## Research Findings (verified against WooCommerce docs + Swiper releases)

### 1. Product filters — efficient query approach

Primary tool: `WC_Product_Query` / `wc_get_products()` (the WooCommerce-recommended data-access layer — it builds on `WP_Query` with product-type defaults and meta handling).

| Filter | Efficient approach | Notes |
|---|---|---|
| Categories | `wc_get_products(['category' => [slugs]])` or `product_category_id` | WPCS-friendly, no raw SQL |
| Tags | `tag` (slugs) or `product_tag_id` | Same |
| On Sale | `wc_get_product_ids_on_sale()` → `post__in` / `'include'` in query args | **Do NOT** query `meta_key=sale_price` — core caches sale IDs in a transient (`wc_products_onsale`); `wc_get_product_ids_on_sale()` respects that cache. Most efficient path. |
| Best Sellers | Order by `meta_value_num` on `total_sales` | `'orderby' => 'meta_value_num', 'meta_key' => 'total_sales'` (WC default ordering for "popularity"); cheap indexed meta |
| Top Rated | Order by `meta_value_num` on `_wc_average_rating` | WC default "rating" orderby; use `'orderby' => 'meta_value_num', 'meta_key' => '_wc_average_rating'` |
| Recent | `'orderby' => 'date', 'order' => 'DESC'` | Default |
| Featured | `wc_get_featured_product_ids()` (core helper) → `include`; or `'featured' => true` in WC_Product_Query | Both supported |
| Manual IDs | `'include' => [ids]` (preserves order with `'orderby' => 'post__in'`) | Validate with `absint` each |
| Hide out of stock | `'stock_status' => 'instock'` (WC 3.0+) | Better than filtering after fetch; avoids loading hidden products entirely |

General query args: `'limit' => $total_products`, `'status' => 'publish'`, `'paginate' => false`. Random order → `'orderby' => 'rand'`. All orderings map to `'orderby'`/`'order'` args — **no post-processing needed**; keep the query layer pure (returns `WC_Product[]`).

### 2. AJAX add-to-cart + immediate checkout redirect

WooCommerce already ships a **built-in AJAX add-to-cart** for the classic storefront: `wc-add-to-cart` script localized with `wc_add_to_cart_params` (`ajax_url`, `wc_ajax_url`, `i18n_view_cart`, `cart_url`, `is_cart`, `cart_redirect_after_add`). It:

- POSTs to `/?wc-ajax=add_to_cart` (via `wc_ajax_url` with nonce `wc-add-to-cart` included by the `woocommerce_add_to_cart_nonce` hidden field).
- Triggers `wc_fragments_refresh` on success → cart fragments update (mini-cart) with **zero extra PHP**.
- Redirects to `cart_url` when `cart_redirect_after_add` is true (product-level option) — but the **redirect target is filterable**: `woocommerce_add_to_cart_redirect` filter.

For "immediate redirect to checkout (skip cart page)":
- Add hidden `add-to-cart` + `quantity` + `product_id` fields (classic form pattern, exactly what the official "quantity in loop" snippet uses).
- To force checkout redirect for the carousel's button, filter `woocommerce_add_to_cart_redirect` → return `wc_get_checkout_url()` when the request came from a carousel (e.g., query arg `carousel_redirect=checkout` set by the card's form). Fallback: also honor the `add-to-cart` + `?add-to-cart=` behavior so no-JS still works (the classic link `?add-to-cart=123` still adds and redirects per settings).
- On the JS side, when redirect-to-checkout is enabled, intercept the success and `window.location = wc_add_to_cart_params.checkout_url` (localize an extra param), and **do not** run the default cart redirect.

**Alternative considered**: WooCommerce **Store API** (`wc/store/cart` via `addItemToCart` from `@woocommerce/blocks-data-store`) — modern, but heavier, block-data dependency, and it conflicts with the "lightweight" goal; the classic `wc-add-to-cart` AJAX path is what the original PRO plugin ecosystem uses and is far easier to make conditional. **Recommendation: classic AJAX endpoint + fragments.**

### 3. Gutenberg block — shared renderer

- Register via **`register_block_type_from_metadata()`** with a `block.json` (namespace e.g. `cwc/carousel`), attribute `carouselId` (int) that references an admin-configured carousel, `apiVersion: 3`.
- Server-side render via `render.php` referenced in block.json → the block and the shortcode **share one renderer**: `render.php` calls the same `render_carousel($carousel_id)` function the shortcode callback uses. Only shell/attribute parsing differs.
- Editor: `edit.jsx` uses `useBlockProps()` and shows an **InspectorControls** select of configured carousels + a placeholder preview. `save: null` (fully dynamic block — server always renders, no serialization risk, no deprecations needed).
- Build tooling: **`@wordpress/scripts`** is the canonical choice (esbuild-based, ships lint/format/test scripts, `wp-scripts build`/`start`). Lightweight alternative (plain esbuild/parcel) is possible but loses `wp-scripts` defaults (`wp-env`, block asset helpers). Since the repo has npm + Node 24, **recommend `@wordpress/scripts`** (dev-only dependency).
- The **frontend block scripts must not depend on React** — frontend uses vanilla JS + Swiper; the React code only exists for the editor. Keep `viewScript` separate (classic script or `viewScriptModule`; classic `viewScript` is safer for broad theme compatibility with Astra).

### 4. Swiper.js — version pinning + conditional enqueue

- **Current stable: v14.0.7 (2026-07-28)** — ground-up TypeScript rewrite, smaller bundles, browser baseline Chrome/Edge 110+, Safari 16.4+, Firefox 110+. v12.2.0 is the last line supporting older browsers (same API; upgrade to v14 is code-compatible).
- The PRD says "current compiled+minified LOCAL version" → **vendor the compiled UMD bundle** (`swiper-bundle.min.js` + `swiper-bundle.min.css`) into the plugin's `assets/vendor/swiper/` — no build-time dependency, no CDN, deterministic.
- **Enqueue only on demand**: set a flag during `render_carousel()` (e.g., `CWCP()->set_carousel_rendered(true)` / a static `$rendered`), then in `wp_enqueue_scripts` check the flag and `wp_enqueue_style('cwc-swiper')` + `wp_enqueue_script('cwc-swiper')` only if a carousel was rendered. The shortcode's `do_shortcode` runs before `wp_enqueue_scripts` in normal page flow (shortcode content is rendered in `the_content`, but the flag approach with `wp_enqueue_scripts` requires the shortcode to execute early — safest pattern: enqueue from a late `wp_footer`/`wp_enqueue_scripts` check **plus** a `has_shortcode($post->post_content, 'cwc_carousel')` pre-check on `wp_enqueue_scripts` for the shortcode, and check block render flag for the block). Standard practice: pre-scan content on `wp_enqueue_scripts` (`has_shortcode()` + block detection via `has_block('cwc/carousel')`).
- **Version pinning risk**: pin the exact version in the handle's `$ver` (e.g., `'14.0.7'`) and record it in `README`/`composer.json` require-dev or a `VENDORED_ASSETS.md`. Re-vendoring is a manual, explicit step — never auto-update.

### 5. Module structure (WPCS + maintainability)

Recommended **modular directory layout** (single bootstrap + focused modules), consistent with the WP plugin dev skill guidelines (single bootstrap file, loader class registers hooks, admin code behind `is_admin()`):

```
carousel-plugin/                      (repo root = plugin root)
├── custom-woo-pro-carousel.php       # bootstrap: header, constants, require, activation/deactivation, boot loader
├── includes/
│   ├── class-plugin.php              # main loader: dependency check (WC), i18n, hook registration
│   ├── class-assets.php              # enqueue Swiper + plugin assets on demand
│   ├── class-query.php               # WC_Product_Query builder from carousel config (pure data layer)
│   ├── class-renderer.php            # shared renderer: card HTML + carousel wrapper (shortcode & block call this)
│   ├── class-shortcode.php           # [cwc_carousel id=""] registration
│   ├── class-cart.php                # AJAX add-to-cart enhancements + checkout redirect filter
│   ├── class-quick-view.php          # quick view modal AJAX + template (later change)
│   ├── class-badges.php              # "Oferta"/"Ahorra X%"/"Nuevo"/"Pocas Unidades" logic (later change)
│   └── admin/
│       ├── class-admin.php           # CPT or submenu + tabbed UI (Settings API per tab)
│       └── views/                    # tab templates (admin/views/tab-general.php, ...)
├── blocks/
│   └── carousel/
│       ├── block.json
│       ├── render.php                # thin wrapper → includes/class-renderer.php
│       └── build/                    # compiled editor JS (wp-scripts output)
├── assets/
│   ├── vendor/swiper/                # swiper-bundle.min.js + swiper-bundle.min.css (pinned version)
│   ├── js/frontend.js                # Swiper init + AJAX cart + quick view wiring
│   └── css/carousel.css              # modular CSS w/ CSS variables (colors, borders, typography)
├── languages/                        # .pot/.po/.mo
├── package.json                      # @wordpress/scripts dev dep (block build)
└── composer.json                     # dev: wp-coding-standards/wpcs + phpcs.xml.dist (later: phpunit/wordpress-tests)
```

Decision: **CPT vs Submenu** for carousels. Recommended: **CPT `cwc_carousel`** (each carousel = a post; settings stored as post meta via Settings-API-style metaboxes/tabs in the CPT edit screen) — advantages: multiple carousels naturally, `wp_insert_post` management, REST-ready, reuses WP list table/autosave; Cons: slightly more UI wiring. Submenu+option rows is simpler to implement but requires hand-rolled list/edit screens. Given the PRO feature set (multi-carousel, per-carousel config), **CPT is the recommended approach** (matches how most carousel plugins do it) with per-carousel meta arrays sanitized with `sanitize_*` callbacks and capability checks.

Single-file vs modular: **modular wins** — PRD explicitly lists ~7 feature areas; one file would hit 2000+ lines and fail WPCS review ergonomics. Single bootstrap file keeps activation hooks at top-level (per plugin skill guidance).

### 6. Slice breakdown (sequencing the large PRD)

The full PRD is far too big for one change. Recommended slice order (each slice = own SDD change, reviewable ≤400 lines):

| # | Change | Scope | Est. files | Depends on |
|---|--------|-------|-----------|------------|
| 1 | `woo-pro-carousel-bootstrap` (THIS) | Plugin skeleton: bootstrap + loader + dependency check (WC active), i18n, assets module w/ vendored Swiper + on-demand enqueue, query module (basic), renderer (basic card: image, title, price), shortcode `[cwc_carousel]`, hardcoded/seed config. **No admin UI yet.** | ~10 | — |
| 2 | `woo-pro-carousel-admin` | CPT `cwc_carousel` + tabbed settings (filters, query logic, Swiper settings, card options) persisted as meta; admin JS/CSS; list/edit screens | ~8 | 1 |
| 3 | `woo-pro-carousel-filters` | Full filter set (on sale, best sellers, top rated, featured, manual IDs, categories/tags, stock hiding, orderings) wired from config | ~3 | 1,2 |
| 4 | `woo-pro-carousel-block` | Gutenberg block (`block.json`, edit, render.php sharing renderer), @wordpress/scripts build setup | ~6 | 1,2 |
| 5 | `woo-pro-carousel-cart` | AJAX add-to-cart card button + quantity selector + redirect-to-checkout toggle | ~4 | 1,3 |
| 6 | `woo-pro-carousel-conversion` | Badges, hover 2nd image, title char limit, ratings, quick view modal, CSS variable theming (Customizer hook) | ~8 | 1,5 |
| 7 | `woo-pro-carousel-testing` | PHPUnit + wp-env setup, unit tests for query/renderer, smoke tests | ~6 | any after 1 |

Each slice keeps tests-with-code where the runner exists (from slice 7); earlier slices ship linted code (phpcs via WPCS).

### 7. Testing approach (strict_tdd=false, runner feasible)

- **Now**: `strict_tdd` stays false; enforce **WPCS lint** early (composer require-dev `wp-coding-standards/wpcs` + `phpcs.xml.dist`, rule set `WordPress` + `WordPress-Extra`) as the always-green gate. This is cheap and catches security/escaping errors.
- **Later (slice 7)**: add `phpunit/phpunit` + `wp-phpunit/wp-phpunit` (or `yoast/phpunit-polyfills`) with `wp-env` (Docker present) as the integration environment; `tests/` mirroring `includes/`. Then flip `strict_tdd` on for subsequent slices. WP-CLI not installed → use `wp-env`'s bundled WP-CLI via `npx wp-env run cli wp ...` (no global install needed).

## Approaches Compared

| Approach | Pros | Cons | Complexity |
|---|---|---|---|
| A. Single-file plugin (all logic in main PHP) | Fastest to write, no structure decisions | 2000+ lines, violates WPCS review ergonomics, poor separation of concerns for 7 feature areas, hard to test | High (in maintenance) |
| B. Modular includes/ + loader (recommended) | Clean separation, testable units, admin isolated behind is_admin, each module small + reviewable, matches WP plugin handbook | More upfront structure, slightly more files | Med |
| C. Namespaced OOP (PSR-4 autoload, full class-per-file) | Modern, IDE-friendly, testable | Overkill for plugin size, Composer autoload in plugin adds activation complexity, WP ecosystem favors pragmatic loading | Med |

Query approach: **WC_Product_Query only, no raw SQL, no post-fetch filtering** (respect core transients for on-sale/featured IDs).

AJAX approach: **classic `wc-ajax=add_to_cart` + fragments** (not Store API blocks data-store).

Block approach: **dynamic block via block.json + render.php sharing the PHP renderer** (save: null), editor React built with `@wordpress/scripts`.

Swiper: **vendor local compiled bundle, pinned version (v14.0.7), enqueue on demand** via content pre-scan + render flag.

## Recommendation

Modular structure (B) with:

1. **Bootstrap** = single main file with header + constants + loader (approach B), activation hooks top-level.
2. **CPT `cwc_carousel`** for multi-carousel management (admin slice), meta-backed settings, tabbed edit screen.
3. **Shared renderer** in `includes/class-renderer.php` consumed by shortcode AND block render.php.
4. **WC_Product_Query** as sole data access, with core ID helpers for on-sale/featured and meta orderby for best-sellers/top-rated.
5. **Classic WooCommerce AJAX** add-to-cart (fragments) + `woocommerce_add_to_cart_redirect` filter for checkout redirect.
6. **Vendored Swiper** (pinned 14.0.7; fall back to 12.2.0 if the store needs older-browser support), conditional enqueue only where a carousel renders.
7. **Slice into 7 changes** per table above; this change = slice 1 (bootstrap + skeleton + shortcode + basic render + Swiper enqueue).

## Risks

- **Scope size**: full PRD is large; must be sliced (7 changes). Do NOT fold admin UI into this bootstrap change.
- **Test-runner absence**: no PHPUnit/wp-env config yet; verification for early slices relies on phpcs + manual/wp-env smoke. Add runner in slice 7 before strict_tdd.
- **Swiper version pinning**: v14 is a fresh TypeScript rewrite (2026-06). Pin exact version + vendor locally; document the fallback to v12 for older browsers. Never pull from CDN.
- **WooCommerce dependency**: plugin must deactivate gracefully when WC is missing (loader check `class_exists('WooCommerce')`).
- **Astra theme compat**: test against Astra; avoid theme-coupled CSS beyond variables; use `get_header`-agnostic enqueueing (enqueue on demand, not on all pages).
- **AJAX redirect interplay**: `woocommerce_add_to_cart_redirect` is global — scope the checkout redirect to carousel-originated requests only (query arg) to avoid hijacking normal add-to-cart flows.
- **No WP-CLI**: use `npx wp-env run cli` for WP-CLI needs instead of installing it globally.

## Ready for Proposal

**Yes.** Enough research is done to write the proposal for change `woo-pro-carousel-bootstrap` (slice 1). The orchestrator should tell the user: architecture = modular `includes/` + CPT for carousels (added in slice 2), shared renderer, WC_Product_Query data layer, classic WooCommerce AJAX, vendored pinned Swiper enqueued on demand; the full PRD will be delivered as 7 sequential SDD changes.
