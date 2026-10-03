# Task: preserve per-category image/title when adding categories via search

**Status**: open — documented, NOT fixed
**Branch**: `fix/ast-compat-and-vendor-rename`
**Created**: 2026-10-03

> Related: `odd/tasks/category-picker-backspace-deselect.md` covers a **different** defect in
> the same picker (a bare Backspace silently deselecting the last item). That one is fixed;
> this one is not.

## Goal

Adding a category to a carousel through the Select2 AJAX search and saving must not
destroy that category's previously configured `cwc_cat_image` / `cwc_cat_title` term meta.

## Reported symptom

In the per-instance editor (`?page=cwc-carousel&cwc_action=edit&slug=<slug>`), the
category field: after adding a category via the search, the previously configured
elements for that category disappear.

## Root cause (reproduced and confirmed)

Chain:

1. `CWC_Admin::render_categories_field()` emits `data-thumb`, `data-image-id` and
   `data-title` **only** on the `<option>` elements it server-renders, i.e. only for
   the categories already stored in that instance's `categories` array.
2. When the user picks a category from the Select2 AJAX search, Select2 creates a bare
   `<option value="<id>">` carrying **none** of those `data-*` attributes.
3. `admin.js` → `appendEditControls()` copies the missing attributes into the chip's
   posting inputs, so the form submits `cwc_cat_images[<term>]=""` and
   `cwc_cat_titles[<term>]=""`.
4. `CWC_Admin::save_category_images()` reads an empty image as `absint('') = 0`, which is
   not `> 0`, so it calls `delete_term_meta()`. An empty title likewise deletes the meta.

The semantic defect: **"the chip could not load this term's stored state" is
indistinguishable from "the user emptied it"**, and the latter is what the server assumes.

### Confirmed evidence (wp-env, WP + WooCommerce 11.0.0)

Term 22 (Deportes) had `cwc_cat_image=34` and `cwc_cat_title="PROBANDO La cosa"`.

| Step | term 22 `cwc_cat_image` | term 22 `cwc_cat_title` |
| --- | --- | --- |
| Before adding it to `demo-categorias` | 34 | PROBANDO La cosa |
| After adding via AJAX search + Save Changes | *(deleted)* | *(deleted)* |

The category itself persisted correctly (`categories` went `[121,21,122,103]` →
`[121,21,122,103,22]`); only the term meta was destroyed. Both values were restored
after the diagnosis.

## Why the earlier fix did not cover this

`captureLiveData()` / `restoreLiveData()` already preserve **in-progress edits** across a
chip rebuild. They cannot help here: the chip is *born* without a baseline, so there is
nothing to restore.

## Fix design

WooCommerce exposes the extension point needed
(`woocommerce/includes/class-wc-ajax.php`:2068):

```php
wp_send_json( apply_filters( 'woocommerce_json_search_found_categories', $found_categories ) );
```

1. **PHP** — extract the per-term chip payload (resolved image id, thumbnail URL, overlay
   title) into one helper, so the server-rendered options and the AJAX response cannot
   drift. `render_categories_field()` uses it as today.
2. **PHP** — hook `woocommerce_json_search_found_categories` and attach the same payload
   to each returned term object. Prime the term meta cache once for the whole result set.
   Resolve the image exactly as the renderer does (`cwc_cat_image` else `thumbnail_id`) so
   a search-added chip and a stored chip behave identically.
3. **JS** — in `processResults()` (categories branch) copy the extra fields onto the
   Select2 result object.
4. **JS** — on `select2:select`, copy that data onto the newly created `<option>` as
   `data-image-id` / `data-title` / `data-thumb` **before** `rebuildChipList()` runs, so the
   chip is built from a correct baseline.
5. **JS** — fail safe: when the AJAX payload carries no baseline fields, mark the option so
   `appendEditControls()` emits no posting inputs. A chip that never loaded a term's state
   must never be able to delete it, even if the filter is unavailable.

## Tasks

- [ ] 1. PHP: extract shared per-term chip payload helper; reuse in `render_categories_field()`
- [ ] 2. PHP: add `woocommerce_json_search_found_categories` enrichment filter
- [ ] 3. JS: map enriched fields in `processResults()`
- [ ] 4. JS: apply baseline onto the option before the chip rebuild
- [ ] 5. JS: fail-safe when the baseline is unknown
- [ ] 6. Verify: no data loss on add-via-search + save (browser harness)
- [ ] 7. Verify: regression — stored categories, in-progress edits, remove, tab switch
- [ ] 8. Commit (work unit: behaviour + docs)

## Verification approach

The repository has **no test suite** (no `tests/`, no PHPUnit config), so there is no
applicable RED/GREEN harness to extend here — test-first is not available for this change.
Proportionate verification instead:

- A Playwright harness outside the repo drives real Chrome against wp-env: it logs in,
  opens the editor, adds a category via the AJAX search, submits, and re-reads the term
  meta with `wp eval` before and after.
- The decisive assertion: term meta for the added category must be **unchanged**.
- Regression pass over the paths the existing bugfix comments call out: stored categories,
  in-progress overlay title, image upload, chip removal, tab switching.

## Non-goals

- Changing the `create` mode flow (it deliberately posts no chip inputs; there is no save
  nonce in create mode).
- Making `cwc_cat_image` / `cwc_cat_title` per-instance. They are term-global by design;
  removing a chip deliberately does not clear them.

## Risks

- The filter runs for **every** `woocommerce_json_search_categories` request in the admin,
  including WooCommerce's own pickers. It only adds fields and is bounded by WC's result
  limit, but it does add term-meta reads; the meta cache is primed once per result set.
- If a third party filters the endpoint and strips the added fields, the JS fail-safe
  degrades the chip to "no inline image/title controls" rather than risking data loss.
