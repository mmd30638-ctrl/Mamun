(function () {
    'use strict';
    if (window.AME_QA_API) return;
    window.AME_QA_API = true;

    function report(url, status, message) {
        if (!url || String(url).indexOf('client-error.php') !== -1) return;
        var id = 'API-' + Date.now().toString(36).toUpperCase();
        var payload = JSON.stringify({
            type: 'API',
            error_id: id,
            message: String(message || ('HTTP ' + status)).slice(0, 800),
            source: String(url).slice(0, 500),
            page: location.pathname + location.search,
            status: Number(status || 0)
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
                headers: {'Content-Type': 'application/json'},
                body: payload
            }).catch(function () {});
        } catch (ignore) {}
    }

    if (typeof window.fetch === 'function') {
        var origFetch = window.fetch;
        window.fetch = function (input, init) {
            var url = typeof input === 'string' ? input : (input && input.url) || '';
            return origFetch.apply(this, arguments).then(function (res) {
                if (res && res.status >= 400 && res.status !== 422) report(url, res.status, 'HTTP ' + res.status + ' ' + url);
                return res;
            }, function (err) {
                var aborted = err && (err.name === 'AbortError' || (init && init.signal && init.signal.aborted));
                if (!aborted) report(url, 0, 'নেটওয়ার্ক ফেল ' + url);
                throw err;
            });
        };
    }

    var origSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.send = function () {
        this.addEventListener('loadend', function () {
            if (this.status >= 400 && this.status !== 422) report(this.responseURL || '', this.status, 'HTTP ' + this.status);
        });
        return origSend.apply(this, arguments);
    };
})();
