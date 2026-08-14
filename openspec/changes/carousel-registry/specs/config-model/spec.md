# Delta for config-model

> Modified capability. Main spec exists at
> `openspec/specs/config-model/spec.md`; the blocks below append (ADDED) with
> new REQ IDs CM-7..CM-10. Archive: ADDED blocks append; CM-1..CM-6 unchanged.

## ADDED Requirements

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

### Requirement: CM-9 — Per-instance 14-key contract preserved

Each resolved instance MUST expose exactly the 14-key config contract
(`type`, `title`, `category`, `categories`, `mix`, `count`, `slides`,
`slides_tablet`, `slides_mobile`, `gap`, `arrows`, `pagination`, `buy`,
`buy_text`) via `normalize()`. The instance base MUST merge over `builtins()` so
any absent per-instance key fills its builtin and booleans stay typed (`parse_bool`).

#### Scenario: Partial instance fills from builtins

- GIVEN an instance config omitting `buy`/`gap`
- WHEN resolved
- THEN `buy` and `gap` carry the builtin values
- AND every config serializes through `wp_json_encode` with all 14 keys (CM-4)

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

## Decisions / Notes

- `name` travels through `$known`? No — `name` is consumed by `resolve()` to pick
  the base, and is NOT part of the serialized 14-key contract (CM-9).
- Att-vs-instance precedence keeps CM-2 semantics: explicit att > instance base >
  builtin.

## Acceptance Criteria

- No-name shortcode resolves the `default` instance with today's output.
- `resolve()` by name picks the instance config; unknown names and no name never
  fail and fall back to `default`.
- Every resolved instance carries all 14 keys and serializes via CM-4.
- Seeds provide exact 3/2/1 (productos) and 4/2/1 (categorias) slide ramps.