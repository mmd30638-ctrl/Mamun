# Global Responsive & Viewport Architecture (Permanent)

This document is the **freeze rule** for layout across the entire APONSHOP application. Future features, modules, and refactors **must not** remove, weaken, or override these rules without an explicit owner-approved architecture change.

## Requirements (locked)

| Tier | Viewport width | `data-ame-tier` | Body classes |
|------|----------------|-----------------|--------------|
| Mobile | `< 576px` | `mobile` | `ame-tier-mobile`, `ame-screen-phone` |
| Tablet | `576px – 991.98px` | `tablet` | `ame-tier-tablet`, `ame-screen-tablet` |
| Desktop | `≥ 992px` | `desktop` | `ame-tier-desktop`, `ame-screen-desktop` |

## Master viewport meta (all HTML pages)

```html
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
```

Injected automatically when missing via `includes/responsive_boot.php` (`AME_VIEWPORT_META`).

## Asset stack (do not reorder without updating this doc)

1. **Early head:** `assets/js/ame-desktop-site.js` — Chrome “Desktop site” on phone → `html.ame-phone-desktop`, `data-ame-device="pc"`.
2. **Early head:** `assets/js/ame-viewport-tier.js` — canonical breakpoints and tier classes.
3. **End of body / output buffer:** `responsive-fix.css`, `ame-android-universal.css`, `ame-modules-universal-responsive.css`, then **`ame-global-responsive-master.css` last**.

## Technical standards

- Prefer **CSS Grid**, **Flexbox**, `clamp()`, `rem`, `vw`/`vh`, and `%` for layout — avoid fixed `px` widths on shells and `#main-content` children.
- Tables: wrap with `.ame-auto-scroll-wrap` or `.table-responsive` (handled by `responsive-fix.js` on most pages).
- Mobile Chrome **Desktop site**: desktop UI via scaled viewport; horizontal overflow only inside intentional scroll wrappers, not on `body` for standard mobile view.

## Files that define this architecture (frozen)

| File | Role |
|------|------|
| `assets/css/ame-global-responsive-master.css` | Global fluid shell, gutters, overflow guards |
| `assets/js/ame-viewport-tier.js` | Breakpoint detection & tier attributes |
| `assets/js/ame-desktop-site.js` | Phone + “Desktop site” mode |
| `assets/js/responsive-fix.js` | Table cards, auto-wrap, calls tier helper |
| `includes/responsive_boot.php` | Pages without header/footer still get assets |
| `includes/global_responsive_standard.php` | Version constants & echo helper |
| `partials/header.php` / `partials/footer.php` | Master template wiring |
| `UI_UX_DEVICE_STANDARD.md` | Device behaviour (BN summary) |

## Developer checklist (new pages)

1. Use `partials/header.php` + `partials/footer.php`, or rely on `ame_responsive_boot()` for standalone HTML.
2. Do **not** add a second viewport meta with conflicting `width=`.
3. Do **not** set `body { overflow-x: hidden }` on module CSS without scoping.
4. Test: real phone portrait, tablet width, desktop, and phone with Chrome **Request desktop site**.

## Version

Architecture lock: **10.98.940** (`AME_GLOBAL_RESPONSIVE_VERSION` / `AME_ASSET_VERSION`).

## Critical pages (tier-aware overlay)

`assets/css/ame-pages-critical-responsive.css` — **mandatory** for:

| Page | Route / body class |
|------|---------------------|
| বিক্রয় (POS) | `/modules/sales/pos.php` · `ame-pos-terminal-page` `ame-sales-pro` |
| ক্রেতার পেমেন্ট গ্রহণ | `/modules/customers/payment-receive.php` · `ame-customer-payment-page` |

Related flows inherit the same header/footer stack (receipt, offline payment queue on payment page). New POS or payment UI **must not** bypass `ame-viewport-tier.js` or this overlay.