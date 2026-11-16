/**
 * অফলাইন কাস্টমার পেমেন্ট কিউ — নেট ফিরলে একবারই সার্ভারে যায়।
 */
(function (global) {
    'use strict';

    var QUEUE_KEY = 'ame_offline_pay_queue';
    var CUST_KEY = 'ame_offline_customers';
    var ACC_KEY = 'ame_offline_accounts';
    var LOCK_KEY = 'ame_offline_pay_lock';
    var ENDPOINT = '/modules/customers/actions/process_payment.php';
    var PING = '/modules/customers/actions/session_ping.php';
    var CUST_URL = '/modules/customers/actions/offline_customers.php';
    var listeners = [];

    function getQueue() {
        try {
            return JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]');
        } catch (e) {
            return [];
        }
    }

    function notify() {
        var n = getQueue().length;
        listeners.forEach(function (fn) {
            try { fn(n); } catch (e) {}
        });
        try {
            global.dispatchEvent(new Event('ame-offline-pay-change'));
        } catch (e) {}
    }

    function saveQueue(queue) {
        try {
            localStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
        } catch (e) {}
        notify();
    }

    function saveCustomers(list) {
        if (!Array.isArray(list)) {
            return;
        }
        try {
            localStorage.setItem(CUST_KEY, JSON.stringify(list));
        } catch (e) {}
    }

    function loadCustomers() {
        try {
            return JSON.parse(localStorage.getItem(CUST_KEY) || '[]');
        } catch (e) {
            return [];
        }
    }

    function saveAccounts(list) {
        if (!Array.isArray(list)) {
            return;
        }
        try {
            localStorage.setItem(ACC_KEY, JSON.stringify(list));
        } catch (e) {}
    }

    function loadAccounts() {
        try {
            return JSON.parse(localStorage.getItem(ACC_KEY) || '[]');
        } catch (e) {
            return [];
        }
    }

    function makeRef() {
        return 'PAYOFF-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
    }

    function payloadFromForm(form) {
        var data = {};
        new FormData(form).forEach(function (value, key) {
            data[key] = value;
        });
        if (!data.idempotency_token) {
            data.idempotency_token = makeRef();
            var hidden = form.querySelector('input[name="idempotency_token"]');
            if (hidden) {
                hidden.value = data.idempotency_token;
            }
        }
        return data;
    }

    function samePay(a, b) {
        return String((a && a.customer_id) || '') === String((b && b.customer_id) || '')
            && Math.abs((parseFloat((a && a.amount) || '0') || 0) - (parseFloat((b && b.amount) || '0') || 0)) < 0.009;
    }

    function queuePayment(payload) {
        var queue = getQueue();
        var ref = payload.idempotency_token || makeRef();
        payload.idempotency_token = ref;
        if (queue.some(function (item) { return item.idempotency_token === ref; })) {
            return ref;
        }
        var now = Date.now();
        var existing = queue.find(function (item) {
            var p = item.payload || {};
            if (!samePay(p, payload)) {
                return false;
            }
            var at = Date.parse(item.queued_at || '') || 0;
            return !at || (now - at) < 60000;
        });
        if (existing) {
            return existing.idempotency_token;
        }
        queue.push({
            idempotency_token: ref,
            queued_at: new Date().toISOString(),
            payload: payload
        });
        saveQueue(queue);
        return ref;
    }

    function queueFromForm(form) {
        return queuePayment(payloadFromForm(form));
    }

    function onQueueChange(fn) {
        listeners.push(fn);
        try { fn(getQueue().length); } catch (e) {}
    }

    function acquireLock() {
        try {
            var now = Date.now();
            var prev = parseInt(localStorage.getItem(LOCK_KEY) || '0', 10);
            if (prev && now - prev < 20000) {
                return false;
            }
            localStorage.setItem(LOCK_KEY, String(now));
            return true;
        } catch (e) {
            return true;
        }
    }

    function releaseLock() {
        try { localStorage.removeItem(LOCK_KEY); } catch (e) {}
    }

    function refreshCsrf() {
        return fetch(PING, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.csrf) {
                    global.AME_CSRF_TOKEN = data.csrf;
                }
                return data;
            })
            .catch(function () { return null; });
    }

    function refreshCustomers() {
        if (!global.navigator.onLine) {
            return Promise.resolve(loadCustomers());
        }
        return fetch(CUST_URL, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && Array.isArray(data.customers) && data.customers.length) {
                    saveCustomers(data.customers);
                    return data.customers;
                }
                return loadCustomers();
            })
            .catch(function () { return loadCustomers(); });
    }

    function postPayload(payload) {
        var body = new URLSearchParams();
        Object.keys(payload || {}).forEach(function (key) {
            if (payload[key] != null && payload[key] !== '') {
                body.set(key, payload[key]);
            }
        });
        body.set('ajax', '1');
        if (global.AME_CSRF_TOKEN) {
            body.set('csrf_token', global.AME_CSRF_TOKEN);
        }
        return fetch(ENDPOINT, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': global.AME_CSRF_TOKEN || ''
            },
            body: body
        });
    }

    function parsePayResponse(res) {
        return res.json().then(function (data) {
            return { http: res.status, data: data };
        }).catch(function () {
            return { http: res.status, data: null };
        });
    }

    function sendPayload(payload) {
        payload = payload || {};
        if (!payload.idempotency_token) {
            payload.idempotency_token = makeRef();
        }
        if (!global.navigator.onLine) {
            return Promise.resolve({ queued: true, ref: queuePayment(payload) });
        }
        return refreshCsrf().then(function () {
            return postPayload(payload);
        }).then(parsePayResponse).then(function (pack) {
            if (pack.data && (pack.data.status === 'success' || pack.data.replayed === true)) {
                return { ok: true, payment_id: pack.data.payment_id, replayed: !!pack.data.replayed };
            }
            if (pack.data && pack.data.status === 'error') {
                return {
                    error: pack.data.message || 'পেমেন্ট যায়নি',
                    duplicate: !!pack.data.duplicate,
                    payment_id: pack.data.payment_id || 0
                };
            }
            return { queued: true, ref: queuePayment(payload) };
        }).catch(function () {
            return { queued: true, ref: queuePayment(payload) };
        });
    }

    function sendForm(form) {
        return sendPayload(payloadFromForm(form));
    }

    var syncing = false;
    function syncQueue() {
        if (syncing || !global.navigator.onLine || !acquireLock()) {
            return Promise.resolve();
        }
        var queue = getQueue();
        if (queue.length === 0) {
            releaseLock();
            var path = (global.location && global.location.pathname) || '';
            if (path.indexOf('payment-receive.php') !== -1) {
                refreshCustomers();
            }
            return Promise.resolve();
        }
        syncing = true;
        var remaining = queue.slice();
        var done = [];

        return refreshCsrf().then(function () {
            return next();
        });

        function next() {
            if (remaining.length === 0) {
                saveQueue(getQueue().filter(function (item) {
                    return done.indexOf(item.idempotency_token) === -1;
                }));
                syncing = false;
                releaseLock();
                return Promise.resolve();
            }
            var item = remaining.shift();
            var payload = Object.assign({}, item.payload || {}, {
                idempotency_token: item.idempotency_token
            });
            if (global.AME_CSRF_TOKEN) {
                payload.csrf_token = global.AME_CSRF_TOKEN;
            }

            return postPayload(payload)
                .then(parsePayResponse)
                .then(function (pack) {
                    if (pack.data && (pack.data.status === 'success' || pack.data.replayed === true || pack.data.duplicate === true)) {
                        done.push(item.idempotency_token);
                    }
                    return next();
                })
                .catch(function () {
                    saveQueue(getQueue().filter(function (item2) {
                        return done.indexOf(item2.idempotency_token) === -1;
                    }));
                    syncing = false;
                    releaseLock();
                });
        }
    }

    function pendingCount() {
        return getQueue().length;
    }

    global.AmeOfflinePay = {
        queuePayment: queuePayment,
        queueFromForm: queueFromForm,
        sendPayload: sendPayload,
        sendForm: sendForm,
        syncQueue: syncQueue,
        saveCustomers: saveCustomers,
        loadCustomers: loadCustomers,
        saveAccounts: saveAccounts,
        loadAccounts: loadAccounts,
        pendingCount: pendingCount,
        makeRef: makeRef,
        refreshCustomers: refreshCustomers,
        onQueueChange: onQueueChange
    };

    global.addEventListener('online', function () {
        syncQueue();
    });
    if (global.navigator.onLine) {
        setTimeout(function () {
            var path = (global.location && global.location.pathname) || '';
            if (path.indexOf('payment-receive.php') === -1 && getQueue().length === 0) {
                return;
            }
            syncQueue();
        }, 1500);
    }
})(window);
