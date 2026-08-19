# Apply Progress — Admin UI Revamp (Slice 1 / PR 1)

## Slice 1: Phase 1 — Settings Model, 13-Key Contract (PR 1) — COMPLETE

- **Branch**: `feat/admin-ui-revamp-config` (base: tracker `feat/admin-ui-revamp`)
- **PR**: #19 — https://github.com/sebaquevedo/carousel-plugin/pull/19
- **Tracker PR**: not yet created — GitHub rejects a tracker PR with no commits vs `main`; create it once PR 1 merges into the tracker.
- **Chain strategy**: feature-branch-chain (tracker `feat/admin-ui-revamp` aggregates to `main`; PR 1 targets the tracker; PRs 2–5 target the previous PR branch).
- **Date**: 2026-08-19

## Tasks completed

| Task | Description | Status |
|------|-------------|--------|
| 1.1 | `class-settings.php` `builtins()` +13: autoplay=false, stop_on_hover=true, timeout=3000, speed=300, loop=false, nav_position=`bottom-right`, 6 colors=`''`, slides_laptop=2 (CM-12) | [x] |
| 1.2 | Add `nav_positions()` + `nav_color_keys()` (D2) | [x] |
| 1.3 | `normalize()`: parse_bool x3; timeout clamp 1000–60000; speed 100–5000; slides_laptop 1–12; nav_position whitelist→bottom-right; hex empty-first (CM-13) | [x] |
| 1.4 | `resolve()` `$known` +13 (CM-9) | [x] |

## Files changed

| File | Action | What was done |
|------|--------|---------------|
| `includes/class-settings.php` | Modified | `builtins()` +13 keys (CM-12); `normalize()` coercions: parse_bool x3, timeout/speed/slides_laptop clamps, nav_position via `sanitize_nav_position()`, six `nav_color_*` empty-first hex (CM-13); `resolve()` `$known` +13 (CM-9); new `nav_positions()`, `sanitize_nav_position()`, `nav_color_keys()` (D2, title_alignments() pattern); 4 docblocks 18→31-key. phpcbf re-aligned the pre-existing `$known`/`builtins()` arrays to the longer key column (repo standard = zero phpcs warnings). |
| `openspec/changes/admin-ui-revamp/tasks.md` | Modified | Phase 1 tasks 1.1–1.4 marked `[x]` |
| `openspec/changes/admin-ui-revamp/apply-progress.md` | Created | This continuity artifact |

No other files touched. `sanitize_nav_position()` was added alongside `nav_positions()` — the title_alignments() pattern (D2) includes the shared sanitize helper as the coerce point, and normalize() consumes it; the admin select renderer (PR 3) will reuse it.

## Commits (slice branch `feat/admin-ui-revamp-config`)

| Hash | Message |
|------|---------|
| `cbc90d1` | feat(settings): 13-key contract — builtins, normalize clamps, nav whitelist |

Tracker branch `feat/admin-ui-revamp` @ `13c93fd` (main HEAD), pushed.

## Verification results (slice acceptance)

- `php -l includes/class-settings.php`: **no syntax errors**.
- `vendor/bin/phpcs --standard=phpcs.xml.dist` (full repo): **clean** (exit 0, no output).
- `wp eval-file` in wp-env (Docker, WP + WooCommerce 11.0.0 active): **27/27 PASS** against the config-model delta scenarios:
  - CM-12: 13 builtins exact defaults (autoplay=false, stop_on_hover=true, timeout=3000, speed=300, loop=false, nav_position=bottom-right, 6 colors `''`, slides_laptop=2).
  - CM-14/CM-9: legacy 18-key instance normalizes to exactly 31 keys; new keys carry builtins; `wp_json_encode` round-trips 31 keys (CM-4).
  - CM-13: clamps — timeout "500"→1000, speed "0"→100, slides_laptop "99"→12; invalid enum `middle`→bottom-right; invalid hex `red`→`''`; valid enum/hex kept; empty hex stays `''`.
  - CM-9: malformed products `["0","abc",7,12]`→`[7,12]`.
  - CM-2/CM-9: resolve() whitelists the 13 atts, drops unknown atts, empty atts fall through; `resolve([])` returns exactly 31 keys.
  - BC: legacy 18-key registry entry resolves with slides 3/2/1, gap 16, count 8, buy_text "Comprar", cover=false, title_align=left, products=[].
  - D2: `nav_positions()` returns the 4 corners; `sanitize_nav_position('middle')`→bottom-right.

The temporary acceptance harness (`verify-pr1.php`) was deleted after the run — not committed.

## Deviations from design

None — implementation matches design.md D1/D2/D3 intent and the CM-12/13/14 delta. Details:

- `sanitize_nav_position()` added alongside `nav_positions()` (design lists only the list method; the title_alignments() pattern the design references includes the shared sanitize helper, and normalize() needs it as the coerce point — the admin select renderer in PR 3 will consume it too).
- Colors: a `foreach ( $this->nav_color_keys() ... )` loop in normalize() instead of six inline expressions — uses the shared key list (D2) so coercion set and emitted set can never drift.
- The color cast uses `(string)` before `sanitize_hex_color()` to avoid a TypeError on crafted non-scalar input (design formula `'' === $v ? '' : (string) sanitize_hex_color( $v )` kept, plus the guard).

## Observations for later slices

- **PR 5 / CR-11 tension (WARNING)**: once `normalize()` returns 31 keys, the renderer's unconditional `wp_json_encode( $config )` (class-renderer.php:77) will emit the 13 new keys in `data-cwc-config` for EVERY instance — including legacy ones. The CR-11 scenario "output equals today's markup exactly" cannot hold for the config attribute text (CR-11 also mandates data-cwc-config carries the extended keys). frontend.js `buildOptions` ignores unknown keys, so there is NO behavioral change — but PR 5's acceptance wording "legacy markup byte-identical" should be read as "wrapper class/style + behavior identical; config attribute intentionally extended".

## Remaining phases

- Phase 2 (PR 2): shortcode whitelist +13 atts — `class-shortcode.php` only, ~15 lines.
- Phase 3 (PR 3): editor tabs + toggles + header field + re-fill + sanitize_instance raw + admin.js/css split.
- Phase 4 (PR 4): new renderers + i18n.
- Phase 5 (PR 5): renderer + frontend wiring + corner CSS.
- Phase 6: final verification.