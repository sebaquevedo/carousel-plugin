# Design: Admin UI Revamp — tabbed editor + 13 missing carousel settings

## Technical Approach

Extend `CWC_Settings` with the 13 keys — the sole source of every clamp/enum/hex/bool (CM-12/13) — then whitelist them in the shortcode, emit them from the renderer, and re-skin the per-instance editor into four nav-tab panels over the existing one-form/one-submit Settings API flow. `sanitize_instance()` passes the 13 keys through raw (cover-keys pattern), so `normalize()` owns coercion on both save paths. `frontend.js` maps the extended `data-cwc-config` onto Swiper with strict presence checks, so legacy containers keep today's 3-breakpoint, no-autoplay behavior and markup stays byte-identical when the new keys are unset (CR-11).

## 1. Architecture Decisions

| # | Decision | Options | Choice / Rationale |
|---|----------|---------|---------------------|
| D1 | Coercion ownership | duplicate in admin / shared layer only | **normalize() only.** sanitize_instance passes the 13 keys raw (cover/title_align pattern); both call sites already normalize downstream. Fixes the 4R duplication WARNING for the new keys; existing bound()/$numeric_bounds paths stay untouched (debt, out of scope). |
| D2 | Enum + key lists | hardcoded / shared | **CWC_Settings::nav_positions()** + **nav_color_keys()** (title_alignments() pattern); normalize(), admin renderers, renderer consume them. |
| D3 | Create re-fill (AS-18) | extend $numeric_bounds map / route through normalize() | **Append `$current = $this->settings->normalize( $current );`** after the existing bounds loop in the rejected-create branch: clamps slides_laptop/timeout/speed exactly as save persists (CM-13), no new literal ranges; idempotent on bounded keys; title/buy_text display matches persistence. |
| D4 | Color posting | color input posts / hex text posts | **Hex text is the posting field**; input[type=color] is a JS-synced picker — browsers coerce empty color values to #000000 on submit (would store a black arrow). Empty hex posts `''` → normalize() empty-check passes it (CM-13). |
| D5 | Tabs | core tabs JS / custom | **nav-tab `<button>`s + 4 panels, all in DOM, visibility toggles in admin.js** (AS-16). No-JS: panels visible, one nonce, submit intact. Roving tabindex, role=tab/tabpanel, arrow keys. |
| D6 | Position class | always emit / non-default only / **shell for sides-outside** | **Emit only when ≠ bottom-right** (CR-11); carousel.css's bottom-right set targets the unclassed container; style attr lists non-empty colors only. **Amended (PR 6):** `sides-inside` = container class + CSS only (Swiper already places nav absolutely inside, vertically centered); `sides-outside` = new `.cwc-carousel-shell` flex wrapper with the arrows as flanking siblings of `.swiper` — the shell carries the nav class + color vars, the inner `.swiper` keeps base classes + `data-cwc-config` (init selects `.cwc-carousel.swiper` → no double-init). New markup only when a side value is selected; default + 4 corners byte-identical. Flex shell over transform-translate hacks: translated absolute buttons take no layout space, so the slider never shrinks and the buttons overflow the viewport; flex grants real layout room. Frontend nav selectors are document-level (FA-8) so out-of-container buttons still resolve — verify at apply time. |
| D7 | frontend presence | truthy defaults / strict | **Strict**: autoplay/loop only on `===true`; speed on `typeof number`; 992 tier only when slides_laptop>0. Absent keys → no Swiper keys (FA-8). |
| D8 | admin.js guard | whole-script wp.media bail / per-module | **Module split**: the wp.media guard wraps only picker/uploader; tabs/color-sync/copy run regardless (today's early return kills all new behavior). |

## 2. Data Flow

```
shortcode atts ─► resolve(): $known +13 (CM-9) ─► normalize() ◄─ sanitize_instance() raw
      ▲                                            │                ▲
      │                                            ▼                │
Editor (4 panels, 1 nonce) ─► sanitize_registry() ─► normalize() ─► registry option
      ▲                                                             │
      └── re-render (create) ◄─ normalize() re-fill ◄─ instance_current() ◄┘
                                             │
Renderer: nav class + color vars + 31-key data-cwc-config ─► buildOptions ─► Swiper
```

## 3. Settings Model + Shortcode

`builtins()` +13 (CM-12); `resolve()` `$known` +13 (base values must survive parse_args); `shortcode_atts` +13 × `''` (SC-12). `normalize()` (CM-13): parse_bool for autoplay/stop_on_hover/loop; timeout `min(60000,max(1000,absint))`; speed `min(5000,max(100,absint))`; slides_laptop `min(12,max(1,absint))`; nav_position via nav_positions(); colors `'' === $v ? '' : (string) sanitize_hex_color( $v )` — empty-check FIRST (`sanitize_hex_color('')` is null).

## 4. Editor (AS-16/17/18)

render_editor(): header shortcode field (edit only, read-only + copy button, labels as data-* attrs — no class-assets change) → tablist + four `.form-table` panels inside the single form. Content: title, type, categories, products (type=product), cover (type=category), subcategories, buy/buy_text. Behavior: 4-device slides picker (SVG icons), gap, count, autoplay, stop_on_hover, timeout, speed, loop. Navigation: arrows, pagination, nav_position, 6 color pickers. Style: title_align, gap. Conditional rows stay server-side on `$current['type']` (today's behavior).

New renderers (`string $prefix, array $current`): `render_toggle_field` (hidden 0 + checkbox 1, `.cwc-toggle` green/red; replaces inline checkbox markup in buy/arrows/pagination/cover/subcategories; serves the 3 new bools); `render_device_slides_field` (render_slides_field + slides_laptop); `render_nav_position_field` (nav_positions()); `render_color_group_field` (6 × color+hex+helper "empty = theme default"); `render_number_field` (render_number() + helper text, e.g. "1000–60000 ms").

## 5. Renderer + Frontend (CR-11, FA-8/9)

Renderer: class `cwc-carousel--nav-{pos}` when ≠ bottom-right; `style="--cwc-nav-color-{suffix}:{hex};…"` built only from non-empty keys via nav_color_keys(), whole attribute esc_attr'd; 31-key `data-cwc-config`. **PR 6 amendment:** for `sides-outside` the renderer wraps the carousel in `.cwc-carousel-shell` (buttons outside `.swiper`, as siblings), carrying the nav class + color vars on the shell; `sides-inside` is class + CSS only. carousel.css: bottom-right set on base `.cwc-carousel`; 3 modifier sets; side-mode rules for `.cwc-carousel--nav-sides-inside` and `.cwc-carousel-shell` (flex `[prev] [slider] [next]`, slider `flex:1; min-width:0`); color vars wired with fallbacks to `--cwc-color-accent`/transparent (FA-3). frontend.js: autoplay `{delay: config.timeout, pauseOnMouseEnter: config.stop_on_hover}` only when `config.autoplay===true`; `options.speed=config.speed` only when `typeof config.speed==='number'`; `loop:true` only when `config.loop===true`; 992 tier only when `numberOr(config.slides_laptop,0)>0`.

## 6. File Changes

| File | Action | Description |
|------|--------|-------------|
| `includes/class-settings.php` | Modify | +13 builtins; nav_positions(); nav_color_keys(); normalize() coercions; `$known` +13 |
| `includes/class-shortcode.php` | Modify | +13 atts whitelist |
| `includes/class-admin.php` | Modify | Tab panels; 6 renderers; toggle re-skins; re-fill via normalize(); sanitize_instance raw pass-through; PR 6: nav select labels + subcategories helper |
| `includes/class-renderer.php` | Modify | Position class + color style attr + sides-outside shell (PR 6) |
| `assets/js/admin.js` | Modify | Module split; tabs, color sync, copy |
| `assets/css/admin.css` | Modify | Tabs, toggles, device icons, colors, shortcode field |
| `assets/js/frontend.js` | Modify | buildOptions autoplay/speed/loop/992 (unchanged by PR 6) |
| `assets/css/carousel.css` | Modify | Corner + side-mode rules + color var wiring (PR 6) |

## 7. Commit Units (>400 lines → chain via ask-always)

| Unit | Commit | Files | ~Lines |
|------|--------|-------|--------|
| 1 settings | `feat(settings): 13-key contract — builtins, normalize clamps, nav whitelist` | class-settings.php | 45 |
| 2 shortcode | `feat(shortcode): whitelist 13 new atts` | class-shortcode.php | 15 |
| 3 admin UI | `feat(admin): tabbed editor + toggle/device/color renderers` | class-admin.php, admin.css (no-JS functional; likely needs sub-split at tasks) | 450+ |
| 4 frontend | `feat(render): nav class, color vars, Swiper autoplay/speed/loop + laptop tier` | class-renderer.php, frontend.js, carousel.css | 150 |
| 5 side-nav (PR 6) | `feat(nav): sides-inside/outside modes + subcategories helper` | class-renderer.php, class-admin.php, carousel.css, languages | 120–180 |

Each unit is self-contained and independently shippable; tasks/apply plans the PR chain (delivery_strategy=ask-always); phpcs + php -l green per unit. PR 6 branches from `feat/admin-ui-revamp-frontend` (PR 5).

## 8. Verification (phpcs + php -l + wp-env smoke; no PHPUnit)

| Check | Approach |
|-------|----------|
| BC DOM diff | wp-env `wp eval 'echo do_shortcode("[cwc_carousel]");'` pre/post — byte-identical (no class/vars) |
| Toggle round-trip | sanitize_registry with 0/1 posts → parse_bool; re-render checked states |
| Tab submit | One Settings API nonce POST persists across all 4 tabs; JS-free save (curl) succeeds |
| Shortcode→Swiper | Success-criteria shortcode → data-cwc-config → buildOptions mapping (browser) |
| Colors / laptop | Non-empty vars only; viewport 1000px → laptop tier; ≥1024 / ≤767 unchanged |
| i18n | tools/make-mo.php recompile es_ES (new admin strings, AS-14) |

## 9. Risks

| Risk | Mitigation |
|------|------------|
| normalize() re-fill alters existing create re-render display | Shows exactly what a save persists (AS-18 intent); noted |
| Color input #000000 coercion | Hex text posts (D4) |
| Default corner look changes existing carousels | Markup BC holds (CR-11); visual change is spec intent |
| Sides-outside breaks Swiper nav (buttons outside container) | Navigation uses document-level element selectors (FA-8); verify click/ARIA wiring in wp-env; fallback: pass button elements from the shell |
| Admin UI unit size | Sub-split at tasks; no-JS degradation keeps unit 3 shippable alone |

## Open Questions

- [ ] None blocking.

## Prior Debt (noted, not refactored)

`bound()`/sanitize_instance + `$numeric_bounds` duplicate normalize() ranges (4R WARNING) — untouched; the 13 new keys route exclusively through the shared layer (mandate).