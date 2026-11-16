'use strict';

(function () {
    function parseWa(href) {
        if (!href) {
            return null;
        }
        try {
            var u = new URL(href, window.location.href);
            if (u.hostname !== 'wa.me' && u.hostname !== 'api.whatsapp.com') {
                return null;
            }
            var phone = u.pathname.replace(/^\//, '').split('/')[0];
            return { phone: phone, text: u.searchParams.get('text') || '' };
        } catch (e) {
            return null;
        }
    }

    function memoId(el) {
        var custom = el && el.getAttribute('data-ame-memo-id');
        if (custom) {
            return custom;
        }
        if (document.getElementById('spayMemo')) {
            return 'spayMemo';
        }
        if (document.getElementById('receiptCard')) {
            return 'receiptCard';
        }
        return 'memo-container';
    }

    function fileBase(el) {
        if (el && el.getAttribute('data-ame-file-base')) {
            return el.getAttribute('data-ame-file-base');
        }
        var root = document.getElementById('spayMemo')
            || document.getElementById('receiptCard')
            || document.getElementById('memo-container');
        if (root) {
            var no = root.getAttribute('data-receipt-no') || root.getAttribute('data-invoice-no');
            if (no) {
                return no;
            }
        }
        return 'receipt-' + Date.now();
    }

    window.ameReceiptShareWhatsApp = function (el) {
        if (typeof window.ameMemoCapture !== 'function') {
            window.alert('মেমো ফাইল লোড হয়নি। ইন্টারনেট চেক করে পেজ রিফ্রেশ করুন।');
            return;
        }
        if (el && el.tagName === 'A' && el.getAttribute('href') && el.getAttribute('href').indexOf('wa.me') !== -1) {
            el.setAttribute('data-share-wa', '1');
        }
        var parsed = parseWa(el.getAttribute('href') || '');
        var phone = el.getAttribute('data-wa-phone') || (parsed && parsed.phone) || '';
        var text = el.getAttribute('data-wa-text') || (parsed && parsed.text) || '';
        var short = text ? String(text).split('\n')[0] : 'পেমেন্ট রসিদ';
        window.ameMemoCapture('share-whatsapp', fileBase(el), memoId(el), {
            phone: phone,
            text: short,
            preferPdf: el.getAttribute('data-wa-pdf') !== '0'
        });
    };

    function patchWaAnchors() {
        document.querySelectorAll('a.inv-btn-wa[href*="wa.me"], a[href*="wa.me"][class*="wa"]').forEach(function (a) {
            if (a.getAttribute('data-share-wa') === '1') {
                return;
            }
            a.setAttribute('data-share-wa', '1');
            a.setAttribute('role', 'button');
        });
    }

    function boot() {
        patchWaAnchors();
        var qs = new URLSearchParams(window.location.search);
        if (qs.get('wa') === '1' || qs.get('whatsapp') === '1') {
            window.setTimeout(function () {
                var btn = document.querySelector('[data-inv-act="share-whatsapp"], .inv-btn-wa, a[data-share-wa="1"]');
                if (btn) {
                    window.ameReceiptShareWhatsApp(btn);
                }
            }, 600);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();