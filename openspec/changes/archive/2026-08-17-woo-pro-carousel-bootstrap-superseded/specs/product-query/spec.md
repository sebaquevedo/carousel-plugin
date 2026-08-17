# Delta for product-query

> New capability (slice 1). Main spec pre-created at `openspec/specs/product-query/spec.md`
> per sdd-spec Step 2 (new capabilities → full spec). Archive: the ADDED blocks below are the
> full requirement set for this change; match by name against the main spec (idempotent).

## ADDED Requirements

### Requirement: PQ-1 — Recent products default

With no explicit selection, the query builder MUST return recent published
products (`orderby` date, descending).

#### Scenario: Default recent products

- GIVEN no product selection is provided
- WHEN the query builder runs
- THEN it returns recent published products ordered newest first

#### Scenario: Limit honored

- GIVEN a requested item limit
- WHEN the query runs
- THEN at most that many products are returned
- AND a limit of zero yields an empty result

### Requirement: PQ-2 — Manual product-ID passthrough

The query builder MUST accept a manual product-ID list, parse it with
`wp_parse_id_list`, sanitize each value with `absint`, drop any value `<= 0`
(`absint` coerces negatives to positive, so filtering is required), and MUST
preserve the requested order.

#### Scenario: Valid manual IDs

- GIVEN a list of valid product IDs
- WHEN the query runs
- THEN only those products are returned in the requested order

#### Scenario: Invalid values dropped

- GIVEN a list containing a non-numeric or zero/negative value
- WHEN the query runs
- THEN the invalid values are dropped (filtered `<= 0` after `absint`)
- AND remaining valid products are returned without error

#### Scenario: Explicit IDs, all invalid

- GIVEN an `ids` attribute is supplied but every value sanitizes away (e.g. `ids="0,abc"`)
- WHEN the query runs
- THEN an empty result is returned
- AND the default recent-products path is NOT substituted

### Requirement: PQ-3 — Pure, WC-idiomatic data layer

The layer MUST return `WC_Product[]` solely via `WC_Product_Query` (or
`wc_get_products`) with no raw SQL, MUST have no side effects, and MUST expose a
signature that later slices can extend with filters/orderings.

#### Scenario: Importable, side-effect free

- GIVEN the query class is called
- WHEN it builds and executes a query
- THEN it returns `WC_Product[]`
- AND it persists no options, transients, or log output
- AND no raw SQL is used

#### Scenario: Extensibility seam

- GIVEN the default builder signature
- WHEN a later filters slice supplies category/tag/on-sale/ordering args
- THEN the layer accepts them without being rewritten
