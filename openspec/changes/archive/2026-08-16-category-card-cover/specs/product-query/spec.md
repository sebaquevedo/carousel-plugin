# Delta for product-query

> Modified capability. Main spec exists at
> `openspec/specs/product-query/spec.md`; PQ-6/PQ-7 append (ADDED). Archive: ADDED
> blocks append; PQ-1..PQ-5 unchanged.

## ADDED Requirements

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

## Decisions / Notes

- Explicit `include_children => true` on the product `tax_query` keeps today's
  effective behavior while making intent visible (proposal: never rely on the
  WP default).
- Term data access stays in the query layer (D8), mirroring `get_categories()`.
- Depth is fixed at 1 in this slice; a depth config key is out of scope
  (proposal Open Decision, deferred).
- `hide_empty => true` counts only a term's DIRECT product count: a child with
  no direct products is omitted from the LISTING even if its own descendant has
  products. This intentionally diverges from PQ-7, where the product QUERY
  includes descendants recursively — listing categories vs. including products
  are different concerns.
- `subcategories=1` requires a non-zero `category`. When `category` is 0 or
  absent, the result is empty and the explicit `categories` list is NOT used
  (D3 precedence overrides the list).

## Acceptance Criteria

- `subcategories="1"` lists direct children; empty/invalid parent → empty
  carousel; `subcategories` off keeps today's selection.
- Scoping to a parent `category` returns products in descendants (any depth);
  `categories` list mode sets the same explicit `include_children` flag.
- No raw SQL; no side effects; phpcs passes.