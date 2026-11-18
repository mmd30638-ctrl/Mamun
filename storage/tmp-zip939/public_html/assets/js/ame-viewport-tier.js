/**
 * Global viewport tier — canonical breakpoints (locked).
 * Mobile < 576 | Tablet 576–991 | Desktop >= 992
 * Works with ame-desktop-site.js (Chrome “Desktop site” on phone).
 * DO NOT change thresholds without updating GLOBAL_RESPONSIVE_ARCHITECTURE.md
 */
(function () {
    'use strict';

    var BP = {
        mobileMax: 575.98,
        tabletMin: 576,
        tabletMax: 991.98,
        desktopMin: 992,
        phoneDesktopLayout: 1366
    };
    window.AME_RESPONSIVE_BP = BP;

    window.ameGetLayoutWidth = function () {
        var root = document.documentElement;
        var layoutVar = parseFloat(root.style.getPropertyValue('--ame-layout-width')) || 0;
        var w = Math.max(layoutVar, root.clientWidth || 0, window.innerWidth || 0);
        if (window.AME_SALE_DESKTOP_SITE || root.classList.contains('ame-phone-desktop')) {
            w = Math.max(w, BP.phoneDesktopLayout);
        }
        return w;
    };

    window.ameApplyGlobalViewportTier = function () {
        if (typeof window.ameApplyDeviceClass === 'function') {
            window.ameApplyDeviceClass();
        }

        var width = window.ameGetLayoutWidth();
        var root = document.documentElement;
        var body = document.body;
        var tier = width <= BP.mobileMax ? 'mobile' : (width < BP.desktopMin ? 'tablet' : 'desktop');

        root.setAttribute('data-ame-tier', tier);
        root.setAttribute(
            'data-ame-bp',
            tier === 'mobile' ? 'xs' : (tier === 'tablet' ? 'md' : 'lg')
        );
        root.style.setProperty('--ame-viewport-width', width + 'px');
        root.style.setProperty('--ame-gr-tier', tier);

        if (body) {
            body.classList.toggle('ame-screen-phone', width <= BP.mobileMax);
            body.classList.toggle('ame-screen-tablet', width >= BP.tabletMin && width < BP.desktopMin);
            body.classList.toggle('ame-screen-desktop', width >= BP.desktopMin);
            body.classList.toggle('ame-tier-mobile', tier === 'mobile');
            body.classList.toggle('ame-tier-tablet', tier === 'tablet');
            body.classList.toggle('ame-tier-desktop', tier === 'desktop');
        }

        var posTier = width >= BP.desktopMin ? 'wide' : (width > BP.mobileMax ? 'tablet' : 'phone');
        root.setAttribute('data-ame-pos-tier', posTier);
    };

    window.ameApplyGlobalViewportTier();
    document.addEventListener('DOMContentLoaded', window.ameApplyGlobalViewportTier);
    window.addEventListener('resize', window.ameApplyGlobalViewportTier);
    window.addEventListener('orientationchange', function () {
        setTimeout(window.ameApplyGlobalViewportTier, 50);
    });
})();