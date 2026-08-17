# Delta for carousel-shortcode

## ADDED Requirements

### Requirement: SC-11 — `products` manual-ID list (BC fallback)

`CWC_Shortcode::render()` MUST add `'products' => ''` to the `shortcode_atts()` default array so the attribute reaches `CWC_Settings::resolve()` (an SC-9-style no-op otherwise). A resolved non-empty `products` array MUST be passed to the query as an ordered manual-ID list (`orderby=post__in`), ignoring `count`. An empty `products` (including one coerced empty by `sanitize_ids()`) MUST leave the query path unchanged — the latest-N fallback (BC). The legacy `ids` attribute MUST remain untouched.

#### Scenario: Manual products render in order

- GIVEN a resolved `products` of `[12, 7, 3]`
- WHEN the shortcode renders
- THEN exactly products 12, 7, 3 are queried and rendered in manual order
- AND `count` is ignored

#### Scenario: Empty products stays BC

- GIVEN `products = []` (or fully coerced away to `[]`)
- WHEN the shortcode renders
- THEN the query uses the latest-N path with `count` as today
- AND output is identical to current behavior

#### Scenario: Explicit attribute overrides the instance

- GIVEN a `productos` instance with stored products `[3, 12, 7]` and `[cwc_carousel name="productos" products="1,2"]`
- WHEN rendered
- THEN the resolved `products` is `[1, 2]`
- AND those products render in that order