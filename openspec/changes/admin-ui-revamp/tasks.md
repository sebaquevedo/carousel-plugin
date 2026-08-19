# Tasks: Admin UI Revamp — tabbed editor + 13 carousel settings

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 620–720 (8 files + languages) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 → PR 2 → PR 3 → PR 4 → PR 5 |
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
| 5 | Renderer + frontend Swiper wiring + corner CSS | PR 5 | Needs PR 1–2; independent of PR 3–4 |

Chain base: main or previous PR branch (orchestrator resolves chain strategy with user).

## Phase 1: Settings Model — 13-Key Contract (PR 1)

- [x] 1.1 `includes/class-settings.php` `builtins()` +13: autoplay=false, stop_on_hover=true, timeout=3000, speed=300, loop=false, nav_position=`bottom-right`, 6 colors=`''`, slides_laptop=2 (CM-12)
- [x] 1.2 Add `nav_positions()` + `nav_color_keys()` (D2)
- [x] 1.3 `normalize()`: parse_bool ×3; timeout clamp 1000–60000; speed 100–5000; slides_laptop 1–12; nav_position whitelist→bottom-right; hex empty-first (CM-13)
- [x] 1.4 `resolve()` `$known` +13 (CM-9)

**Acceptance**: php -l + phpcs; wp eval — legacy 18-key normalizes to 31; clamps/invalid enum+hex per CM-13.

## Phase 2: Shortcode Whitelist (PR 2)

- [x] 2.1 `includes/class-shortcode.php`: `shortcode_atts` +13 × `''` (SC-12)

**Acceptance**: success shortcode resolves fully; omitted/empty atts fall through BC (SC-12).

## Phase 3: Editor Structure — Tabs, Toggles, Header (PR 3)

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
- [x] 4.5 admin.js color↔hex sync + icons; admin.css device/color/helper styles
- [x] 4.6 `languages/*`: extract new strings via `__()`, `tools/make-mo.php` recompile es_ES

**Acceptance**: device picker saves 4 ramps; hex persists/empties stay `''`; rejected save preserves Behavior/Navigation values clamped (AS-18).

## Phase 5: Renderer + Frontend Wiring (PR 5)

- [ ] 5.1 `includes/class-renderer.php`: nav-position class when ≠ bottom-right; `--cwc-nav-color-*` vars only non-empty; 31-key `data-cwc-config` (CR-11)
- [ ] 5.2 `frontend.js` `buildOptions`: autoplay `{delay, pauseOnMouseEnter}` only `===true`; speed on `typeof number`; loop only `===true`; 992 tier only when slides_laptop>0 (FA-8)
- [ ] 5.3 `carousel.css`: 4 corner rules; `--cwc-nav-color-*` wiring with fallbacks (FA-9)

**Acceptance**: wp eval pre/post diff — legacy markup byte-identical; only non-empty vars; 1000px→laptop, ≥1024/<768 unchanged; admin assets admin-only.

## Phase 6: Final Verification

- [ ] 6.1 php -l + phpcs (WordPress + WordPress-Extra) all files; `node --check` both JS
- [ ] 6.2 E2E success shortcode: data-cwc-config → buildOptions → Swiper (browser)
- [ ] 6.3 Final `tools/make-mo.php` recompile; es_ES no fallback strings
- [ ] 6.4 Rollback sanity: revert 8 files → legacy output unchanged (no migration)

## Review Workload Forecast

| Unit | Files | ~Lines |
|------|-------|--------|
| PR 1 | class-settings.php | 45 |
| PR 2 | class-shortcode.php | 15 |
| PR 3 | class-admin.php, admin.js, admin.css | 250 |
| PR 4 | class-admin.php, admin.js, admin.css, languages | 200 |
| PR 5 | class-renderer.php, frontend.js, carousel.css | 150 |
| Total | 8 files + languages | 620–720 |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High