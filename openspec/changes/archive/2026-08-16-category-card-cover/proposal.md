# Proposal: Category Card Cover Mode

## Intent

Category cards today show a thumbnail with the name below. The reference wants full-bleed portrait cover cards: uniform 7:12, dark overlay, centered white title, no button. Adds opt-in cover mode for `type=category` (uniform sizing, per-category overlay title, subcategory listing), a carousel header with alignment for all types, product-card full category-path breadcrumbs, and gettext i18n (EN + neutral ES).

## Scope

### In Scope
- Cover mode (`type=category` only): full-bleed image (`cwc_cat_image` → `thumbnail_id` → placeholder), single-opacity overlay, centered white title, card links to archive; no button/text.
- Per-category overlay title: new term meta `cwc_cat_title` (category edit screen, alongside `cwc_cat_image`); empty → category term name.
- Carousel header: keep `title` (h2) for ALL carousels + new `title_align` (`center` | `left` | `right`).
- Product-card breadcrumb: replace the shallow first-term line with the FULL category path (root → most-specific term), independent of the carousel's `category`; deepest path wins on multi-branch ties; long paths truncated via CSS ellipsis (data intact).
- Explicit subcategory inclusion: product query filters by parent category with `include_children => true` (made explicit, not left to the WP default).
- Uniform responsive 7/12 category cards; gap ~36 (existing `gap` key, range 8-64).
- Thumbnailless terms: placeholder + title on top, never excluded.
- Subcategory listing (direct children of a parent) for `type=category`.
- i18n: `languages/` .pot/.po/.mo — EN + neutral ES, admin labels (this slice adds no frontend literal strings).

### Out of Scope
- Gradients, overlay config params, other product-card layout changes, Gutenberg block (later slice), depth control beyond direct children for the category subcategory listing.

## Capabilities

### New Capabilities
- `category-card-cover`: cover-mode cards, 7/12 sizing, placeholder, overlay, per-category title, subcategory listing, header alignment, i18n strings.

### Modified Capabilities
- `config-model`: contract → 17 keys (`cover`, `subcategories`, `title_align`; defaults false/false/left).
- `carousel-renderer`: header `title_align`; CR-5 cover branch — overlay + centered title from `cwc_cat_title` → term name; product-card full-path breadcrumb.
- `carousel-shortcode`: whitelist `cover`/`subcategories`/`title_align`.
- `admin-settings`: editor fields (cover, subcategories, title_align) + per-category `cwc_cat_title` field; translated strings.
- `product-query`: `get_categories()` children mode (parent = `category`); explicit `include_children => true` on the product category filter.
- `frontend-assets`: cover/overlay/7:12 CSS as `--cwc-cover-*` properties; header alignment; breadcrumb single-line truncation.

## Approach

1. Settings: `cover`/`subcategories` bools + `title_align` enum in `builtins()`/`normalize()`/`$known`; cover ignored for products.
2. Renderer: header `title_align` class on `cwc-carousel__title`; cover branch in `render_category_card()` — full-bleed image, overlay span, centered title from term meta `cwc_cat_title` → term name; product breadcrumb in `render_category_line()` — full root→leaf path for the product's most-specific term, independent of the carousel config.
3. Admin: per-category title text field (term meta `cwc_cat_title`) beside the existing `cwc_cat_image` field.
4. Query: `subcategories` → `get_terms(parent=…)`; empty parent → empty result. Add `'include_children' => true` to the product `tax_query` for explicit intent.
5. CSS: 7/12 `aspect-ratio`, `--cwc-cover-overlay-opacity`, gap via `--cwc-gap`, header alignment classes, breadcrumb `text-overflow: ellipsis` (single line).
6. i18n: generate .pot + `es_ES.po/.mo`; wrap new strings in `__()`/`esc_html_e()`.

## Open Decisions (defaults — confirm in spec/design)

- Param name: `cover` (bool, default false; `cover="1"`).
- `title_align` values: `center` | `left` | `right` (default `left`, matches current theme-default alignment — no visual change for existing configs).
- 7/12 scope: cover mode only; non-cover keeps today's layout.
- Subcategories: direct children (depth 1) of `category` id; depth config deferred.
- Per-category title meta key: `cwc_cat_title` (mirrors the existing `cwc_cat_image` term-meta pattern).
- Product breadcrumb: full root→leaf path, independent of the carousel's `category`; multi-branch tie → deepest path, then first term; long path → CSS ellipsis (data intact).

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `includes/class-settings.php` | Modified | `cover`/`subcategories`/`title_align` keys |
| `includes/class-renderer.php` | Modified | Header align + cover branch + term-meta title fallback + product breadcrumb |
| `includes/class-query.php` | Modified | Children fetch + explicit include_children |
| `includes/class-shortcode.php` | Modified | Whitelist new atts |
| `includes/class-admin.php` | Modified | New editor fields + per-category title field |
| `assets/css/carousel.css` | Modified | 7/12, overlay, placeholder, header align, breadcrumb truncation |
| `languages/` | New | .pot/.po/.mo EN + es_ES |
| `tools/make-mo.php` | New | Dev-only POMO compile script for `.mo` (not shipped) |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| `title_align` CSS leaks to non-carousel titles | Low | Scope to `.cwc-carousel__title` |
| Breadcrumb picks a confusing term for multi-branch products | Med | Deepest path, then first term on tie |
| Long breadcrumbs overflow the card | Low | Single-line CSS ellipsis |
| 7/12 on non-cover changes look | Med | Cover only; confirm in spec |
| Empty parent for subcategories | Low | Graceful empty result |
| .po/.mo drift | Med | Regenerate .pot per release |

## Rollback Plan

Revert the six source files + CSS; delete new `languages/*` files and `tools/make-mo.php`. New params default to false/left, so existing configs render unchanged — no migration, no data loss.

## Dependencies

- WooCommerce active (existing, self-deactivates when absent — WD-2).
- Registry/admin editor (merged) supplies field plumbing.

## Success Criteria

- [ ] `cover="1"` renders 7/12 full-bleed cards: overlay, centered white title (`cwc_cat_title` → term name), no button.
- [ ] `title_align` centers/lefts/rights the header for all carousels.
- [ ] `cover` ignored for products; buy/buy_text unchanged.
- [ ] Product cards show the full root→leaf category path, independent of the carousel's scope; deepest path wins on ties.
- [ ] Mixed carousels (products from different subcategories) each show their own accurate path.
- [ ] Product carousel scoped to a parent category includes products in its subcategories (explicit include_children).
- [ ] Thumbnailless terms render placeholder + title; never excluded.
- [ ] `subcategories="1"` lists direct children; empty parent → empty carousel.
- [ ] Non-cover category layout unchanged.
- [ ] es_ES site shows neutral Spanish strings from .mo.
- [ ] phpcs passes (WordPress + WordPress-Extra).
