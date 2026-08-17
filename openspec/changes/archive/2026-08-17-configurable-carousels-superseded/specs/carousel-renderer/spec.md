# Delta for carousel-renderer

> Modified capability. Main spec already exists at
> `openspec/specs/carousel-renderer/spec.md`; the blocks below are already
> merged there (idempotent). Archive: ADDED blocks append; CR-1..CR-4 remain
> unchanged.

## ADDED Requirements

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