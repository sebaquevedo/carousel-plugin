# Design: Woo Pro Carousel Bootstrap (Slice 1 of 7)

## Technical Approach

Modular `includes/` plugin (approach B): one bootstrap file, five focused classes,
`WC_Product_Query` as sole data access, pinned vendored Swiper v14.0.7 enqueued only
where a carousel renders. Shortcode `[cwc_carousel]` → query → shared renderer. WPCS
(`WordPress` + `WordPress-Extra`) is the always-green gate (strict_tdd false).
Satisfies all 6 specs: PB, WD, FA, PQ, CR, SC. Self-contained ZIP, no runtime
Composer/Node/CDN.

## 1. File / Module Layout

```
custom-woo-pro-carousel.php   # header, consts, require-map, activation hooks, boot
includes/
  class-plugin.php            # CWC_Plugin — WC guard, i18n, instantiates modules
  class-assets.php            # CWC_Assets — registers handles, on-demand enqueue
  class-query.php             # CWC_Query — pure WC_Product_Query builder
  class-renderer.php          # CWC_Renderer — escaped cards + Swiper wrapper + title
  class-shortcode.php         # CWC_Shortcode — [cwc_carousel]
assets/
  vendor/swiper/swiper-bundle.min.js|.css   # pinned 14.0.7 (committed)
  css/carousel.css            # --cwc-* custom properties
  js/frontend.js              # Swiper init (vanilla, no jQuery)
languages/                    # textdomain .mo target (.pot deferred — no wp-cli)
VENDORED_ASSETS.md            # pin record + v12 fallback note
composer.json  phpcs.xml.dist # dev-only WPCS gate
```

**Loading (D1/D2)**: explicit require-map of the 5 class files in the bootstrap —
never Composer autoload. Composer is dev-only (WPCS); shipping a generated
autoloader/`vendor/` inside the ZIP adds a build step and runtime coupling for zero
gain. Later slices add files to the same require list.

## 2. Bootstrapping

`custom-woo-pro-carousel.php`: plugin header (`Plugin Name`, `Version`, `Requires
PHP >= 7.4`, `Text Domain: cwc-carousel`); guarded consts `CWC_VERSION`,
`CWC_FILE`, `CWC_DIR`, `CWC_URL`; top-level `register_activation_hook`/
`register_deactivation_hook` → no-op functions (no DB/schema/options per PB-2);
require-map; boot `CWC_Plugin` on `plugins_loaded` (prio 10). Plugin header
`Requires PHP >= 7.4` for maximum WP/WooCommerce compatibility (distributable
plugin); the local dev environment is PHP 8.2.27. Code stays conservative and
WPCS-aligned but does not ban arrow functions or typed properties — both arrived
in PHP 7.4, so they are fully supported at the declared floor.

## 3. WC Dependency Guard

`CWC_Plugin::run()` begins by registering side-effect-free infrastructure
UNCONDITIONALLY, before the WooCommerce check: `load_plugin_textdomain(
'cwc-carousel', false, dirname( plugin_basename(CWC_FILE) ) . '/languages' )`
(PB-3; falls back to source strings when no `.mo`) and
`add_action( 'admin_notices', [...] )` — the WD-3 notice callback, guarded by
`current_user_can('activate_plugins')`, emitting one escaped, translatable
notice. Registering both up-front guarantees the "WooCommerce is required" notice
is reachable when WC is missing — never gated behind the success-path `return`.

Then the WC guard: `class_exists('WooCommerce')` (WC's class is defined at
include time, so reliable pre-`plugins_loaded`). If missing → skip Carousel
module instantiation (no partial state) WITHOUT calling `deactivate_plugins()`
on the front-end/CLI request: `deactivate_plugins()` lives in
`wp-admin/includes/plugin.php`, which is not loaded on non-admin requests, and
calling it there fatals — violating WD-2 ("must never produce a fatal error").
Automatic self-deactivation happens ONLY in an admin context: on `admin_init`,
if `! class_exists('WooCommerce')` then `deactivate_plugins( plugin_basename(
CWC_FILE ) )` so the plugin retires cleanly for the next admin request, with the
already-registered notice explaining why. WC present → instantiate `CWC_Assets` +
`CWC_Shortcode`.

## 4. On-Demand Asset Enqueue (dual trigger, FA-2)

`wp_enqueue_scripts` fires **before** `the_content`, so the shortcode has not run
yet. Two complementary triggers in `CWC_Assets`:

1. **Header pre-scan** on `wp_enqueue_scripts`: `$post = get_post(); if ( $post
   && has_shortcode( $post->post_content, 'cwc_carousel' ) )` → enqueue now.
2. **Render-flag fallback**: `CWC_Renderer::render()` sets `public static
   $rendered = true` (verified inside render). On `wp_footer` (prio 10): if
   `$rendered && ! $this->enqueued` → late-enqueue. Late CSS/JS are printed by
   core `print_late_styles` (prio 19) / `wp_print_footer_scripts` (prio 20), so
   widget/nested-shortcode paths the pre-scan misses still get assets.

Handles: `cwc-swiper` (style+script, `ver => '14.0.7'`), `cwc-carousel` (style,
`CWC_VERSION`), `cwc-carousel-frontend` (script, deps `['cwc-swiper']`,
`in_footer => true`). URLs from `CWC_URL`. Nothing loads on pages without a
carousel (DOM proof).

## 5. Swiper v14.0.7 Integration

`swiper-bundle.min.js` (152 kB) + `.css` (14.6 kB) fetched at dev time from
unpkg/npm and committed under `assets/vendor/swiper/` (FA-1). `frontend.js`:
DOMContentLoaded → `document.querySelectorAll('.cwc-carousel.swiper')` →
`new Swiper(el, { breakpoints: { 768:{slidesPerView:2}, 1024:{slidesPerView:3} }, slidesPerView:1, spaceBetween:16, navigation:{...}, pagination:{...} })`
(compound selector matching the single container `<div class="cwc-carousel
swiper">` in §7 — a descendant `'.cwc-carousel .swiper'` would match zero nodes
since both classes live on the same element, leaving Swiper uninitialized)
(Resolved Decision 2 defaults, hardcoded this slice). `VENDORED_ASSETS.md`
documents the pin, acquisition, browser baseline (Chrome/Edge 110+, Safari 16.4+,
Firefox 110+), and the v12.2.0 fallback (code-compatible; re-vendor + bump the
handle `ver` only).

## 6. Query Layer (PQ)

`CWC_Query::query( array $args = [] ) : array` — pure, side-effect-free, no raw
SQL. `ids`: first parse the comma/whitespace string into a unique `int[]` via
`wp_parse_id_list( $raw_ids )`, then `array_map( 'absint', ... )` and an
`array_filter` dropping any value `<= 0` (zeros AND negatives — `absint` coerces
`-3` to `3`, so filtering is required to honor PQ-2's "zero/negative dropped")
(PQ-2) — never `array_map('absint', ...)` directly over the raw string (that
iterates its characters and `absint('12,7,3')` yields only `12`); `limit` →
`absint`, **short-circuit `if ( 0 === $limit ) return [];`** before any query (WP
`posts_per_page=0` semantics are unreliable; guarantee PQ-1 explicitly).
Defaults: `wc_get_products( [ 'limit', 'status' => 'publish', 'orderby' =>
'date', 'order' => 'DESC' ] )`. With a non-empty sanitized `$ids`: `'include' =>
$ids, 'orderby' => 'post__in'` (preserves order).
Distinct empty-result decision (PQ-2/SC-3): when NO `ids` attribute is supplied,
the default recent-products path runs. When the caller SUPPLIED a non-empty
`ids` attribute but every value sanitizes away, `CWC_Query::query()` returns `[]`
— an explicitly-requested-but-invalid selection renders nothing rather than
silently showing unrelated recent products. Seam: `$args` merged over defaults +
`apply_filters( 'cwc_carousel_query_args', $query_args )` for slice 3.

## 7. Renderer (CR)

`CWC_Renderer::render( array $products, string $title = '' ) : string` renders
from `WC_Product[]` objects — every per-product value comes **from the product,
never the global `$post`** (which is the page hosting the shortcode, so all cards
would otherwise be identical). Sets `self::$rendered = true`; returns `''` (no
flag) on empty set — graceful, no assets. Output, all escaped: container `<div
class="cwc-carousel swiper">` → `<div class="swiper-wrapper">` → per product
`<div class="swiper-slide cwc-card">` with:
- link: `esc_url( $product->get_permalink() )`
- image: `$product->get_image( 'woocommerce_thumbnail', array( 'class' =>
  'cwc-card__image' ) )` — delegates to core `get_the_post_thumbnail()`
  internally using `$product->get_id()`, so it always renders the right product
  image and returns an already-escaped `<img>`; a CSS-only placeholder span
  replaces it when `! has_post_thumbnail( $product->get_id() )` — no external
  image URL
- title: `esc_html( $product->get_name() )`
- price: `$product->get_price_html()` (WC formatting, core-escaped)

Title: `<h2 class="cwc-carousel__title">` only when non-empty (CR-4), `esc_html`
— single escape point (SC-2 intent) since slice-4 block also calls render.
Optional `.swiper-pagination` / `.swiper-button-prev|next`.

## 8. Shortcode (SC)

`CWC_Shortcode` registers `add_shortcode('cwc_carousel', [$this, 'render'])`.
`shortcode_atts( ['title'=>'', 'count'=>8, 'ids'=>''], $atts, 'cwc_carousel' )`
drops unknowns (SC-1). Sanitize: `sanitize_text_field(title)`, `absint(count)`,
`ids` via `wp_parse_id_list` + `absint` as in §6 (parse the comma-string into a
unique `int[]` first). `CWC_Query::query( ['ids'=>..., 'limit'=>$count] )` →
`CWC_Renderer::render()`. Returns the string (never echoes).

## 9. Theming (FA-3/CR-3)

`carousel.css` declares `--cwc-*` tokens (colors, borders, gap, typography) on
`.cwc-carousel` at normal specificity — **no inline style attribute**, so theme/
later-slice stylesheet overrides apply without re-compiling (FA-3). Per-carousel
inline overrides arrive with the CPT slice via `wp_add_inline_style`. Swiper CSS
is enqueued as its own handle (no `@import` — `@import` is render-blocking and
couples to the stylesheet path).

## 10. WPCS Lint Gate

`composer.json`: `require-dev { "wp-coding-standards/wpcs": "^3.1" }`, scripts
`phpcs`/`phpcbf` → `phpcs --standard=phpcs.xml.dist`. `phpcs.xml.dist`: rulesets
`WordPress` + `WordPress-Extra`, exclude `vendor/`, `assets/vendor/`. Always-green
gate for apply.

## Architecture Decisions

| # | Decision | Options | Choice / Rationale |
|---|----------|---------|---------------------|
| D1 | Structure | single-file / modular / PSR-4 | Modular (B) — 7 slices need seams; PSR-4 overkill |
| D2 | Loading | Composer autoload / require-map | Require-map — self-contained ZIP, no runtime vendor |
| D3 | Enqueue | pre-scan only / flag only / both | Both (§4) — each misses cases the other covers |
| D4 | Swiper | CDN / runtime npm / vendored | Vendored pinned 14.0.7 — self-contained, deterministic |
| D5 | Title escape | shortcode-escape / renderer-escape | Renderer esc_html once — block path shares renderer, no double-escape |
| D6 | CSS tokens | inline style / stylesheet vars | Stylesheet vars on `.cwc-carousel` — override-friendly |
| D7 | limit=0 | pass through / short-circuit | Short-circuit `return []` — spec-guaranteed |

## Data Flow

```
[the_content] → CWC_Shortcode::render()
  → shortcode_atts → sanitize (title/count/ids)
  → CWC_Query::query() → wc_get_products → WC_Product[]
  → CWC_Renderer::render() → escaped HTML string from each WC_Product (not $post) (sets $rendered=true)

CWC_Assets: wp_enqueue_scripts has_shortcode($post) → enqueue
           wp_footer: $rendered → late enqueue → print_late_styles(19)/footer_scripts(20)
frontend.js: DOMContentLoaded → new Swiper(..., 1/2/3 breakpoints)
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `custom-woo-pro-carousel.php` | Create | Header, consts, require-map, no-op activation hooks, boot |
| `includes/class-plugin.php` | Create | WC guard + self-deactivate + notice, textdomain, instantiate modules |
| `includes/class-assets.php` | Create | Handles, dual-trigger enqueue, ver `14.0.7` |
| `includes/class-query.php` | Create | Pure `WC_Product_Query` builder |
| `includes/class-renderer.php` | Create | Escaped cards, Swiper wrapper, title, `$rendered` |
| `includes/class-shortcode.php` | Create | `[cwc_carousel]` wiring |
| `assets/vendor/swiper/swiper-bundle.min.js` + `.css` | Create | Pinned 14.0.7 (FA-1) |
| `assets/css/carousel.css` | Create | `--cwc-*` tokens + card/container styles |
| `assets/js/frontend.js` | Create | Vanilla Swiper init (1/2/3 breakpoints) |
| `languages/` | Create | Empty dir + `.gitkeep`; `.pot` deferred |
| `VENDORED_ASSETS.md` | Create | Pin record + v12.2.0 fallback |
| `composer.json`, `phpcs.xml.dist` | Create | WPCS dev gate |

## Interfaces / Contracts

```php
class CWC_Query {
    public function query( array $args = array() ) : array; // WC_Product[]
    // args: 'ids' => int[] (wp_parse_id_list then absint + drop <=0; order via post__in;
    //                 no ids -> recent defaults; ids supplied but all invalid -> []),
    //           'limit' => int (0 -> [])
}
class CWC_Renderer {
    public static $rendered = false;
    // renders card data EXCLUSIVELY from each WC_Product (permalink/name/image/price),
    // never from the global $post which is the hosting page
    public function render( array $products, string $title = '' ) : string;
}
$atts = shortcode_atts( array( 'title' => '', 'count' => 8, 'ids' => '' ), $atts, 'cwc_carousel' );
// handles: cwc-swiper (js+css ver '14.0.7'), cwc-carousel (css), cwc-carousel-frontend (js)
```

## Testing Strategy

| Layer | What | Approach |
|-------|------|----------|
| Lint | All PHP | `composer phpcs` (WordPress+Extra) always-green |
| Manual smoke | Fresh WP+WC renders `[cwc_carousel]`; no-WC shows notice, no fatal; DOM shows Swiper/CSS **only** on carousel pages; 1/2/3 breakpoints; `ids` order | wp-env via `npx wp-env run cli wp ...` (no global wp-cli) |
| Review | Escaping surface, enqueue paths, 400-line budget | PR review + design |

## Migration / Rollout

No migration: no DB schema/options/transients (PB-2). Rollback = delete/deactivate
folder. Swiper v14 baseline Chrome/Edge 110+, Safari 16.4+, Firefox 110+;
v12.2.0 fallback documented in `VENDORED_ASSETS.md`.

## Next Phase

Dependency Graph edge: **design → tasks**. This design is the input for the `sdd-tasks` phase (break into implementation tasks).

## Open Questions

- [ ] None blocking. Apply must re-verify `swiper-bundle.min.js/.css` filenames in the downloaded package and `WC_Product_Query` `include` + `orderby=post__in` ordering on the target WC version.
- [ ] Renderer empty-set behavior: design returns `''` and skips the flag (no empty carousel UI, no assets) — confirm acceptable vs. emitting an empty wrapper.
