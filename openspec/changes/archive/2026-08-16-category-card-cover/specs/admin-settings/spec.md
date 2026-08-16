# Delta for admin-settings

> Modified capability. Main spec exists at
> `openspec/specs/admin-settings/spec.md`; AS-9/AS-10 append (ADDED). Archive:
> ADDED blocks append; AS-1..AS-8 unchanged.

## ADDED Requirements

### Requirement: AS-9 — Editor fields for cover, subcategories, title_align

The per-instance editor MUST expose a `cover` checkbox (category carousels
only), a `subcategories` checkbox, and a `title_align` select
(`center`|`left`|`right`) alongside the existing fields. The sanitizer MUST
coerce them like the rest of the contract (bools via `parse_bool`,
`title_align` restricted to the enum, default `left`).

#### Scenario: Cover shown for category carousels

- GIVEN an authorized user edits a category instance
- WHEN the editor renders
- THEN a cover checkbox, a subcategories checkbox, and a title_align select are shown
- AND saved values are pre-filled

#### Scenario: Save round-trips the new fields

- GIVEN the user checks `cover`, leaves `subcategories` unchecked, and picks `center`
- WHEN the registry sanitizer runs
- THEN the stored instance has cover=true, subcategories=false, title_align=`center`

#### Scenario: Invalid title_align rejected

- GIVEN a crafted submission with `title_align="diagonal"`
- WHEN the sanitizer runs
- THEN `title_align` coerces to `left`
- AND the save succeeds with no fatal error

### Requirement: AS-10 — Per-category overlay title term meta

Alongside the `cwc_cat_image` field (AS-3), the category-images group MUST
render a per-category text input stored as `cwc_cat_title` term meta
(`update_term_meta`/`delete_term_meta`), sanitized with `sanitize_text_field`.
Saving MUST keep the existing capability + nonce guard of
`save_category_images()`. The renderer reads this meta for the cover overlay
title (CR-9).

#### Scenario: Title saved per term

- GIVEN an authorized user enters "Verano" for term 7
- WHEN the category-images save handler runs
- THEN term meta `cwc_cat_title` for term 7 stores "Verano"
- AND the value appears pre-filled on the next edit

#### Scenario: Empty title clears the meta

- GIVEN a previously saved `cwc_cat_title` and the user clears the field
- WHEN the save handler runs
- THEN the term meta is deleted
- AND the renderer falls back to the term name (CR-9)

#### Scenario: Capability gate holds

- GIVEN a user without `manage_woocommerce`
- WHEN they submit the form
- THEN no term meta is written (guard mirrors AS-3)

## Decisions / Notes

- Term-title saves ride the existing `save_category_images()` admin_init path
  (same nonce/capability pattern as `cwc_cat_image`, D7), so term side-effects
  never leak into the option sanitizer.
- New editor strings ship translated in `languages/` (CCC-6).

## Acceptance Criteria

- Editor round-trips cover/subcategories/title_align under `manage_woocommerce`.
- Per-category `cwc_cat_title` saves, clears, and pre-fills; unauthorized writes
  are rejected.
- phpcs passes (WordPress + WordPress-Extra).