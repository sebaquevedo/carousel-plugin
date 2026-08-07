# Design: Configurable Carousels (Admin Panel + Per-Instance Overrides)

## Technical Approach

Turn the hardcoded presentation values (shortcode defaults + `frontend.js` 1/2/3 ramp) into a
resolved config system. A pure `CWC_Settings` model owns `defaults()` (reads sanitized
`cwc_carousel_options`, autoload off) and `resolve($atts)` (merges admin-defaults → explicit
shortcode atts). A Settings API page (new `CWC_Admin`) edits the globals. The shortcode resolves
one config per instance; the query layer consumes selection/count, the renderer consumes the rest
and emits one JSON `data-cwc-config` per container; `frontend.js` builds Swiper options from it.
`count` stays numeric-only (`0 ⇒ empty`, no `all`).

## 1. Architecture Decisions

| # | Decision | Options | Choice / Rationale |
|---|----------|---------|---------------------|
| D1 | Config storage | CPT slice-2 plan / Settings API | Settings API — global+override (no saved presets); CPT deferred |
| D2 | Per-category keys | `category` slugs / `tax_query` term_ids | **`tax_query` + `field=>term_id`** — exact, one call, no slug lookups (PQ-4/5) |
| D3 | `count` semantics | `all` sentinel / numeric-only | **Numeric-only**, `0 ⇒ []` — confirmed PQ-1; no `all` |
| D4 | JS channel | `wp_localize_script` global / per-container `data-` | **Per-container `data-cwc-config`** — two instances differ on one page |
| D5 | Config JSON emit | renderer builds / shortcode builds | **Renderer, one `wp_json_encode`+`esc_attr`** — CR-7 single emit point |
| D6 | Per-cat image upload | raw id-field / `wp.media` | **`wp.media` uploader, id in term meta** — WC-idiomatic, reuses library |
| D7 | Admin assets | always / on-page only | **Only our screen** (`admin_enqueue_scripts` + hook suffix guard, FA-5) |
| D8 | Category term fetch | in renderer / query layer | **`CWC_Query::get_categories()`** — keeps data access in query layer |

## 2. Resolved Config Contract (all consumers share it)

```php
// CWC_Settings::resolve($atts): array — admin defaults → explicit atts; unknown dropped.
array(
    'type'          => 'product',        // 'product'|'category'
    'title'         => '',               // shortcode-only (sanitize_text_field)
    'category'      => 0,               // int  single-term primary (product) — absent in option
    'categories'    => array(),         // int[] term ids — selection default + mix seed
    'mix'           => false,           // bool — mix mode (product, categories multi)
    'count'         => 8,               // int  absint; 0 ⇒ empty; no 'all'
    'slides'        => 3,               // int  desktop slidesPerView (shortcode 'slides')
    'slides_tablet' => 2,               // int  (admin only)
    'slides_mobile' => 1,               // int  (admin only)
    'gap'           => 16,              // int  px → spaceBetween + --cwc-gap
    'buy'           => true,            // bool — show Buy button
    'buy_text'      => 'Comprar',       // string, sanitize_text_field, default 'Comprar'
);
```

`defaults()` reads `get_option('cwc_carousel_options', [])`, `wp_parse_args` over built-ins
(`product`, 3/2/1, gap 16, count 8, buy true, 'Comprar', categories `[]`) — read-only, no option
write (CM-1). `resolve($atts)` whitelist-maps atts → keys (`type`, `title`, `category`,
`categories` via `wp_parse_id_list`+absint, `mix` from `'yes'`, `count` absint, `slides`→`slides`,
`buy`, `buy_text`), each sanitized. Pure/side-effect-free (CM-4).

## 3. Admin Page (CWC_Admin, new `includes/class-admin.php`)

- `add_submenu_page('woocommerce','Carousel','Carousel','manage_woocommerce','cwc-carousel',...)` (AS-1). Capability via menu arg + `current_user_can` guard on save.
- `register_setting('cwc_options_group','cwc_carousel_options',[_]['admin','sanitize_options'])`, autoload-off arg; sections/fields: type select, `product_cat` multiselect (chosen `categories`), slides desktop/tablet/mobile, gap, count, buy checkbox + buy_text. **One sanitize callback** validates each field (slides ints 1–12, gap 8–64, count int ≥0, categories `wp_parse_id_list`+absint, buy → bool, buy_text `sanitize_text_field`, type in {product,category}) (AS-2).
- **Per-category image (AS-3)**: separate field group `cwc_cat_images[<term_id>]` + nonce on the same form; hidden attachment-id input + preview + Upload button. `wp.media` opened from `assets/js/admin.js` (enqueued via `admin_enqueue_scripts` + hook-suffix + `wp_enqueue_media`), sets the input + preview `<img>`. Saved by `CWC_Admin::save_category_images()` on `admin_init`, guarded by `check_admin_referer` + `current_user_can`, writing `absint` id via `update_term_meta($term_id,'cwc_category_image',$id)`. Keeps term side-effects OUT of the pure option sanitizer (D7).
- Hooks: `admin_menu`, `admin_init`, `admin_enqueue_scripts` (screen-guarded).

## 4. Query (product-query)

Keep pure arg-builder + `apply_filters('cwc_carousel_query_args')`. New args:
- `type=product` single `category` id → `tax_query` single `['taxonomy'=>'product_cat','field'=>'term_id','terms'=>[$cat]]` (PQ-4).
- `type=product`, `mix` + `categories` → same tax_query with `terms`=ids, default `operator IN` (PQ-5). Empty sanitized terms ⇒ `[]`.
- `count` absint; `0` short-circuits `[]`. Defaults unchanged (recent, date desc).
- `CWC_Query::get_categories(array $term_ids): array` → `get_terms(['taxonomy'=>'product_cat','include'=>ids,'hide_empty'=>true,'orderby'=>'include'])` (D8; category card data).

## 5. Renderer (carousel-renderer)

`render(array $config, array $items): string` dispatches on `config['type']` (`$items` = `WC_Product[]` or `WP_Term[]`):
- `category`: for each term `render_category_card($term, $cfg)` — image = `get_term_meta` custom `cwc_cat_image` ?: `thumbnail_id`, via `wp_get_attachment_image` (or placeholder), name `esc_html($term->name)`, whole card `<a href=esc_url(get_term_link($term,'product_cat'))>`; wrapped `.swiper-slide cwc-category-card`.
- `product`: existing `render_card()` gains category line (`wc_get_product_term_ids`, shallow term `esc_html`) + gated Buy; Buy only when `buy && is_purchasable() && (is_in_stock()||backorders_allowed())`, `<a class="cwc-card__buy btn" href=esc_url(get_permalink())>` label `esc_html(buy_text)`.

Container emits `data-cwc-config="' . esc_attr( wp_json_encode( $config ) ) . '"` **once** (CR-7). Empty set/no terms ⇒ `''`, no render flag (graceful).

## 6. Shortcode (carousel-shortcode)

`shortcode_atts([whitelist], $atts, 'cwc_carousel')` still drops unknowns (SC-4). Then
`$cfg = (new CWC_Settings())->resolve($atts)` (SC-5: per-instance). Dispatch:
- `type=product` → `CWC_Query::query($cfg-args)` (category/mix/count) → `renderer->render($cfg, $products)`.
- `type=category` → `CWC_Query::get_categories($cfg['categories'])` → `renderer->render($cfg, $terms)`.

## 7. Frontend (frontend-assets)

`frontend.js` for each `.cwc-carousel.swiper`: guard `typeof Swiper==='undefined'`; `try/catch JSON.parse(el.dataset.cwcConfig)`; fallback defaults. Options: `{resizeObserver:false, spaceBetween:cfg.gap||16, slidesPerView:cfg.slides_mobile||1, navigation/pagination (scoped), breakpoints:{768:{slidesPerView:cfg.slides_tablet||2},1024:{slidesPerView:cfg.slides_desktop||3}}}`. Two instances each read their own attr. `CWC_Assets` keeps on-demand enqueue; adds `admin_enqueue_scripts` only for our screen (FA-5).

## 8. Wiring + CSS

- Bootstrap `custom-woo-pro-carousel.php`: require-map += `class-settings.php`, `class-admin.php`.
- `CWC_Plugin::register_modules()`: `new CWC_Settings()`, `new CWC_Admin()` (class_exists guards).
- CSS: `.cwc-card__category-line`, `.cwc-card__buy`, `.cwc-category-card__image`; reuse `--cwc-*` tokens + `--cwc-gap` (themeable onto `.cwc-carousel`).

## Data Flow

```
[p] → shortcode_atts → CWC_Settings::resolve($atts)  (admin option merged)
        ├─ product  → CWC_Query::query(tax_query) → WC_Product[] → renderer (product cards + buy + data-attr)
        └─ category → CWC_Query::get_categories()  → WP_Term[]  → renderer (category cards + data-attr)
frontend.js ← reads data-cwc-config → new Swiper(opts)
admin form ──→ sanitize callback ──→ cwc_carousel_options (autoload off)
          + cwc_cat_images ──→ term_meta (cwc_cat_image)
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `includes/class-settings.php` | Create | `CWC_Settings` defaults()+resolve()+serialize subset |
| `includes/class-admin.php` | Create | Settings page, sanitize, per-category upload, admin enqueue |
| `assets/js/admin.js` | Create | `wp.media` uploader for category images |
| `assets/js/frontend.js` | Modify | data-config → dynamic slides/breakpoints/gap |
| `assets/css/carousel.css` | Modify | category card, category line, buy button styles |
| `includes/class-query.php` | Modify | tax_query(PQ-4/5) + get_categories |
| `includes/class-renderer.php` | Modify | type dispatch + cat card + buy + data-cwc-config |
| `includes/class-shortcode.php` | Modify | new atts + resolve() dispatch |
| `includes/class-assets.php` | Modify | admin screen-guard enqueue |
| `includes/class-plugin.php` + bootstrap | Modify | instantiate settings/admin; require-map |

## Interfaces / Contracts

```php
class CWC_Settings {
  public function defaults(): array;                 // option → defaults
  public function resolve( array $atts ): array;     // per-instance config
}
class CWC_Query {
  public function query( array $args ): array;               // WC_Product[]
  public function get_categories( array $term_ids ): array;  // WP_Term[] product_cat
}
class CWC_Renderer {
  public static $rendered;
  // $config['type']='product' → $items are WC_Product[] (product card + buy)
  // $config['type']='category' → $items are WP_Term[] (category card)
  public function render( array $config, array $items ): string;
}
```

## Testing Strategy

| Layer | What | Approach |
|-------|------|----------|
| Lint | All PHP | `composer phpcs` (WordPress+Extra) always-green |
| Manual | Admin page only on our screen (FA-5); save → autoload off; rollback delete_option; upload overrides thumbnail; `data-cwc-config` per instance; two same-shortcode carousels differ; `count=0` empty | wp-env smoke + manual settings |

## Migration / Rollout

Option is new (autoload off). Existing shortcodes render unchanged (admin defaults equal yesterday's hardcoded 1/2/3, gap 16). Rollback = `delete_option('cwc_carousel_options')` + deactivate; no DB schema.

## Chained-PR Split (review budget, >400 lines)

- **Split A (backend + render)** — `class-settings.php`, query tax/cat-ext, shortcode resolve+dispatch, renderer (two cards + buy + data-attr), `frontend.js`, `carousel.css`, `class-assets` frontend part. (Biggest file → renderer methods kept slim.)
- **Split B (admin)** — `class-admin.php`, `admin.js`, per-category upload, admin enqueue guards, do bootstrap wiring (require-map + plugin instantiate) here or with A per dependency.

## Open Questions

- [ ] None blocking. Verify `tax_query` + `orderby=include` `get_terms` ordering on target WC; `wp.media` uploader nonce pattern on real WP version.
- [ ] Category-card Buy/Card markup: confirm `.cwc-card__buy` as `<a>` vs `<button>` (spec says element linking to product page → `<a>`).