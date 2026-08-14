# Delta for plugin-bootstrap

> Modified capability. Main spec exists at
> `openspec/specs/plugin-bootstrap/spec.md`; the block below appends (ADDED) with
> new REQ ID PB-4. Archive: ADDED blocks append; PB-1..PB-3 unchanged.

## ADDED Requirements

### Requirement: PB-4 — Lazy, version-gated, idempotent registry migration

`cwc_carousel_boot()` (main file L109, on `plugins_loaded`) MUST run a lazy
migration — never on activation (PB-2 forbids activation writes) — that seeds the
`cwc_carousel_registry` option exactly once. The migration MUST run only when the
registry option is absent; it MUST be idempotent. Seeding:

1. `default` — copied from the legacy `cwc_carousel_options` option and
   normalized when that legacy option exists, else from `builtins()` (fresh
   install). Representing today's behavior exactly.
2. `productos` and `categorias` seeds (CM-10 values).
3. The legacy `cwc_carousel_options` option MUST NEVER be deleted — it remains the
   restore path (rollback).

The migration MUST NOT write on clean installs *where the registry already
exists*, and must not overwrite the registry once present (user edits preserved).

#### Scenario: First run migrates legacy into `default` + seeds

- GIVEN `cwc_carousel_options` exists and `cwc_carousel_registry` is absent
- WHEN `cwc_carousel_boot()` runs
- THEN registry is written once with `default` (from legacy, normalized)
  plus `productos`/`categorias` seeds
- AND legacy `cwc_carousel_options` is left untouched

#### Scenario: Idempotent on subsequent boots

- GIVEN a `cwc_carousel_registry` already exists with an edited `productos`
- WHEN `cwc_carousel_boot()` runs again
- THEN no registry write occurs and the edited `productos` is preserved

#### Scenario: Clean install seeds without legacy option

- GIVEN no `cwc_carousel_options` and no registry
- WHEN `cwc_carousel_boot()` runs
- THEN `default` is seeded from `builtins()` plus the two seeds
- AND no legacy option is created

#### Scenario: Legacy option preserved as rollback path

- GIVEN migration has run
- WHEN inspected afterwards
- THEN `delete_option('cwc_carousel_registry')` restores today's behavior
  (`default` resolves from `builtins()`, CM-1/CM-8)
- AND `cwc_carousel_options` still exists for manual restore

## Decisions / Notes

- Version gate: only the "registry absent" condition gates seeding, which is
  self-idempotent; combined with `CWC_Settings` `builtins()` fallback this covers
  clean installs and upgrades without writes on activation (per PB-2).
- Migration is lazy (runs at boot, not activation) so no network/activation-time
  write and no hook ordering risk (D: R3, R4 from proposal).

## Acceptance Criteria

- Migration seeds `default` + `productos` + `categorias` exactly once.
- Re-runs are no-ops; user edits are never overwritten.
- Legacy `cwc_carousel_options` is never deleted (restore path).
- Clean installs seed without a legacy option; phpcs passes.