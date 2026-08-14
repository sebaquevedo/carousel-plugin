# Delta for admin-settings

> Modified capability. Main spec exists at
> `openspec/specs/admin-settings/spec.md`; the blocks below append (ADDED) with
> new REQ IDs AS-5..AS-8. Archive: ADDED blocks append; AS-1..AS-4 unchanged.

## ADDED Requirements

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

## Decisions / Notes

- D1: single keyed option keeps sanitize/migrate in one surface (confirmed 2, R7).
- D2: deleting an in-use name falls back to `default`; detection is confirm-only —
  render-time reference tracking is out of scope (decision from proposal risk 2).
- Reserved `default` cannot be deleted OR renamed (D3).

## Acceptance Criteria

- Registry round-trips create/edit/delete scoped per slug under `manage_woocommerce`.
- Slug sanitization + duplicate/rejection + reserved guard hold.
- Deleting an in-use name warns and, afterwards, `[cwc_carousel name=...]` falls
  back to `default` (defended in AS-7 / CM-8).
- phpcs passes (WordPress + WordPress-Extra).