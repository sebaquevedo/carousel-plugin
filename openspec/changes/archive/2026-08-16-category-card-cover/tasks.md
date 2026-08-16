# Tasks: Category Card Cover Mode

## Review Workload Forecast

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High

| Field | Value |
|-------|-------|
| Estimated changed lines | ~450 (420–490) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Delivery strategy | ask-always |
| Chain strategy | feature-branch-chain (tracker `feat/cwc-cover`; PR1 `feat/cwc-cover-config`) |
| Suggested split | PR 1 → PR 2 → PR 3 → PR 4 |

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | 17-key contract + att whitelist | PR 1 | settings + shortcode; legacy BC |
| 2 | Children mode + include_children | PR 2 | query + dispatch |
| 3 | Cover render + breadcrumb + CSS | PR 3 | renderer + carousel.css |
| 4 | Editor fields + term meta + i18n | PR 4 | admin + languages/ + tools/make-mo.php |

## Phase 1: Config Contract (CM-11, SC-10)

- [x] 1.1 `class-settings.php` builtins(): add `cover`=>false, `subcategories`=>false, `title_align`=>'left'
- [x] 1.2 `class-settings.php` resolve() `$known`: whitelist the 3 keys from `$base`
- [x] 1.3 `class-settings.php` normalize(): parse_bool x2; title_align `['center','right']` else 'left'
- [x] 1.4 `class-shortcode.php` shortcode_atts(): add 3 empty-string atts so they reach resolve()
- [x] 1.5 Verify: phpcs + `php -l`; wp-env: 17-key JSON, legacy instance BC, invalid title_align coerces

## Phase 2: Query Children Mode (PQ-6/7)

- [x] 2.1 `class-query.php` category_filter(): `include_children => true` on both tax_query arrays
- [x] 2.2 `class-query.php` get_child_categories(): absint; invalid parent → []; get_terms(parent, hide_empty, orderby=name); is_wp_error → []
- [x] 2.3 `class-shortcode.php` category branch: subcategories ? get_child_categories(category) : get_categories(categories)
- [x] 2.4 Verify: phpcs + `php -l`; wp-env: depth-1 children, hide_empty, invalid parent → [], grandchild included, outside excluded

## Phase 3: Renderer + CSS (CR-8/9/10, FA-6/7)

- [x] 3.1 render(): add `cwc-carousel--cover` when type=category && cover; h2 modifier `--center|--right` for non-left title_align
- [x] 3.2 render_category_card(): accept `array $config`; extract image lookup (cwc_cat_image → thumbnail_id → placeholder) to helper
- [x] 3.3 Cover branch: whole-card link, media span (image + overlay + cover-title), title = sanitized cwc_cat_title ?: term name; no button/caption; non-cover path unchanged
- [x] 3.4 render_category_line(): full root→leaf path via wc_get_product_term_ids + get_terms(orderby=include) + array_reverse(get_ancestors); deepest wins, name-ASC tie; esc_html per segment, static `›`, title attr = full path
- [x] 3.5 `carousel.css`: `--cwc-cover-overlay-opacity: 0.45`; `--cover` 7/12 aspect-ratio, absolute image/placeholder + overlay, centered white title; align classes; category-line ellipsis
- [x] 3.6 Verify: phpcs + `php -l`; wp-env DOM: cover markup, meta fallback, thumbnailless kept, no button, breadcrumb 1/2/4-level + tie + escaped, ellipsis

## Phase 4: Admin + i18n (AS-9/10, CCC-6)

- [x] 4.1 3 field renderers (cover hidden-0+checkbox category-only, subcategories checkbox, title_align select)
- [x] 4.2 sanitize_instance(): coerce 3 keys (parse_bool x2, title_align enum)
- [x] 4.3 render_category_images(): per-row `cwc_cat_titles[term_id]` input, pre-filled
- [x] 4.4 save_category_images(): sanitize_text_field, get_term instanceof WP_Term, update/delete_term_meta; same nonce + manage_woocommerce gate
- [x] 4.5 Create `languages/cwc-carousel.pot` (canonical new admin msgids) + `cwc-carousel-es_ES.po` (neutral ES)
- [x] 4.6 Create `tools/make-mo.php` (POMO); compile `cwc-carousel-es_ES.mo` in wp-env (confirm mount `carousel-plugin`)
- [x] 4.7 Verify: phpcs + `php -l`; wp-env round-trip, invalid title_align coerced, meta save/clear/prefill, unauthorized POST no-op; WPLANG=es_ES shows translated labels

## Phase 5: End-to-End Verification

- [x] 5.1 `composer phpcs` + `php -l` green on all 6 changed PHP files (WordPress + WordPress-Extra)
- [x] 5.2 wp-env acceptance smoke: cover 7/12, products unaffected, subcategories, non-cover BC, es_ES admin
- [x] 5.3 Cross-check .pot msgids vs `__()`/`esc_html_e()` in class-admin.php; confirm frontend.js/class-plugin.php/class-assets.php untouched