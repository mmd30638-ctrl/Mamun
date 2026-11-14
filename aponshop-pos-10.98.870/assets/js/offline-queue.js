/**
 * অফলাইন ক্যাটালগ + বিক্রয় কিউ। নেট ফিরলে বিক্রয় একবারই সার্ভারে যায়।
 */
(function (global) {
    'use strict';

    var PROD_KEY = 'ame_offline_products';
    var CUST_KEY = 'ame_offline_customers';
    var ACCT_KEY = 'ame_offline_accounts';
    var SUP_KEY = 'ame_offline_suppliers';
    var QUEUE_KEY = 'ame_offline_sales_queue';
    var WRITE_KEY = 'ame_offline_write_queue';
    var LOCK_KEY = 'ame_offline_sync_lock';
    var CATALOG_URL = '/modules/sales/offline_catalog.php';
    var SALE_URL = '/modules/sales/process_sale.php';
    var CUST_URL = '/modules/customers/actions/process_customer.php';
    var PUR_URL = '/modules/purchase/process_purchase.php';
    var EXP_URL = '/modules/expenses/process_add.php';
    var PROD_URL = '/modules/inventory/process_product.php';
    var WS_URL = '/modules/workshop/job_process.php';
    var SUPPAY_URL = '/modules/suppliers/process_pay.php';
    var SUPADD_URL = '/modules/suppliers/process_add.php';
    var XFER_URL = '/modules/accounts/process_transfer.php';
    var TXN_URL = '/modules/accounts/actions/process_transaction.php';
    var PING = '/modules/customers/actions/session_ping.php';
    var listeners = [];
    var UI_VER = '11.00.0';
    var UI_FLAG = 'ame_offline_ui_ver';
    var KEEP_CACHE = 'ame-pwa-v1098261';

    (function dropStaleOfflineShell() {
        if (!global.navigator || !global.navigator.onLine) return;
        var seen = '';
        try { seen = localStorage.getItem(UI_FLAG) || ''; } catch (e) {}
        if (seen === UI_VER) return;
        var finished = false;
        function finish() {
            if (finished) return;
            finished = true;
            try { localStorage.setItem(UI_FLAG, UI_VER); } catch (e) {}
            try { location.reload(); } catch (e) {}
        }
        var tasks = [];
        try {
            if (global.caches && caches.keys) {
                tasks.push(caches.keys().then(function (keys) {
                    return Promise.all(keys.map(function (k) {
                        if (k === KEEP_CACHE) return null;
                        return caches.delete(k);
                    }));
                }));
            }
        } catch (e) {}
        try {
            if (global.navigator.serviceWorker) {
                tasks.push(navigator.serviceWorker.register('/sw.js?v=10.98.320'));
            }
        } catch (e) {}
        Promise.all(tasks).then(finish).catch(finish);
        setTimeout(finish, 2500);
    })();

    function readJson(key, fallback) {
        try {
            var raw = localStorage.getItem(key);
            return raw ? JSON.parse(raw) : fallback;
        } catch (e) {
            return fallback;
        }
    }
    function writeJson(key, value) {
        try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) {}
    }

    function loadProducts() { return readJson(PROD_KEY, []); }
    function loadAccounts() { return readJson(ACCT_KEY, []); }
    function loadSuppliers() { return readJson(SUP_KEY, []); }
    function saveProducts(rows) { if (Array.isArray(rows)) writeJson(PROD_KEY, rows); }
    function saveAccounts(rows) { if (Array.isArray(rows)) writeJson(ACCT_KEY, rows); }
    function saveSuppliers(rows) { if (Array.isArray(rows)) writeJson(SUP_KEY, rows); }
    function saveCustomers(rows) {
        if (!Array.isArray(rows)) return;
        writeJson(CUST_KEY, rows);
        if (global.AmeOfflinePay && global.AmeOfflinePay.saveCustomers) {
            global.AmeOfflinePay.saveCustomers(rows);
        }
    }

    function searchProducts(query) {
        var q = String(query || '').trim().toLowerCase();
        if (!q) return [];
        var list = loadProducts();
        var exact = [];
        var hits = [];
        for (var i = 0; i < list.length; i++) {
            var p = list[i];
            var sku = String(p.sku || '').toLowerCase();
            var barcode = String(p.barcode || '').toLowerCase();
            var id = String(p.id);
            if (sku === q || barcode === q || id === q) {
                exact.push(p);
                continue;
            }
            var blob = ((p.name || '') + ' ' + sku + ' ' + (p.oem_code || '') + ' ' + (p.supplier_code || '') + ' ' + barcode + ' ' + id).toLowerCase();
            if (blob.indexOf(q) !== -1) {
                hits.push(p);
            }
        }
        return exact.concat(hits);
    }

    function prefetchShell() {
        if (!global.navigator.onLine) return;
        try {
            var shellAt = parseInt(sessionStorage.getItem('ame_shell_at') || '0', 10);
            if (shellAt > 0 && (Date.now() - shellAt) < 30 * 60 * 1000) return;
            sessionStorage.setItem('ame_shell_at', String(Date.now()));
        } catch (e0) {}
        [
            '/offline.html',
            '/offline-fix.html',
            '/offline-sale.html',
            '/offline-payment.html',
            '/offline-customers.html',
            '/offline-products.html',
            '/assets/css/offline-app.css',
            '/assets/js/offline-boot.js'
        ].forEach(function (url) {
            fetch(url, { credentials: 'same-origin' }).catch(function () {});
        });
    }

    function refreshCatalog() {
        if (!global.navigator.onLine) {
            return Promise.resolve({
                products: loadProducts(),
                customers: readJson(CUST_KEY, []),
                accounts: loadAccounts()
            });
        }
        return fetch(CATALOG_URL, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.status === 'success') {
                    if (data.products) saveProducts(data.products);
                    if (data.customers) saveCustomers(data.customers);
                    if (data.accounts) saveAccounts(data.accounts);
                    if (data.suppliers) saveSuppliers(data.suppliers);
                    try { localStorage.setItem('ame_offline_catalog_at', String(Date.now())); } catch (e) {}
                }
                return data || {};
            })
            .catch(function () {
                return { products: loadProducts(), customers: readJson(CUST_KEY, []), accounts: loadAccounts() };
            });
    }

    function getQueue() { return readJson(QUEUE_KEY, []); }
    function notifyListeners(count) {
        listeners.forEach(function (fn) {
            try { fn(count); } catch (e) {}
        });
        try { global.dispatchEvent(new Event('ame-offline-sale-change')); } catch (e) {}
        try { global.dispatchEvent(new Event('ame-offline-pay-change')); } catch (e) {}
        try { global.dispatchEvent(new Event('ame-offline-write-change')); } catch (e) {}
    }
    function saveQueue(queue) {
        writeJson(QUEUE_KEY, queue);
        notifyListeners(queue.length);
    }
    function onQueueChange(fn) {
        listeners.push(fn);
        try { fn(getQueue().length); } catch (e) {}
    }
    function makeRef() {
        return 'OFF-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
    }
    function pendingCount() { return getQueue().length; }

    function queueSale(saleData) {
        var queue = getQueue();
        var ref = (saleData && saleData.offline_client_ref) ? String(saleData.offline_client_ref) : makeRef();
        if (queue.some(function (item) { return item.client_ref === ref; })) {
            return ref;
        }
        queue.push({
            client_ref: ref,
            queued_at: new Date().toISOString(),
            payload: Object.assign({}, saleData || {}, { offline_client_ref: ref, action: 'complete' })
        });
        saveQueue(queue);
        return ref;
    }

    function acquireLock() {
        try {
            var now = Date.now();
            var prev = parseInt(localStorage.getItem(LOCK_KEY) || '0', 10);
            if (prev && now - prev < 20000) return false;
            localStorage.setItem(LOCK_KEY, String(now));
            return true;
        } catch (e) { return true; }
    }
    function releaseLock() {
        try { localStorage.removeItem(LOCK_KEY); } catch (e) {}
    }

    function refreshCsrf() {
        return fetch(PING, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.csrf) global.AME_CSRF_TOKEN = data.csrf;
                return data;
            })
            .catch(function () { return null; });
    }

    function postSale(payload) {
        return fetch(SALE_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json; charset=UTF-8', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        });
    }

    function sendSale(saleData) {
        var payload = Object.assign({}, saleData || {});
        if (!payload.offline_client_ref) payload.offline_client_ref = makeRef();
        payload.action = 'complete';
        if (!global.navigator.onLine) {
            return Promise.resolve({ queued: true, ref: queueSale(payload) });
        }
        payload.csrf_token = global.AME_CSRF_TOKEN || payload.csrf_token || '';
        return refreshCsrf().then(function () {
            payload.csrf_token = global.AME_CSRF_TOKEN || payload.csrf_token || '';
            return postSale(payload);
        }).then(function (res) {
            return res.json().then(function (data) { return { data: data }; }).catch(function () { return { data: null }; });
        }).then(function (pack) {
            if (pack.data && (pack.data.status === 'success' || pack.data.replayed === true)) {
                return { ok: true, sale_id: pack.data.sale_id, replayed: !!pack.data.replayed };
            }
            if (pack.data && pack.data.status === 'confirm' && pack.data.needs_confirm) {
                return {
                    needs_confirm: true,
                    message: pack.data.message || 'আবার সাবমিট করবেন?',
                    warnings: pack.data.warnings || []
                };
            }
            if (pack.data && pack.data.status === 'error') {
                return { error: pack.data.message || 'বিক্রয় যায়নি' };
            }
            return { queued: true, ref: queueSale(payload) };
        }).catch(function () {
            return { queued: true, ref: queueSale(payload) };
        });
    }

    var syncing = false;
    function syncQueue(endpointUrl) {
        var url = endpointUrl || SALE_URL;
        if (syncing || !global.navigator.onLine || !acquireLock()) return Promise.resolve();
        var queue = getQueue();
        if (queue.length === 0) {
            releaseLock();
            return Promise.resolve();
        }
        syncing = true;
        var remaining = queue.slice();
        var succeeded = [];
        return refreshCsrf().then(function () { return next(); });

        function next() {
            if (remaining.length === 0) {
                syncing = false;
                saveQueue(getQueue().filter(function (item) {
                    return succeeded.indexOf(item.client_ref) === -1;
                }));
                releaseLock();
                refreshCatalog();
                return Promise.resolve();
            }
            var item = remaining.shift();
            var payload = Object.assign({}, item.payload, {
                offline_client_ref: item.client_ref,
                csrf_token: global.AME_CSRF_TOKEN || (item.payload && item.payload.csrf_token) || ''
            });
            return fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json; charset=UTF-8', 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            }).then(function (res) { return res.json().catch(function () { return { status: 'error' }; }); })
                .then(function (data) {
                    if (data && (data.status === 'success' || data.replayed === true)) {
                        succeeded.push(item.client_ref);
                    }
                    return next();
                })
                .catch(function () {
                    syncing = false;
                    saveQueue(getQueue().filter(function (item2) {
                        return succeeded.indexOf(item2.client_ref) === -1;
                    }));
                    releaseLock();
                });
        }
    }

    function getWrites() { return readJson(WRITE_KEY, []); }
    function saveWrites(rows) {
        writeJson(WRITE_KEY, rows);
        notifyListeners(getQueue().length);
    }
    function queueWrite(kind, url, payload) {
        var rows = getWrites();
        var ref = (payload && (payload.idempotency_token || payload.offline_client_ref))
            ? String(payload.idempotency_token || payload.offline_client_ref)
            : makeRef();
        if (rows.some(function (item) { return item.ref === ref; })) return ref;
        if (payload && !payload.idempotency_token) payload.idempotency_token = ref;
        rows.push({
            ref: ref,
            kind: kind,
            url: url,
            queued_at: new Date().toISOString(),
            payload: payload || {}
        });
        saveWrites(rows);
        return ref;
    }
    function writePending() { return getWrites().length; }

    function postForm(url, payload) {
        var body = new URLSearchParams();
        Object.keys(payload || {}).forEach(function (key) {
            var value = payload[key];
            if (value == null || value === false) return;
            if (value === true) {
                body.set(key, '1');
                return;
            }
            body.set(key, typeof value === 'object' ? JSON.stringify(value) : String(value));
        });
        if (global.AME_CSRF_TOKEN) body.set('csrf_token', global.AME_CSRF_TOKEN);
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': global.AME_CSRF_TOKEN || '' },
            body: body,
            redirect: 'follow'
        });
    }
    function formLooksOk(res, text) {
        if (!res) return false;
        if (res.status === 401 || res.status === 403 || res.status >= 500) return false;
        var finalUrl = '';
        try { finalUrl = String(res.url || ''); } catch (e) {}
        if (finalUrl.indexOf('login.php') !== -1 || finalUrl.indexOf('logout.php') !== -1) return false;
        if (text && text.indexOf('❌') !== -1) return false;
        if (text && /name=["']password["']/i.test(text)) return false;
        if (text && text.indexOf('process_login.php') !== -1) return false;
        return res.status >= 200 && res.status < 400;
    }
    function sendFormJob(kind, url, payload) {
        payload = Object.assign({}, payload || {});
        if (!payload.idempotency_token) payload.idempotency_token = makeRef();
        if (!global.navigator.onLine) {
            return Promise.resolve({ queued: true, ref: queueWrite(kind, url, payload) });
        }
        return refreshCsrf().then(function () {
            payload.csrf_token = global.AME_CSRF_TOKEN || payload.csrf_token || '';
            return postForm(url, payload);
        }).then(function (res) {
            return res.text().then(function (text) { return { res: res, text: text }; });
        }).then(function (pack) {
            if (formLooksOk(pack.res, pack.text)) return { ok: true, ref: payload.idempotency_token };
            if (pack.text && pack.text.indexOf('❌') !== -1) {
                return { error: 'সার্ভার প্রত্যাখ্যান করেছে' };
            }
            return { queued: true, ref: queueWrite(kind, url, payload) };
        }).catch(function () {
            return { queued: true, ref: queueWrite(kind, url, payload) };
        });
    }

    var writeSyncing = false;
    function syncWrites() {
        if (writeSyncing || !global.navigator.onLine) return Promise.resolve();
        var rows = getWrites();
        if (!rows.length) return Promise.resolve();
        writeSyncing = true;
        var remaining = rows.slice();
        var ok = [];
        return refreshCsrf().then(function () { return next(); });
        function next() {
            if (!remaining.length) {
                writeSyncing = false;
                saveWrites(getWrites().filter(function (item) { return ok.indexOf(item.ref) === -1; }));
                return Promise.resolve();
            }
            var item = remaining.shift();
            var payload = Object.assign({}, item.payload, {
                csrf_token: global.AME_CSRF_TOKEN || (item.payload && item.payload.csrf_token) || ''
            });
            return postForm(item.url, payload).then(function (res) {
                return res.text().then(function (text) { return { res: res, text: text }; });
            }).then(function (pack) {
                if (formLooksOk(pack.res, pack.text)) ok.push(item.ref);
                return next();
            }).catch(function () {
                writeSyncing = false;
                saveWrites(getWrites().filter(function (item2) { return ok.indexOf(item2.ref) === -1; }));
            });
        }
    }

    function searchCustomers(query) {
        var q = String(query || '').trim().toLowerCase();
        var list = readJson(CUST_KEY, []);
        if (!q) return list.slice(0, 40);
        return list.filter(function (c) {
            return (c.name || '').toLowerCase().indexOf(q) !== -1
                || (c.phone || '').indexOf(q) !== -1
                || (c.code || '').toLowerCase().indexOf(q) !== -1;
        }).slice(0, 40);
    }

    function fillPosSelects() {
        var sel = global.document && global.document.getElementById('customer_id');
        if (sel) {
            var rows = readJson(CUST_KEY, []);
            var have = {};
            Array.prototype.forEach.call(sel.options, function (opt) { have[String(opt.value)] = true; });
            rows.forEach(function (c) {
                if (!c.id || have[String(c.id)]) return;
                var opt = global.document.createElement('option');
                opt.value = c.id;
                opt.textContent = (c.name || '') + (c.phone ? ' (' + c.phone + ')' : '');
                sel.appendChild(opt);
            });
        }
    }

    global.AmeOfflineCatalog = {
        refresh: refreshCatalog,
        searchProducts: searchProducts,
        searchCustomers: searchCustomers,
        loadProducts: loadProducts,
        loadCustomers: function () { return readJson(CUST_KEY, []); },
        loadAccounts: loadAccounts,
        loadSuppliers: loadSuppliers,
        saveProducts: saveProducts,
        saveCustomers: saveCustomers,
        saveAccounts: saveAccounts,
        saveSuppliers: saveSuppliers,
        fillPosSelects: fillPosSelects
    };

    global.AmeOfflineQueue = {
        queueSale: queueSale,
        sendSale: sendSale,
        pendingCount: pendingCount,
        syncQueue: syncQueue,
        onQueueChange: onQueueChange,
        makeRef: makeRef
    };

    function writeTarget(path) {
        if (!path) return null;
        if (path.indexOf('/modules/customers/actions/process_customer.php') !== -1) {
            return { kind: 'customer', url: CUST_URL };
        }
        if (path.indexOf('/modules/purchase/process_purchase.php') !== -1) {
            return { kind: 'purchase', url: PUR_URL };
        }
        if (path.indexOf('/modules/expenses/process_add.php') !== -1) {
            return { kind: 'expense', url: EXP_URL };
        }
        if (path.indexOf('/modules/inventory/process_product.php') !== -1) {
            return { kind: 'product', url: PROD_URL };
        }
        if (path.indexOf('/modules/workshop/job_process.php') !== -1) {
            return { kind: 'workshop', url: WS_URL };
        }
        if (path.indexOf('/modules/suppliers/process_pay.php') !== -1) {
            return { kind: 'supplier_pay', url: SUPPAY_URL };
        }
        if (path.indexOf('/modules/suppliers/process_add.php') !== -1) {
            return { kind: 'supplier', url: SUPADD_URL };
        }
        if (path.indexOf('/modules/accounts/process_transfer.php') !== -1) {
            return { kind: 'transfer', url: XFER_URL };
        }
        if (path.indexOf('/modules/accounts/actions/process_transaction.php') !== -1) {
            return { kind: 'transaction', url: TXN_URL };
        }
        return null;
    }

    function formToPayload(form) {
        var payload = {};
        var fd;
        try { fd = new FormData(form); } catch (e) { return payload; }
        fd.forEach(function (value, key) {
            if (typeof File !== 'undefined' && value instanceof File) return;
            payload[key] = value;
        });
        return payload;
    }

    function interceptLiveForms(e) {
        if (!global.document || global.navigator.onLine) return;
        var form = e.target;
        if (!form || form.tagName !== 'FORM') return;
        if (String(form.method || form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
        if (form.getAttribute('data-ame-offline') === 'skip') return;
        var action = form.getAttribute('action') || form.action || '';
        var path = action;
        try { path = new URL(action, global.location.href).pathname; } catch (err) {}
        if (path.indexOf('process_payment.php') !== -1 || path.indexOf('process_sale.php') !== -1) return;
        var target = writeTarget(path);
        if (!target) return;
        e.preventDefault();
        e.stopPropagation();
        var payload = formToPayload(form);
        if (target.kind === 'customer' && payload.is_customer == null) payload.is_customer = 1;
        sendFormJob(target.kind, target.url, payload).then(function (res) {
            var msg = (res && res.queued)
                ? ('ফোনে সংরক্ষিত। নেট ফিরলে সার্ভারে যাবে। রেফ: ' + res.ref)
                : ((res && res.ok) ? 'জমা হয়েছে।' : ((res && res.error) || 'সংরক্ষণ যায়নি'));
            try { global.alert(msg); } catch (err2) {}
        });
    }

    function injectNav() {
        var el = global.document && global.document.getElementById('ame-offline-nav');
        if (!el) return;
        el.innerHTML = [
            '<a href="/offline.html">মেনু</a>',
            '<a href="/offline-sale.html">বিক্রয়</a>',
            '<a href="/offline-payment.html">পেমেন্ট</a>',
            '<a href="/offline-customers.html">কাস্টমার</a>',
            '<a href="/offline-products.html">স্টক</a>',
            '<a href="/offline-purchase.html">ক্রয়</a>',
            '<a href="/offline-expense.html">খরচ</a>',
            '<a href="/offline-workshop.html">ওয়ার্কশপ</a>'
        ].join('');
    }

    global.AmeOfflineWrite = {
        sendCustomer: function (payload) { return sendFormJob('customer', CUST_URL, payload); },
        sendPurchase: function (payload) { return sendFormJob('purchase', PUR_URL, payload); },
        sendExpense: function (payload) { return sendFormJob('expense', EXP_URL, payload); },
        sendProduct: function (payload) { return sendFormJob('product', PROD_URL, payload); },
        sendWorkshop: function (payload) { return sendFormJob('workshop', WS_URL, payload); },
        sendSupplierPay: function (payload) { return sendFormJob('supplier_pay', SUPPAY_URL, payload); },
        sendSupplier: function (payload) { return sendFormJob('supplier', SUPADD_URL, payload); },
        sendTransfer: function (payload) { return sendFormJob('transfer', XFER_URL, payload); },
        sendTxn: function (payload) { return sendFormJob('transaction', TXN_URL, payload); },
        pendingCount: writePending,
        syncQueue: syncWrites,
        writeTarget: writeTarget,
        formToPayload: formToPayload
    };

    function syncAllQueues() {
        if (global.AmeOfflinePay && global.AmeOfflinePay.syncQueue) global.AmeOfflinePay.syncQueue();
        global.AmeOfflineQueue.syncQueue(SALE_URL);
        syncWrites();
    }
    global.AmeOfflineSync = { all: syncAllQueues };

    global.addEventListener('online', function () {
        syncAllQueues();
        prefetchShell();
        if (salesDeskOpen()) {
            refreshCatalog();
        }
    });
    global.addEventListener('storage', function (ev) {
        if (ev.key === QUEUE_KEY || ev.key === WRITE_KEY) {
            notifyListeners(getQueue().length);
        }
    });
    function salesDeskOpen() {
        var path = (global.location && global.location.pathname) || '';
        return /\/modules\/sales\/pos\.php$/i.test(path);
    }
    function catalogFresh() {
        try {
            var at = parseInt(localStorage.getItem('ame_offline_catalog_at') || '0', 10);
            return at > 0 && (Date.now() - at) < 10 * 60 * 1000;
        } catch (e) {
            return false;
        }
    }
    if (global.navigator && global.navigator.onLine) {
        setTimeout(function () {
            var onDesk = salesDeskOpen();
            if (!onDesk) {
                prefetchShell();
            }
            if (onDesk && !catalogFresh()) {
                refreshCatalog();
            }
            global.AmeOfflineQueue.syncQueue(SALE_URL);
            syncWrites();
        }, salesDeskOpen() ? 12000 : 4000);
    }
    if (global.document) {
        global.document.addEventListener('submit', interceptLiveForms, false);
        if (global.document.readyState === 'loading') {
            global.document.addEventListener('DOMContentLoaded', function () {
                fillPosSelects();
                injectNav();
            });
        } else {
            fillPosSelects();
            injectNav();
        }
    }
})(window);
