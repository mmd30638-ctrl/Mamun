/**
 * যে পেজেই থাকুন, নেট ফিরলে বিক্রয়/পেমেন্ট/ক্রয়/খরচ/পণ্য/জব একসাথে সার্ভারে যায়।
 */
(function (global) {
    'use strict';

    function syncAll() {
        if (global.AmeOfflineSync && global.AmeOfflineSync.all) {
            global.AmeOfflineSync.all();
            return;
        }
        if (global.AmeOfflinePay && global.AmeOfflinePay.syncQueue) global.AmeOfflinePay.syncQueue();
        if (global.AmeOfflineQueue && global.AmeOfflineQueue.syncQueue) global.AmeOfflineQueue.syncQueue();
        if (global.AmeOfflineWrite && global.AmeOfflineWrite.syncQueue) global.AmeOfflineWrite.syncQueue();
    }

    global.addEventListener('online', syncAll);
    global.addEventListener('pageshow', function () {
        if (global.navigator.onLine) syncAll();
    });
    global.addEventListener('visibilitychange', function () {
        if (!global.document.hidden && global.navigator.onLine) syncAll();
    });
    if (global.navigator && global.navigator.onLine) {
        setTimeout(syncAll, 700);
        if (global.navigator.serviceWorker) {
            try { global.navigator.serviceWorker.register('/sw.js?v=10.98.320'); } catch (e) {}
        }
    }
    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest
            ? e.target.closest('#go, #goTxn')
            : null;
        if (!btn || !global.AmeSubmitWait) {
            return;
        }
        global.AmeSubmitWait.show(btn);
    }, true);
    global.ameWaitSend = function (promise) {
        var p = promise;
        if (global.AmeSubmitWait && typeof global.AmeSubmitWait.follow === 'function') {
            p = global.AmeSubmitWait.follow(promise);
        }
        return Promise.resolve(p).catch(function (err) {
            return { error: (err && err.message) || 'নেট যায়নি' };
        });
    };
    setInterval(function () {
        if (!global.navigator.onLine) return;
        var pending = 0;
        try { pending += (global.AmeOfflineQueue && global.AmeOfflineQueue.pendingCount()) || 0; } catch (e) {}
        try { pending += (global.AmeOfflinePay && global.AmeOfflinePay.pendingCount()) || 0; } catch (e) {}
        try { pending += (global.AmeOfflineWrite && global.AmeOfflineWrite.pendingCount()) || 0; } catch (e) {}
        if (pending > 0) syncAll();
    }, 20000);
})(window);
