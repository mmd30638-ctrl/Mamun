/**
 * Instant module hops — feels like offline desktop software.
 * Prefetch on pointerdown. Speculation Rules when the browser supports them.
 * No sale / stock / ledger / due HTML is written to Cache Storage.
 */
(function () {
    'use strict';

    if (window.AME_MODULE_NAVIGATION) {
        return;
    }
    window.AME_MODULE_NAVIGATION = true;

    var warmed = Object.create(null);
    var bar;

    function ensureBar() {
        if (bar) {
            return bar;
        }
        var style = document.createElement('style');
        style.textContent = 'html[data-ame-navigating]{cursor:progress}#ameModuleNavProgress{position:fixed;z-index:1000000;left:0;top:0;width:0;height:3px;background:linear-gradient(90deg,#003f8f,#0f766e,#f59e0b);box-shadow:0 1px 6px rgba(0,63,143,.3);transition:width .12s linear;pointer-events:none}#ameModuleNavProgress.is-on{width:78%}#ameModuleNavProgress.is-done{width:100%;opacity:0;transition:width .08s linear,opacity .14s ease .08s}';
        document.head.appendChild(style);
        bar = document.createElement('div');
        bar.id = 'ameModuleNavProgress';
        bar.setAttribute('role', 'progressbar');
        bar.setAttribute('aria-label', 'পরবর্তী পেজ লোড হচ্ছে');
        document.body.appendChild(bar);
        return bar;
    }

    function sameOriginGet(href) {
        if (!href) return null;
        var raw = String(href);
        if (!raw || raw.charAt(0) === '#' || /^(javascript:|mailto:|tel:)/i.test(raw)) return null;
        try {
            var url = new URL(raw, window.location.href);
            if (url.origin !== window.location.origin) return null;
            if (url.protocol !== 'http:' && url.protocol !== 'https:') return null;
            return url;
        } catch (e) {
            return null;
        }
    }

    function isSafeNav(url) {
        if (!url) return false;
        var path = url.pathname || '';
        if (/logout\.php|process_|download|factory_reset|backup_/i.test(path)) return false;
        return true;
    }

    function warm(href) {
        var url = typeof href === 'string' ? sameOriginGet(href) : href;
        if (!url || !isSafeNav(url)) return;
        var path = url.pathname || '';
        if (/\.php$/i.test(path)) return;
        var key = url.origin + url.pathname + url.search;
        if (warmed[key]) return;
        warmed[key] = 1;
        try {
            fetch(url.href, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Purpose: 'prefetch' }
            }).catch(function () {});
        } catch (e) {}
    }
    window.AME_NAV_WARM = warm;

    function injectSpeculation() {
        if (document.getElementById('ameNavSpec')) return;
        var supports = window.HTMLScriptElement
            && HTMLScriptElement.supports
            && HTMLScriptElement.supports('speculationrules');
        if (!supports) return;
        try {
            var el = document.createElement('script');
            el.id = 'ameNavSpec';
            el.type = 'speculationrules';
            el.textContent = JSON.stringify({
                prefetch: [{
                    where: { href_matches: '/assets/*' },
                    eagerness: 'conservative'
                }]
            });
            document.head.appendChild(el);
        } catch (e) {}
    }

    function fromLink(link) {
        if (!link) return null;
        if (link.target && link.target.toLowerCase() !== '_self' && link.target.toLowerCase() !== '') return null;
        if (link.hasAttribute('download') || link.getAttribute('data-no-loading') === '1') return null;
        return sameOriginGet(link.getAttribute('href') || link.href);
    }

    function internalNavigation(link, event) {
        if (!link || event.defaultPrevented || event.button > 0) return false;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
        var url = fromLink(link);
        if (!url) return false;
        return url.href !== window.location.href;
    }

    var warmTimer = 0;
    document.addEventListener('pointerdown', function (event) {
        var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
        var url = fromLink(link);
        var home = event.target && event.target.closest
            ? event.target.closest('.hdr-i-home, .hdr-i-fresh')
            : null;
        window.clearTimeout(warmTimer);
        if (!url && !home) return;
        warmTimer = window.setTimeout(function () {
            if (url) warm(url);
            if (home) warm('/index.php');
        }, 450);
    }, true);

    document.addEventListener('click', function (event) {
        window.clearTimeout(warmTimer);
        var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
        if (!internalNavigation(link, event)) return;
        window.requestAnimationFrame(function () {
            ensureBar().classList.add('is-on');
            document.documentElement.setAttribute('data-ame-navigating', '1');
        });
    }, true);

    window.addEventListener('pageshow', function () {
        var el = ensureBar();
        el.classList.remove('is-on');
        el.classList.add('is-done');
        document.documentElement.removeAttribute('data-ame-navigating');
        window.setTimeout(function () { el.classList.remove('is-done'); }, 280);
    });

    function boot() {
        ensureBar();
        injectSpeculation();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
