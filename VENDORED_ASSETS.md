# VENDORED_ASSETS.md

This file records the third-party assets vendored into this plugin so the plugin
ships as a self-contained ZIP with **no CDN, runtime Composer, Node, or build
step** (FA-1). It is the pin record and update guide for every vendored library.

---

## Swiper 14.0.7

| Field | Value |
|-------|-------|
| Library | [Swiper](https://swiperjs.com/) |
| Version | **14.0.7** (pinned) |
| License | MIT |
| Files | `assets/vendor/swiper/swiper-bundle.min.js` (151,614 B) |
|        | `assets/vendor/swiper/swiper-bundle.min.css` (14,610 B) |
| Handle version argument | `14.0.7` (on `cwc-swiper` style + script, prevents silent updates) |
| Enqueued | Only on pages where a carousel renders (on-demand, FA-2) |

### Why vendored

The Swiper compiled bundle is committed under `assets/vendor/swiper/` instead of
being loaded from a CDN so the plugin installs and runs offline with no external
request and no runtime build step. This makes the installed experience
deterministic and CDN-independent (FA-1 scenario: self-contained install).

### Acquisition method

The bundle was obtained from the npm registry via the **`npm pack swiper@14.0.7`**
workflow at dev time (design D4), then the two bundle files were committed directly:

- `package/swiper-bundle.min.js`  → `assets/vendor/swiper/swiper-bundle.min.js`
- `package/swiper-bundle.min.css` → `assets/vendor/swiper/swiper-bundle.min.css`

The packed tarball (`swiper-14.0.7.tgz`) SHA-1 was reported during the vendor
acquisition step:

```
cfdae71aec6174f01fc456d06f6e9703d6cf62a4
```

#### Current committed file checksums (SHA-1, re-verified at apply time)

```
assets/vendor/swiper/swiper-bundle.min.js   E143328978E71CB7ABF37CF6C61B08B9D254398B
assets/vendor/swiper/swiper-bundle.min.css  FC51F21167B0BCFA88BC5C3EFABF086574E74156
```

The `swiper-bundle.min.js` header banner reads `Swiper 14.0.7`, matching the pin.

---

## Browser baseline

Swiper 14 targets modern evergreen browsers. The plugin assumes the following
minimum browser versions:

| Browser   | Minimum |
|-----------|---------|
| Chrome    | 110+    |
| Edge      | 110+    |
| Safari    | 16.4+   |
| Firefox   | 110+    |

---

## v12.2.0 fallback

If a target site must support a browser older than the Swiper 14 baseline,
fall back to **Swiper 12.2.0**, which is API-compatible for this usage:

1. Re-vendor the two bundle files from `swiper@12.2.0` (same `npm pack` workflow).
2. Replace `assets/vendor/swiper/swiper-bundle.min.js` and `.css`.
3. Bump only the `cwc-swiper` handle version argument in
   `includes/class-assets.php` from `'14.0.7'` to `'12.2.0'`.
4. Update this record (version, sizes, acquisition SHA-1, file checksums) and
   re-run `composer phpcs`.

No other plugin code changes are required for the fallback.

---

## Update / upgrade procedure

When upgrading a vendored asset:

1. Re-run the acquisition step (`npm pack <package>@<version>`).
2. Replace the committed files under `assets/vendor/`.
3. Verify the bundle version banner matches the new pin.
4. Update the asset handle version argument(s) in `includes/class-assets.php`.
5. Update this record, including the file checksums, and re-verify with
   `composer phpcs` (zero errors).