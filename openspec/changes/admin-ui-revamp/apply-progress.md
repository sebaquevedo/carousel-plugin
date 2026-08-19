# Apply Progress — Admin UI Revamp (Slices 1–2 / PRs 1–2)

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

## Slice 2: Phase 2 — Shortcode Whitelist (PR 2) — COMPLETE

- **Branch**: `feat/admin-ui-revamp-shortcode` (base: `feat/admin-ui-revamp-config` per feature-branch-chain — PR 2 targets the PR 1 branch, NOT the tracker)
- **PR**: #20 — https://github.com/sebaquevedo/carousel-plugin/pull/20
- **Date**: 2026-08-19

## Tasks completed (cumulative)

| Task | Description | Status |
|------|-------------|--------|
| 1.1 | `class-settings.php` `builtins()` +13: autoplay=false, stop_on_hover=true, timeout=3000, speed=300, loop=false, nav_position=`bottom-right`, 6 colors=`''`, slides_laptop=2 (CM-12) | [x] |
| 1.2 | Add `nav_positions()` + `nav_color_keys()` (D2) | [x] |
| 1.3 | `normalize()`: parse_bool x3; timeout clamp 1000–60000; speed 100–5000; slides_laptop 1–12; nav_position whitelist→bottom-right; hex empty-first (CM-13) | [x] |
| 1.4 | `resolve()` `$known` +13 (CM-9) | [x] |
| 2.1 | `class-shortcode.php` `shortcode_atts` +13 × `''` (SC-12) | [x] |

## Slice 2 files changed

| File | Action | What was done |
|------|--------|---------------|
| `includes/class-shortcode.php` | Modified | `shortcode_atts()` default array +13 keys (`autoplay`, `stop_on_hover`, `timeout`, `speed`, `loop`, `nav_position`, 6 × `nav_color_*`, `slides_laptop`) all `''` (SC-12); render() docblock documents the extension; phpcbf re-aligned the array to the longest key column (`nav_color_border_hover`, repo standard). |
| `openspec/changes/admin-ui-revamp/tasks.md` | Modified | Task 2.1 marked `[x]` |
| `openspec/changes/admin-ui-revamp/apply-progress.md` | Modified | This merged continuity artifact |

## Slice 2 commits (branch `feat/admin-ui-revamp-shortcode`)

| Hash | Message |
|------|---------|
| `72dc04a` | feat(shortcode): whitelist 13 new config atts (SC-12) |
| `43a34f5` | docs(sdd): mark admin-ui-revamp Phase 2 task complete and record apply progress |

## Slice 2 verification results (acceptance: SC-12)

- `php -l includes/class-shortcode.php`: **no syntax errors**.
- `vendor/bin/phpcs --standard=phpcs.xml.dist` (full repo): **clean** (exit 0, no output).
- `wp eval-file` in wp-env (Docker, WP + WooCommerce 11.0.0 active): **52/52 PASS** against the SC-12 delta scenarios:
  - Success-criteria shortcode (`autoplay="true" loop="true" speed="500" timeout="4000" stop_on_hover="false" slides_laptop="3" nav_position="top-left"`) renders a config carrying all 7 explicit values; `nav_color_arrow="#ff0000"` reaches resolve and sanitizes; CM-13 clamps still apply through the shortcode path (timeout 500→1000, slides_laptop 99→12, speed 0→100).
  - BC: `[cwc_carousel]` with none of the 13 atts resolves exactly 31 keys; every new key equals its builtin and all 18 legacy keys equal the merge base (`instance_base('')` = legacy `cwc_carousel_options` — gap=64, buy_text='reservar ya' in this long-lived env, NOT raw builtins) — output unchanged for existing shortcodes.
  - Empty-string atts (`autoplay="" loop=""`) fall through to builtins (treated as absent); unknown atts still dropped.
- The temporary acceptance harness (`verify-pr2.php`) was deleted after the run — not committed.

**Env discovery (affects later slices)**: this wp-env has a persisted legacy `cwc_carousel_options` option (gap=64, buy_text='reservar ya', slides=3 — leftovers from earlier panel-ux test runs). With no registry `default` entry, `instance_base('')` falls back to that legacy option. Later admin-save tests (PR 3/4) must account for this state or reset the option first.

## Deviations from design

None — implementation matches design.md section 3 and SC-12 exactly. The only addition beyond the 13-key array is the docblock sentence documenting the extension (same pattern PR 1 applied to its docblocks).

## Observations for later slices

- **PR 5 / CR-11 tension (carried forward from Slice 1)**: `data-cwc-config` now carries the 13 new keys for every instance — wrapper class/style and behavior stay byte-identical; the config attribute text is intentionally extended (frontend.js buildOptions ignores unknown keys, no behavioral change). PR 5's acceptance wording "legacy markup byte-identical" should be read as "wrapper class/style + behavior identical; config attribute intentionally extended".
- The registry in this wp-env has NO `default` entry — the legacy global option is the effective base. If PR 3's tests create a registry `default`, the merge base will switch to it.

## Remaining phases

- Phase 3 (PR 3): editor tabs + toggles + header field + re-fill + sanitize_instance raw + admin.js/css split.
- Phase 4 (PR 4): new renderers + i18n.
- Phase 5 (PR 5): renderer + frontend wiring + corner CSS.
- Phase 6: final verification.

---

## Slice 6 + Phase 6 (PR 6 / PR #24, branch `feat/admin-ui-revamp-sidenav`) — COMPLETE

- **Date**: 2026-08-20
- **Slices 3–6** were recorded in Engram (topic `sdd/admin-ui-revamp/apply-progress`, 6 revisions); this file caught up at the end. All phases 1–5b tasks (1.1–5b.7) are `[x]` in tasks.md; PRs #19–#24 are OPEN in the feature-branch-chain (tracker `feat/admin-ui-revamp` → config → shortcode → editor → fields → frontend → sidenav), none merged.
- **Phase 6 (final verification) tasks 6.1–6.4 — COMPLETE**, committed as `ffcac39` docs(sdd) and pushed (PR #24 stays open).

## Phase 6 verification results (all PASS)

| Task | Evidence |
|------|----------|
| 6.1 Lint + standards | `php -l` 9/9 plugin PHP files clean; `node --check` admin.js + frontend.js clean; `vendor/bin/phpcs --standard=phpcs.xml.dist` (WordPress + WordPress-Extra) 11/11 files clean, 0 errors/warnings |
| 6.2 E2E shortcode → config → Swiper | Live page http://localhost:8888/demo-carousels-2/ HTTP 200; both demo carousels carry 31-key `data-cwc-config`; `demo-productos` renders `.cwc-carousel-shell cwc-carousel--nav-sides-outside` with `[prev] [.swiper] [next]` flanking siblings + config on the inner slider; `demo-categorias` carries `cwc-carousel--cover cwc-carousel--nav-top-left` + only non-empty `--cwc-nav-color-*` vars; frontend.js + swiper bundle enqueued; admin.js/admin.css absent on frontend (FA-5). jsdom harness against the real vendored Swiper bundle: **40/40 PASS** — success-criteria config (autoplay/loop/speed/timeout/stop_on_hover/slides_laptop) reaches the real instance (`autoplay.delay=4000`, `pauseOnMouseEnter=false`, `speed=500`, `loop=true`, 992 tier=3, 768/1024 unchanged); legacy-resolved 31-key config emits no autoplay/loop keys and speed=300 (== Swiper default) with a 992 tier equal to the pre-change effective value → behavior identical; literal 18-key config keeps the exact 3-breakpoint ramp; sides-outside shell nav resolves via document-level fallback and prev/next clicks advance activeIndex |
| 6.3 i18n | `tools/make-mo.php` recompiled in-container (`Success: Compiled …cwc-carousel-es_ES.mo`); fresh POT = 112 real msgids; es_ES PO has 112 entries — **0 missing, 0 empty, 0 fuzzy**; runtime probe 4/4: "Sides (inside)"→"Laterales (dentro)", "Sides (outside)"→"Laterales (fuera)", subcategories helper + device helper resolve under es_ES with no fallback |
| 6.4 Rollback sanity | BC harness **26/26 PASS** (wp eval-file): legacy-shaped instance (11-key `cwc_carousel_options`, new keys absent) resolves with the 13 keys at builtins; wrapper open tag byte-identical vs the pre-change renderer (`CWC_Renderer_Old` extracted from `main`); full markup byte-identical once the config JSON is normalized; config diff is exactly the 13 new keys (18→31); no migration ran — legacy option (still 11 keys) and registry untouched by the render path; PB-4 only reads `cwc_carousel_options` (never writes), so reverting the branch leaves the legacy option untouched |

## Phase 6 files changed

| File | Action | What was done |
|------|--------|---------------|
| `openspec/changes/admin-ui-revamp/tasks.md` | Modified | Phase 6 tasks 6.1–6.4 marked `[x]` |
| `openspec/changes/admin-ui-revamp/apply-progress.md` | Modified | This merged continuity artifact (slices 3–6 were recorded in Engram) |

## Phase 6 commits (branch `feat/admin-ui-revamp-sidenav`)

| Hash | Message |
|------|---------|
| `ffcac39` | docs(sdd): mark admin-ui-revamp Phase 6 verification tasks complete |

## Phase 6 notes / risks

- **Browser-only remainder (6.2)**: jsdom proved DOM wiring + real Swiper option mapping and nav click behavior; actual pixel layout of the side modes (CSS arrow positioning, flex shell geometry, hover colors) and real-window resize breakpoint re-evaluation remain browser-only — recommend a visual pass on PR #24 review.
- **Known Swiper quirk (carried from PR #24)**: with `sides-outside` and MULTIPLE carousels on one page, the flanking buttons are outside `.swiper`, so `uniqueNavElements` falls back to the first document match and all carousels bind the same button pair. Single-carousel pages resolve correctly (proven); the multi-carousel page on /demo-carousels-2/ inherits the library behavior. Tracked as a risk in PR #24.
- **Harness hygiene**: all Phase 6 scratch files (jsdom harness in `%TEMP%\opencode\cwc-p6`, i18n probe, BC harness + old-renderer copy under `tools/`) were created OUTSIDE the committed tree or deleted after the run; `phpcs` re-run clean afterwards. A stray `npm install` briefly added `jsdom` to `D:\code\package.json` (parent dir, outside the repo) — restored to its original state.
- **Planning artifacts** (proposal.md, design.md, specs/) remain UNCOMMITTED — orchestrator to commit; not swept into child PR commits.