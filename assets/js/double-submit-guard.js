/**
 * সাবমিট বাটনে বড় লোডিং চাকা + ডাবল সাবমিট আটকানো।
 * নেট স্লো থাকলে চাকা ঘুরতে থাকে যতক্ষণ সার্ভার উত্তর না দেয়।
 */
(function (global) {
    'use strict';

    function injectCss() {
        if (document.getElementById('ame-submit-spinner-css')) {
            return;
        }
        if (document.querySelector('link[href*="submit-spinner.css"]')) {
            return;
        }
        var link = document.createElement('link');
        link.id = 'ame-submit-spinner-css';
        link.rel = 'stylesheet';
        link.href = '/assets/css/submit-spinner.css?v=10.98.31';
        document.head.appendChild(link);
    }

    function buttonsOf(root) {
        if (!root) {
            return [];
        }
        return Array.prototype.slice.call(root.querySelectorAll(
            'button[type="submit"]:not([hidden]), input[type="submit"]:not([hidden]), .ame-pos-complete, .cpay-confirm, #go, #goTxn'
        ));
    }

    function prepare(btn) {
        if (!btn || btn.getAttribute('data-ame-wait-ready') === '1') {
            return;
        }
        if (btn.tagName === 'INPUT') {
            btn.setAttribute('data-ame-wait-ready', '1');
            return;
        }
        var spin = document.createElement('span');
        spin.className = 'ame-wait-spin';
        spin.setAttribute('aria-hidden', 'true');
        spin.innerHTML = '<i></i>';
        var label = document.createElement('span');
        label.className = 'ame-wait-label';
        while (btn.firstChild) {
            label.appendChild(btn.firstChild);
        }
        btn.appendChild(label);
        btn.appendChild(spin);
        btn.setAttribute('data-ame-wait-ready', '1');
        if (!btn.style.position) {
            btn.style.position = 'relative';
        }
    }

    function veil() {
        var box = document.getElementById('ameProcessVeil');
        if (box) {
            return box;
        }
        box = document.createElement('div');
        box.id = 'ameProcessVeil';
        box.className = 'ame-process-veil';
        box.innerHTML = '<div class="ame-process-card" id="ameProcessCard">'
            + '<div class="ame-process-spin" aria-hidden="true"></div>'
            + '<div class="ame-process-check" aria-hidden="true">✓</div>'
            + '<p id="ameProcessMsg">প্রক্রিয়া সম্পন্ন হচ্ছে, অনুগ্রহ করে অপেক্ষা করুন…</p>'
            + '</div>';
        document.body.appendChild(box);
        return box;
    }

    function showVeil(msg) {
        injectCss();
        var box = veil();
        var card = document.getElementById('ameProcessCard');
        var text = document.getElementById('ameProcessMsg');
        if (card) {
            card.classList.remove('is-ok', 'is-err');
        }
        if (text) {
            text.textContent = msg || 'প্রক্রিয়া সম্পন্ন হচ্ছে, অনুগ্রহ করে অপেক্ষা করুন…';
        }
        box.classList.add('is-on');
    }

    function showSuccess(msg, href) {
        var box = veil();
        var card = document.getElementById('ameProcessCard');
        var text = document.getElementById('ameProcessMsg');
        if (card) {
            card.classList.add('is-ok');
            card.classList.remove('is-err');
        }
        if (text) {
            text.textContent = msg || 'তথ্য সফলভাবে সংরক্ষণ হয়েছে!';
        }
        box.classList.add('is-on');
        if (href) {
            setTimeout(function () {
                window.location.href = href;
            }, 1000);
        }
    }

    function showError(msg) {
        var box = veil();
        var card = document.getElementById('ameProcessCard');
        var text = document.getElementById('ameProcessMsg');
        if (card) {
            card.classList.add('is-err');
            card.classList.remove('is-ok');
        }
        if (text) {
            text.textContent = msg || 'কাজ সম্পন্ন হয়নি। আবার চেষ্টা করুন।';
        }
        box.classList.add('is-on');
        setTimeout(function () {
            box.classList.remove('is-on');
        }, 2200);
    }

    function hideVeil() {
        var box = document.getElementById('ameProcessVeil');
        if (box) {
            box.classList.remove('is-on');
        }
    }

    function show(btn) {
        if (!btn) {
            return;
        }
        injectCss();
        prepare(btn);
        btn.classList.add('ame-is-waiting');
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        showVeil();
    }

    function hide(btn) {
        if (!btn) {
            return;
        }
        btn.classList.remove('ame-is-waiting');
        btn.disabled = false;
        btn.removeAttribute('aria-busy');
        hideVeil();
    }

    function hideAll() {
        Array.prototype.forEach.call(document.querySelectorAll('.ame-is-waiting'), hide);
        Array.prototype.forEach.call(document.querySelectorAll('form[data-ame-submitted="1"]'), function (form) {
            form.removeAttribute('data-ame-submitted');
        });
    }

    function showIn(root) {
        var list = buttonsOf(root).filter(function (btn) {
            var r = btn.getBoundingClientRect();
            return r.width > 8 && r.height > 8 && btn.offsetParent !== null;
        });
        (list.length ? list : buttonsOf(root)).forEach(show);
    }

    function hideIn(root) {
        buttonsOf(root).forEach(hide);
        if (root && root.removeAttribute) {
            root.removeAttribute('data-ame-submitted');
        }
    }

    function keepsSpinning(res) {
        if (!res || typeof res !== 'object') {
            return false;
        }
        if (res.sale_id && (res.ok || res.status === 'success')) {
            return true;
        }
        if (res.payment_id && (res.ok || res.duplicate)) {
            return true;
        }
        return false;
    }

    function follow(promise) {
        return Promise.resolve(promise).then(function (res) {
            if (!keepsSpinning(res)) {
                hideAll();
            }
            return res;
        }, function (err) {
            hideAll();
            throw err;
        });
    }

    function wrapAlert() {
        if (global.__ameWaitAlert) {
            return;
        }
        global.__ameWaitAlert = true;
        var orig = global.alert;
        if (typeof orig !== 'function') {
            return;
        }
        global.alert = function () {
            hideAll();
            return orig.apply(global, arguments);
        };
    }

    function arm(form) {
        if (!form || form.getAttribute('data-ame-wait-armed') === '1') {
            return;
        }
        form.setAttribute('data-ame-wait-armed', '1');
        form.addEventListener('submit', function (event) {
            // data-confirm মডাল আগে সাবমিট আটকায়; ওখানেই submitted মার্ক
            // করলে নিশ্চিত-ওকেতে দ্বিতীয় requestSubmit ব্লক হয়ে টাকা যায় না।
            if (event.defaultPrevented) {
                return;
            }
            if (form.getAttribute('data-confirm') && !form.__ameConfirmed) {
                return;
            }
            // এডিট ফর্ম আগে ডুপ্লিকেট চেক করে। সেই পর্যন্ত সাবমিট লক করবেন না।
            if (form.getAttribute('data-ame-hold') === '1' && form.getAttribute('data-ready') !== '1') {
                return;
            }
            if (form.getAttribute('data-ame-submitted') === '1') {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }
            form.setAttribute('data-ame-submitted', '1');
            showIn(form);
        }, true);
        form.addEventListener('submit', function (event) {
            if (!event.defaultPrevented) {
                return;
            }
            form.removeAttribute('data-ame-submitted');
            hideIn(form);
        });
    }

    function injectCsrf(form) {
        if (!global.AME_CSRF_TOKEN || !form) {
            return;
        }
        if (form.querySelector('input[name="csrf_token"]')) {
            return;
        }
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'csrf_token';
        input.value = global.AME_CSRF_TOKEN;
        form.appendChild(input);
    }

    function scan() {
        injectCss();
        wrapAlert();
        document.querySelectorAll('form').forEach(function (form) {
            var method = String(form.getAttribute('method') || 'get').toLowerCase();
            var moneyForm = method === 'post'
                || form.getAttribute('data-ame-once') === '1'
                || form.querySelector('.cpay-confirm, .ame-pos-complete');
            if (!moneyForm) {
                return;
            }
            injectCsrf(form);
            arm(form);
            buttonsOf(form).forEach(prepare);
        });
        document.querySelectorAll('.ame-pos-complete, .cpay-confirm, #go, #goTxn').forEach(prepare);
    }

    global.AmeSubmitWait = {
        show: show,
        hide: hide,
        hideAll: hideAll,
        showIn: showIn,
        hideIn: hideIn,
        prepare: prepare,
        follow: follow,
        veil: showVeil,
        success: showSuccess,
        error: showError,
        hideVeil: hideVeil
    };

    global.addEventListener('pageshow', function () {
        hideAll();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scan);
    } else {
        scan();
    }
})(window);
