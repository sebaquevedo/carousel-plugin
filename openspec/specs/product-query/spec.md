# product-query Specification

## Purpose

A pure, minimal `WC_Product_Query` data-access layer for the carousel: recent
products by default plus manual product-ID passthrough. Hardcoded defaults with no
admin UI. Designed for later extension by the filters slice. Out of scope this
slice: admin UI (2), full filters/orderings (3).

## Requirements

### Requirement: PQ-1 — Recent products default

With no explicit selection and no category filter, the query builder MUST return
recent published products (`orderby` date, descending). The requested `count` is
numeric-only: it MUST be coerced via `absint`, a count of zero MUST yield an
empty result, and there MUST be no "all" sentinel.

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

### Requirement: PQ-6 — Subcategory listing (direct children)

When `subcategories` is true, the query layer MUST return the direct child terms
(depth 1, `product_cat`) of the parent `category` term id instead of the explicit
`categories` selection, using the WordPress terms API (no raw SQL). The children
listing MUST use `hide_empty => true` (mirrors `get_categories()`), so a child
term with no products is omitted. A parent id of 0 or a non-existent parent MUST
yield an empty array without error.

#### Scenario: Children of a parent listed

- GIVEN `subcategories` true and a parent `category` id with children A and B
- WHEN the query runs
- THEN WP_Term[] A and B are returned (depth 1 only, no grandchildren)

#### Scenario: Empty child term omitted

- GIVEN `subcategories` true and a parent `category` id with children A, B (with products) and C (no products)
- WHEN the query runs
- THEN only A and B are returned (`hide_empty => true`)
- AND child C is omitted

#### Scenario: Empty or invalid parent

- GIVEN `subcategories` true and `category` = 0 (or a non-existent term id)
- WHEN the query runs
- THEN an empty array is returned
- AND no error is raised

#### Scenario: Disabled keeps explicit selection

- GIVEN `subcategories` false
- WHEN the query runs
- THEN the explicit `categories`/`category` selection is used as today (PQ-4/PQ-5)

### Requirement: PQ-7 — Explicit subcategory inclusion (include_children)

When the product carousel filters by a parent `category` term, the `tax_query`
MUST set `include_children => true` explicitly — never relying on the WP
default — so products assigned to descendant subcategories of the scoped
category are included. The same explicit flag MUST be applied when filtering
by the `categories` list (IN query). Products outside the scoped subtree MUST
NOT be returned.

#### Scenario: Child subcategory product included

- GIVEN a carousel scoped to parent category X and a product assigned only to child subcategory Y of X
- WHEN the product query runs
- THEN the product is returned
- AND the `tax_query` carries an explicit `include_children => true`

#### Scenario: Deeper descendant included

- GIVEN a carousel scoped to parent category X and a product assigned only to grandchild Z (X › Y › Z)
- WHEN the product query runs
- THEN the product is returned (recursive descendant inclusion)

#### Scenario: Outside the subtree excluded

- GIVEN a carousel scoped to parent category X and a product assigned only to unrelated category W
- WHEN the product query runs
- THEN the product is NOT returned

#### Scenario: Categories list mode includes children

- GIVEN a carousel filtering by a `categories` list (IN query) and a product assigned to a child subcategory of a listed term
- WHEN the product query runs
- THEN the product is returned
- AND the list query also sets `include_children => true` explicitly