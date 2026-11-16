/**
 * POS ডিভাইস অটো সেটআপ — যে মেশিনে খুলবে, সেই অনুযায়ী tier (phone / tablet / wide).
 * ame-phone-desktop আলাদা শাখা নয়; একই responsive-fix-এর সাথে মিল রাখে।
 */
(function () {
    'use strict';
    if (window.AME_POS_DEVICE_SETUP) {
        return;
    }
    window.AME_POS_DEVICE_SETUP = true;

    function layoutWidth() {
        var root = document.documentElement;
        var w = Math.max(root.clientWidth || 0, window.innerWidth || 0);
        if (window.visualViewport && window.visualViewport.width > 0) {
            w = Math.max(w, window.visualViewport.width);
        }
        if (window.AME_SALE_DESKTOP_SITE || root.classList.contains('ame-phone-desktop')) {
            w = Math.max(w, 1366);
        }
        return w;
    }

    function applyPosDeviceTier() {
        var root = document.documentElement;
        var body = document.body;
        if (!body) {
            return;
        }
        var w = layoutWidth();
        var tier = w >= 1000 ? 'wide' : (w > 760 ? 'tablet' : 'phone');
        root.setAttribute('data-ame-pos-tier', tier);
        root.style.setProperty('--ame-layout-width', w + 'px');
        root.style.setProperty('--ame-viewport-width', w + 'px');
        body.classList.toggle('ame-pos-tier-phone', tier === 'phone');
        body.classList.toggle('ame-pos-tier-tablet', tier === 'tablet');
        body.classList.toggle('ame-pos-tier-wide', tier === 'wide');
        body.classList.toggle('ame-screen-phone', w <= 760);
        body.classList.toggle('ame-screen-tablet', w > 760 && w < 1100);
        body.classList.toggle('ame-screen-desktop', w >= 1100);
    }

    window.AME_POS_DEVICE_APPLY = applyPosDeviceTier;

    applyPosDeviceTier();
    document.addEventListener('DOMContentLoaded', applyPosDeviceTier);
    window.addEventListener('resize', applyPosDeviceTier);
    window.addEventListener('orientationchange', applyPosDeviceTier);
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', applyPosDeviceTier);
    }
})();