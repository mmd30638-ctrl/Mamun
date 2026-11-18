/**
 * Auto-match any phone / tablet / desktop / foldable display.
 */
(function () {
    if (window.AME_DEVICE_DISPLAY) {
        return;
    }
    window.AME_DEVICE_DISPLAY = true;

    function viewportMeta() {
        var meta = document.querySelector('meta[name="viewport"]');
        if (!meta) {
            meta = document.createElement('meta');
            meta.setAttribute('name', 'viewport');
            document.head.appendChild(meta);
        }
        if (window.AME_SALE_DESKTOP_SITE) {
            return;
        }
        meta.setAttribute(
            'content',
            'width=device-width, initial-scale=1, viewport-fit=cover'
        );
    }

    function sync() {
        var root = document.documentElement;
        var vv = window.visualViewport;
        var w = Math.round((vv && vv.width) || window.innerWidth || root.clientWidth || 0);
        var h = Math.round((vv && vv.height) || window.innerHeight || root.clientHeight || 0);
        root.style.setProperty('--ame-screen-w', w + 'px');
        root.style.setProperty('--ame-screen-h', h + 'px');
        root.style.setProperty('--ame-vh', (h * 0.01) + 'px');
        root.style.setProperty('--ame-safe-top', 'env(safe-area-inset-top, 0px)');
        if (window.AME_SALE_DESKTOP_SITE || root.classList.contains('ame-phone-desktop')) {
            w = Math.max(w, 1366);
        }
        if (document.body) {
            document.body.classList.toggle('ame-screen-phone', w <= 760);
            document.body.classList.toggle('ame-screen-tablet', w >= 576 && w < 992);
            document.body.classList.toggle('ame-screen-desktop', w >= 992);
            document.body.classList.toggle('ame-screen-landscape', w > h);
            document.body.classList.add('ame-display-fit');
        }
    }

    function boot() {
        viewportMeta();
        sync();
    }

    boot();
    window.addEventListener('resize', sync, { passive: true });
    window.addEventListener('orientationchange', function () {
        setTimeout(sync, 80);
    });
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', sync, { passive: true });
        window.visualViewport.addEventListener('scroll', sync, { passive: true });
    }
    document.addEventListener('DOMContentLoaded', boot);
})();
