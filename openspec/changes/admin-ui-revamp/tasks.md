# Tasks: Admin UI Revamp â€” tabbed editor + 13 carousel settings

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 740â€“900 (8 files + languages) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 â†’ PR 2 â†’ PR 3 â†’ PR 4 â†’ PR 5 â†’ PR 6 (6 PRs total) |
| Delivery strategy | ask-always |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | 13-key settings contract (builtins/normalize/$known) | PR 1 | class-settings.php only; BC-safe |
| 2 | Shortcode whitelist of 13 atts | PR 2 | Needs PR 1 |
| 3 | Editor tabs + toggle re-skin + re-fill | PR 3 | class-admin.php restructure; sub-split of design unit 3 |
| 4 | New renderers (device/nav-position/colors/helpers) + i18n | PR 4 | Needs PR 3; JS-free by design |
| 5 | Renderer + frontend Swiper wiring + corner CSS | PR 5 | Needs PR 1â€“2; independent of PR 3â€“4 |
| 6 | Side nav modes (sides-inside/-outside) + subcategories helper text | PR 6 | Needs PR 5 (branch feat/admin-ui-revamp-frontend); class-renderer.php, class-admin.php, carousel.css, languages; ~120â€“180 lines |

Chain base: main or previous PR branch (orchestrator resolves chain strategy with user).

## Phase 1: Settings Model â€” 13-Key Contract (PR 1)

- [x] 1.1 `includes/class-settings.php` `builtins()` +13: autoplay=false, stop_on_hover=true, timeout=3000, speed=300, loop=false, nav_position=`bottom-right`, 6 colors=`''`, slides_laptop=2 (CM-12)
- [x] 1.2 Add `nav_positions()` + `nav_color_keys()` (D2)
- [x] 1.3 `normalize()`: parse_bool Ã—3; timeout clamp 1000â€“60000; speed 100â€“5000; slides_laptop 1â€“12; nav_position whitelistâ†’bottom-right; hex empty-first (CM-13)
- [x] 1.4 `resolve()` `$known` +13 (CM-9)

**Acceptance**: php -l + phpcs; wp eval â€” legacy 18-key normalizes to 31; clamps/invalid enum+hex per CM-13.

## Phase 2: Shortcode Whitelist (PR 2)

- [x] 2.1 `includes/class-shortcode.php`: `shortcode_atts` +13 Ã— `''` (SC-12)

**Acceptance**: success shortcode resolves fully; omitted/empty atts fall through BC (SC-12).

## Phase 3: Editor Structure â€” Tabs, Toggles, Header (PR 3)

- [x] 3.1 `render_editor`: 4 nav-tab panels (Content/Behavior/Navigation/Style) over ONE form+submit; conditional type rows stay in Content (AS-16)
- [x] 3.2 `render_toggle_field` (hidden 0 + visible 1, `.cwc-toggle`) replaces inline checkboxes buy/arrows/pagination/cover/subcategories + new bools (AS-17)
- [x] 3.3 Header shortcode field `[cwc_carousel name="{slug}"]` read-only + copy button (AS-17)
- [x] 3.4 Rejected-create re-fill: append `normalize( $current )` after bounds loop (D3, AS-18)
- [x] 3.5 `sanitize_instance` raw pass-through of 13 keys (D1)
- [x] 3.6 admin.js module split (D8): tabs (roles/roving tabindex) + copy run without wp.media; admin.css tab/toggle/header styles

**Acceptance**: curl JS-free POST saves all panels under one nonce; toggles 0/1 round-trip; php -l + phpcs + `node --check`.

## Phase 4: New Field Renderers + i18n (PR 4)

- [x] 4.1 `render_device_slides_field`: slides/slides_laptop/slides_tablet/slides_mobile + device icons (AS-17)
- [x] 4.2 `render_nav_position_field` from `nav_positions()` (AS-17)
- [x] 4.3 `render_color_group_field`: 6 color+hex, hex posts (D4), "empty = theme default" (AS-17)
- [x] 4.4 `render_number_field` helper text for timeout/speed/count/gap (AS-17)
- [x] 4.5 admin.js colorâ†”hex sync + icons; admin.css device/color/helper styles
- [x] 4.6 `languages/*`: extract new strings via `__()`, `tools/make-mo.php` recompile es_ES

**Acceptance**: device picker saves 4 ramps; hex persists/empties stay `''`; rejected save preserves Behavior/Navigation values clamped (AS-18).

## Phase 5: Renderer + Frontend Wiring (PR 5)

- [x] 5.1 `includes/class-renderer.php`: nav-position class when â‰  bottom-right; `--cwc-nav-color-*` vars only non-empty; 31-key `data-cwc-config` (CR-11)
- [x] 5.2 `frontend.js` `buildOptions`: autoplay `{delay, pauseOnMouseEnter}` only `===true`; speed on `typeof number`; loop only `===true`; 992 tier only when slides_laptop>0 (FA-8)
- [x] 5.3 `carousel.css`: 4 corner rules; `--cwc-nav-color-*` wiring with fallbacks (FA-9)

**Acceptance**: wp eval pre/post diff â€” legacy markup byte-identical; only non-empty vars; 1000pxâ†’laptop, â‰¥1024/<768 unchanged; admin assets admin-only.

## Phase 5b: Nav Position Side Modes + Subcategories Helper (PR 6)

- [x] 5b.1 `includes/class-renderer.php`: `cwc-carousel--nav-sides-inside` class on the container (no markup change); `cwc-carousel-shell` flex wrapper for `sides-outside` with prev/next as flanking siblings of `.swiper`; shell carries nav class + color vars; inner `.swiper` keeps base classes + `data-cwc-config`; default + 4 corners byte-identical (CR-11 amendment)
- [x] 5b.2 `includes/class-admin.php`: nav-position select +2 options from `nav_positions()` with labels "Sides (inside)" / "Sides (outside)" (label map in `render_nav_position_field`); subcategories toggle helper text exactly "Lists child terms of the parent category and ignores the selected categories." (AS-17 amendment)
- [x] 5b.3 `assets/css/carousel.css`: sides-inside absolute edge rules (vertically centered, overlay) + sides-outside flex shell rules (`[prev] [slider] [next]`, slider `flex:1; min-width:0`); `--cwc-nav-color-*` apply in both modes, kebab-case var names (FA-9 amendment)
- [x] 5b.4 `languages/*`: extract new strings (2 select labels + subcategories helper + device sizes helper); es_ES: "Lista los tÃ©rminos hijos de la categorÃ­a principal e ignora las categorÃ­as seleccionadas."; `tools/make-mo.php` recompile
- [x] 5b.6 `includes/class-admin.php` `render_device_slides_field`: helper text under the device inputs stating each breakpoint in px â€” exactly "Screen sizes: Mobile < 768px, Tablet â‰¥ 768px, Laptop 992â€“1023px, Desktop â‰¥ 1024px." (must match frontend.js tiers: base <768, 768, 992, 1024)
- [x] 5b.7 Type-conditional rows without save: `includes/class-admin.php` renders the products picker row AND the cover row unconditionally with `data-cwc-type-row="product"|"category"` + `hidden` per the SAVED type (no-JS fallback unchanged); `assets/js/admin.js` new `initTypeRows()` module (D8 split, no wp.media dependency) toggles `hidden` on type select change so Cover appears instantly when switching to category
- [x] 5b.5 wp-env verification: BC DOM diff â€” default + 4 corners byte-identical (no shell); sides-inside/sides-outside render and position per CSS; Swiper nav resolves for both side modes; `php -l` + `node --check` + phpcs

**Acceptance**: new values emit new markup only when selected; buttons resolve for Swiper in both side modes; helper text + labels localized (es_ES, no fallback).

## Phase 6: Final Verification

- [x] 6.1 php -l + phpcs (WordPress + WordPress-Extra) all files; `node --check` both JS
- [x] 6.2 E2E success shortcode: data-cwc-config â†’ buildOptions â†’ Swiper (browser)
- [x] 6.3 Final `tools/make-mo.php` recompile; es_ES no fallback strings
- [x] 6.4 Rollback sanity: revert 8 files â†’ legacy output unchanged (no migration)

## Review Workload Forecast

| Unit | Files | ~Lines |
|------|-------|--------|
| PR 1 | class-settings.php | 45 |
| PR 2 | class-shortcode.php | 15 |
| PR 3 | class-admin.php, admin.js, admin.css | 250 |
| PR 4 | class-admin.php, admin.js, admin.css, languages | 200 |
| PR 5 | class-renderer.php, frontend.js, carousel.css | 150 |
| PR 6 | class-admin.php, class-renderer.php, carousel.css, languages | 120â€“180 |
| Total | 8 files + languages | 740â€“900 |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High