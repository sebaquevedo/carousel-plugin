# plugin-bootstrap Specification

## Purpose

Single-entry bootstrap for the Custom Woo Pro Carousel plugin: main plugin file,
unique constants, module loader, activation/deactivation hooks, and i18n. This is
slice 1 of 7; it installs no DB schema, options, or persistent data. Out of scope
for this slice: admin UI/CPT (slice 2), filters (3), block (4), AJAX cart (5),
badges/quick-view (6), test runner (7).

## Requirements

### Requirement: PB-1 — Main bootstrap file

The main file `custom-woo-pro-carousel.php` MUST declare a standard plugin header,
define globally-unique constants (version, plugin file, plugin dir), and MUST load
the modular `includes/` classes on the `plugins_loaded` hook.

#### Scenario: Normal load

- GIVEN a freshly uploaded and activated plugin
- WHEN WordPress loads the main file
- THEN the header is parsed, constants are defined, and `includes/` modules are required
- AND modules load via an explicit require-map with no runtime Composer/autoloader dependency

#### Scenario: Header sanity

- GIVEN a static scan of the main file
- WHEN reviewed against plugin best practice
- THEN it contains the required `Plugin Name`, `Version`, `Requires PHP >= 7.4` header fields

### Requirement: PB-2 — Activation and deactivation hooks

The plugin MUST register activation and deactivation hooks. In this slice these
hooks MUST NOT create database rows, options, or any persisted data.

#### Scenario: Clean activation

- GIVEN activation fires
- WHEN the hook runs
- THEN no DB schema, options, or transient data are created

#### Scenario: Clean deactivation

- GIVEN the plugin is deactivated
- WHEN the hook runs
- THEN there is no persisted state to remove, so removal is clean

### Requirement: PB-3 — Internationalization

The plugin MUST call `load_plugin_textdomain` on `plugins_loaded` with a stable
text domain, and all user-facing strings MUST be output through translation
functions.

#### Scenario: Translation loaded

- GIVEN a textdomain and a valid `.mo` file in `languages/`
- WHEN the plugin initializes
- THEN the textdomain is loaded and strings resolve to translated text

#### Scenario: No translation file

- GIVEN no `.mo` file is present
- WHEN the plugin initializes
- THEN the plugin still loads and falls back to the source-language strings

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