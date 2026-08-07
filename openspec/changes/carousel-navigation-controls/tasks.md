# Tasks: Carousel Navigation Controls (arrows & pagination)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~60-90 (5-file targeted change; PHP ~45-60, JS ~15-25) |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR |
| Delivery strategy | force-chained (cached) â€” forecast LOW, single PR suffices |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

Changed-line estimate well under the 400 review budget â†’ no chained PR, no size exception; a single PR is sufficient.

## Planning

| Task | Description | Files | Criterion |
|------|-------------|-------|-----------|
| 1.1 | builtins: `arrows`/`pagination` default `true` | class-settings.php | appears next to `buy` |
| 1.2 | resolve `$known`: add `arrows`/`pagination` | class-settings.php | explicit att overrides default |
| 1.3 | normalize: `parse_bool()` both | class-settings.php | `"false"`/`"0"` â†’ `false` |
| 2.1 | shortcode_atts whitelist += `arrows`/`pagination` `''` (SC-6) | class-shortcode.php | att reaches resolve |
| 3.1 | renderer gate prev/next on `arrows` | class-renderer.php | off â†’ no nav elements |
| 3.2 | renderer gate pagination on `pagination` | class-renderer.php | off â†’ no dots |
| 4.1 | admin builtins += both `true` | class-admin.php | absent â†’ `true` |
| 4.2 | add combined controls field (checkboxes + hidden companions) | class-admin.php | `false` round-trips |
| 4.3 | sanitize_options: parse_bool both (isset-else-builtin) | class-admin.php | mirrors buy L493 |
| 4.4 | `buy` hidden companion fix | class-admin.php | uncheck disables buy |
| 5.1 | frontend conditional nav/pan + legacy fallback | frontend.js | legacy keeps controls |
| 6.1 | lint all changed files | â€” | phpcs + php -l + node --check |
| 6.2 | wp-env + BC verification | â€” | scenarios green |

## Phase 1: Settings Model

- [x] **1.1** `class-settings.php` `builtins()` (L109-124): add `'arrows' => true`, `'pagination' => true` next to `'buy'`.
- [x] **1.2** `class-settings.php` `resolve()` `$known` (L80-93): add `'arrows' => $defaults['arrows']`, `'pagination' => $defaults['pagination']` near `'buy'` â€” explicit shortcode att overrides admin default (CM-6).
- [x] **1.3** `class-settings.php` `normalize()` (L141-154): add `'arrows' => $this->parse_bool($merged['arrows'])`, `'pagination' => $this->parse_bool($merged['pagination'])` (CM-5).

## Phase 2: Shortcode Whitelist (CRITICAL SC-6)
- [x] **2.1** `class-shortcode.php` `shortcode_atts()` defaults (L52-63): add `'arrows' => ''`, `'pagination' => ''`. **#1 failure mode** â€” without this, `[cwc_carousel arrows="false"]` never reaches `resolve()` and controls stay ON. Do NOT touch absent `slides_tablet`/`slides_mobile`/`gap` (out of scope).

## Phase 3: Renderer Gating (CR-8)
- [x] **3.1** `class-renderer.php` (L83-84): emit `.swiper-button-prev`/`.swiper-button-next` only when `! empty( $config['arrows'] )`.
- [x] **3.2** `class-renderer.php` (L82): emit `.swiper-pagination` only when `! empty( $config['pagination'] )`. `data-cwc-config` (L63) unchanged â€” always carries both keys.

## Phase 4: Admin Controls (AS-4 + buy fix)
- [x] **4.1** `class-admin.php` `builtins()` (L544-556): add `'arrows' => true`, `'pagination' => true`.
- [x] **4.2** `class-admin.php`: register controls field in `cwc_carousel_main`; add `render_controls_field()` with two labeled checkboxes `name=cwc_carousel_options[arrows]`/`[pagination]`, each **preceded by a hidden companion `<input type="hidden" name="cwc_carousel_options[...]" value="0" />`** so `false` is reachable.
- [x] **4.3** `class-admin.php` `sanitize_options()` (L492-505): add `arrows`/`pagination` via the `isset(...)`-else-`$built` `parse_bool()` path, mirroring `buy` (L493) â€” lossless round-trip.
- [x] **4.4** `class-admin.php` `render_buy_field()` (L343): prepend the same hidden `value="0"` companion before the `buy` checkbox (pre-existing uncheck no-op bug fix).

## Phase 5: Frontend (FA-6)
- [x] **5.1** `assets/js/frontend.js` `buildOptions()` (L92-118): include `navigation:{nextEl,prevEl}` only when `config.arrows` truthy; `pagination:{el,clickable:true}` only when `config.pagination` truthy; treat **undefined** (missing config / legacy) as enabled so `DEFAULT_OPTIONS` path keeps both controls.

## Phase 6: Verification
- [x] **6.1** `composer phpcs` green on changed PHP; `php -l` each of class-settings/class-shortcode/class-renderer/class-admin; `node --check assets/js/frontend.js`.
- [ ] **6.2** wp-env: default shows arrows+dots; `[cwc_carousel arrows="false"]` hides prev/next only (SC-6); `pagination="false"` hides dots only; both-false shows neither but slides render; admin checkboxes round-trip `false`; legacy no-config container still shows arrows+dots (FA-6).

## Ordering & Notes
- Dependency: 1.x â†’ 2.1 â†’ 3.x; 4.1â†’4.4; 5.1 depends on 3.x (data-attr) + 1.3 for selectors. 6.1/6.2 last.
- BC: defaults both `true`; `data-cwc-config` gains two keys even for defaults (byte-diff, behavior identical â€” approved).
- Gates: manual wp-env smoke is the gate (`strict_tdd:false`); `phpcs` always-green.
