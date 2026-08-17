# Proposal: Woo Pro Carousel Bootstrap (Slice 1 of 7)

## Intent

Lay the foundation for "Custom Woo Pro Carousel", a modular, secure, lightweight
WordPress plugin (WooCommerce + Swiper, Astra-optimized). The repo has **no plugin
code** — this slice creates the skeleton later slices extend (admin, filters, block,
AJAX cart, conversion, testing). Deliver a working plugin that renders a basic
product carousel via `[cwc_carousel]`, proving the architecture end-to-end.

**Distribution assumption**: self-hosted installs via ZIP upload (NOT WordPress.org).
Plugin must be fully self-contained (all assets local), no Node at runtime in
production, no license/activation gatekeepers.

## Scope

### In Scope
1. Modular `includes/` skeleton + main bootstrap file (header, constants, activation hooks, require-map or autoloader).
2. Loader `class-plugin.php`: `class_exists('WooCommerce')` check → graceful deactivation + admin notice; `load_plugin_textdomain` i18n.
3. Vendored Swiper **v14.0.7** (local `swiper-bundle.min.js/.css`) committed under `assets/vendor/swiper/`; **on-demand enqueue** (content pre-scan `has_shortcode()` + render-flag). No CDN.
4. Query module `class-query.php`: pure `WC_Product_Query` builder — minimal default (recent products) + manual-ID passthrough (absint). Hardcoded defaults; no admin UI.
5. Renderer `class-renderer.php`: basic card (image, title, price) + Swiper markup.
6. `[cwc_carousel]` shortcode wiring query → renderer.
7. Modular CSS with CSS custom properties (colors/borders/typography) for future theme overrides. WPCS-only lint gate (phpcs, `WordPress`+`WordPress-Extra`).

### Out of Scope (later slices)
- Admin UI / CPT `cwc_carousel` + tabbed settings (slice 2)
- Full product filters / orderings (slice 3)
- Gutenberg block + wp-scripts build (slice 4)
- AJAX add-to-cart / qty selector / checkout redirect (slice 5)
- Badges / quick view / ratings / Customizer theming (slice 6)
- PHPUnit + wp-env test runner (slice 7)

## Capabilities

### New Capabilities
- `plugin-bootstrap`: main file, constants, activation hooks, module loader. → `openspec/specs/plugin-bootstrap/spec.md`
- `woocommerce-dependency`: WC presence check + graceful deactivation + admin notice.
- `frontend-assets`: vendored Swiper (v14.0.7), on-demand enqueue, local-only.
- `product-query`: minimal `WC_Product_Query` layer (recent + manual IDs), extendable.
- `carousel-renderer`: shared card/carousel HTML + CSS variables.
- `carousel-shortcode`: `[cwc_carousel]` registration.

### Modified Capabilities
- None.

## Approach

Modular `includes/` (approach B from exploration): single bootstrap file + focused
classes; `WC_Product_Query` sole data access; vendored pinned Swiper enqueued only
where a carousel renders; WPCS lint as the always-green gate (strict_tdd stays false).

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `custom-woo-pro-carousel.php` | New | Bootstrap: header, constants, requires, activation hooks |
| `includes/class-plugin.php` | New | Loader: WC check, i18n, hook registration |
| `includes/class-assets.php` | New | On-demand Swiper + CSS enqueue |
| `includes/class-query.php` | New | `WC_Product_Query` builder (recent + manual IDs) |
| `includes/class-renderer.php` | New | Card + carousel HTML, Swiper markup |
| `includes/class-shortcode.php` | New | `[cwc_carousel]` |
| `assets/vendor/swiper/` | New | Pinned v14.0.7 local bundle |
| `assets/css/carousel.css` | New | Modular CSS w/ custom properties |
| `languages/`, `composer.json`, `phpcs.xml.dist` | New | i18n + dev WPCS gate |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Scope creep into later slices | Med | Strict IN/OUT; admin UI → slice 2 |
| No test runner yet | High | phpcs always-green + manual wp-env smoke |
| Swiper v14 is fresh TS rewrite | Med | Pin 14.0.7 + vendor; document v12 fallback |
| WC missing at runtime | Low | Loader deactivates gracefully |

## Rollback Plan

Delete the plugin folder / deactivate in WP; no DB schema, options, or data written
in this slice, so removal is clean. `assets/vendor/swiper` re-vendor is manual only.

## Dependencies

- WooCommerce active (checked at load).
- PHP ≥ 7.4 (aligns with WC), WPCS dev dependency via Composer.
- Swiper v14.0.7 vendored locally (pinned).

## Success Criteria

- [ ] Fresh WP + WooCommerce install renders `[cwc_carousel]` (recent products) with Swiper markup.
- [ ] Active plugin without WooCommerce shows admin notice and does not fatal.
- [ ] Swiper/CSS enqueued ONLY on pages with the shortcode (verified via DOM).
- [ ] phpcs passes (WordPress + WordPress-Extra) with zero errors.
- [ ] ZIP install is self-contained with no runtime Node/CDN dependency.

<!-- Proposal question round: (1) Default query = recent products OK for this slice? (2) Manual-ID passthrough stays hardcoded until admin UI (slice 2)? (3) Swiper v14 baseline accepted, v12 fallback documented only? -->

## Resolved Decisions (from proposal Q&A)

Confirmed by the product owner. These shape later slices and the slice-1 shortcode:

1. **Slice-1 default query = recent products** (no admin UI yet). Manual/product + on-sale filters land in a later slice.
2. **Responsive columns, configurable:** 1 mobile / 2 tablet / 3 laptop+ by default, plus configurable inter-image gap -> Swiper settings slice.
3. **Query selection** among options (manual per-product, on-sale, etc.) -> filters slice.
4. **Multiple simultaneous carousels/configurations** -> CPT admin slice.
5. **Per-carousel title** (ad-hoc styled to the active theme/template) displayed above the carousel -> renderer/style slices.
6. **Product image override:** choose a gallery image or upload one on the fly (configurable), and custom arrow colors via RGB/hex -> visual/config slices.

Slice-1 continuation: the `[cwc_carousel]` shortcode should accept a `title` attribute now (low cost), ready for later config.