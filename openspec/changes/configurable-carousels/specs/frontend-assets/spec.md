# Delta for frontend-assets

> Modified capability. Main spec already exists at
> `openspec/specs/frontend-assets/spec.md`; the blocks below are already merged
> there (idempotent). Archive: ADDED blocks append; FA-1..FA-3 remain unchanged.

## ADDED Requirements

### Requirement: FA-4 — Dynamic Swiper options from config

`frontend.js` MUST read each container's `data-cwc-config` JSON and build Swiper
options from it: slides desktop/tablet/mobile, breakpoints, and gap — replacing
the hardcoded 1/2/3 ramp. It MUST keep `resizeObserver: false`.

#### Scenario: Config-driven slides

- GIVEN a category carousel with 4 desktop slides
- WHEN it initializes
- THEN Swiper uses 4 slides on desktop from its container config
- AND `resizeObserver` stays false

#### Scenario: Two setups on one page

- GIVEN two carousels with different `data-cwc-config` values
- WHEN both initialize
- THEN each uses its own slides/breakpoints/gap

### Requirement: FA-5 — Admin assets only on the admin page

Admin styles/scripts MUST be enqueued only on the plugin's admin settings page,
using a screen guard (capability `manage_woocommerce`), and MUST NOT load on the
frontend or other admin screens.

#### Scenario: Admin page loads admin assets

- GIVEN an authorized user views the Carousel settings page
- WHEN `admin_enqueue_scripts` runs
- THEN the admin enqueue happens only for that screen

#### Scenario: Admin assets never on frontend

- GIVEN a carousel renders on a frontend page
- WHEN assets are evaluated
- THEN no admin styles/scripts appear in the frontend markup