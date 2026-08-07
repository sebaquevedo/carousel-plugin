# Delta for admin-settings

> New capability. Main spec pre-created at `openspec/specs/admin-settings/spec.md`
> per sdd-spec Step 2 (new capabilities → full spec). Archive: the ADDED blocks
> below are the full requirement set for this change; match by name against the
> main spec (idempotent).

## ADDED Requirements

### Requirement: AS-1 — Settings API page under WooCommerce

The plugin MUST register a "Carousel" submenu page under the WooCommerce menu,
gated by the `manage_woocommerce` capability, using the Settings API
(`register_setting` + `add_settings_section`/`add_settings_field`).

#### Scenario: Capability-gated access

- GIVEN a user without `manage_woocommerce`
- WHEN they request the page
- THEN the page is not rendered and WP denies access

#### Scenario: Page renders global defaults

- GIVEN an authorized user opens the page
- WHEN the page renders
- THEN fields for type, slides, gap, categories, count, buy toggle/text are shown
- AND current saved values are pre-filled

### Requirement: AS-2 — One sanitized option, autoload off

All settings MUST be stored in a single `cwc_carousel_options` array, sanitized
in one callback, with autoload disabled; rollback is `delete_option`.

#### Scenario: Single sanitize pass

- GIVEN a form submission
- WHEN the sanitize callback runs
- THEN every field is sanitized into one options array
- AND the stored array contains no unsanitized values

#### Scenario: Autoload disabled and rollback

- GIVEN the option is saved
- WHEN the site loads
- THEN the option is not loaded into the autoload cache
- AND `delete_option( 'cwc_carousel_options' )` fully resets to defaults

### Requirement: AS-3 — Per-category custom image override

For each chosen `product_cat` term, the panel MUST allow uploading a custom image
stored as an attachment id in term meta; category carousels MUST use that image
over the WC category `thumbnail_id` when present.

#### Scenario: Upload overrides thumbnail

- GIVEN a term with both `thumbnail_id` and a custom upload
- WHEN the category card renders
- THEN the custom image is shown

#### Scenario: No custom image

- GIVEN a term with no custom upload
- WHEN the category card renders
- THEN the WC category thumbnail is used
- AND a placeholder is rendered when no thumbnail exists either