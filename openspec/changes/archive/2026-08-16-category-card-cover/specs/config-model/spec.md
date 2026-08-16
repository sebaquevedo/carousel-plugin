# Delta for config-model

> Modified capability. Main spec exists at
> `openspec/specs/config-model/spec.md`; CM-9 is replaced (MODIFIED) and CM-11
> is appended (ADDED). Archive: apply MODIFIED, then append ADDED; CM-1..CM-8,
> CM-10 unchanged.

## MODIFIED Requirements

### Requirement: CM-9 — Per-instance 17-key contract preserved

Each resolved instance MUST expose exactly the 17-key config contract
(`type`, `title`, `category`, `categories`, `mix`, `count`, `slides`,
`slides_tablet`, `slides_mobile`, `gap`, `arrows`, `pagination`, `buy`,
`buy_text`, `cover`, `subcategories`, `title_align`) via `normalize()`. The
instance base MUST merge over `builtins()` so any absent per-instance key fills
its builtin and booleans stay typed (`parse_bool`).
(Previously: the 14-key contract without `cover`/`subcategories`/`title_align`.)

#### Scenario: Partial instance fills from builtins

- GIVEN an instance config omitting `buy`/`gap`
- WHEN resolved
- THEN `buy` and `gap` carry the builtin values
- AND every config serializes through `wp_json_encode` with all 17 keys (CM-4)

#### Scenario: New keys present on legacy instances

- GIVEN a stored instance saved before this change (no new keys)
- WHEN resolved
- THEN `cover` and `subcategories` resolve false and `title_align` resolves `left`
- AND the instance renders identically to before (BC)

## ADDED Requirements

### Requirement: CM-11 — Cover-mode config keys

`builtins()`, `normalize()` and the `$known` whitelist MUST include `cover`
(bool, default false), `subcategories` (bool, default false) and `title_align`
(enum `center`|`left`|`right`, default `left`). An invalid `title_align` value
MUST coerce to `left`; an empty-string shortcode attribute MUST fall through to
the instance/builtin default.

#### Scenario: Defaults when absent

- GIVEN a config with none of the three keys
- WHEN `normalize()` runs
- THEN `cover`=false, `subcategories`=false, `title_align`=`left`

#### Scenario: Explicit values resolve

- GIVEN `[cwc_carousel cover="1" subcategories="1" title_align="center"]`
- WHEN `resolve()` runs
- THEN the resolved config carries cover=true, subcategories=true, title_align=`center`

#### Scenario: Invalid title_align coerced

- GIVEN `title_align="diagonal"`
- WHEN `normalize()` runs
- THEN `title_align` coerces to `left`
- AND resolution succeeds without error

## Decisions / Notes

- Seeds (CM-10) are untouched: they inherit the new builtin defaults
  (false/false/left), so existing seeded instances keep today's rendering.
- The renderer ignores `cover` for `type=product` (CCC-1); the config model
  still stores the key on every resolved shape (CM-9 contract).

## Acceptance Criteria

- Resolved configs serialize exactly 17 keys; legacy instances stay BC.
- `cover`/`subcategories` default false; `title_align` defaults `left`.
- Invalid `title_align` never errors; phpcs passes.