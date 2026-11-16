(function () {
    'use strict';
    if (window.AME_ERROR_BOUNDARY) return;
    window.AME_ERROR_BOUNDARY = true;

    var shown = false;
    var sent = Object.create(null);

    function makeId() {
        var random = Math.random().toString(16).slice(2, 8).toUpperCase();
        var stamp = new Date().toISOString().replace(/\D/g, '').slice(0, 14);
        return 'JS-' + stamp + '-' + random;
    }

    function internalPrevious() {
        try {
            var raw = sessionStorage.getItem('ame_nav_stack');
            var stack = raw ? JSON.parse(raw) : [];
            var current = location.pathname + location.search;
            for (var i = stack.length - 1; i >= 0; i--) {
                if (typeof stack[i] === 'string' && stack[i] !== current && stack[i].charAt(0) === '/') {
                    return stack[i];
                }
            }
        } catch (ignore) {}
        return '';
    }

    function report(id, message, source, line, column) {
        if (sent[id]) return;
        sent[id] = true;
        var payload = JSON.stringify({
            error_id: id,
            message: String(message || 'JavaScript error').slice(0, 800),
            source: String(source || '').slice(0, 500),
            line: Number(line || 0),
            column: Number(column || 0),
            page: location.pathname + location.search
        });
        try {
            if (navigator.sendBeacon) {
                navigator.sendBeacon('/client-error.php', new Blob([payload], {type: 'application/json'}));
                return;
            }
            fetch('/client-error.php', {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': window.AME_CSRF_TOKEN || ''},
                body: payload
            }).catch(function () {});
        } catch (ignore) {}
    }

    function benign(message) {
        return /ResizeObserver loop|Script error\./i.test(String(message || ''));
    }

    function resourceUrl(target) {
        if (!target || target === window) return '';
        return String(target.src || target.href || target.currentSrc || '');
    }

    function quietResource(url, tag) {
        if (tag === 'LINK' || tag === 'IMG') return true;
        return /fonts\.(googleapis|gstatic)\.com|googletagmanager|google-analytics/i.test(url);
    }

    function show(id) {
        if (shown || !document.body) return;
        shown = true;
        var box = document.createElement('aside');
        box.setAttribute('role', 'alert');
        box.style.cssText = 'position:fixed;z-index:2147483646;left:50%;top:calc(var(--ame-header-h, 64px) + 8px);transform:translateX(-50%);width:min(560px,calc(100vw - 24px));box-sizing:border-box;background:#fff;border:1px solid #fecaca;border-left:7px solid #dc2626;border-radius:14px;padding:14px 15px;box-shadow:0 16px 42px rgba(15,23,42,.22);font:14px/1.5 system-ui,-apple-system,sans-serif;color:#10233f';
        box.innerHTML = '<strong style="display:block;color:#b91c1c;font-size:16px">এই পেজের একটি কাজ থেমে গেছে</strong>'
            + '<span>সংরক্ষিত তথ্য বদলানো হয়নি। আবার চেষ্টা করতে পারেন।</span>'
            + '<small style="display:block;margin-top:5px;color:#64748b">Error ID: ' + id + '</small>';
        var actions = document.createElement('div');
        actions.style.cssText = 'display:flex;gap:7px;flex-wrap:wrap;margin-top:10px';

        function button(text, color, handler) {
            var el = document.createElement('button');
            el.type = 'button';
            el.textContent = text;
            el.style.cssText = 'border:0;border-radius:8px;padding:8px 12px;background:' + color + ';color:#fff;font-weight:800;cursor:pointer';
            el.addEventListener('click', handler);
            return el;
        }
        actions.appendChild(button('আবার চেষ্টা', '#0e8f52', function () { location.reload(); }));
        actions.appendChild(button('পেছনে', '#1565c0', function () {
            var previous = internalPrevious();
            if (previous) location.assign(previous);
            else if (history.length > 1) history.back();
            else location.assign('/index.php');
        }));
        actions.appendChild(button('বন্ধ করুন', '#475569', function () {
            box.remove();
            shown = false;
        }));
        box.appendChild(actions);
        document.body.appendChild(box);
    }

    window.addEventListener('error', function (event) {
        var target = event.target;
        var resource = resourceUrl(target);
        var tag = target && target.tagName ? String(target.tagName).toUpperCase() : '';
        if (resource && quietResource(resource, tag)) {
            return;
        }
        var message = resource ? ('Resource failed: ' + resource) : (event.message || 'JavaScript error');
        if (benign(message)) {
            return;
        }
        var id = makeId();
        report(id, message, event.filename || resource, event.lineno, event.colno);
        if (resource) {
            return;
        }
        if (!message || message === 'Script error.' || /chrome-extension:|moz-extension:/i.test(String(event.filename || ''))) {
            return;
        }
        show(id);
    }, true);

    window.addEventListener('unhandledrejection', function (event) {
        var reason = event.reason;
        var message = reason && reason.message ? reason.message : String(reason || 'Unhandled promise rejection');
        if (benign(message) || /AbortError|Failed to fetch|Load failed|NetworkError/i.test(message)) {
            return;
        }
        var id = makeId();
        report(id, message, '', 0, 0);
        show(id);
    });
})();
