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

### Requirement: CM-7 — Registry read with empty-map fallback

`CWC_Settings` MUST read the `cwc_carousel_registry` option (keyed
`{ slug => full_config }`, autoload off) and return it as an instance map. A
missing or non-array option MUST read as an empty map, and reading MUST never
write/create the option (mirrors CM-1's read-only guarantee).

#### Scenario: Registry absent reads empty

- GIVEN no `cwc_carousel_registry` option
- WHEN `CWC_Settings` reads the registry
- THEN an empty map is returned
- AND no option is created

#### Scenario: Non-array stored value

- GIVEN the registry value is a non-array (e.g. a string)
- WHEN read
- THEN it is treated as an empty map without error

### Requirement: CM-8 — Resolve-by-name with `default` BC fallback

`CWC_Settings::resolve($atts)` MUST turn the instance config into the defaults
base. An absent/empty `name` MUST use the reserved `default` instance; a supplied
`name` not present in the registry MUST fall back to `default` (never fatal). The
existing empty-string filter + `$known` whitelist then layer atts over the base, so
an explicit attribute wins over the instance config (CM-2 order preserved).

#### Scenario: No name resolves today's behavior

- GIVEN `resolve(['count' => ''])` with a `default` instance seeded from legacy
- WHEN resolution runs
- THEN the `default` instance is the base
- AND the omitted count falls back to the instance's stored value (BC)

#### Scenario: Unknown name falls back to default

- GIVEN `name = "missing"` with no such registry key
- WHEN `resolve()` runs
- THEN the `default` instance is used as the base
- AND resolution succeeds without error

#### Scenario: Named base + attribute override

- GIVEN a `productos` instance with count 8 and `resolve(['name' => 'productos', 'count' => '4'])`
- WHEN resolution runs
- THEN the base is `productos` and resolved count is 4
- AND all non-overridden keys come from the `productos` instance

### Requirement: CM-9 — Per-instance 17-key contract preserved

Each resolved instance MUST expose exactly the 17-key config contract
(`type`, `title`, `category`, `categories`, `mix`, `count`, `slides`,
`slides_tablet`, `slides_mobile`, `gap`, `arrows`, `pagination`, `buy`,
`buy_text`, `cover`, `subcategories`, `title_align`) via `normalize()`. The
instance base MUST merge over `builtins()` so any absent per-instance key fills
its builtin and booleans stay typed (`parse_bool`).

#### Scenario: Partial instance fills from builtins

- GIVEN an instance config omitting `buy`/`gap`
- WHEN resolved
- THEN `buy` and `gap` carry the builtin values
- AND every config serializes through `wp_json_encode` with all 17 keys (CM-4)

#### Scenario: New keys present on legacy instances

- GIVEN a stored instance saved before this change (no new keys)
- WHEN resolved
- THEN `cover` and `subcategories` resolve false and `title_align` resolves `left`
- AND the instance renders identically to before (BC)

### Requirement: CM-10 — Seeds define exact instance defaults

The two seeds MUST define these defaults (only a *defaults source* — editable; the
registry is never auto-overwritten once present):

- `productos`: type `product`, slides `3/2/1`, gap `16`, count `8`, arrows +
  pagination `true`, buy `true`, buy_text builtin (`Comprar`), category `0`,
  categories `[]`, mix `false`, title `''`.
- `categorias`: type `category`, slides `4/2/1`, gap `16`, count `8`, arrows +
  pagination `true`, category `0`, categories `[]`, mix `false`, title `''`.
  (buy/buy_text carry builtins — unused for category type.)

#### Scenario: Seed drives instance resolution

- GIVEN a freshly seeded registry
- WHEN `resolve(['name' => 'productos'])` runs
- THEN base resolves to 3/2/1 slides with arrows+pagination ON

### Requirement: CM-11 — Cover-mode config keys

`builtins()`, `normalize()` and the `$known` whitelist MUST include `cover`
(bool, default false), `subcategories` (bool, default false) and `title_align`
(enum `center`|`left`|`right`, default `left`). An invalid `title_align` value
MUST coerce to `left`; an empty-string shortcode attribute MUST fall through to
the instance/builtin default.

#### Scenario: Defaults when absent

- GIVEN a config with none of the three keys
- WHEN `normalize()` runs
- THEN `cover`=false, `subcategories`=false, `title_align`=`left`

#### Scenario: Explicit values resolve

- GIVEN `[cwc_carousel cover="1" subcategories="1" title_align="center"]`
- WHEN `resolve()` runs
- THEN the resolved config carries cover=true, subcategories=true, title_align=`center`

#### Scenario: Invalid title_align coerced

- GIVEN `title_align="diagonal"`
- WHEN `normalize()` runs
- THEN `title_align` coerces to `left`
- AND resolution succeeds without error
