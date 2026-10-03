# Delta for carousel-renderer

> Modified capability. Main spec exists at
> `openspec/specs/carousel-renderer/spec.md`. ADDED block CR-11 appends.
> Amended (PR 6): CR-11 +2 side values — sides-inside class-only, sides-outside shell markup.
> Archive: append.

## ADDED Requirements

### Requirement: CR-11 — Nav-position class and nav-color CSS variables

The renderer MUST emit a scoped nav-position wrapper class when the resolved
`nav_position` differs from the default `bottom-right` (one of `bottom-left` |
`top-right` | `top-left` | `sides-inside` | `sides-outside`); the default MUST
emit today's markup with no class (byte-identical BC). `sides-inside` MUST add
the `cwc-carousel--nav-sides-inside` class to the container with no markup
change (Swiper's default absolute placement). `sides-outside` MUST wrap the
carousel in a `cwc-carousel-shell` flex wrapper and emit the prev/next buttons
as flanking siblings of the `.swiper` container — `[prev] [.swiper] [next]`;
the shell MUST carry the `cwc-carousel--nav-sides-outside` class and the
nav-color variables, while the inner `.swiper` keeps the base classes and
`data-cwc-config`. The two side values MUST emit new markup ONLY when
selected; the default and the 4 corners keep today's byte-identical wrapper
markup (BC). For each non-empty `nav_color_*` key, the renderer MUST
emit the corresponding CSS custom property on the wrapper element — the
container, or the shell for `sides-outside` —
`--cwc-nav-color-arrow`, `--cwc-nav-color-bg`, `--cwc-nav-color-border`,
`--cwc-nav-color-arrow-hover`, `--cwc-nav-color-bg-hover`,
`--cwc-nav-color-border-hover`; empty colors MUST emit no variable (theme
default, FA-3). `data-cwc-config` (CR-7) MUST carry the extended keys:
`autoplay`, `loop`, `speed`, `timeout`, `stop_on_hover`, `slides_laptop`,
`nav_position` and the six `nav_color_*` values.

#### Scenario: New keys unset renders byte-identical markup

- GIVEN a legacy instance (no new keys resolved)
- WHEN the renderer runs
- THEN the wrapper carries no nav-position class and no nav-color variables
- AND the output equals today's markup exactly

#### Scenario: Non-default position emits a class

- GIVEN `nav_position="top-left"`
- WHEN the renderer runs
- THEN the wrapper carries the top-left position class
- AND the arrows restyle per carousel.css (FA-9)

#### Scenario: Sides-inside adds a class only

- GIVEN `nav_position="sides-inside"`
- WHEN the renderer runs
- THEN the container carries the sides-inside class
- AND the nav buttons stay inside `.swiper` (no shell, markup shape unchanged)

#### Scenario: Sides-outside emits the flex shell

- GIVEN `nav_position="sides-outside"`
- WHEN the renderer runs
- THEN the output is `[swiper-button-prev] [.cwc-carousel.swiper] [swiper-button-next]` inside `cwc-carousel-shell`
- AND the shell carries the sides-outside class and any non-empty nav-color variables
- AND `data-cwc-config` stays on the inner `.swiper`

#### Scenario: Only non-empty colors emit variables

- GIVEN nav_color_arrow="#ff0000" and the other five empty
- WHEN the renderer runs
- THEN only the arrow-color variable is emitted on the container
- AND no other nav-color variable appears

## Acceptance Criteria

- Unset new keys → byte-identical markup (BC).
- Default + 4 corners → byte-identical wrapper markup (no shell) (BC).
- Non-default nav_position emits exactly one scoped class; side values emit
  new markup only when selected.
- Non-empty colors emit only their own variables; all-empty emits none.
