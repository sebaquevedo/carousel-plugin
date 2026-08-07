# Delta for carousel-shortcode

> Modified capability. Main spec already exists at
> `openspec/specs/carousel-shortcode/spec.md`; the blocks below are already
> merged there (idempotent). Archive: ADDED blocks append; SC-1..SC-3 remain
> unchanged.

## ADDED Requirements

### Requirement: SC-4 — Configurable carousel attributes

The shortcode MUST accept `type`, `title`, `category`, `categories`, `mix`,
`count`, `slides`, `buy`, and `buy_text` attributes, defaulting each from the
admin defaults via `CWC_Settings::resolve()` (not local hardcoded defaults).
Unknown attributes MUST be dropped by `shortcode_atts`.

#### Scenario: Attribute accepted

- GIVEN `[cwc_carousel type="category" categories="1,2,3"]`
- WHEN processed
- THEN the resolved config routes a category carousel for terms 1, 2, 3
- AND the query/renderer receive the resolved config

#### Scenario: Override wins over admin default

- GIVEN admin default `count` of 8 and `[cwc_carousel count="6"]`
- WHEN processed
- THEN the instance renders at most 6 products

#### Scenario: Unknown attribute dropped

- GIVEN `[cwc_carousel unknown="x"]`
- WHEN processed
- THEN the unknown attribute is ignored with no error

### Requirement: SC-5 — Per-instance config resolution

Each shortcode invocation MUST resolve its own config through
`CWC_Settings::resolve()` so multiple instances of the same shortcode on one
page can differ; no single global config is shared.

#### Scenario: Two different instances

- GIVEN `[cwc_carousel type="category"]` and `[cwc_carousel type="product"]` on one page
- WHEN processed
- THEN each renders its own type and config independently