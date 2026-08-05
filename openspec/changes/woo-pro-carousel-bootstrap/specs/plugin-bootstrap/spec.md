# Delta for plugin-bootstrap

> New capability (slice 1). Main spec pre-created at `openspec/specs/plugin-bootstrap/spec.md`
> per sdd-spec Step 2 (new capabilities → full spec). Archive: the ADDED blocks below are the
> full requirement set for this change; match by name against the main spec (idempotent).

## ADDED Requirements

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
