# Task: Backspace must not silently remove the last chosen category

**Status**: fixed (pending commit)
**Branch**: `fix/ast-compat-and-vendor-rename`
**Created**: 2026-10-03

## Goal

In the carousel editor's category/product picker, pressing Backspace while the search box is
empty must not deselect the last chosen item.

## Reported symptom

> "en el tab de contenido, al ponerle en categorias, cuando ya busque y elegí 'Deportes' y
> luego en el campo de búsqueda si borro, me acaba por borrar la categoría que ya había
> elegido — siendo que ya debería haber quedado."

## Root cause (reproduced in a real browser)

`selectWoo`/Select2 clears the last selected item when **Backspace is pressed while the
inline search input is empty** (`select2/selection/search.js`). That shortcut is a normal
multi-select affordance — but this plugin deliberately hides Select2's own tags:

```css
/* admin.css */
.cwc-picker .select2-selection__choice { display: none; }
```

with the intent recorded right above it: *"the sortable .cwc-chip-list renders the selection
instead … selectWoo's native chips stay hidden so the inline AJAX search input keeps working."*

So the destructive key has **no visible affordance**: the picker shows one search box and a
chip list, and a bare Backspace deletes a chip with nothing on screen explaining why. The
user cannot tell the key acted on the selection at all.

Unlike the meta-preservation defect, this is **plugin-owned**: the destructiveness exists
because the plugin hid the widget's own feedback, not because Select2 is misbehaving.

### Measured evidence (Playwright + Chrome against wp-env, before the fix)

```
after choosing Deportes   : foco = INPUT.select2-search__field (valor ""), chips = 5,
                            seleccionadas = 121,21,122,103,22
after ONE Backspace       : chips = 4, seleccionadas = 121,21,122,103   <- 22 gone
```

A single keypress removed the category, and the following save would drop it from the
`categories` array.

## Fix

`assets/js/admin.js` — new `guardSelectionBackspace( picker )`, called from `initPickers()`:

- Listens for `keydown` in the **capture phase** on the `.cwc-picker` wrapper, so the event
  is stopped before Select2's own handler receives it.
- Intervenes only when the key is Backspace **and** the event target is inside
  `.select2-container` **and** the inline search field is empty. With text present, Backspace
  keeps editing that text normally.
- The `.select2-container` scoping matters: a chip's overlay-title input also lives inside
  the picker wrapper and must keep normal Backspace editing. Without that guard the fix
  would have broken text editing in every chip title field.
- The chip's own **✕** button remains the explicit, visible way to deselect, so no
  functionality is lost.

## Verification

Browser harness (outside the repo, real Chrome against wp-env), category picker on
`demo-categorias`:

| Check | Result |
| --- | --- |
| category added via AJAX search | PASS |
| Backspace ×5 with empty search — the reported bug | PASS (all 5 chips survive) |
| Backspace still edits search text (`frescos` → `fresc`) | PASS |
| **regression**: Backspace inside a chip title input (`ABCDE` → `ABC`) | PASS |
| chip ✕ still removes a category | PASS |
| no JS errors | PASS |

Product picker on `demo-productos`: 8 selected products survived Backspace ×5, and text
editing still works.

`node --check assets/js/admin.js` passes. The registry and term meta were unchanged by the
runs (the harness never submits the form).

## Notes

- The repo has no test suite, so these checks are a disposable Playwright harness rather than
  a committed regression test. Extracting a permanent one would be a separate task.
- **Asset cache (fixed alongside)**: the fix appeared not to work at first because `admin.js`
  was enqueued with `?ver=CWC_VERSION` (`0.1.0`), which does not change when the file is
  edited, so the browser kept serving the stale copy. `CWC_Assets::asset_version()` now uses
  the file mtime as the version argument for the admin CSS/JS, so the asset URL changes
  exactly when the file does and no manual cache bust is needed. Front-end handles keep their
  pinned/versioned arguments, since the vendored Swiper bundle must not drift from 14.0.7.
  Verified: a fresh page load now requests `admin.js?ver=<mtime>`.

## Related but separate

`odd/tasks/category-picker-meta-preservation.md` — adding a category via search and saving
wipes that term's `cwc_cat_image` / `cwc_cat_title`. Different root cause (PHP save contract),
still **open**.
