# Design: Category Card Cover Mode

## Technical Approach

Add three opt-in config keys (`cover`, `subcategories`, `title_align`) to the 17-key contract in `CWC_Settings` (CM-11), whitelist them in the shortcode (SC-10), then branch at three existing seams: the query layer gains a children mode + explicit `include_children` (PQ-6/7), the renderer gains a cover branch and a full-path breadcrumb (CR-8/9/10), and the admin gains three editor fields plus per-category `cwc_cat_title` term meta (AS-9/10). CSS scopes everything under `.cwc-carousel--cover` (FA-6/7). `frontend.js`, `class-plugin.php`, `class-assets.php` and the bootstrap are untouched.

## 1. Architecture Decisions

| # | Decision | Options | Choice / Rationale |
|---|----------|---------|---------------------|
| D1 | Config keys | store only on category / all types | **17-key contract for ALL resolved shapes** (CM-9) — `cover` serializes into `data-cwc-config` for products but the renderer ignores it for `type=product` (CCC-1). `frontend.js` reads only `slides*`/`gap`/`arrows`/`pagination`; the extra keys are inert in `buildOptions()` — no JS change |
| D2 | `title_align` coercion | regex / whitelist | **Whitelist `['center','right']`, else `left`** in `normalize()` — single coerce point, invalid values never error (CM-11) |
| D3 | Subcategory listing | `get_terms(parent=)` / walk query | **`get_terms(parent=$id, orderby=name)`** — WP terms API, depth-1 only, `category` 0/nonexistent → empty array, no raw SQL (PQ-6); `subcategories` true takes precedence over `categories`/`mix`; empty parent → empty carousel via existing CR-2 empty-set path |
| D4 | Breadcrumb tie-break | `get_the_terms` order / pinned order | **`wc_get_product_term_ids` + `get_terms(orderby=include)`** — pins deterministic order; deepest path wins, first pinned term on tie (CR-10) |
| D5 | `cwc_cat_title` save path | new handler / extend existing | **Extend `save_category_images()`** — the per-category group already emits the AS-3 nonce (`image_nonce_action`); one form, one nonce, same `manage_woocommerce` gate, never weakened (AS-10) |
| D6 | i18n generation | host gettext / hand-written .mo / POMO script | **Hand-authored .pot/.po + pure-PHP POMO compile** — no host `wp`/`msgfmt`/`xgettext` exist (checked); WP's bundled `wp-includes/pomo` classes compile `.mo` inside the wp-env container (CCC-6) |

## 2. Config Model (CM-11)

`builtins()` gains `'cover' => false, 'subcategories' => false, 'title_align' => 'left'`; `$known` in `resolve()` gains the three keys from `$base`; `normalize()` adds:

```php
'cover'         => $this->parse_bool( $merged['cover'] ),
'subcategories' => $this->parse_bool( $merged['subcategories'] ),
'title_align'   => in_array( (string) $merged['title_align'], array( 'center', 'right' ), true )
    ? (string) $merged['title_align'] : 'left',
```

Seeds (CM-10) inherit builtins → stay false/false/left; legacy 14-key instances fill from builtins → BC. `sanitize_instance()` in admin must emit the three keys (parse_bool / enum whitelist) so edits/creates persist them.

## 3. Breadcrumb (CR-10)

`render_category_line()` replaces the first-term line with the full root→leaf path:

1. `$term_ids = wc_get_product_term_ids( $id, 'product_cat' )` — stable input (name-ASC via `wp_get_post_terms`).
2. Pin order: `get_terms(['taxonomy' => 'product_cat', 'include' => $term_ids, 'orderby' => 'include', 'hide_empty' => false])` — mirrors existing `get_categories()`.
3. Per term (pinned order): `array_reverse( get_ancestors( $term->term_id, 'product_cat' ) )` + `$term->term_id` — `get_ancestors` returns lowest-first (verified: `get_ancestors(208,'category')` → `[23,6]`), reverse gives root→leaf. Track max-depth path; first pinned term wins ties.
4. Resolve path ids to `WP_Term`s in one `get_terms(orderby=include)`, `esc_html` each name, join with static ` › `.

Renders `<div class="cwc-card__category-line" title="{full path, esc_attr}">Root › Child › Leaf</div>`. Unassigned product → `''` (today's behavior). No dependency on the carousel `category` (CR-10 scenarios).

## 4. Cover Branch (CR-9) + Subcategories Dispatch

`render()` adds `cwc-carousel--cover` to the container class when `type=category && cover` (all cover CSS scopes under it); the h2 gains `cwc-carousel__title--center|--right` only for non-left `title_align` (left → today's markup). `render_category_card()` signature gains `array $config`; image lookup (`cwc_cat_image` → `thumbnail_id` → placeholder span) extracts to a shared helper; cover branch:

```php
$title = get_term_meta( $term->term_id, 'cwc_cat_title', true );
$title = sanitize_text_field( (string) $title );
if ( '' === $title ) { $title = $term->name; }
// <div class="swiper-slide cwc-category-card cwc-category-card--cover">
//   <a class="cwc-category-card__link" href="{esc_url(term_link)}">
//     <span class="cwc-category-card__media">{image}{overlay span}{cover-title span}</span>
//   </a></div>  — no button/caption below; whole card links to archive
```

Non-cover path unchanged. `CWC_Shortcode::render()` category branch: `$items = ! empty( $config['subcategories'] ) ? $query->get_child_categories( $config['category'] ) : $query->get_categories( $config['categories'] );`.

## 5. Query (PQ-6/7)

`category_filter()` adds `'include_children' => true` to BOTH tax_query arrays (single-term and IN-list) — WP's current default, so backward-compatible; intent pinned if core ever changes it. New `get_child_categories( int $parent_id )`: `absint`, `<= 0` or `! get_term(...) instanceof WP_Term` → `[]`; else `get_terms(parent=$parent_id, hide_empty=true, orderby=name)` → `WP_Term[]`, `is_wp_error` → `[]`.

## 6. Admin (AS-9/10)

Three renderers following the existing pattern: `render_cover_field()` (hidden `value="0"` companion + checkbox, **rendered only when `$current['type'] === 'category'`**), `render_subcategories_field()`, `render_title_align_field()` (select). `sanitize_instance()` coerces all three. `render_category_images()` gains per-row text input `cwc_cat_titles[term_id]`; `save_category_images()` (same nonce + `manage_woocommerce` gate, capability checked first) reads it, `sanitize_text_field`, validates `get_term() instanceof WP_Term`, `update_term_meta('cwc_cat_title')` / `delete_term_meta` on empty.

## 7. CSS (FA-6/7) + i18n (CCC-6)

`.cwc-carousel` gains `--cwc-cover-overlay-opacity: 0.45` (default). Under `.cwc-carousel--cover`: `.cwc-category-card { aspect-ratio: 7 / 12; position: relative; }`, image/placeholder absolute inset-0 + `object-fit: cover`, overlay span absolute inset-0 with the opacity var, `.cwc-category-card__cover-title` absolutely centered white. `.cwc-carousel__title--center/--right { text-align: ... }`. `.cwc-card__category-line { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }` — single line, full path stays in DOM + `title` attr. Gap reuses `--cwc-gap` (Swiper `spaceBetween` from `config.gap`, unchanged).

i18n: `load_plugin_textdomain` is wired (PB-3, `class-plugin.php` L52). New strings are admin labels only (cover markup has no literal text; title comes from term meta). .pot scope: this slice wraps/translates the new admin labels only — existing admin strings remain untranslated this slice. Generation without host `wp`/gettext (none installed): hand-author `languages/cwc-carousel.pot` (canonical msgids), derive `languages/cwc-carousel-es_ES.po` (neutral ES), compile `.mo` via dev-only `tools/make-mo.php` using WP's bundled POMO (`wp-includes/pomo/po.php` + `mo.php`) run inside the wp-env container where the plugin is mounted (wp-env mounts `plugins: ["."]` by directory basename — `carousel-plugin`; the real container path MUST be confirmed during tasks). The catalog files MUST be named `cwc-carousel-{locale}.po`/`.mo` because `load_plugin_textdomain` looks up `cwc-carousel-es_ES.mo`. Verify by setting `WPLANG` option to `es_ES` in wp-env and asserting translated admin strings.

## Data Flow

```
[shortcode atts] → CWC_Shortcode::render() → whitelist (SC-10)
  → CWC_Settings::resolve() → normalize() → 17-key config
  → data-cwc-config JSON (JS reads slides*/gap/arrows/pagination only; new keys inert)
  → type=category:  subcategories? → get_child_categories(category) : get_categories(categories)
  → type=product:   query() → category_filter() + include_children → render_card()
  → CWC_Renderer::render() → h2(+align) → cover/legacy/product cards → breadcrumb via get_ancestors
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `includes/class-settings.php` | Modify | 3 keys in `builtins()`/`$known`/`normalize()` (CM-11) |
| `includes/class-shortcode.php` | Modify | Whitelist 3 atts; subcategories dispatch (SC-10, PQ-6) |
| `includes/class-query.php` | Modify | `get_child_categories()`; `include_children => true` (PQ-6/7) |
| `includes/class-renderer.php` | Modify | Title align class, cover container class, cover branch + image helper, breadcrumb (CR-8/9/10) |
| `includes/class-admin.php` | Modify | 3 fields + `sanitize_instance()` keys + `cwc_cat_titles` row + save path (AS-9/10) |
| `assets/css/carousel.css` | Modify | Cover tokens/7-12/overlay/centered title, header align, breadcrumb ellipsis (FA-6/7) |
| `languages/cwc-carousel.pot` | Create | Canonical gettext template |
| `languages/cwc-carousel-es_ES.po`, `cwc-carousel-es_ES.mo` | Create | Neutral Spanish translation + compiled catalog (named `{domain}-{locale}` so `load_plugin_textdomain` finds them) |
| `tools/make-mo.php` | Create | Dev-only POMO compile script (not shipped) |

**Not changed**: `assets/js/frontend.js`, `class-plugin.php`, `class-assets.php`, bootstrap migration (seeds inherit builtins).

## Testing Strategy

| Layer | What | Approach |
|-------|------|----------|
| Static | All changed PHP | `composer phpcs` (WordPress + WordPress-Extra) + `php -l` always green |
| Integration | Breadcrumb paths (root-only, 2/3/4-level, multi-branch tie), cover markup (custom title/fallback/thumbnailless), children mode (empty/invalid parent), include_children inclusion, editor round-trip + invalid `title_align`, cap-gate on titles | wp-env smoke: seed `product_cat` tree + products, render shortcodes, assert DOM; craft unauthorized POST |

## Migration / Rollout

None — new keys default false/false/left; legacy instances BC via `normalize()`; `cwc_cat_title` meta created on first save; `languages/` is additive. Rollback: revert the six source files + CSS, delete `languages/*` and `tools/`.

## Risks

| Risk | Mitigation |
|------|------------|
| .pot/.po/.mo drift | Regenerate/re-audit .pot per release; grep cross-check msgids vs `__()`/`esc_html_e()` calls in changed files |
| Breadcrumb cost (get_ancestors per card) | Term cache; paths are shallow; smoke test with 4-level chain |
| `get_ancestors` reorder | `get_ancestors` is filterable — a core/mu-plugin filter could reorder ancestors, breaking pinned determinism; smoke-verify path output |
| `cover`/`title_align` leak into JS | frontend.js reads only known keys — extra keys inert (verified in `buildOptions()`) |
| phpcs on new admin markup | Reuse existing escaped renderer patterns |

## Open Questions

- [ ] None blocking. Children listing order pinned to `name ASC` (D3) — `menu_order` ordering can be a later slice if requested.