# Delta for frontend-assets

## MODIFIED Requirements

### Requirement: FA-5 — Admin assets only on the admin page

Admin styles/scripts MUST be enqueued only on the plugin's admin settings page, using a screen guard (capability `manage_woocommerce`), and MUST NOT load on the frontend or other admin screens. On that page the enqueue MUST include `wc-enhanced-select` (JS) and `woocommerce_admin_styles` (CSS) for Select2 chrome, `jquery-ui-sortable` (WP core) for chip drag & drop, the plugin `admin.css`, and a `wp_localize_script` object supplying `admin.js` its translatable strings.
(Previously: the admin enqueue shipped only `wp_enqueue_media()` + `cwc-carousel-admin` with a `jquery` dependency.)

#### Scenario: Admin page loads admin assets

- GIVEN an authorized user views the Carousel settings page
- WHEN `admin_enqueue_scripts` runs
- THEN the admin enqueue happens only for that screen
- AND `wc-enhanced-select`, `woocommerce_admin_styles`, `jquery-ui-sortable` and `admin.css` are present

#### Scenario: Admin assets never on frontend

- GIVEN a carousel renders on a frontend page
- WHEN assets are evaluated
- THEN no admin styles/scripts appear in the frontend markup

#### Scenario: JS strings localized

- GIVEN the admin page loads
- WHEN `admin.js` initializes
- THEN the localized strings object is registered with the admin script
- AND the media-uploader strings come from that object, not hardcoded literals