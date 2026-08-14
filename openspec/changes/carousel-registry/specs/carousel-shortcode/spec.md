# Delta for carousel-shortcode

> Modified capability. Main spec exists at
> `openspec/specs/carousel-shortcode/spec.md`; the blocks below append (ADDED)
> with new REQ IDs SC-8/SC-9. Archive: ADDED blocks append; SC-1..SC-7 unchanged.

## ADDED Requirements

### Requirement: SC-8 — `name` attribute whitelisted and resolved

`CWC_Shortcode::render()` MUST add `'name' => ''` to the `shortcode_atts()`
default array and pass it to `CWC_Settings::resolve()`, where an empty string is
filtered (treated as "no name" → reserved `default` instance, CM-8) and a supplied
name selects that registry instance as the defaults base. Unknown names fall back
to `default` without error.

#### Scenario: Named instance selects base config

- **GIVEN** `[cwc_carousel name="productos"]`
- WHEN the shortcode renders
- THEN `resolve()` uses the `productos` instance config as the base (3/2/1, arrows+pagination ON)
- AND renderer/query consume the same 14-key resolved config as today (CR-7, PQ)

#### Scenario: No name is backward compatible

- **GIVEN** `[cwc_carousel]` (no name)
- WHEN rendered
- THEN `resolve()` uses the `default` instance
- AND output is identical to today's shortcode (BC)

#### Scenario: Unknown name degrades gracefully

- **GIVEN** `[cwc_carousel name="nope"]`
- WHEN rendered
- THEN it falls back to the `default` instance
- AND rendering succeeds with no fatal error

### Requirement: SC-9 — Whitelist gains slides_tablet/slides_mobile/gap (CRITICAL)

`CWC_Shortcode::render()` MUST add `'slides_tablet' => ''`,
`'slides_mobile' => ''`, and `'gap' => ''` to the `shortcode_atts()` default
array. Without them `shortcode_atts()` drops the attributes and the
per-instance slides/gap overrides would never reach `resolve()` (an SC-6-style
no-op). An empty-string value (missing att) MUST fall through `resolve()`'s
empty-string filter to the instance/builtin default (BC for existing no-name
shortcodes); an explicit value MUST now override the default instead of silently
dying — the intended fix.

#### Scenario: Explicit slides override reaches resolve

- **GIVEN** `[cwc_carousel name="productos" slides_tablet="3"]`
- WHEN rendered
- THEN resolved `slides_tablet` is `3`
- AND `.swiper` breaks at tablet=3 on that container

#### Scenario: Explicit gap override applies

- **GIVEN** `[cwc_carousel gap="24"]`
- WHEN rendered
- THEN resolved `gap` is `24`
- AND the container gap reflects it

#### Scenario: Omitted atts keep defaults (BC)

- **GIVEN** `[cwc_carousel]` (no slides/gap atts, `slides_tablet`/`gap` unfilled `''`)
- WHEN rendered
- THEN instance/builtin defaults apply
- AND no behavior change for existing no-name shortcodes

## Decisions / Notes

- The gap fix is limited to the **shortcode whitelist**; `resolve()` and
  `builtins()` already whitelist `slides_tablet`/`slides_mobile`/`gap` in
  `CWC_Settings` (class-settings.php L88-90, L120-122) — so the seeds' per-instance
  overrides reach the config once the shortcode passes them.
- No re-render changes: renderer/query/frontend consume the same resolved 14 keys,
  so `data-cwc-config`, `frontend.js`, `CWC_Query` are untouched (out of scope).

## Acceptance Criteria

- `name` selects the named instance; absent/unknown `name` resolves `default` and
  never fails (BC preserved).
- `slides_tablet`/`slides_mobile`/`gap` reach `resolve()` and override the default.
- Existing no-name shortcodes render identically to today.
- phpcs passes (WordPress + WordPress-Extra).