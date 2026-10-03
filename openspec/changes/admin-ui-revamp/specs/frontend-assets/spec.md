# Delta for frontend-assets

> Modified capability. Main spec exists at
> `openspec/specs/frontend-assets/spec.md`. ADDED blocks FA-8..FA-9 append.
> Amended (PR 6): FA-9 +2 side-mode rule sets; color vars apply in both.
> Archive: append.

## ADDED Requirements

### Requirement: FA-8 — buildOptions gains autoplay, speed, loop, laptop breakpoint

`buildOptions()` (frontend.js) MUST map the extended config: `autoplay` true →
`autoplay: { delay: timeout, pauseOnMouseEnter: stop_on_hover }` (false or
absent → no autoplay key); `speed` → Swiper `speed`; `loop` true → `loop: true`
(false or absent → no loop key). The breakpoints MUST gain a laptop tier at
**992 px** (resolved from proposal open question 2 — Bootstrap's standard lg
breakpoint, strictly between tablet 768 and desktop 1024) WITHOUT changing the
existing tiers: 768 still resolves tablet and 1024 still resolves desktop
slides; `slides_laptop` applies only in the 992–1023 band.

#### Scenario: Autoplay/loop/speed reach Swiper

- GIVEN a config with autoplay=true, stop_on_hover=false, timeout=4000, speed=500, loop=true
- WHEN the carousel initializes
- THEN Swiper receives delay=4000, pauseOnMouseEnter=false, speed=500, loop=true

#### Scenario: Laptop tier without regressions

- GIVEN slides 4/3/2/1 (desktop/laptop/tablet/mobile)
- WHEN the viewport is 1000 px
- THEN slidesPerView is 3 (laptop tier)
- AND ≥1024 stays 4 and <768 stays 1 (today's tiers unchanged)

#### Scenario: Legacy config keeps 3-breakpoint ramp

- GIVEN a config without the new keys
- WHEN the carousel initializes
- THEN no autoplay/loop/speed keys are set
- AND the 768/1024 ramp behaves exactly as today (BC)

### Requirement: FA-9 — Corner CSS rules and admin behavior assets

`carousel.css` MUST define the four corner rules (`bottom-right` | `bottom-left`
| `top-right` | `top-left`) plus the two side modes: `sides-inside` positions
the arrows absolutely at the slider's left/right edges, vertically centered,
overlaying the slides (Swiper's default placement); `sides-outside` makes
`.cwc-carousel-shell` a flex row — `[prev] [slider] [next]` — with the arrows
as fixed-width flex items and the slider shrinking (`flex: 1; min-width: 0`)
to make room. The `--cwc-nav-color-*` variables MUST style the arrows in both
side modes: the buttons are descendants of the element carrying the variables
(container for sides-inside, shell for sides-outside), falling back to the
theme default when unset (CR-11, FA-3). `admin.js`/`admin.css` MUST
implement the tab switching, Yes/No toggle styling, device icons, helper text
and shortcode copy button used by AS-16/AS-17, enqueued through the existing
admin screen guard (FA-5).

#### Scenario: Corner rules position arrows

- GIVEN a carousel with the top-left position class
- WHEN carousel.css applies
- THEN the nav arrows render top-left
- AND no other position's rules apply

#### Scenario: Sides-inside overlays the edges

- GIVEN a carousel with the sides-inside class
- WHEN carousel.css applies
- THEN the arrows render at the left/right edges, vertically centered, over the slides
- AND the nav-color variables style them (theme default when unset)

#### Scenario: Sides-outside shrinks the slider

- GIVEN a carousel with the sides-outside shell
- WHEN carousel.css applies
- THEN the layout is [prev] [slider] [next] with the slider shrinking to fit
- AND the nav-color variables style the arrows via the shell

#### Scenario: Color variables consumed

- GIVEN a container carrying `--cwc-nav-color-arrow`
- WHEN carousel.css applies
- THEN the arrow fill uses that variable
- AND an unset variable falls back to the theme default (FA-3)

#### Scenario: Admin assets stay admin-only

- GIVEN a frontend page with a carousel
- WHEN assets are evaluated
- THEN admin.js/admin.css do not load (FA-5 guard kept)

## Decisions / Notes

- Laptop breakpoint resolved to 992 px (proposal open question 2): strictly
  between 768 and 1024, aligns with Bootstrap `lg`, and keeps tablet/desktop
  tiers at their existing px values.
- Swiper loop is only enabled when resolved `loop` is true; legacy configs get
  no loop key (no behavior change).

## Acceptance Criteria

- Extended config reaches Swiper options via buildOptions (no hardcoding).
- Laptop tier applies at 992–1023 only; existing tiers unchanged.
- Corner + side-mode rules land in carousel.css; color vars apply in all six
  positions; admin assets stay admin-screen-only.
