# admin-settings Specification

## Purpose

A single Settings API admin page ("Carousel" submenu under WooCommerce) exposes
the global defaults stored as one sanitized `cwc_carousel_options` array
(autoload off). Includes the per-category custom image upload that overrides the
WC category thumbnail in category carousels. Out of scope: presets/CPT, frontend
enqueues (frontend-assets), config resolution (config-model).

## Requirements

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

### Requirement: AS-5 — Registry option with per-slug sanitizer

The plugin MUST store named carousels in one registered keyed array
`cwc_carousel_registry` (`{ slug => full_config }`, autoload off). Its sanitize
callback MUST run per slug, reusing today's field bounds (type within
{product,category}, slides 1-12, gap 8-64, count ≥ 0, bools via `parse_bool`,
`buy_text` via `clean_text`), scoped as `cwc_carousel_registry[slug][key]`. The
page and all saves MUST stay gated by `manage_woocommerce` (AS-1).

#### Scenario: Save scoped per slug

- GIVEN an authorized user edits `productos`
- WHEN the registry sanitizer runs
- THEN only `registry['productos']` is replaced, with every value coerced
- AND other slugs are untouched

#### Scenario: Capability gate holds

- GIVEN a user without `manage_woocommerce`
- WHEN they submit the editor
- THEN the save is rejected and no registry key is written

### Requirement: AS-6 — List view of instances

The Carousel page MUST list every registered instance (slug, type, slides,
controls state) with create/edit/delete controls. The reserved `default` instance
MUST appear in the list but MUST NOT be deletable or renamable.

#### Scenario: Seeds render in the list

- **GIVEN** a migrated registry with `productos`/`categorias`/`default`
- WHEN the page renders
- THEN each is shown
- AND `default` carries no delete/rename action

### Requirement: AS-7 — Per-instance editor (create / edit / delete)

The editor MUST support creating a new instance from a slug + full field set,
editing an existing instance's fields, and deleting an instance. Delete MUST
present a confirmation warning that pages referencing the removed slug will fall
back to `default`; render-time reference tracking is out of scope (decision D2).
The reserved `default` MUST NOT be deleted or renamed.

#### Scenario: Create round-trips

- **GIVEN** an authorized user on Create with a valid slug and fields
- THEN a new registry slug is stored and shown in the list

#### Scenario: Delete warns and removes the slug

- **GIVEN** the user deletes `myhero`
- WHEN confirmed
- THEN `myhero` is removed from the registry
- AND the confirmation warned that referencing pages render `default` (never fatal)
- AND `default` itself cannot be deleted

### Requirement: AS-8 — Slug sanitization, uniqueness, reserved name

New/renamed slugs MUST be produced via `sanitize_title`/`sanitize_key` allowing
only lowercase Latin letters, digits and hyphens. A duplicate slug MUST be
rejected with a clear admin error (never overwrites). The `default` slug MUST be
reserved: it cannot be used for a new/edited name, nor deleted, nor renamed.

#### Scenario: Mixed input sanitized

- **GIVEN** a name `"Productos!"`
- WHEN created
- THEN the slug becomes `productos` (sanitized) and is stored once

#### Scenario: Duplicate and reserved rejected

- **GIVEN** an existing `productos` and a create for the same slug
- THEN a clear admin error is shown
- AND no existing instance is overwritten
- AND `default` is never offered as a writable slug
