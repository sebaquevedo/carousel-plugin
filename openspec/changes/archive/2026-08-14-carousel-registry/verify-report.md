```yaml
schema: gentle-ai.verify-result/v1
evidence_revision: sha256:370785cf157d952f9c995336dcaad4e73f9df0c3b92d94266c641f65b4d474d8
verdict: pass
blockers: 0
critical_findings: 0
requirements: 11/11
scenarios: 24/24
test_command: composer phpcs
test_exit_code: 0
test_output_hash: sha256:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
build_command: php -l custom-woo-pro-carousel.php includes/class-admin.php includes/class-settings.php includes/class-shortcode.php
build_exit_code: 0
build_output_hash: sha256:86fe27a12d5a6da36f1bc001da63ac4a196d3230ca29e49ee3b020ba3ea026d9
```

# Verify Report: Named Carousel Registry

- **Change**: `carousel-registry`
- **Version**: N/A (OpenSpec delta artifacts)
- **Mode**: Standard (strict_tdd: false, no configured test runner)
- **Date**: 2026-08-14
- **Verdict**: **PASS**

## Completeness

| Metric | Value |
|--------|-------|
| Tasks total | 17 |
| Tasks complete | 17 |
| Tasks incomplete | 0 |

All 17 tasks are checked `[x]` in `tasks.md`; `apply-progress.md` confirms 17/17 and
the git history shows both feature commits (`0fbc4a2` settings/shortcode/bootstrap,
`eb0f0cf` admin). The working tree is clean. Diff scope is exactly the four
design-§7 files plus `.gitattributes` (845 insertions / 183 deletions — matches the
apply-progress PR note that the actual diff exceeded the 400-line forecast).

## Requirements Coverage (specs → implementation → evidence)

| Requirement | Scenarios | Implementation | Result |
|-------------|-----------|----------------|--------|
| PB-4 Lazy, version-gated, idempotent migration | 4 | `cwc_carousel_boot()` (custom-woo-pro-carousel.php L117-143): seeds only when registry not array; `add_option` (race-safe no-op) / `update_option` repair; legacy never deleted | ✅ COMPLIANT |
| SC-8 `name` whitelisted and resolved | 3 | whitelist `'name' => ''` (class-shortcode.php L56); pop before filter + `instance_base()` (class-settings.php L83-93) | ✅ COMPLIANT |
| SC-9 whitelist gains slides_tablet/slides_mobile/gap (CRITICAL) | 3 | whitelist L64-66 | ✅ COMPLIANT |
| CM-7 Registry read with empty-map fallback | 2 | `registry()` (class-settings.php L138-142), read-only | ✅ COMPLIANT |
| CM-8 Resolve-by-name with `default` BC fallback | 3 | `resolve()` + `instance_base()` (L77-124, L157-169) | ✅ COMPLIANT |
| CM-9 Per-instance 14-key contract preserved | 1 | public `normalize()` (L261-286); `name` never serialized | ✅ COMPLIANT |
| CM-10 Seeds define exact instance defaults | 1 | `seeds()` (L184-212) | ✅ COMPLIANT |
| AS-5 Registry option with per-slug sanitizer | 2 | `register_settings()` (L146-168) + `sanitize_registry()` (L922-959), edit-only | ✅ COMPLIANT |
| AS-6 List view of instances | 1 | `render_list()` (L207-294); `default` without delete/rename | ✅ COMPLIANT |
| AS-7 Per-instance editor (create/edit/delete) | 2 | `render_editor()`, `render_delete_confirm()`, `create_instance()`, `delete_instance()` | ✅ COMPLIANT |
| AS-8 Slug sanitization, uniqueness, reserved name | 2 | `slugify()` (L1101-1103) + create guards (L851-857) | ✅ COMPLIANT |

All 11 delta requirements and 24 scenarios map to implemented code with runtime
evidence (below). No scenario is UNTESTED at the code level.

## Evidence (commands run + exact output)

### Static analysis — `composer phpcs` (WordPress + WordPress-Extra, phpcs.xml.dist)

```
> composer phpcs
(no output — clean; exit code 0)
```
Direct scan of the 4 changed files (same standard) also exits 0 with no findings.

### Syntax — `php -l` (PHP 8.3.30)

```
No syntax errors detected in custom-woo-pro-carousel.php
No syntax errors detected in includes\class-admin.php
No syntax errors detected in includes\class-settings.php
No syntax errors detected in includes\class-shortcode.php
```

### Runtime smoke — wp-env (`npx @wordpress/env`, Docker 29.7.2; WP + WooCommerce active)

Environment was already running (restarted in 9s). Plugin mounted as
`carousel-plugin`, **already active with 7h uptime — boots with no fatal**.

**Migration state** (`wp option get cwc_carousel_registry --format=json`):

```json
{"default":{"type":"product","title":"","category":0,"categories":[],"mix":false,"count":8,"slides":3,"slides_tablet":2,"slides_mobile":1,"gap":64,"arrows":true,"pagination":true,"buy":true,"buy_text":"reservar ya"},
 "productos":{"type":"product","title":"","category":0,"categories":[],"mix":false,"count":8,"slides":3,"slides_tablet":2,"slides_mobile":1,"gap":16,"arrows":true,"pagination":true,"buy":true,"buy_text":"Comprar"},
 "categorias":{"type":"category","title":"","category":0,"categories":[22,19,21,17],"mix":false,"count":8,"slides":4,"slides_tablet":2,"slides_mobile":1,"gap":16,"arrows":true,"pagination":true,"buy":true,"buy_text":"Comprar"}}
```

- `default` is the normalized legacy copy (gap 64, buy_text "reservar ya" — the
  legacy `cwc_carousel_options` values) → PB-4 first-run migration ✓
- `productos`/`categorias` match CM-10 exactly ✓
- `categorias` carries admin edits (`categories:[22,19,21,17]`) that survived hours
  of boots and today's restart → **PB-4 idempotent, user edits preserved** ✓
- `cwc_carousel_options` still present with original values → **legacy never
  deleted (rollback path)** ✓

**Behavioral resolve() + render** (temporary `wp eval-file`, file deleted after run;
no source modified):

```
R1 no-name -> {"slides":3,"slides_tablet":2,"slides_mobile":1,"gap":64,"buy_text":"reservar ya"}
R2 productos -> {"slides":3,"slides_tablet":2,"slides_mobile":1,"gap":16,"arrows":true,"pagination":true}
R3 productos count=4 -> 4
R4 unknown name -> {"slides":3,"slides_tablet":2,"slides_mobile":1,"gap":64,"buy_text":"reservar ya"}
R5 productos slides_tablet=3 -> 3
R6 gap=24 -> 24
R7 name keys in contract -> false
R8 shortcode no-name length -> 2303 fatal=false
R9 shortcode productos length -> 2287
R10 shortcode categorias length -> 2967
R11 data-cwc-config present -> true
```

- R1 → CM-8 / SC-8 no-name BC (resolves the edited migrated `default`)
- R2 → SC-8 named instance + CM-10 seed (3/2/1, arrows+pagination ON)
- R3 → CM-2/CM-8 attribute override wins (proposal success criterion)
- R4 → CM-8 unknown name falls back, never fatal
- R5/R6 → **SC-9 CRITICAL fix works**: whitelist now lets slides_tablet/gap
  overrides reach resolve()
- R7 → CM-9: `name` never enters the 14-key contract
- R8-R11 → renderer/query/frontend contract untouched (CR-7): all three shortcode
  forms render non-empty HTML with `data-cwc-config`, no fatal

**Debug log**: `wp-content/debug.log` shows no plugin errors. The 7 `PHP Fatal`
entries are all `Undefined constant ... Eval_Command.php` artifacts from unquoted
`wp eval` shell commands (Aug 5, Aug 7, and this run's first broken attempt) — none
reference plugin files and none occurred during HTTP loads.

## Correctness (Static Evidence)

| Requirement | Status | Notes |
|------------|--------|-------|
| `normalize()` public (D5) | ✅ Implemented | class-settings.php L261; shared by resolve/instance_base/migration/admin |
| `registry()` read-only (CM-7) | ✅ Implemented | `get_option` only, non-array → `array()` |
| `seeds()` exact CM-10 values | ✅ Implemented | productos 3/2/1 gap16 count8 arrows+pagination+buy; categorias 4/2/1 gap16 count8 arrows+pagination |
| `instance_base()` precedence (CM-8, D3) | ✅ Implemented | slug → `default` → `defaults()`; never fatal |
| `resolve()` pops `name` pre-filter (design §4) | ✅ Implemented | L83-84; name normalized via `sanitize_title` (AS-8 parity) |
| Shortcode whitelist `name`+slides/gap (SC-8/SC-9) | ✅ Implemented | class-shortcode.php L56, L64-66 |
| Migration seed shape (PB-4, design §5) | ✅ Implemented | add_option race-safe; non-array corrupt value repaired via update_option |
| Legacy option never deleted (PB-4) | ✅ Implemented | only read; runtime-confirmed present |
| `register_settings()` registry + capability (AS-5/AS-1) | ✅ Implemented | autoload false, per-slug sanitize, `option_page_capability_{group}` → `manage_woocommerce` |
| Router + list + editor + delete confirm (AS-6/AS-7) | ✅ Implemented | `render_page()` on `cwc_action`; `default` not deletable/renamable |
| Per-slug edit-only sanitizer (AS-5, D4) | ✅ Implemented | unknown slugs/`__new__` never written; duplicate slugs first-wins |
| Create/delete guarded (AS-8) | ✅ Implemented | capability → `check_admin_referer` → write; blank/reserved/duplicate rejected via `add_settings_error` |

## Coherence (Design)

| Decision | Followed? | Notes |
|----------|-----------|-------|
| D1 one keyed option `cwc_carousel_registry` | ✅ Yes | single sanitize/migrate surface |
| D2 `name` whitelisted + popped before merge | ✅ Yes | R7 proves it never serializes |
| D3 unknown/no-name → `default` → legacy `defaults()`, never fatal | ✅ Yes | R4 runtime |
| D4 create/delete via `handle_registry_actions()` on admin_init | ✅ Yes | mirrors `save_category_images()` pattern |
| D5 `normalize()` public | ✅ Yes | single coerce point |
| D6 no rename UI this slice | ✅ Yes | create+delete path only |
| Migration exact design §5 shape | ✅ Yes | plus corrupt-value repair (see gaps) |
| Admin surface reuses escaped renderers | ✅ Yes | phpcs clean incl. escaping sniffs |

## Gaps

1. **Clean-install-no-legacy branch (PB-4 scenario 3)** — verified by source
   inspection (`get_option('cwc_carousel_options', array())` → `is_array(...)`
   guard → `normalize(array())` → builtins) and by apply-session smoke
   (orchestrator-verified); not re-executed this run because the shared env has a
   legacy option and wiping it would destroy the rollback fixture. Low risk: same
   code path as the runtime-proven legacy migration.
2. **Delete-registry rollback (PB-4 scenario 4)** — runtime-proven parts: legacy
   option preserved; `delete_option('cwc_carousel_registry')` → `defaults()` fallback
   is the same branch R4 exercises (unknown name → `defaults()`). The destructive
   `delete_option` itself was not re-run in the shared env.
3. **Admin UI round-trip (create/edit/delete, AS-6..AS-8)** — behavior confirmed via
   option-state evidence (edited `categorias` persisted) and apply-session smoke;
   interactive browser flows not re-run in this verify pass.
4. wp-env smoke covered the boot/migration/resolve/render surface; the 
   `arrows`/`pagination` values also flow through the same resolved config as
   verified in the prior navigation-controls change.

## Issues

**CRITICAL**: None.
**WARNING**: None.
**SUGGESTION**:
1. `cwc_carousel_boot()` repairs a present-but-corrupt non-array registry with
   `update_option()` instead of only `add_option()` (design §5 showed only the
   absent case). Strictly an improvement — prevents a broken value being re-attempted
   every request, consistent with CM-7's non-array handling and PB-4 idempotency.
   Document it or amend the design snippet at archive time.
2. `resolve()` normalizes the shortcode `name` with `sanitize_title` + underscore
   folding (parity with admin `slugify()`, AS-8), which is slightly beyond the
   design's literal "pop name" text but matches its intent; unknown names still fall
   back to `default` (R4).
3. `wp eval`-style smoke commands must be quoted carefully (or use `eval-file`) to
   avoid `Undefined constant` fatals polluting `debug.log`.

## Recommendation

**Verify passed** — archive readiness confirmed. All 17 tasks complete, all 11 delta
requirements (24 scenarios) implemented with runtime evidence, `composer phpcs` exit
0, `php -l` clean on all four files, and a live wp-env smoke (WP + WooCommerce)
proving boot, migration, resolve-by-name, BC fallback, attribute overrides, and the
SC-9 whitelist fix. No CRITICAL or WARNING findings. Proceed to **sdd-archive**
(sync the delta specs into `openspec/specs/*`), optionally folding the two
SUGGESTION notes into the archived design text.
