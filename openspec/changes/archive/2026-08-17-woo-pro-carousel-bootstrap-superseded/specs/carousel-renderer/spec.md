# Delta for carousel-renderer

> New capability (slice 1). Main spec pre-created at `openspec/specs/carousel-renderer/spec.md`
> per sdd-spec Step 2 (new capabilities → full spec). Archive: the ADDED blocks below are the
> full requirement set for this change; match by name against the main spec (idempotent).

## ADDED Requirements

### Requirement: CR-1 — Escaped product card markup

The renderer MUST output a card per product containing image, title, and price,
escaping every value: image URL and alt with `esc_url`/`esc_attr`, title with
`esc_html`, and price through WC formatting.

#### Scenario: Well-formed card

- GIVEN a product with image, title and price
- WHEN the card is rendered
- THEN image, title, and formatted price are output
- AND all attribute/HTML/URL values are escaped and url-safe
- AND card data is read from each `WC_Product` object, never from the global `$post` (the hosting page)

#### Scenario: Missing product image

- GIVEN a product has no image
- WHEN the card is rendered
- THEN a safe placeholder/omitted image is rendered
- AND no broken output or warnings occur

### Requirement: CR-2 — Swiper-compatible carousel wrapper

The renderer MUST output Swiper markup structure (outer container with slide
children) that initializes against the vendored Swiper.

#### Scenario: Carousel skeleton

- GIVEN one or more rendered cards
- WHEN wrapped
- THEN the output exposes Swiper's expected `.swiper` container and `.swiper-slide` children

#### Scenario: Empty product set

- GIVEN the query returns no products
- WHEN the renderer runs
- THEN it returns an empty string with no carousel markup
- AND no rendered flag is set, so no assets are enqueued
- AND no fatal error occurs

### Requirement: CR-3 — CSS custom-property theming

The renderer MUST render the carousel container with CSS custom properties for
colors, borders, gap, and typography, enabling theme/later-slice overrides.

#### Scenario: Overridable tokens

- GIVEN a rendered carousel
- WHEN inspected
- THEN custom properties (e.g. color/gap/typography) are present on the container

### Requirement: CR-4 — Carousel title above the carousel

When provided, the renderer MUST output an escaped title heading element above the
carousel, styled ad-hoc to be compatible with the active theme/template.

#### Scenario: Title supplied

- GIVEN a carousel title was provided
- WHEN rendered
- THEN the escaped title is shown above the carousel wrapper

#### Scenario: No title

- GIVEN no title is provided
- WHEN rendered
- THEN no title element is output and layout is unaffected
