# Delta for config-model

> Modified capability. Main spec: `openspec/specs/config-model/spec.md`.
> CM-9 MODIFIED (18→31-key contract); CM-12..CM-14 ADDED.
> Archive: MODIFIED replaces CM-9; ADDED blocks append.

## MODIFIED Requirements

### Requirement: CM-9 — Per-instance 31-key contract preserved

Each resolved instance MUST expose exactly the 31-key config contract (the 18
existing keys of CM-9 — `type`, `title`, `category`, `categories`, `mix`,
`count`, `slides`, `slides_tablet`, `slides_mobile`, `gap`, `arrows`,
`pagination`, `buy`, `buy_text`, `cover`, `subcategories`, `title_align`,
`products` — plus CM-12's 13 keys) via `normalize()`. `products` MUST default
to `[]` in `builtins()`, MUST be coerced in `normalize()` via `sanitize_ids()`
(absint, values ≤ 0 dropped), and MUST be listed in the `$known` whitelist.
The instance base MUST merge over `builtins()` so any absent per-instance key
fills its builtin and booleans stay typed (`parse_bool`).
(Previously: exactly the 18-key contract.)

#### Scenario: Partial instance fills from builtins

- GIVEN an instance config omitting `buy`/`gap`/`products`/all 13 new keys
- WHEN resolved
- THEN `buy`, `gap` and `products` carry the builtin values (`products` → `[]`)
- AND the 13 new keys carry their builtins
- AND every config serializes through `wp_json_encode` with all 31 keys (CM-4)

#### Scenario: New keys present on legacy instances

- GIVEN a stored instance saved before this change (no new keys)
- WHEN resolved
- THEN `cover` and `subcategories` resolve false, `title_align` resolves `left`,
  and `products` resolves `[]`
- AND the 13 new keys resolve to their builtins (CM-12)
- AND the instance renders identically to before (BC)

#### Scenario: Malformed products coerced

- GIVEN stored `products = ["0", "abc", 7, 12]`
- WHEN `normalize()` runs
- THEN `products` resolves to `[7, 12]`
- AND resolution succeeds without error

## ADDED Requirements

### Requirement: CM-12 — builtins() gains the 13 new keys

`builtins()` MUST return the existing 18 keys plus 13 new ones: `autoplay`
bool `false`, `stop_on_hover` bool `true`, `timeout` int `3000`, `speed` int
`300`, `loop` bool `false`, `nav_position` enum `bottom-right`, the six
`nav_color_*` strings `''`, `slides_laptop` int `2`.

#### Scenario: Defaults when absent

- GIVEN a config with none of the 13 keys
- WHEN `normalize()` runs
- THEN autoplay/loop=false, stop_on_hover=true, timeout=3000, speed=300,
  nav_position=`bottom-right`, all six colors=`''`, slides_laptop=2

### Requirement: CM-13 — Coercion rules for the 13 keys

`normalize()` MUST coerce: `autoplay`, `stop_on_hover`, `loop` via
`parse_bool`; `timeout` via `absint` clamped 1000–60000 (default 3000; 1 s
floor keeps autoplay legible, 60 s cap covers slow displays); `speed` via
`absint` clamped 100–5000 (default 300; 100 ms floor keeps transitions
perceivable, 5 s cap for long slides); `slides_laptop` via `absint` clamped
1–12 (same bounds as the other slides keys); `nav_position` against
the shared `nav_positions()` whitelist (D2) — `bottom-right` |
`bottom-left` | `top-right` | `top-left` | `sides-inside` |
`sides-outside` (invalid → `bottom-right`); each `nav_color_*` via `sanitize_hex_color` with
empty allowed — empty checked first (`sanitize_hex_color('')` is null): empty passes through,
invalid hex coerces to `''` (theme default).

#### Scenario: Clamps applied

- GIVEN timeout="500" speed="0" slides_laptop="99"
- WHEN `normalize()` runs
- THEN timeout=1000, speed=100, slides_laptop=12

#### Scenario: Invalid enum and hex coerced

- GIVEN nav_position="middle" nav_color_arrow="red"
- WHEN `normalize()` runs
- THEN nav_position=`bottom-right` and nav_color_arrow=`''`

#### Scenario: Side modes accepted

- GIVEN nav_position="sides-inside" or "sides-outside"
- WHEN `normalize()` runs
- THEN the value passes through unchanged (whitelisted via nav_positions())
- AND an invalid value still coerces to `bottom-right`

### Requirement: CM-14 — Legacy registry entries resolve unchanged

A stored instance lacking all 13 keys MUST resolve with each new key at its
builtin (CM-12), so the resolved 31-key shape serializes via CM-4 and the
renderer emits markup byte-identical to before the change (CR-11). No migration
script is required.

#### Scenario: Legacy instance normalizes

- GIVEN a registry entry saved before this change (18 keys only)
- WHEN `normalize()` runs
- THEN the 13 new keys carry builtins
- AND no migration script runs or is required

## Decisions / Notes

- Clamp bounds (proposal open question 1): timeout 1000–60000 ms, speed
  100–5000 ms — floors are user-meaningful; sub-second autoplay and sub-100 ms
  transitions are unusable/imperceptible.
- Nav-position whitelist extended to 6 values (PR 6 amendment): the two side
  modes flow to the admin select and the renderer through the shared
  `nav_positions()` source (D2) — no per-layer literal lists.

## Acceptance Criteria

- 13 new keys resolve with exact defaults on absent input.
- timeout/speed/slides_laptop clamp per CM-13; invalid enum/hex coerce safely.
- Side values normalize unchanged; legacy 18-key instances resolve to 31 keys
  with no migration and render byte-identical markup.
