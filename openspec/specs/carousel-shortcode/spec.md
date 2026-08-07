# carousel-shortcode Specification

## Purpose

Registers `[cwc_carousel]`, wiring shortcode attributes → query layer → renderer.
Supports a `title` attribute now (Resolved Decision 6) in readiness for later
config. Out of scope this slice: admin UI (2), filters (3), block (4), AJAX cart (5).

## Requirements

### Requirement: SC-1 — Shortcode registration

The plugin MUST register `[cwc_carousel]` and, when invoked, route default
attributes through the query module and then the renderer.

#### Scenario: Shortcode renders a carousel

- GIVEN a page containing `[cwc_carousel]`
- WHEN the shortcode is processed
- THEN the query layer is invoked for recent products (default)
- AND the renderer outputs the carousel markup in place of the shortcode

#### Scenario: Unknown attribute ignored

- GIVEN `[cwc_carousel unknown="x"]`
- WHEN processed
- THEN the unknown attribute is ignored
- AND rendering succeeds with defaults

### Requirement: SC-2 — `title` attribute

The shortcode MUST accept a `title` attribute, defaulting to no title when absent,
and MUST pass the sanitized title to the renderer, which performs the single output
escape (`esc_html`) — a shared escape point so the block render path never
double-escapes.

#### Scenario: Title provided

- GIVEN `[cwc_carousel title="Ofertas"]`
- WHEN processed
- THEN the escaped computed heading is passed to the renderer
- AND it is displayed above the carousel

#### Scenario: Title omitted

- GIVEN `[cwc_carousel]` with no title attribute
- WHEN processed
- THEN the title defaults to empty and no heading is rendered

### Requirement: SC-3 — Default fallbacks with manual-ID override

Without manual IDs the shortcode MUST fall back to recent products; when provided,
manual IDs (sanitized via `absint`) MUST override the default. Display copy uses
English/neutral strings and a stable text domain.

#### Scenario: Default recent products

- GIVEN `[cwc_carousel]` with no manual IDs
- WHEN processed
- THEN the query returns recent products and the carousel renders

#### Scenario: Manual IDs override

- GIVEN `[cwc_carousel ids="12,7,3"]`
- WHEN processed
- THEN only sanitized products 12, 7, 3 are queried and rendered in order

#### Scenario: Empty product set is graceful

- GIVEN the query yields no products
- WHEN the shortcode renders
- THEN output degrades gracefully with no slides and no fatal error

#### Scenario: Explicit but invalid IDs render nothing

- GIVEN `[cwc_carousel ids="0,abc"]` (IDs supplied but every value invalid)
- WHEN processed
- THEN no fallback to recent products occurs and nothing renders

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