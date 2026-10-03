# Delta for admin-settings

> Modified capability. Main spec exists at
> `openspec/specs/admin-settings/spec.md`. ADDED blocks AS-16..AS-18 append.
> Amended (PR 6): AS-17 nav-position select +2 side options; subcategories helper text.
> Archive: append.

## ADDED Requirements

### Requirement: AS-16 — Four-tab editor over one form and one submit

The per-instance editor MUST render four tabs — Content / Behavior /
Navigation / Style — as `nav-tab` markup over ONE `<form>` and ONE submit
button (AS-7 flow kept). Tab panels MUST be pure client-side visibility
toggles in admin.js: every field MUST stay present in the DOM so the Settings
API nonce and the single submit never depend on JS. Conditional rows stay in
Content: the type=product products picker (AS-12) and the type=category cover
field render only inside Content.

#### Scenario: Tabs render under one form

- GIVEN an authorized user opens the editor
- WHEN it renders
- THEN the four tabs and one submit button appear
- AND all tab panels' fields exist in the DOM regardless of the active tab

#### Scenario: Conditional rows stay in Content

- GIVEN a type=category instance
- WHEN the editor renders
- THEN the cover field appears in Content only
- AND the products picker is absent (AS-12 kept)

#### Scenario: JS-free submit works

- GIVEN JavaScript disabled in the browser
- WHEN the user submits
- THEN every tab's fields post under the single nonce
- AND the save succeeds as today

### Requirement: AS-17 — New field renderers

The editor MUST render: Yes/No toggle switches (hidden `0` + visible `1`
posting under the registry key, reusing `parse_bool` on save) replacing native
checkboxes; a per-device slides picker of four number inputs (desktop / laptop
/ tablet / mobile with device icons) binding `slides` / `slides_laptop` /
`slides_tablet` / `slides_mobile`, with a helper text under the picker reading
exactly "Screen sizes: Mobile < 768px, Tablet ≥ 768px, Laptop 992–1023px,
Desktop ≥ 1024px." (must match the frontend.js tiers: base <768, 768, 992,
1024) (amended PR 6); a nav-position select of the 6 positions —
the 4 corners plus `sides-inside` and `sides-outside`, labeled "Bottom right" /
"Bottom left" / "Top right" / "Top left" / "Sides (inside)" / "Sides (outside)"
(amended PR 6); six `input[type=color]` + hex text pickers for the `nav_color_*`
keys; helper text under every number input; a read-only copyable shortcode
field in the editor header rendering `[cwc_carousel name="{slug}"]` (edit
mode); and a subcategories toggle whose helper text reads exactly "Lists child
terms of the parent category and ignores the selected categories."
(cwc-carousel text domain; es_ES: "Lista los términos hijos de la categoría
principal e ignora las categorías seleccionadas.").

The type-conditional rows (products picker, cover mode) MUST render in the DOM
unconditionally, hidden per the SAVED type via a `hidden` attribute and a
`data-cwc-type-row="product"|"category"` marker, and admin.js MUST toggle
their visibility when the type select changes — so Cover mode appears
instantly when switching to category without saving first; without JS the
saved-type visibility is unchanged (amended PR 6).

#### Scenario: Toggles post 0/1

- GIVEN a user sets autoplay to Yes and loop to No
- WHEN the registry sanitizer runs
- THEN stored autoplay=1 and loop=0
- AND both re-render as Yes/No respectively (parse_bool round-trip)

#### Scenario: Device picker saves all four ramps

- GIVEN desktop=4 laptop=3 tablet=2 mobile=1
- WHEN the editor saves
- THEN slides=4 slides_laptop=3 slides_tablet=2 slides_mobile=1

#### Scenario: Colors and shortcode field

- GIVEN nav_color_arrow="#ff0000" and the remaining five colors empty
- WHEN the editor saves and re-renders
- THEN the hex value persists and empty colors stay empty
- AND the header shows the copyable `[cwc_carousel name="{slug}"]`

#### Scenario: Side positions and subcategories helper

- GIVEN an authorized user opens the editor
- WHEN the Navigation tab renders and Content shows the subcategories toggle
- THEN the nav-position select offers "Sides (inside)" and "Sides (outside)"
- AND the subcategories toggle shows the exact helper text underneath
- AND both strings resolve through the cwc-carousel text domain (es_ES)

### Requirement: AS-18 — Validation re-fill preserves values across tabs

A rejected save (edit or create) MUST re-render with every tab's values
preserved. The create-mode `__new__` coercion map (AS-7 re-fill) MUST extend
with `slides_laptop` (1–12), `timeout` (1000–60000) and `speed` (100–5000),
clamping exactly as `bound()` clamps on save (CM-13), so the re-rendered form
shows precisely what a successful save would persist.

#### Scenario: Rejected create re-fills all tabs

- GIVEN a create submission rejected for a duplicate slug
- WHEN the editor re-renders
- THEN the Behavior / Navigation / Style values posted remain selected
- AND slides_laptop / timeout / speed display at their clamped values

## Acceptance Criteria

- Four tabs, one form, one submit; JS disabled still saves (no nonce break).
- Toggles persist 0/1; device picker persists all four slide ramps.
- Rejected submissions preserve values across every tab.
- Nav-position select offers all 6 values; subcategories helper text is exact
  and localized (es_ES).
