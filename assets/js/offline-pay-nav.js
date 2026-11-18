/**
 * অফলাইনে দোকানের লিংক ক্যাশ বা স্ট্যাটিক পেজে খোলে।
 */
(function (global) {
    'use strict';

    function offlinePayUrl(fromHref) {
        var id = '';
        try {
            id = new URL(fromHref, global.location.origin).searchParams.get('id') || '';
        } catch (e) {}
        return '/offline-payment.html' + (id ? ('?id=' + encodeURIComponent(id)) : '');
    }

    function fallbackForHref(href) {
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return '';
        var path = href;
        try { path = new URL(href, global.location.origin).pathname; } catch (e) {}
        if (path.indexOf('login.php') !== -1 || path.indexOf('logout.php') !== -1) return '';
        if (path.indexOf('payment-receive.php') !== -1) return offlinePayUrl(href);
        if (path.indexOf('/modules/sales/pos.php') !== -1) return '/offline-sale.html';
        if (path.indexOf('/modules/customers/') !== -1) return '/offline-customers.html';
        if (path.indexOf('/modules/inventory/add_product.php') !== -1) return '/offline-product-add.html';
        if (path.indexOf('/modules/inventory/') !== -1) return '/offline-products.html';
        if (path.indexOf('/modules/purchase/') !== -1) return '/offline-purchase.html';
        if (path.indexOf('/modules/expenses/') !== -1) return '/offline-expense.html';
        if (path.indexOf('/modules/workshop/') !== -1) return '/offline-workshop.html';
        if (path.indexOf('/modules/suppliers/pay.php') !== -1) return '/offline-supplier-pay.html';
        if (path.indexOf('/modules/suppliers/') !== -1) return '/offline-supplier-pay.html';
        if (path.indexOf('/modules/accounts/transfer.php') !== -1) return '/offline-transfer.html';
        if (path.indexOf('/modules/accounts/add_transaction.php') !== -1) return '/offline-transfer.html';
        if (path.indexOf('/modules/pulse/') !== -1) return '/offline-pulse.html';
        if (path === '/index.php' || path === '/') return '/offline.html';
        if (path.indexOf('.php') !== -1) return '/offline.html';
        return '';
    }

    function rewriteLinks() {
        if (global.navigator.onLine) return;
        var links = document.querySelectorAll('a[href]');
        Array.prototype.forEach.call(links, function (a) {
            var href = a.getAttribute('href') || '';
            var fallback = fallbackForHref(href);
            if (!fallback) return;
            if (!global.caches) {
                a.setAttribute('href', fallback);
                return;
            }
            var abs = href;
            try { abs = new URL(href, global.location.origin).href; } catch (e) {}
            global.caches.match(abs, { ignoreSearch: true }).then(function (hit) {
                if (!hit) a.setAttribute('href', fallback);
            }).catch(function () {
                a.setAttribute('href', fallback);
            });
        });
    }

    function refreshNetBadge() {
        var btn = document.getElementById('ame-net-icon');
        var dot = document.getElementById('ame-net-dot');
        if (!btn || !dot) return;
        var pendingSales = (global.AmeOfflineQueue && global.AmeOfflineQueue.pendingCount)
            ? global.AmeOfflineQueue.pendingCount() : 0;
        var pendingPay = (global.AmeOfflinePay && global.AmeOfflinePay.pendingCount)
            ? global.AmeOfflinePay.pendingCount() : 0;
        var pendingWrite = (global.AmeOfflineWrite && global.AmeOfflineWrite.pendingCount)
            ? global.AmeOfflineWrite.pendingCount() : 0;
        var pending = pendingSales + pendingPay + pendingWrite;
        var online = global.navigator.onLine;
        btn.classList.remove('is-off', 'is-sync');
        if (!online) {
            dot.style.background = '#c62828';
            btn.classList.add('is-off');
            btn.title = 'অফলাইন' + (pending ? ' (' + pending + ')' : '');
        } else if (pending > 0) {
            dot.style.background = '#1D4ED8';
            btn.classList.add('is-sync');
            btn.title = 'সিঙ্ক হচ্ছে (' + pending + ')';
        } else {
            dot.style.background = '#0B8043';
            btn.title = 'অনলাইন';
        }
    }

    function syncAll() {
        if (global.AmeOfflinePay && global.AmeOfflinePay.syncQueue) global.AmeOfflinePay.syncQueue();
        if (global.AmeOfflineQueue && global.AmeOfflineQueue.syncQueue) global.AmeOfflineQueue.syncQueue();
        if (global.AmeOfflineWrite && global.AmeOfflineWrite.syncQueue) global.AmeOfflineWrite.syncQueue();
    }

    document.addEventListener('click', function (e) {
        var a = e.target && e.target.closest ? e.target.closest('a') : null;
        if (!a || global.navigator.onLine) return;
        var href = a.getAttribute('href') || '';
        var fallback = fallbackForHref(href);
        if (!fallback) return;
        e.preventDefault();
        var abs = href;
        try { abs = new URL(href, global.location.origin).href; } catch (err) {}
        if (!global.caches) {
            global.location.href = fallback;
            return;
        }
        global.caches.match(abs, { ignoreSearch: true }).then(function (hit) {
            global.location.href = hit ? abs : fallback;
        }).catch(function () {
            global.location.href = fallback;
        });
    }, true);

    document.addEventListener('DOMContentLoaded', rewriteLinks);
    global.addEventListener('offline', rewriteLinks);
    global.addEventListener('online', function () {
        rewriteLinks();
        refreshNetBadge();
        syncAll();
    });
    global.addEventListener('ame-offline-pay-change', refreshNetBadge);
    global.addEventListener('ame-offline-sale-change', refreshNetBadge);
    global.addEventListener('ame-offline-write-change', refreshNetBadge);
    rewriteLinks();
    refreshNetBadge();
})(window);
