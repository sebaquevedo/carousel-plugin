# Delta for carousel-shortcode

> Modified capability. Main spec exists at
> `openspec/specs/carousel-shortcode/spec.md`; SC-10 appends (ADDED). Archive:
> ADDED blocks append; SC-1..SC-9 unchanged.

## ADDED Requirements

### Requirement: SC-10 — Whitelist gains cover/subcategories/title_align

`CWC_Shortcode::render()` MUST add `'cover' => ''`, `'subcategories' => ''` and
`'title_align' => ''` to the `shortcode_atts()` default array so the attributes
reach `CWC_Settings::resolve()` (an SC-9-style no-op otherwise). An
empty-string value (missing att) MUST fall through `resolve()`'s empty-string
filter to the instance/builtin default; an explicit value MUST now override the
default. `cover`/`subcategories` MUST be parsed as booleans and `title_align`
restricted to `center`|`left`|`right` by the config model (CM-11).

#### Scenario: Cover att reaches resolve

- GIVEN `[cwc_carousel type="category" cover="1"]`
- WHEN rendered
- THEN the resolved config carries `cover` true
- AND the renderer emits the cover branch (CR-9)

#### Scenario: Title align att applies

- GIVEN `[cwc_carousel title="Ofertas" title_align="center"]`
- WHEN rendered
- THEN the resolved `title_align` is `center`
- AND the header h2 carries the centered modifier (CR-8)

#### Scenario: Omitted atts keep defaults (BC)

- GIVEN `[cwc_carousel]` with none of the three new atts
- WHEN rendered
- THEN `cover`/`subcategories` stay false and `title_align` stays `left`
- AND output is unchanged for existing shortcodes

## Decisions / Notes

- Mirrors SC-9: whitelist-only here; coercion lives in `CWC_Settings`
  (`$known` + `normalize`, CM-11), so the shortcode never hardcodes defaults.
- No re-render changes for product carousels: `cover` is ignored by the
  renderer for `type=product` (CCC-1).

## Acceptance Criteria

- `cover`/`subcategories`/`title_align` reach `resolve()` and override defaults.
- Existing shortcodes without the new atts render identically to today.
- phpcs passes (WordPress + WordPress-Extra).