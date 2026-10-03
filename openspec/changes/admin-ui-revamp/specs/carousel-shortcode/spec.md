# Delta for carousel-shortcode

> Modified capability. Main spec exists at
> `openspec/specs/carousel-shortcode/spec.md`. ADDED block SC-12 appends.
> Archive: append.

## ADDED Requirements

### Requirement: SC-12 — Whitelist gains the 13 new atts

`CWC_Shortcode::render()` MUST add `'autoplay' => ''`, `'stop_on_hover' => ''`,
`'timeout' => ''`, `'speed' => ''`, `'loop' => ''`, `'nav_position' => ''`,
the six `'nav_color_*' => ''` keys, and `'slides_laptop' => ''` to the
`shortcode_atts()` default array (an SC-9-style no-op otherwise — unwhitelisted
atts are dropped before `resolve()`). An empty-string value (missing att) MUST
fall through `resolve()`'s empty-string filter to the instance/builtin default
(BC); an explicit value MUST override. Coercion stays in the config model
(CM-13) — the shortcode layer only whitelists and passes through.

#### Scenario: Explicit atts reach resolve

- GIVEN `[cwc_carousel autoplay="true" loop="true" speed="500" timeout="4000" stop_on_hover="false" slides_laptop="3" nav_position="top-left"]`
- WHEN rendered
- THEN the resolved config carries those values
- AND buildOptions maps them onto Swiper (FA-8)

#### Scenario: Omitted atts keep defaults (BC)

- GIVEN `[cwc_carousel]` with none of the 13 atts
- WHEN rendered
- THEN every new key resolves to its builtin
- AND output is unchanged for existing shortcodes

#### Scenario: Empty-string att treated as absent

- GIVEN `[cwc_carousel autoplay="" loop=""]`
- WHEN rendered
- THEN autoplay and loop fall through to the instance/builtin default

## Acceptance Criteria

- The 13 new atts reach `resolve()`; unknown atts still dropped.
- Absent/empty atts produce no output change (BC).
- The success-criteria shortcode (`autoplay="true" loop="true" speed="500"
  timeout="4000" stop_on_hover="false" slides_laptop="3"`) resolves fully.
