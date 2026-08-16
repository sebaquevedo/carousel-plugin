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

### Requirement: SC-8 — `name` attribute whitelisted and resolved

`CWC_Shortcode::render()` MUST add `'name' => ''` to the `shortcode_atts()`
default array and pass it to `CWC_Settings::resolve()`, where an empty string is
filtered (treated as "no name" → reserved `default` instance, CM-8) and a supplied
name selects that registry instance as the defaults base. Unknown names fall back
to `default` without error.

#### Scenario: Named instance selects base config

- **GIVEN** `[cwc_carousel name="productos"]`
- WHEN the shortcode renders
- THEN `resolve()` uses the `productos` instance config as the base (3/2/1, arrows+pagination ON)
- AND renderer/query consume the same 14-key resolved config as today (CR-7, PQ)

#### Scenario: No name is backward compatible

- **GIVEN** `[cwc_carousel]` (no name)
- WHEN rendered
- THEN `resolve()` uses the `default` instance
- AND output is identical to today's shortcode (BC)

#### Scenario: Unknown name degrades gracefully

- **GIVEN** `[cwc_carousel name="nope"]`
- WHEN rendered
- THEN it falls back to the `default` instance
- AND rendering succeeds with no fatal error

### Requirement: SC-9 — Whitelist gains slides_tablet/slides_mobile/gap (CRITICAL)

`CWC_Shortcode::render()` MUST add `'slides_tablet' => ''`,
`'slides_mobile' => ''`, and `'gap' => ''` to the `shortcode_atts()` default
array. Without them `shortcode_atts()` drops the attributes and the
per-instance slides/gap overrides would never reach `resolve()` (an SC-6-style
no-op). An empty-string value (missing att) MUST fall through `resolve()`'s
empty-string filter to the instance/builtin default (BC for existing no-name
shortcodes); an explicit value MUST now override the default instead of silently
dying — the intended fix.

#### Scenario: Explicit slides override reaches resolve

- **GIVEN** `[cwc_carousel name="productos" slides_tablet="3"]`
- WHEN rendered
- THEN resolved `slides_tablet` is `3`
- AND `.swiper` breaks at tablet=3 on that container

#### Scenario: Explicit gap override applies

- **GIVEN** `[cwc_carousel gap="24"]`
- WHEN rendered
- THEN resolved `gap` is `24`
- AND the container gap reflects it

#### Scenario: Omitted atts keep defaults (BC)

- **GIVEN** `[cwc_carousel]` (no slides/gap atts, `slides_tablet`/`gap` unfilled `''`)
- WHEN rendered
- THEN instance/builtin defaults apply
- AND no behavior change for existing no-name shortcodes

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