# Delta for carousel-renderer

> Modified capability. Main spec exists at
> `openspec/specs/carousel-renderer/spec.md`; CR-8..CR-10 append (ADDED). Archive:
> ADDED blocks append; CR-1..CR-7 unchanged.

## ADDED Requirements

### Requirement: CR-8 — Header title alignment modifier

When the resolved `title_align` is `center` or `right`, the renderer MUST add a
scoped alignment modifier class to the carousel title element (CR-4's h2); when
`left` (default) no alignment class is needed. The modifier MUST be scoped to
the carousel title so it never leaks to other page headings.

#### Scenario: Centered header

- GIVEN a carousel with `title_align="center"` and a non-empty `title`
- WHEN rendered
- THEN the title h2 carries the center-alignment modifier class
- AND no other page heading is affected

#### Scenario: Left is the default

- GIVEN `title_align` resolves to `left` (default)
- WHEN rendered
- THEN the title h2 renders without an alignment modifier
- AND output matches today's markup

#### Scenario: No title, no element

- GIVEN an empty `title`
- WHEN rendered
- THEN no title element is output regardless of `title_align` (CR-4 kept)

### Requirement: CR-9 — Cover branch in the category card

For `type=category` with `cover` true, `render_category_card()` MUST emit the
cover layout: the whole card links to the archive (`get_term_link`, escaped),
the image (`cwc_cat_image` → `thumbnail_id`) is full-bleed, a single-opacity
overlay and a centered white title render on top, and NO button or text renders
below. The overlay title MUST be term meta `cwc_cat_title` when non-empty,
otherwise the escaped term name. With `cover` false, today's thumbnail + name
layout MUST be produced unchanged.

#### Scenario: Cover card with custom title

- GIVEN a term with image, `cover` true, and `cwc_cat_title` = "Verano"
- WHEN the category card renders
- THEN the card links to the archive, shows image + overlay, and white centered "Verano"
- AND no button/caption renders below

#### Scenario: Term-meta fallback to term name

- GIVEN a term with image and empty `cwc_cat_title`
- WHEN the cover card renders
- THEN the centered title is the escaped term name

#### Scenario: Cover off keeps current layout

- GIVEN `cover` false
- WHEN the category card renders
- THEN the current image + name-below card is produced (BC)

#### Scenario: Thumbnailless cover card

- GIVEN a term with no image and `cover` true
- WHEN the category card renders
- THEN a placeholder background card renders with the title on top
- AND the term is never excluded from the slide set

### Requirement: CR-10 — Product-card full category-path breadcrumb

When rendering a product card, the category line MUST show the product's FULL
category path from the taxonomy root to its most-specific term, independent of
the carousel's `category` config. A product in the chain `Root › Child › Leaf`
MUST render all three levels; a product assigned directly to a root term MUST
render that term alone. Depth MUST be dynamic — every level of the chain
renders, with no fixed 2-level cap. When a product is assigned to multiple
branches, the DEEPEST path (most levels) MUST be shown; on a depth tie, the
path of the term whose NAME sorts first MUST win — the name-ASC input order
from `wc_get_product_term_ids`, preserved by `get_terms(orderby=include)`.
Resolution MUST NOT depend on the carousel's
`category` (scoped, absent, or unrelated) — recent/mixed carousels and products
outside the scoped branch still show their own full path. Every segment MUST be
escaped with `esc_html` and the separator MUST be a safe static delimiter
(e.g. `›`); raw or untrusted HTML MUST never be emitted.

#### Scenario: Single chain renders the full path

- GIVEN a product assigned to the chain `Root › Child › Leaf`
- WHEN the product card renders
- THEN the category line shows `Root › Child › Leaf`

#### Scenario: Root-only assignment

- GIVEN a product assigned directly to root term `Root`
- WHEN the product card renders
- THEN the category line shows only `Root`

#### Scenario: Depth is dynamic

- GIVEN a product in the four-level chain `A › B › C › D`
- WHEN the product card renders
- THEN all four levels render as `A › B › C › D`
- AND no fixed level cap truncates the path

#### Scenario: Multi-branch picks the deepest path

- GIVEN a product assigned to `A › B` (2 levels) and `A › B › C › D` (4 levels)
- WHEN the product card renders
- THEN the deepest path `A › B › C › D` is shown

#### Scenario: Depth tie resolved by name order

- GIVEN a product assigned to `A › B` and `X › Y` (equal depth)
- WHEN the product card renders
- THEN the path whose term name sorts first is shown (`A › B` — `A` sorts before `X`)

#### Scenario: No scoped category

- GIVEN a recent-products carousel with no `category` scoping
- WHEN the product card renders
- THEN the category line still shows the product's full root-to-leaf path

#### Scenario: Different branch than the scoped category

- GIVEN a carousel scoped to `category` X and a product whose chain is `Y › Z` (unrelated to X)
- WHEN the product card renders
- THEN the category line shows the product's own `Y › Z`
- AND no path is anchored to or fabricated through X

#### Scenario: Output is escaped

- GIVEN term names containing markup or special characters
- WHEN the category line renders
- THEN every segment is escaped with `esc_html`
- AND the separator is the static `›` delimiter, never raw or untrusted HTML

## Decisions / Notes

- The breadcrumb is the product's absolute root-to-leaf path, independent of
  the carousel's `category`; deepest path wins, on a depth tie the term whose
  NAME sorts first wins (name-ASC via `wc_get_product_term_ids` +
  `get_terms(orderby=include)`) (proposal Open Decision).
- The `title_align` modifier rides on the existing h2 (CR-4); the single escape
  point is unchanged — the shortcode passes the sanitized title, the renderer
  escapes once.
- Cover branch reuses the existing `cwc_cat_image` → `thumbnail_id` fallback
  chain (CR-5); a missing/deleted attachment falls back to the placeholder.

## Acceptance Criteria

- `title_align` center/right apply a scoped modifier; left and no-title behave
  as today.
- `cover="1"` renders full-bleed overlay cards with `cwc_cat_title` → term name,
  no button; non-cover category cards unchanged.
- Thumbnailless cover terms render placeholder + title; phpcs passes.
- Product cards show the full root-to-leaf category path, independent of the
  carousel's `category`; deepest path wins, on a tie the name-ASC first term
  wins (deterministic via `wc_get_product_term_ids` + `get_terms(orderby=include)`);
  all segments escaped (`esc_html`), static `›` separator.