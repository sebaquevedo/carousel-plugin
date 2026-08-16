# Delta for frontend-assets

> Modified capability. Main spec exists at
> `openspec/specs/frontend-assets/spec.md`; FA-6..FA-7 append (ADDED). Archive:
> ADDED blocks append; FA-1..FA-5 unchanged.

## ADDED Requirements

### Requirement: FA-6 — Cover-mode CSS tokens and header alignment

The carousel CSS MUST define cover-mode styling as custom properties on the
container: `--cwc-cover-overlay-opacity` (single overlay opacity, defaulted in
CSS) and a 7/12 `aspect-ratio` on cover cards, plus scoped alignment classes
(`center`/`right`, default `left`) for `.cwc-carousel__title`. The 7/12 and
overlay styles MUST apply only to cover-mode category cards so non-cover
carousels and product cards keep today's look.

#### Scenario: Cover tokens available

- GIVEN a rendered cover carousel
- WHEN the CSS applies
- THEN cover cards use `aspect-ratio: 7 / 12`
- AND the overlay uses `--cwc-cover-overlay-opacity`
- AND a theme can override both without re-compiling assets (FA-3)

#### Scenario: Non-cover untouched

- GIVEN a non-cover category carousel or a product carousel
- WHEN the CSS applies
- THEN the cover aspect-ratio and overlay styles do not apply
- AND today's layout is unchanged

#### Scenario: Header alignment scoped

- GIVEN a carousel with `title_align="center"`
- WHEN the title renders
- THEN the centered alignment class applies to `.cwc-carousel__title` only
- AND no other page headings are affected

### Requirement: FA-7 — Breadcrumb single-line truncation

The product-card category breadcrumb (CR-10) MUST render on a single line with
`text-overflow: ellipsis` so long paths truncate visually WITHOUT dropping
data: the full root-to-leaf path MUST remain present in the markup (a `title`
attribute with the full path MAY be added). Truncation MUST NOT cause layout
shift or horizontal overflow of the card.

#### Scenario: Long path truncates with ellipsis

- GIVEN a product whose full category path exceeds the card width
- WHEN the product card renders
- THEN the breadcrumb stays on one line and ends with an ellipsis
- AND the card does not overflow or shift layout

#### Scenario: Full path stays in the markup

- GIVEN a truncated breadcrumb label
- WHEN the card's DOM is inspected
- THEN the complete root-to-leaf path text is still present
- AND a `title` attribute with the full path MAY be present

#### Scenario: Short path unaffected

- GIVEN a category path that fits within the card width
- WHEN the product card renders
- THEN the full path displays on one line without truncation

## Decisions / Notes

- Breadcrumb truncation is presentation-only: `text-overflow: ellipsis` hides
  the tail visually, never removes the path from the DOM (proposal Risk:
  long breadcrumbs overflow — Low).

- Overlay opacity is a single value exposed as `--cwc-cover-overlay-opacity`;
  per-card gradient/overlay config params are out of scope (proposal).
- Gap between cover cards reuses the existing `--cwc-gap` token (range 8-64).

## Acceptance Criteria

- 7/12 and overlay styles apply only to cover cards; header alignment is
  scoped to the carousel title; non-cover output unchanged.
- Breadcrumbs truncate on one line with ellipsis, no overflow/layout shift;
  the full path stays in the DOM (title attribute optional).