# Tasks: Configurable Carousels (Admin Panel + Per-Instance Overrides)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~720-810 total (Split A ~420-460: PHP ~300 + JS ~40 + CSS ~35 + wiring; Split B ~300-350: admin PHP ~280 + JS ~45) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 Split A (backend + render) → PR 2 Split B (admin) |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Split A: settings model + query/shortcode/renderer + frontend | PR 1 | `class-settings.php`, query tax-ext, shortcode resolve+dispatch, renderer type-cards+buy+data-attr, `frontend.js`, `carousel.css`, require-map(class-settings). Verifiable: two differing carousels on one page; Buy gating; phpcs green. |
| 2 | Split B: admin panel + upload + wiring | PR 2 | `class-admin.php`, `admin.js`, assets admin guard, require-map(class-admin)+register_modules. Verifiable: page gated by `manage_woocommerce`, save autoload-off, upload overrides thumbnail, `delete_option` rollback. |

## Split A: Backend + Render

- [x] **A1** Create `includes/class-settings.php` — `defaults()`: `get_option('cwc_carousel_options', [])` wp_parse_args over built-ins (type=product, slides 3/2/1, gap 16, count 8, buy true, 'Comprar', categories []); read-only, never writes option (CM-1). `resolve($atts)`: whitelist-map type/title/category/categories(`wp_parse_id_list`+absint)/mix('yes')/count(absint)/slides/buy/buy_text, sanitize each; pure, unknown dropped (CM-2, CM-4). count numeric-only, no 'all' (CM-3). DONE: config array serializes via `wp_json_encode`.
- [x] **A2** Modify `custom-woo-pro-carousel.php` — append `'class-settings.php'` to `$cwc_includes` require-map (needed by shortcode in A4). DONE: `class_exists('CWC_Settings')` after boot.
- [x] **A3** Modify `includes/class-query.php` — add single `category` → `tax_query` `['taxonomy'=>'product_cat','field'=>'term_id','terms'=>[$cat]]` (PQ-4); mix + `categories` → same tax_query `terms`=ids, default IN (PQ-5); empty sanitized terms ⇒ `[]`; add `get_categories(array $ids): array` via `get_terms` include + `orderby=include` + hide_empty (D8). Keep pure builder + `cwc_carousel_query_args` filter. DONE: no raw SQL; zero/empty short-circuit.
- [x] **A4** Modify `includes/class-shortcode.php` — whitelist `shortcode_atts` gains `type/category/categories/mix/count/slides/buy/buy_text` (drop `ids` or keep per compat — see notes); `$cfg = (new CWC_Settings())->resolve($atts)` per instance (SC-5); dispatch: type=product → `CWC_Query::query()` (count/category/mix), type=category → `CWC_Query::get_categories($cfg['categories'])` (SC-4). DONE: two instances resolve independently; returns string.
- [x] **A5** Modify `includes/class-renderer.php` — `render(array $config, array $items)` dispatch on `config['type']`; category card: image = `get_term_meta('cwc_cat_image')` ?: `thumbnail_id` → `wp_get_attachment_image` or placeholder, whole-card `<a href=esc_url(get_term_link())>` (CR-5); product card: category line (`wc_get_product_term_ids` + `esc_html`) + Buy `<a class="cwc-card__buy" href=esc_url(get_permalink())>` only when `buy && is_purchasable() && (is_in_stock()||backorders_allowed())`, label `esc_html(buy_text)` (CR-6); emit `data-cwc-config="' . esc_attr(wp_json_encode($config)) . '"` once per container (CR-7). DONE: empty set/terms ⇒ '' without render flag.
- [x] **A6** Modify `assets/js/frontend.js` — per container: `typeof Swiper` guard; `try/catch JSON.parse(el.dataset.cwcConfig)` fallback defaults; options `{resizeObserver:false, spaceBetween:cfg.gap||16, slidesPerView:cfg.slides_mobile||1, navigation/pagination scoped, breakpoints:{768:{slidesPerView:cfg.slides_tablet||2},1024:{slidesPerView:cfg.slides||3}}}` (FA-4). DONE: two carousels read their own attr.
- [x] **A7** Modify `assets/css/carousel.css` — add `.cwc-card__category-line`, `.cwc-card__buy`, `.cwc-category-card__image` styles reusing `--cwc-*` tokens; keep `--cwc-gap` themeable on `.cwc-carousel`. DONE: no new top-level colors.
- [x] **A8** Verify `includes/class-assets.php` frontend path — no new handle/enqueue needed for dynamic config; pre-scan + render-flag unchanged (FA-2, FA-4). DONE: frontend CSS/JS still only on carousel pages.

## Split B: Admin

- [x] **B1** Create `includes/class-admin.php` — submenu "Carousel" under WooCommerce, cap `manage_woocommerce` (AS-1); `register_setting('cwc_options_group','cwc_carousel_options', sanitize)` autoload-off, sections/fields (type, product_cat multiselect, slides 1-12, gap 8-64, count ≥0, buy toggle/text); one sanitize callback (AS-2); per-category `cwc_cat_images[<term_id>]` field group + nonce + `save_category_images()` on `admin_init` (check_admin_referer + `current_user_can`, writes `cwc_cat_image` term meta), keeping term side-effects out of the option sanitizer (AS-3, D7). Dep: A1 (defaults shape). DONE: `delete_option` fully resets.
- [x] **B2** Create `assets/js/admin.js` — `wp.media` uploader: click → open library, set hidden attachment-id input + preview `<img>` per category row. Dep: B1. DONE: upload writes id, preview refreshes.
- [x] **B3** Modify `includes/class-assets.php` — add admin enqueue on `admin_enqueue_scripts` guarded by hook suffix of our screen + `wp_enqueue_media` + `admin.js`, cap `manage_woocommerce`; never on frontend (FA-5). Dep: B1. DONE: assets absent on other admin screens.
- [x] **B4** Modify `custom-woo-pro-carousel.php` (+`'class-admin.php'` require-map) and `includes/class-plugin.php` `register_modules()` — instantiate `new CWC_Settings()` + `new CWC_Admin()` behind `class_exists` guards. Dep: A2, B1. DONE: boot clean without WC fatal (guard path).
- [x] **B5** Verification gate — phpcs exits 0 on the LF checkout (only CRLF checkout artifacts remain locally); `manage_woocommerce` declared in `phpcs.xml.dist`; `php -l` clean on all changed PHP; `node --check` clean on `admin.js`; `cwc_cat_image` term-meta key confirmed as the one both written and read. DONE: no real (non-EOL) phpcs violations introduced.

## Task Ordering Notes

- **Dependency chain**: A1 → A2 → A4 → A5 (renderer consumes config + `get_categories`); A3 independent of A1 but feeds A5; A6 depends on A5 (data-attr exists); A7/A8 independent.
- **Parallelism**: B1..B4 can start once A1 lands (defaults shape); do NOT wire `class-admin.php` (B4) before B1 exists or the no-WC path fatals — `class_exists` guards cover it.
- **Gates**: `composer phpcs` always-green after each task; wp-env smoke per split (A8/A5 scenarios for A, B5 for B). `strict_tdd: false` — no unit runner; manual smoke is the gate.
- **Open question 1 (Buy element)**: CR-6 says "element, not text link"; design recommends `<a>` (links to product page) — apply uses `<a class="cwc-card__buy btn">` per design §5.
- **Open question 2 (WC behavior)**: apply must verify `tax_query` single/mix + `get_terms` `orderby=include` ordering on the target WC version during smoke.
