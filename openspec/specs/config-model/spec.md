# config-model Specification

## Purpose

`CWC_Settings` is the pure configuration model for carousels: it owns the global
defaults (from the admin panel) and resolves per-shortcode instances by merging
admin defaults → explicit shortcode attributes. The resolved config is a plain
array shared by the query builder, the renderer, and the container's `data-`
attribute. Out of scope: the admin UI itself (admin-settings), storage format
(one `cwc_carousel_options` option), named presets/CPT.

## Requirements

### Requirement: CM-1 — Global defaults with fallback

`CWC_Settings::defaults()` MUST return the global default config (type, slides
desktop/tablet/mobile, gap, categories, count, buy toggle/text), reading the
sanitized `cwc_carousel_options` option and falling back to built-in defaults
when the option is absent or partial.

#### Scenario: Option absent

- GIVEN no `cwc_carousel_options` option exists
- WHEN `defaults()` runs
- THEN built-in defaults are returned (type=product, slides 3/2/1, gap 16, count 8)
- AND no option is created (read-only)

#### Scenario: Partial option merged over built-ins

- GIVEN the option contains only `slides`
- WHEN `defaults()` runs
- THEN that value is merged over the built-in defaults
- AND every other key keeps its built-in default

### Requirement: CM-2 — Resolve merges admin defaults → explicit atts

`CWC_Settings::resolve($atts)` MUST merge global defaults with explicit shortcode
attributes so an explicitly supplied attribute overrides the admin default, and
MUST drop any attribute outside the known config key set.

#### Scenario: Explicit override

- GIVEN admin default `count` is 8 and `[cwc_carousel count="6"]`
- WHEN `resolve($atts)` runs
- THEN the resolved config has count=6
- AND other keys retain the admin defaults

#### Scenario: Unknown attribute dropped

- GIVEN `[cwc_carousel unknown="x"]`
- WHEN `resolve($atts)` runs
- THEN the resolved config contains no `unknown` key
- AND resolution succeeds without error

### Requirement: CM-3 — Numeric-only count

The resolved `count` MUST be a non-negative integer (coerced via `absint`);
`count=0` MUST yield an empty carousel, and there MUST be no "all" sentinel.

#### Scenario: Zero count

- GIVEN a resolved count of 0
- WHEN the config is handed to the query
- THEN an empty result is produced

#### Scenario: Non-numeric count coerced

- GIVEN a count attribute of `"abc"`
- WHEN `resolve()` runs
- THEN count coerces to 0 and the carousel renders empty
- AND no "all" path exists

### Requirement: CM-4 — Serializable config contract

The resolved config MUST expose a plain-array shape serializable with
`wp_json_encode` into one per-container JSON attribute consumed by
`frontend.js`, without side effects.

#### Scenario: Serialized shape

- GIVEN a resolved config
- WHEN serialized with `wp_json_encode`
- THEN the JSON contains only known config keys
- AND it round-trips to the same values
