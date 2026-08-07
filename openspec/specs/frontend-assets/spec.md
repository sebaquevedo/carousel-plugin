# frontend-assets Specification

## Purpose

Vendors a pinned Swiper bundle locally and enqueues all frontend assets only where
a carousel actually renders — never on every page. Fully self-contained: no CDN, no
runtime Node. Out of scope this slice: block view scripts (slice 4), AJAX-cart JS
(slice 5).

## Requirements

### Requirement: FA-1 — Locally vendored Swiper (pinned)

The Swiper v14.0.7 compiled bundle MUST be committed under
`assets/vendor/swiper/` and MUST NOT be fetched from a CDN.

#### Scenario: Self-contained install

- GIVEN the plugin ZIP is installed on a fresh site
- WHEN the frontend requests Swiper
- THEN the JS and CSS resolve from the local vendored bundle
- AND no external CDN or runtime Node/build step is required

#### Scenario: Version pinning

- GIVEN the Swiper bundle is present
- WHEN the asset handle is registered
- THEN its version argument pins `14.0.7` to prevent silent updates

### Requirement: FA-2 — On-demand enqueue

Swiper and plugin CSS MUST be enqueued only on pages where a carousel renders,
using a content pre-scan (`has_shortcode`) and/or a render flag.

#### Scenario: Page with carousel shortcode

- GIVEN post content contains `[cwc_carousel]`
- WHEN `wp_enqueue_scripts` runs
- THEN Swiper JS/CSS and carousel CSS are enqueued on that page

#### Scenario: Page without carousel (DOM proof)

- GIVEN a page whose content has no carousel
- WHEN the page loads
- THEN no Swiper or carousel CSS `<link>`/`<script>` tags appear in the DOM

#### Scenario: Render-flag fallback for missed paths

- GIVEN a carousel renders through a path the content pre-scan misses (e.g. a widget or nested shortcode)
- WHEN the footer assets hook fires
- THEN Swiper and carousel assets are enqueued late and still present in the DOM

### Requirement: FA-3 — Modular CSS with custom properties

The plugin CSS MUST define theme-able values (colors, borders, typography,
gap) as CSS custom properties on the carousel container.

#### Scenario: Theme overrides available

- GIVEN the carousel renders
- WHEN a theme or later slice overrides a custom property
- THEN the override applies without re-compiling assets

#### Scenario: No carousel, no CSS bloat

- GIVEN no carousel is present on the page
- WHEN styles are evaluated
- THEN carousel CSS is absent, keeping the page lightweight

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