# Tasks: Woo Pro Carousel Bootstrap (Slice 1 of 7)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~700-800 (PHP ~480 + CSS/JS ~150 + docs/config ~50; vendored Swiper ~167k excluded from phpcs) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 foundation → PR 2 query/render pipeline → PR 3 frontend assets |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | WPCS dev gate + bootstrap + WC guard | PR 1 | composer.json, phpcs.xml.dist, `custom-woo-pro-carousel.php`, `includes/class-plugin.php`. Verifiable: wpcs green, activates, no-WC notice. |
| 2 | Query → shortcode → renderer pipeline | PR 2 | `class-query.php`, `class-shortcode.php`, `class-renderer.php`. Verifiable: `[cwc_carousel]` outputs Swiper card markup. |
| 3 | On-demand frontend assets | PR 3 | `assets/vendor/swiper/`, `assets/css/carousel.css`, `assets/js/frontend.js`, `languages/`, `VENDORED_ASSETS.md`. Verifiable: DOM shows Swiper/CSS only on carousel pages. |

## Phase 1: Foundations

- [x] 1.1 Create `composer.json` (require-dev wpcs ^3.1, `phpcs`/`phpcbf` scripts). (PB)
- [x] 1.2 Create `phpcs.xml.dist` (`WordPress` + `WordPress-Extra`, exclude `vendor/`, `assets/vendor/`).
- [x] 1.3 Create `custom-woo-pro-carousel.php` — header (`Plugin Name`, `Version`, `Requires PHP >= 7.4`, `Text Domain: cwc-carousel`), consts `CWC_VERSION/CWC_FILE/CWC_DIR/CWC_URL` guarded, top-level `register_activation_hook`/`register_deactivation_hook` → no-op, require-map of 5 `includes/` classes, boot `CWC_Plugin` on `plugins_loaded` (prio 10). (PB-1, PB-2, D1, D2)
- [x] 1.4 Create `includes/class-plugin.php` — `CWC_Plugin::run()`: always `load_plugin_textdomain('cwc-carousel', false, .../languages)` + register `admin_notices` notice callback (capability `activate_plugins`, escaped/translatable); then `class_exists('WooCommerce')` check → on `admin_init` `deactivate_plugins(plugin_basename(CWC_FILE))`, no partial state, instantiate `CWC_Assets` + `CWC_Shortcode` only when WC present. (PB-3, WD-1, WD-2, WD-3)

## Phase 2: Data & Render Pipeline

- [x] 2.1 Create `includes/class-query.php` — `CWC_Query::query(array $args): array`: `limit` absint with `if (0 === $limit) return [];`; defaults recent publish/date DESC; `ids` via `wp_parse_id_list`→`absint`→filter `<= 0`→`include` + `orderby=post__in`; ids supplied but empty → return `[]` (no default substitution); no raw SQL/side effects; `apply_filters('cwc_carousel_query_args', ...)`. (PQ-1, PQ-2, PQ-3, D7)
- [x] 2.2 Create `includes/class-shortcode.php` — `add_shortcode('cwc_carousel', [$this,'render'])`, `shortcode_atts(['title'=>'','count'=>8,'ids'=>''])`, `sanitize_text_field(title)`, `absint(count)`, ids parse per 2.1; `CWC_Query::query() → CWC_Renderer::render()`; returns string, never echoes. (SC-1, SC-2, SC-3)
- [x] 2.3 Create `includes/class-renderer.php` — `public static $rendered=false`; `render(array $products, string $title=''): string` returns `''` (no flag) on empty set; data strictly from each `WC_Product` (never `$post`); container `.cwc-carousel swiper` → `.swiper-wrapper` → `.swiper-slide cwc-card` with `esc_url(get_permalink())`, `get_image('woocommerce_thumbnail', ...)` or CSS placeholder when no thumbnail, `esc_html(get_name())`, `get_price_html()`; `<h2 class="cwc-carousel__title">` only when non-empty; optional `.swiper-pagination`/`.swiper-button-prev|next`. (CR-1, CR-2, CR-3, CR-4, D5)

## Phase 3: Frontend Assets

- [x] 3.1 Commit vendored Swiper 14.0.7 `swiper-bundle.min.js`/`.css` under `assets/vendor/swiper/`; verify filenames against downloaded package. (FA-1, D4)
- [x] 3.2 Create `includes/class-assets.php` — handles `cwc-swiper` (js+css ver `'14.0.7'`), `cwc-carousel` (css `CWC_VERSION`), `cwc-carousel-frontend` (js deps `['cwc-swiper']`, in_footer); header pre-scan `has_shortcode($post->post_content, 'cwc_carousel')` + `wp_footer` render-flag late enqueue. (FA-2, D3)
- [x] 3.3 Create `assets/js/frontend.js` — DOMContentLoaded → `document.querySelectorAll('.cwc-carousel.swiper')` → `new Swiper(..., {breakpoints:{768:2,1024:3}, slidesPerView:1, spaceBetween:16, navigation, pagination})`. (FA-2, D4)
- [x] 3.4 Create `assets/css/carousel.css` — `--cwc-*` custom properties (colors/borders/gap/typography) on `.cwc-carousel` at normal specificity + card/container styles. (FA-3, CR-3, D6)
- [x] 3.5 Create `languages/.gitkeep`. (PB-3)

## Phase 4: Docs & Verification

- [ ] 4.1 Create `VENDORED_ASSETS.md` — pin record, acquisition, browser baseline (Chrome/Edge 110+, Safari 16.4+, Firefox 110+), v12.2.0 fallback. (FA-1)
- [ ] 4.2 Run `composer phpcs` → zero errors (always-green gate).
- [ ] 4.3 Manual wp-env smoke: fresh WP+WC renders `[cwc_carousel]`; no-WC shows notice without fatal; Swiper/CSS enqueued ONLY on carousel pages; 1/2/3 breakpoints; `ids` order preserved.