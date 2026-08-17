# Delta for config-model

## MODIFIED Requirements

### Requirement: CM-9 — Per-instance 18-key contract preserved

Each resolved instance MUST expose exactly the 18-key config contract (`type`, `title`, `category`, `categories`, `mix`, `count`, `slides`, `slides_tablet`, `slides_mobile`, `gap`, `arrows`, `pagination`, `buy`, `buy_text`, `cover`, `subcategories`, `title_align`, `products`) via `normalize()`. `products` MUST default to `[]` in `builtins()`, MUST be coerced in `normalize()` via `sanitize_ids()` (absint, values ≤ 0 dropped), and MUST be listed in the `$known` whitelist. The instance base MUST merge over `builtins()` so any absent per-instance key fills its builtin and booleans stay typed (`parse_bool`).
(Previously: a 17-key contract with no `products` key.)

#### Scenario: Partial instance fills from builtins

- GIVEN an instance config omitting `buy`/`gap`/`products`
- WHEN resolved
- THEN `buy`, `gap` and `products` carry the builtin values (`products` → `[]`)
- AND every config serializes through `wp_json_encode` with all 18 keys (CM-4)

#### Scenario: New keys present on legacy instances

- GIVEN a stored instance saved before this change (no new keys)
- WHEN resolved
- THEN `cover` and `subcategories` resolve false, `title_align` resolves `left`, and `products` resolves `[]`
- AND the instance renders identically to before (BC)

#### Scenario: Malformed products coerced

- GIVEN stored `products = ["0", "abc", 7, 12]`
- WHEN `normalize()` runs
- THEN `products` resolves to `[7, 12]`
- AND resolution succeeds without error

### Requirement: CM-10 — Seeds define exact instance defaults

The two seeds MUST define these defaults (only a *defaults source* — editable; the registry is never auto-overwritten once present):

- `productos`: type `product`, slides `3/2/1`, gap `16`, count `8`, arrows + pagination `true`, buy `true`, buy_text builtin (`Comprar`), category `0`, categories `[]`, products `[]`, mix `false`, title `''`.
- `categorias`: type `category`, slides `4/2/1`, gap `16`, count `8`, arrows + pagination `true`, category `0`, categories `[]`, products `[]`, mix `false`, title `''`. (buy/buy_text carry builtins — unused for category type.)
(Previously: seeds defined no `products` key.)

#### Scenario: Seed drives instance resolution

- GIVEN a freshly seeded registry
- WHEN `resolve(['name' => 'productos'])` runs
- THEN base resolves to 3/2/1 slides with arrows+pagination ON and `products` `[]`
- AND rendering falls back to the latest-N query path (BC)