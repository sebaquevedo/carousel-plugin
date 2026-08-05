# woocommerce-dependency Specification

## Purpose

Guarantees the plugin only runs when WooCommerce is active. If WooCommerce is
missing, the plugin deactivates gracefully (no fatal error) and tells the admin
why. Out of scope for this slice: admin settings, CPT, block, AJAX cart — those
are later slices.

## Requirements

### Requirement: WD-1 — WooCommerce presence check

Before registering any carousel functionality, the loader MUST verify
`class_exists( 'WooCommerce' )`.

#### Scenario: WooCommerce active

- GIVEN WooCommerce is active when the plugin loads
- WHEN the loader runs its dependency check
- THEN carousel modules are registered and the plugin functions normally

#### Scenario: WooCommerce absent

- GIVEN `class_exists( 'WooCommerce' )` is false at load
- WHEN the loader runs its dependency check
- THEN no carousel functionality is registered

### Requirement: WD-2 — Graceful self-deactivation

When WooCommerce is missing, the plugin MUST self-deactivate via
`deactivate_plugins()` without producing a fatal error or broken state.

#### Scenario: Missing dependency deactivates cleanly

- GIVEN the plugin is active but WooCommerce is not
- WHEN bootstrap detects the missing dependency
- THEN the plugin is deactivated programmatically
- AND no fatal error is raised and no partial Carousel state remains

#### Scenario: Reactivation after WooCommerce install

- GIVEN WooCommerce is later installed and activated
- WHEN the admin activates the carousel plugin
- THEN the plugin now passes the dependency check and runs normally

### Requirement: WD-3 — Admin notice for missing dependency

When WooCommerce is missing, the plugin SHOULD render an admin notice (only to
users with the `activate_plugins` capability) explaining that WooCommerce is
required, with the message escaped and translatable.

#### Scenario: Notice shown to responsible admin

- GIVEN WooCommerce is inactive and the current user may activate plugins
- WHEN the self-deactivation path runs
- THEN an escaped, translated admin notice is displayed stating WooCommerce is required

#### Scenario: Non-privileged context

- GIVEN a request without plugin-activation capability
- WHEN the notice hook evaluates
- THEN no notice is output (capability guard holds)