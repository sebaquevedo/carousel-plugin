# Proposal: Admin UI Revamp — WPCPSU-style settings UI + missing carousel settings

## Intent

The per-instance editor (`render_editor`, includes/class-admin.php:332-518) is one flat 12-row `.form-table` — verified gaps: no tabs, native checkboxes, no color controls, no device-aware slides picker. The reference plugin "WooCommerce Product Grid Carousel Slider Ultimate" (WPCPSU) organizes the same functionality in tabs with Yes/No toggle switches, a per-device column picker, a corner nav-position select, and nav color pickers — patterns our panel lacks. Independently, 13 settings Swiper already supports (autoplay, loop, speed, hover-pause, nav colors, laptop breakpoint) are missing from the model, so users cannot configure them from admin or shortcode. This change re-skins the editor to match the reference UX and adds the missing settings end-to-end (model → sanitize → editor → shortcode → Swiper).

**Decided scope (not re-litigating):** vanilla PHP/JS/CSS only; one form + one submit button across tabs; validation failures preserve values in all tabs; BC via `normalize()` defaults — existing carousels render unchanged.

## Scope

### In Scope
- Editor re-skin: 4 tabs (Content / Behavior / Navigation / Style) over ONE form and submit button; conditional type=product/type=category rows stay inside Content.
- Yes/No toggle switches replacing native checkboxes (post `0`/`1`, compatible with existing sanitize).
- Number inputs with helper text; copyable shortcode field in the editor header (not a tab).
- 13 new settings through settings model, sanitize, editor, shortcode atts, and Swiper frontend.
- 4th Swiper breakpoint for laptop (between desktop and tablet).
- Two new nav-position modes beyond the 4 corners: `sides-inside` (arrows at the slider's left/right edges, vertically centered, overlaying inside) and `sides-outside` (arrows in flanking columns outside the slider, slider shrinks via a flex shell wrapper) — amendment from manual-testing feedback.

### Out of Scope
- CPT migration — registry stays in Options API (`cwc_carousel_registry`).
- Frontend preview inside admin.
- List view revamp (minor cosmetic at most).
- React/build tooling, new dependencies.

## Settings Additions (13 keys)

| Key | Type | Default | Coercion |
|-----|------|---------|----------|
| `autoplay` | bool | `false` | `parse_bool` |
| `stop_on_hover` | bool | `true` | `parse_bool` |
| `timeout` | int ms | `3000` | `absint` + clamp (bounds in spec) |
| `speed` | int ms | `300` | `absint` + clamp (bounds in spec) |
| `loop` | bool | `false` | `parse_bool` |
| `nav_position` | enum | `bottom-right` | whitelist of 6 values (4 corners + `sides-inside` + `sides-outside`) |
| `nav_color_arrow` | string | `''` | `sanitize_hex_color`, empty allowed |
| `nav_color_bg` | string | `''` | `sanitize_hex_color`, empty allowed |
| `nav_color_border` | string | `''` | `sanitize_hex_color`, empty allowed |
| `nav_color_arrow_hover` | string | `''` | `sanitize_hex_color`, empty allowed |
| `nav_color_bg_hover` | string | `''` | `sanitize_hex_color`, empty allowed |
| `nav_color_border_hover` | string | `''` | `sanitize_hex_color`, empty allowed |
| `slides_laptop` | int 1–12 | `2` | `absint` + clamp 1–12 |

Empty nav colors → theme default (no CSS emitted). All keys added to `builtins()`/`normalize()` (class-settings.php:251-320), so existing registry entries receive defaults with no migration script.

## UI Component Plan

- **Tabs**: nav-tab markup; four panels toggled client-side (admin.js), all fields inside one `<form>` — one Settings API nonce, one submit; rejected saves re-render with values preserved (existing `__new__`/coercion re-fill logic extended to new keys).
- **Toggle switches**: styled checkbox/radio pair posting hidden `0` + visible `1`, reusing `parse_bool` on save; green Yes / red No states in admin.css.
- **Device picker**: 4 stacked number inputs (desktop/laptop/tablet/mobile) with device icons in the Behavior tab (extends `render_slides_field`).
- **Color pickers**: 6 `input[type=color]` + hex text (WP 5.5+ native), in the Navigation tab.
- **Shortcode field**: read-only copy input in editor header, built from the instance slug.

## Capabilities

### New Capabilities
None — all changes land in existing capabilities.

### Modified Capabilities
- `config-model` — resolved shape gains the 13 keys (builtins + coercion rules above).
- `carousel-shortcode` — `shortcode_atts` whitelist (:70-95) gains the 13 keys; absent atts fall through to instance/builtin defaults.
- `carousel-renderer` — `nav_position` class + 6 nav-color CSS variables on the container; markup unchanged when new keys unset; `data-cwc-config` carries the extended config.
- `frontend-assets` — `buildOptions` (frontend.js:86-129) gains autoplay (`delay`/`pauseOnMouseEnter`), `speed`, `loop`, and the `slides_laptop` breakpoint (between 768 and 1024); admin.js/admin.css gain tab/toggle/device-picker/color behavior.
- `admin-settings` — editor re-skin: tabs, toggles, device picker, color pickers, helper text, shortcode field.

## Approach

1. `class-settings.php`: add 13 keys to `builtins()` + `normalize()` (shared coerce point — all consumers stay in sync).
2. `class-shortcode.php`: whitelist the 13 new atts.
3. `class-admin.php`: restructure `render_editor` into tab panels; add 6 field renderers (toggle, device picker, nav position select, color group, helper numbers); emit shortcode field; extend create re-fill coercion with `slides_laptop`/`timeout`/`speed`.
4. `admin.js`/`admin.css`: tab switching, toggle styling, device icons, helper text, copy button.
5. `class-renderer.php`: nav-position class + CSS variables when colors set.
6. `frontend.js`: `buildOptions` gains autoplay/speed/loop + laptop breakpoint; `carousel.css` gains corner position rules + variable wiring.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `includes/class-settings.php` | Modify | 13 keys in builtins/normalize |
| `includes/class-shortcode.php` | Modify | Whitelist 13 new atts |
| `includes/class-admin.php` | Modify | Editor re-skin + new field renderers |
| `includes/class-renderer.php` | Modify | Nav-position class + color CSS vars |
| `assets/js/admin.js` | Modify | Tabs, toggles, device picker, copy field |
| `assets/js/frontend.js` | Modify | Swiper autoplay/speed/loop + laptop breakpoint |
| `assets/css/admin.css` | Modify | Tab/toggle/device/color styling |
| `assets/css/carousel.css` | Modify | Corner nav positions + color variables |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| **Size exceeds 400-line review budget** — 8 files, 13 keys × 5 layers, full editor re-skin | High | Flag explicitly; plan commit units per layer (settings → shortcode → admin UI → frontend); verify in chunks |
| Tab JS breaks one-form submit / nonce flow | Med | No JS required for submit; panels are pure visibility; all fields always present in DOM |
| Validation re-fill misses new keys | Med | Extend create-mode coercion map (`slides_laptop` etc.) exactly as `bound()` clamps on save |
| `sanitize_hex_color` rejects empties | Med | Empty-check before sanitize; empty → default (no CSS emitted) |
| New keys absent from legacy instances | Low | `normalize()` defaults cover all 13; no migration script |
| Shortcode with unset new atts changes output | Low | Empty-string atts fall through to instance/builtin defaults (existing mechanics) |

## Rollback Plan

Revert the 8 files. Deleting the new keys from any saved registry entry is not required — `normalize()` of a legacy key set simply omits them and frontend.js falls back to current behavior (no autoplay/loop, 3-breakpoint ramp). No schema, no data migration.

## Dependencies

- None new. Swiper (already bundled) supports autoplay/loop/speed natively; WP 5.5+ `input[type=color]` (no JS picker dependency).

## Success Criteria

- [ ] Existing carousels render byte-identical output with new keys unset.
- [ ] All 13 settings persist via editor and survive reload; `0`/`1` toggles reach `parse_bool`.
- [ ] 4 tabs render under one form; failed validation preserves values across tabs.
- [ ] `[cwc_carousel autoplay="true" loop="true" speed="500" timeout="4000" stop_on_hover="false" slides_laptop="3"]` reaches Swiper options (verified in `buildOptions`).
- [ ] `nav_position` + 6 colors emit wrapper class/CSS variables; empty colors emit nothing.
- [ ] `slides_laptop` applies at its breakpoint; tablet/mobile/desktop unchanged.
- [ ] phpcs passes (WordPress + WordPress-Extra); no React/build step added.

## Open Questions

1. Clamp bounds for `timeout`/`speed` (proposal suggests spec fixes, e.g. 1–60000 / 1–10000).
2. Exact laptop breakpoint px (between 768 and 1024 — spec/design decides, e.g. 992).
