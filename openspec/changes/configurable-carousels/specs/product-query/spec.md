# Delta for product-query

> Modified capability. Main spec already exists at
> `openspec/specs/product-query/spec.md`; the blocks below are already merged
> there (idempotent). Archive: ADDED blocks append, MODIFIED block replaces
> PQ-1 by name.

## ADDED Requirements

### Requirement: PQ-4 — Scoped category filtering (single term)

When a single category term id is supplied (`category`), the query builder MUST
filter products via `tax_query` on `product_cat` with `field => term_id` (no raw
SQL), combined with the numeric-only `count` limit.

#### Scenario: Single-category scoped carousel

- GIVEN `category = 4` and `count = 6`
- WHEN the query runs
- THEN only published products in term 4 are returned, at most 6
- AND no raw SQL is used

#### Scenario: Zero products in category

- GIVEN a category term with no published products
- WHEN the query runs
- THEN an empty result is returned without error

### Requirement: PQ-5 — Mix mode (multiple categories)

When multiple category term ids are supplied (`categories`), the query builder
MUST return products belonging to any of the given terms via a single
`tax_query` on `product_cat` term ids (no raw SQL). A non-empty `categories`
list always filters (IN) — it is treated as an IN-list whether or not `mix` is
enabled; `mix` stays an explicit flag but is not a precondition.

#### Scenario: Mixed categories

- GIVEN `categories = "4,7,9"` and mix enabled
- WHEN the query runs
- THEN published products from terms 4, 7, or 9 are returned
- AND the `count` limit is still honored

#### Scenario: Categories filter without mix

- GIVEN `categories = "4,7"` and mix disabled
- WHEN the query runs
- THEN published products from terms 4 or 7 are returned
- AND `mix` being off does not drop the selection

#### Scenario: Empty term list dropped

- GIVEN `categories` sanitizes to no valid term ids
- WHEN the query runs
- THEN an empty result is returned without error

## MODIFIED Requirements

### Requirement: PQ-1 — Recent products default

With no explicit selection and no category filter, the query builder MUST return
recent published products (`orderby` date, descending). The requested `count` is
numeric-only: it MUST be coerced via `absint`, a count of zero MUST yield an
empty result, and there MUST be no "all" sentinel.
(Previously: recent products default with a hardcoded local limit; "all" was
unsettled in exploration.)

#### Scenario: Default recent products

- GIVEN no product selection is provided
- WHEN the query builder runs
- THEN it returns recent published products ordered newest first

#### Scenario: Limit honored

- GIVEN a requested item limit
- WHEN the query runs
- THEN at most that many products are returned
- AND a count of zero yields an empty result
- AND a non-numeric count coerces to zero (no "all" sentinel)