# category-card-cover Specification

## Purpose

Opt-in cover mode for `type=category` carousels: uniform 7/12 portrait cards,
full-bleed image, single-opacity dark overlay, centered white per-card title; no
button/text below. Includes per-category overlay title (term meta
`cwc_cat_title`), placeholder rendering for thumbnailless terms,
direct-children subcategory listing, and gettext i18n (EN + neutral ES).
Products are never affected.

## Requirements

### Requirement: CCC-1 — Cover mode is opt-in and category-only

The `cover` boolean MUST be honored for `type=category` only: true emits the
cover branch; false keeps today's thumbnail-below-title layout. For
`type=product`, `cover` MUST be ignored.

#### Scenario: Cover off keeps current layout

- GIVEN a category carousel with `cover` false
- WHEN cards render
- THEN the current thumbnail + name-below layout is produced

#### Scenario: Cover is ignored for products

- GIVEN a product carousel with `cover` true
- WHEN cards render
- THEN product cards render unchanged (image, title, price, Buy gating)

### Requirement: CCC-2 — Uniform 7/12 cover card sizing

Cover-mode cards MUST render at `aspect-ratio 7/12` (≈1:1.72), equal responsive
widths inside the slide, spaced by the carousel `gap` (8-64).

#### Scenario: Equal responsive cards

- GIVEN a cover carousel with 4 desktop slides and `gap` 36
- WHEN the CSS applies
- THEN every cover card shares the 7/12 aspect ratio
- AND cards are evenly spaced by the configured gap

### Requirement: CCC-3 — Full-bleed image, overlay, centered white title

A cover card MUST link to the category archive (`get_term_link`), show the
category image (`cwc_cat_image` → `thumbnail_id`) full-bleed, apply a
single-opacity dark overlay, and render the title centered in white ON the card.
The overlay title MUST be term meta `cwc_cat_title` when non-empty, else the
term name. No button or text renders below the image.

#### Scenario: Cover card renders

- GIVEN a term with an image and `cwc_cat_title` "Verano"
- WHEN the cover card renders
- THEN the card links to the archive with image, overlay, and white centered "Verano"
- AND no button or caption appears below the image

#### Scenario: Overlay title falls back to term name

- GIVEN a term with an image but empty `cwc_cat_title`
- WHEN the cover card renders
- THEN the centered white title is the escaped term name

### Requirement: CCC-4 — Thumbnailless terms render a placeholder title card

A term with no `cwc_cat_image`, no `thumbnail_id`, or a missing attachment MUST
render a placeholder background with the title overlaid on top, and MUST never
be excluded from the carousel.

#### Scenario: No image, term kept

- GIVEN a term with no image and no custom upload
- WHEN the cover card renders
- THEN a placeholder background card with the centered title renders
- AND the term is included in the slide set

### Requirement: CCC-5 — Subcategory listing (direct children)

When `subcategories` is true and a parent `category` term id is supplied, the
carousel MUST list that parent's direct child terms (depth 1, `product_cat`)
instead of the explicit `categories` selection. The listing MUST use
`hide_empty => true` (mirrors `get_categories()`), so a child term with no
products is omitted. A missing parent or `category` 0 MUST yield an empty
carousel without error.

#### Scenario: Direct children listed

- GIVEN `subcategories` true and `category` = 4 with children A and B
- WHEN the query runs
- THEN terms A and B are returned as cards

#### Scenario: Empty child term omitted

- GIVEN `subcategories` true and `category` = 4 with children A, B (with products) and C (no products)
- WHEN the query runs
- THEN only A and B are returned as cards
- AND child C is omitted (`hide_empty => true`)

#### Scenario: Empty or invalid parent

- GIVEN `subcategories` true and `category` = 0 (or a missing term id)
- WHEN the query runs
- THEN an empty result is returned
- AND no fatal error occurs

### Requirement: CCC-6 — gettext i18n (EN + neutral ES)

New ADMIN strings MUST use `__()`/`esc_html_e()` with the `cwc-carousel` text
domain, the domain MUST be loaded, and `languages/` MUST ship
`cwc-carousel.pot` + `cwc-carousel-es_ES.po`/`.mo` with neutral professional
Spanish. This change introduces NO frontend user-facing strings: cover markup
has no literal text (the overlay title is term meta `cwc_cat_title` → term
name) and `buy_text` is config data, not a translatable string.

#### Scenario: Spanish site shows translated strings

- GIVEN a site with `es_ES` locale and the `.mo` installed
- WHEN the admin editor loads
- THEN the new admin labels appear in Spanish (neutral register)

#### Scenario: English is the default

- GIVEN an English site without the `.mo`
- THEN all strings fall back to the English source strings

## Acceptance Criteria

- `cover="1"` renders 7/12 full-bleed cards: overlay, centered white title
  (`cwc_cat_title` → term name), no button; products unaffected.
- Thumbnailless terms render placeholder + title, never excluded.
- `subcategories="1"` lists direct children; empty parent → empty carousel.
- Non-cover layout unchanged; es_ES shows neutral Spanish; phpcs passes.