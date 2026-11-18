/**
 * One centered message for the customer payment page.
 * Bangladesh mobiles: 01XXXXXXXXX opens as wa.me/8801XXXXXXXXX.
 */
(function (root) {
    'use strict';

    function digits(phone) {
        var d = String(phone || '').replace(/\D+/g, '');
        if (!d) {
            return '';
        }
        if (d.indexOf('880') === 0 && d.length >= 13) {
            return d;
        }
        if (d.charAt(0) === '0' && d.length === 11) {
            return '88' + d;
        }
        if (d.length === 10 && d.charAt(0) === '1') {
            return '880' + d;
        }
        return d;
    }

    function ameWhatsappHref(phone) {
        var d = digits(phone);
        return d ? ('https://wa.me/' + d) : '';
    }

    var afterClose = null;

    function cpaySay(message, opts) {
        var box = document.getElementById('cpayNotice');
        var btn = document.getElementById('cpayNoticeBtn');
        if (!box || !btn) {
            return;
        }
        opts = opts || {};
        btn.textContent = String(message || '');
        btn.classList.toggle('is-ok', opts.tone === 'ok');
        btn.classList.toggle('is-warn', opts.tone !== 'ok');
        afterClose = typeof opts.after === 'function' ? opts.after : null;
        box.hidden = false;
        if (btn.focus) {
            btn.focus();
        }
    }

    function cpaySayClose() {
        var box = document.getElementById('cpayNotice');
        var next = afterClose;
        afterClose = null;
        if (box) {
            box.hidden = true;
        }
        if (next) {
            next();
        }
    }

    function bind() {
        var btn = document.getElementById('cpayNoticeBtn');
        if (btn) {
            btn.addEventListener('click', cpaySayClose);
        }
    }

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bind);
        } else {
            bind();
        }
    }

    root.ameWhatsappHref = ameWhatsappHref;
    root.cpaySay = cpaySay;
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { ameWhatsappHref: ameWhatsappHref, cpaySay: cpaySay };
    }
})(typeof window !== 'undefined' ? window : global);
