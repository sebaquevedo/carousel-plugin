# carousel-renderer Specification

## Purpose

Shared, server-side HTML generation for cards and the carousel wrapper, consumed
by both the shortcode and (later) the block render path. Output is fully escaped
and theme-safe. Carousel title support is included (Resolved Decision 5). Out of
scope this slice: badges, hover effects, quantity selector, AJAX cart (later).

## Requirements

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

### Requirement: CR-5 — Category card

For type `category`, the renderer MUST output one card per chosen `product_cat`
term: the category image (WC `thumbnail_id`, overridden by the per-category
custom upload when present) and the escaped term name, with the whole card
linking to the category archive via `get_term_link()` (escaped with `esc_url`).

#### Scenario: Category card renders

- GIVEN a category carousel with chosen terms
- WHEN rendered
- THEN each card shows the category image and escaped name
- AND the card links to that category's archive

#### Scenario: Missing category image

- GIVEN a term with no thumbnail and no custom upload
- WHEN rendered
- THEN a safe placeholder/omitted image is rendered
- AND no broken output or warnings occur

### Requirement: CR-6 — Buy button gating

For type `product` with buy enabled, the renderer MUST output a Buy button
(element, not text link) per product ONLY when the product is purchasable AND
available (`is_purchasable() && ( is_in_stock() || backorders_allowed() )`),
linking to the product page via `get_permalink()`, with the escaped configurable
label (`buy_text`, default "Comprar").

#### Scenario: Purchasable and in stock

- GIVEN a purchasable product in stock and buy enabled
- WHEN the card renders
- THEN a Buy button with the configured label links to the product page

#### Scenario: Unavailable product hides the button

- GIVEN a product that is not purchasable, or out of stock without backorders
- WHEN the card renders
- THEN no Buy button is output
- AND the rest of the card still renders

#### Scenario: Buy disabled

- GIVEN buy is disabled for the instance
- WHEN the card renders
- THEN no Buy button is output regardless of product availability

### Requirement: CR-7 — Per-container config emission

The renderer MUST emit the resolved config once per container as a JSON
`data-cwc-config` attribute (`wp_json_encode` + `esc_attr`, one emit point) so
each carousel instance carries its own Swiper options.

#### Scenario: Two instances, two configs

- GIVEN two carousels of the same shortcode with different resolved configs
- WHEN rendered on one page
- THEN each container carries its own `data-cwc-config` JSON
- AND no single global config is emitted

#### Scenario: Empty config still escaped

- GIVEN a resolved config that serializes to a short/empty JSON object
- WHEN rendered
- THEN the attribute is still `esc_attr`-escaped and well-formed