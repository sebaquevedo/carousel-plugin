# Exploration: Configurable Carousels — Admin Panel + Per-Shortcode Overrides

> SDD change: `configurable-carousels` (proposal to confirm) | artifact: `explore` | date: 2026-08-05
> Base: `woo-pro-carousel-bootstrap` (slice 1 of 7) — complete on `feat/cwc-carousel-03-assets`.

## Current State

The plugin is fully built for slice 1 and working (`[cwc_carousel]` renders a recent-product
carousel). The pipeline is a clean shortcode → query → renderer → HTML handoff, assets enqueued
on demand, Swiper v14.0.7 vendored and pinned, WPCS gate green. There is **no admin surface, no
settings, and no persisted configuration** — every presentation value is hardcoded in one of two
places: default `shortcode_atts` (title/count/ids) in the shortcode, and the Swiper options
(`slidesPerView 1/2/3`, breakpoints `768/1024`, `spaceBetween:16`) in `assets/js/frontend.js`.

This change converts those hardcoded presentation values into a **configurable system**: a global
defaults admin panel plus **per-shortcode overrides** (confirmed Option B), supporting **two
distinct carousel types** — category carousels and per-category product carousels.

### Confirmed product decisions (from requirements)
- **A) Category carousel**: `slidesPerView 4` initially (mobile→desktop ramp TBD). Explicitly
  **chosen categories** (selected `product_cat` terms, not auto-all). Each card = category image,
  links **directly to the category archive** page.
- **B) Product carousel (per-category)**: `slidesPerView 3` initially; lists **all OR a defined
  count N** of products of the selected category(ies). Each card = product image + the product's
  category / subcategory term + a **"Buy"** button linking to the product page. The Buy button
  appears **only for published, available (in-stock / purchasable) products**.
- Config is flexible: **global defaults in an admin panel + per-shortcode override**. The same
  shortcode must be able to produce different setups per instance.

## Affected Areas

| File | Impact | Why |
|------|--------|-----|
| `includes/class-shortcode.php` | Modify | New atts (`type`, `categories`, `count`, `slides`, `buy`); per-instance override merging over admin defaults |
| `includes/class-query.php` | Modify | Products-by-category (`tax_query`/`category`) + `count="all"` (−1) path; keep `ids`/recent paths |
| `includes/class-renderer.php` | Modify | Second card type (category card), product card gains category term + Buy button (gated), emit per-carousel settings to the container |
| `includes/class-assets.php` | Modify | Optional `wp_localize_script`; enqueue admin styles/scripts guard; carousel frontend still on-demand |
| `includes/class-plugin.php` + bootstrap require-map | Modify | Instantiate new CWC_Admin / CWC_Settings modules |
| `assets/js/frontend.js` | Modify | Read per-carousel settings (data attrs) → dynamic Swiper options; drop hardcoded 1/2/3 |
| `assets/css/carousel.css` | Modify | Category card + product-card category line + Buy button styles; new tokens |
| `includes/admin/…` (NEW) + `includes/class-admin.php` (NEW) | Create | Settings API page (global defaults); sanitize + capability |
| `includes/class-settings.php` (NEW, optional) | Create | Pure config model: `defaults()` + `resolve(shortcode_atts)`; shared by shortcode/renderer/assets |
| `openspec/specs/…` | Create | Mirror delta specs per domain (see conventions) |

## Terrain Map (technical unknowns, not solutions)

### 1. Current rendering pipeline (verified by reading the code)
`[cwc_carousel]` → `CWC_Shortcode::render()` → `shortcode_atts(['title'=>'','count'=>8,'ids'=>''])`
→ sanitize (`sanitize_text_field`/`absint`/`wp_parse_id_list`) → `CWC_Query::query()` →
`wc_get_products` → `WC_Product[]` → `CWC_Renderer::render()` → escaped HTML string (never echoes).
`CWC_Renderer::$rendered` static flag drives late enqueue. `CWC_Query` is pure + side-effect free
(PQ-3) with an `apply_filters('cwc_carousel_query_args')` seam and the PQ-2 `limit 0 ⇒ []` /
explicit-invalid-ID ⇒ empty-no-substitute semantics.

**Consequence for config**: the query is a pure arg-builder, so adding category/tag/stock args is
a natural extension. The renderer builds a single "product card" — the new design needs a category
card and an enriched product card, so the renderer's card-builder must become type-aware.

### 2. Frontend config flow (settings → JS)
Currently Swiper options are hardcoded `slidesPerView: 1` + `breakpoints {768:2, 1024:3}` +
`spaceBetween:16` in `frontend.js`, and `resizeObserver:false` was just added. There is **no**
`wp_localize_script` today — the assets class only registers/enqueues handles. Three ways to move
per-carousel settings to JS:

| Option | Pros | Cons |
|--------|------|------|
| **A. Per-container `data-*`/JSON attributes** (recommended) | Each `.cwc-carousel` carries its own config → two instances of the same shortcode on one page behave differently (matches the confirmed "same shortcode, different setups"); no global state; renderer owns the escape (`esc_attr` JSON) | JSON in HTML attr must be escaped; slightly more markup |
| B. `wp_localize_script` global config | Natural WP mechanism, printed before the script handle, core-safe | One global object → **can't represent two different setups for the same shortcode** on one page; needs config-render-time + per-id keying |
| C. Both: localize shared defaults; data-attrs for instance overrides | Cleanest long-term | More moving parts this slice |

Recommendation for proposal: **Option A (per-container data attribute)** as the primary channel —
it directly satisfies per-instance overrides. `wp_localize_script` is a *fallback* only if a
global shared default (e.g. gap) proves simpler; keep it out of this slice unless required.

### 3. Admin / settings (currently ABSENT)
The plugin has **no admin page and no settings storage** (slice 1 wrote zero DB/options). To add a
global-defaults panel the standard WordPress approaches are:

- **Settings API + menu page**: `add_menu_page()` or `add_submenu_page('woocommerce', …)`,
  `register_setting('cwc_carousel_group', 'cwc_carousel_options', $sanitize_cb)`,
  `add_settings_section()` + `add_settings_field()`. Capability: the plugin already requires
  WooCommerce, so `manage_woocommerce` (WC's shop-manager cap) is the natural gate
  (`current_user_can()`). Single `wp_options` array stores all global defaults under one autoload−0
  option; sanitized in one callback. No schema/DB.
- **CPT `cwc_carousel`** (bootstrap slice-2 plan, stored as post meta). Powers saved *named*
  instances/presets. **Heavier** and only worth it if users need to save named carousel presets
  and reference them by id.

Given the user explicitly chose **global defaults + per-shortcode override (Option B)** — not
named saved instances — a single **Settings API page** is the correct fit for this change. The
CPT plan from slice-2 becomes a *later* "saved presets" enhancement, and the proposal should flag
that this change **revises** the bootstrap plan (slice 2 = CPT → now = Settings API page). Keep
`register_setting` + autoload-off option (autoload off via `register_setting` args where
supported), all through one sanitize callback; no managed options pages.

### 4. WooCommerce APIs (term & product, verified)
- Categories: `get_terms(array('taxonomy'=>'product_cat','include'=>$ids,'hide_empty'=>true))` —
   term objects. Flexible order: `get_terms(...,'orderby'=>'include')` (term ID order).
- Category image: `get_term_meta($term->term_id,'thumbnail_id',true)` → attachment id →
   `wp_get_attachment_image()` (escaped `<img>`); placeholder when 0. Standard WC pattern.
- Category link: `get_term_link($term_id,'product_cat')` → `esc_url` → links to the category
   archive (WooCommerce lists that category's products).
- Product(s) by category:
  - `WC_Product_Query` documented `'category' => [slugs]` (need slugs from term IDs),
  - or native `'tax_query' => [['taxonomy'=>'product_cat','field'=>'term_id','terms'=>$ids]]`
     (precise, avoids slug lookups). **Decision for design phase**: prefer `tax_query` with
    `term_id` (exact, one call) or `category` slugs. Both satisfy PQ-3 (no raw SQL).
- "All vs N": WooCommerce treats `limit` as posts-per-page — `-1` (or 0) means "no limit" (all).
  This conflicts with slice-1 PQ-1 semantics (`limit 0 ⇒ []`). New cards must map `count="all"`
  (attr) → `limit=-1`; keep hard `0 ⇒ []` for int. Flag as an edge the spec must pin (proposal Q).
- Stock/availability for the Buy button: gate per product on
  `$product->is_purchasable()` (covers publish/status) **and**
  `($product->is_in_stock() || $product->backorders_allowed())` (the "available" branch). Buy link:
  `$product->get_permalink()` (to product page) — a real add-to-cart AJAX cart is slice-5, out of
  scope here; the button links to the product page as specified.
- Product's category term for the card: `wc_get_product_term_ids($id,'product_cat')` or
   `wp_get_post_terms($id,'product_cat')` — the card shows the primary (or a shallow)
   category/subcategory term for that product.

### 5. SDD conventions (bootstrap change mirrored)
Eight delta specs keyed `PB/WD/FA/PQ/CR/SC` with req IDs `-n`, `Given/When/Then` scenarios, RFC
2119 MUST/SHOULD, single-slice scope, modular `includes/` + require-map, renderer-only escape,
CSS tokens on `.cwc‑carousel`. This change adds specs: config-model (new), admin/settings (new),
and MODIFIED input `product-query` (category/all), `carousel-renderer` (category card/buy),
`carousel-shortcode` (new atts + override merging), `frontend-assets` (dynamic Swiper + buy CSS).
All new files mirror `class-*.php`.

## Approaches Compared — config surface

| Approach | Pros | Cons | Complexity |
|---|---|---|---|
| A. Settings API page + `shortcode_atts` overrides (recommended) | Matches user Option B exactly; entire system stays simple, no DB schema, `register_setting` all sanitized, capability `manage_woocommerce`; per-instance via atts | No saved named presets | Med |
| B. `CWC_Settings` model (defaults + merge) behind A | Pure config resolution reusable by shortcode/renderer/assets; keeps separation | One extra class | Low |
| C. CPT `cwc_carousel` presets (slice-2 plan) | Named reusable presets, list/edit screens | Overkill for global+override; bigger UI | High |

**Recommendation:** A + B. **Per-carousel channel:** per-container `data-` JSON attr (recommended)
over `wp_localize_script`. Product filtering via a non-raw-SQL `tax_query`/`category` extension
of `CWC_Query`.

## Recommendation — Cleanest attribute + admin setting set covering both types

**Shortcode attributes (only set ones override the admin default; unknown dropped by
`shortcode_atts`):**

| Attr | Values | Type | Default |
|------|--------|------|---------|
| `type` | `product \| category` | selector — carousel kind | from admin (`product`) |
| `title` | string | both | `` (unchanged) |
| `categories` | comma-separated `product_cat` term IDs | BOTH: category carousel = which terms; product carousel = which term products | from admin |
| `count` | int or `all` | product only (`all`=no limit) | from admin |
| `slides` | int | both — desktop visible count (ramp derives from admin ratio) | from admin |
| `buy` | `yes\|no` | product only — show Buy button | from admin |

**Global admin settings (defaults, Settings API page):**
`carousel_type` (default `product`), `slides_desktop` (desktop visible count; category default `4`,
product `3`), `slides_tablet`, `slides_mobile` (the responsive ramp; e.g. `2`/`1` applied to the
desktop count), `gap` (px, default 16 / `--cwc-gap`), `categories` (global category selection),
`product_count` (int|`all`), `buy_enabled` (bool), `buy_text` (default `Comprar`), all sanitized
via Settings API and stored in one `cwc_carousel_options` array.

Resolution rule to hand off: per shortcode instance → `CWC_Settings::resolve($atts)` merges
`admin defaults → explicit shortcode atts`; renderer gets a `config`, the query gets the selection
(`categories`/`count`), both share the same resolved config object. Gap/breakpoints fill `data-`
attrs on the container once, emitted by the renderer.

**Buy-button rule (hand-off):** render a Buy link card only when `type=product`, `buy` enabled,
and `$product->is_purchasable() && ($product->is_in_stock() || $product->backorders_allowed())`.

## Risks

- **PQ semantics change**: `count="all"` vs PQ-1 "0⇒empty". Spec must define the new mapping
  (`all` ⇒ `limit=-1`) without breaking existing instantiations.
- **First write to admin state**: plugin starts persisting options for the first time. Keep autoload
  off; one sanitize callback; rollback = delete the option + remove the page. No DB schema.
- **Scope vs slice plan**: this revises slice-2 (CPT planned → Settings API now). Verify with user;
  if named presets become a real need, bump CPT later (not now).
- **Double renderer type**: renderer grows a category card + enriched product card → watch the
  400-line budget and the CR `escape` discipline.
- **WC API detail**: `tax_query` vs `category` slugs for product-by-category, and `orderby =>
include` for terms — decide in design; both avoid raw SQL and satisfy PQ-3.
- **Astra/theme**: keep Buy button + category card shareable via tokens; nothing theme-coupled.

## Ready for Proposal

**Yes.** The design space (admin config, per-shortcode channel, two carousel types, WC category /
product APIs) is fully mapped against the real code. The hand-off recommendation (attribute set +
settings + `CWC_Query` extension + per-container data-attribute channel) is concrete. The proposal
agent should:
1. Confirm `configurable-carousels` as the change name.
2. Re-confirm the **Settings API page** (not CPT) given the "global defaults + per-shortcode
   override" requirement, and note it supersedes the slice-2 CPT plan.
3. Confirm the `attributes`/`settings` shape above and the `tax_query` product lookup.