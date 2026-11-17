/* Permanent UI/UX Device Standard — every page.
   Mobile Chrome = mobile layout. Chrome Desktop site = desktop layout.
   See /UI_UX_DEVICE_STANDARD.md */
(function () {
    window.amePhoneDesktopSite = function () {
        var ua = navigator.userAgent || '';
        var touch = navigator.maxTouchPoints > 0;
        try {
            if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) {
                touch = true;
            }
        } catch (e) {}
        if (!touch) {
            return false;
        }
        var shortSide = Math.min(screen.width || 0, screen.height || 0);
        if (!(shortSide > 0 && shortSide <= 540)) {
            return false;
        }
        var mobileUa = /Mobile|iPhone|iPod|IEMobile|Opera Mini/i.test(ua);
        var uadMobile = null;
        try {
            if (navigator.userAgentData && typeof navigator.userAgentData.mobile === 'boolean') {
                uadMobile = navigator.userAgentData.mobile;
            }
        } catch (e2) {}
        if (uadMobile === false) {
            return true;
        }
        return !mobileUa;
    };

    /** স্ট্যান্ডঅ্যালোন মেমো/রসিদ — ভিউপোর্ট ১৩৬৬ স্কেল করলে মোবাইলে অর্ধেক খালি হয় */
    function ameMemoStandaloneNoDesktopViewport() {
        var root = document.documentElement;
        if (root && root.getAttribute('data-ame-memo-standalone') === '1') {
            return true;
        }
        var b = document.body;
        if (!b) {
            return false;
        }
        if (b.classList.contains('ame-customer-pay-receipt') || b.classList.contains('ame-payment-receipt')) {
            return true;
        }
        if (b.getAttribute('data-ame-memo-standalone') === '1') {
            return true;
        }
        if (document.getElementById('memo-container') && document.querySelector('.cpay-sheet')) {
            return true;
        }
        return false;
    }

    window.ameApplySaleDesktopViewport = function () {
        if (!window.amePhoneDesktopSite()) {
            return;
        }
        window.AME_SALE_DESKTOP_SITE = true;
        document.documentElement.classList.add('ame-phone-desktop');
        if (ameMemoStandaloneNoDesktopViewport()) {
            document.documentElement.classList.add('ame-memo-standalone-fit');
            var metaMemo = document.querySelector('meta[name="viewport"]');
            if (metaMemo) {
                metaMemo.setAttribute(
                    'content',
                    'width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=5, user-scalable=yes, viewport-fit=cover'
                );
            }
            window.AME_SALE_DESKTOP_SITE = false;
            return;
        }
        var design = 1366;
        if (!window.AME_SALE_DESKTOP_FIT) {
            var fitNow = window.innerWidth || screen.width || design;
            var side = Math.min(screen.width || fitNow, screen.height || fitNow);
            if (side > 0 && side <= 540) {
                fitNow = Math.min(fitNow, side);
            }
            window.AME_SALE_DESKTOP_FIT = fitNow;
        }
        var scale = Math.max(0.2, Math.min(1, window.AME_SALE_DESKTOP_FIT / design));
        var meta = document.querySelector('meta[name="viewport"]');
        if (!meta) {
            return;
        }
        meta.setAttribute(
            'content',
            'width=' + design + ', initial-scale=' + scale.toFixed(4) + ', minimum-scale=0.2, maximum-scale=5, user-scalable=yes, viewport-fit=cover'
        );
    };

    function isPhone() {
        if (window.AME_SALE_DESKTOP_SITE) {
            return false;
        }
        var ua = navigator.userAgent || '';
        try {
            if (navigator.userAgentData && navigator.userAgentData.mobile) {
                return true;
            }
        } catch (e) {}
        if (/Android.+Mobile|iPhone|iPod|webOS|BlackBerry|IEMobile|Opera Mini/i.test(ua)) {
            return true;
        }
        if (/Android/i.test(ua) && !/Tablet|iPad/i.test(ua) && Math.min(screen.width || 0, screen.height || 0) <= 550) {
            return true;
        }
        var coarse = false;
        try {
            coarse = window.matchMedia && window.matchMedia('(hover: none) and (pointer: coarse)').matches;
        } catch (e2) {}
        var shortSide = Math.min(screen.width || 0, screen.height || 0);
        return !!(coarse && shortSide && shortSide <= 550);
    }

    function isTablet() {
        if (window.AME_SALE_DESKTOP_SITE) {
            return false;
        }
        var ua = navigator.userAgent || '';
        if (/iPad|Android(?!.*Mobile)|Tablet|Silk/i.test(ua)) {
            return true;
        }
        var shortSide = Math.min(screen.width || 0, screen.height || 0);
        var coarse = false;
        try {
            coarse = window.matchMedia && window.matchMedia('(hover: none) and (pointer: coarse)').matches;
        } catch (e) {}
        return !!(coarse && shortSide > 550 && shortSide <= 900);
    }

    function deviceMode() {
        if (window.AME_SALE_DESKTOP_SITE) {
            return 'pc';
        }
        if (isPhone()) {
            return 'phone';
        }
        if (isTablet()) {
            return 'tablet';
        }
        var w = window.innerWidth || document.documentElement.clientWidth || 0;
        if (w < 576) {
            return 'phone';
        }
        if (w < 992) {
            return 'tablet';
        }
        return 'pc';
    }

    window.ameApplyDeviceClass = function () {
        window.ameApplySaleDesktopViewport();
        var mode = deviceMode();
        var root = document.documentElement;
        root.setAttribute('data-ame-device', mode);
        root.classList.remove('ame-device-phone', 'ame-device-tablet', 'ame-device-pc', 'ame-is-phone', 'ame-force-phone');
        root.classList.add('ame-device-' + mode);
        if (mode === 'phone') {
            root.classList.add('ame-is-phone', 'ame-force-phone');
        }
        if (document.body) {
            document.body.setAttribute('data-ame-device', mode);
            document.body.classList.remove('ame-device-phone', 'ame-device-tablet', 'ame-device-pc', 'ame-force-phone');
            document.body.classList.add('ame-device-' + mode);
            if (mode === 'phone') {
                document.body.classList.add('ame-force-phone');
            }
        }
    };

    window.ameApplyDeviceClass();
    document.addEventListener('DOMContentLoaded', window.ameApplyDeviceClass);
    window.addEventListener('resize', window.ameApplyDeviceClass);
    window.addEventListener('orientationchange', function () {
        setTimeout(window.ameApplyDeviceClass, 50);
    });
})();
