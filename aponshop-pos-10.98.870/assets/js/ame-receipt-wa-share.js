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
            return {
                phone: phone,
                text: u.searchParams.get('text') || ''
            };
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
        var tag = document.querySelector('[data-receipt-no]');
        if (tag) {
            return tag.getAttribute('data-receipt-no');
        }
        return 'receipt-' + Date.now();
    }

    function shareWhatsApp(el) {
        if (typeof window.ameMemoCapture !== 'function') {
            return false;
        }
        var parsed = parseWa(el.getAttribute('href') || '');
        var phone = el.getAttribute('data-wa-phone') || (parsed && parsed.phone) || '';
        var text = el.getAttribute('data-wa-text') || (parsed && parsed.text) || '';
        var short = text ? String(text).split('\n')[0] : 'পেমেন্ট রসিদ';
        window.ameMemoCapture('share-whatsapp', fileBase(el), memoId(el), {
            phone: phone,
            text: short
        });
        return true;
    }

    function bind(el) {
        el.addEventListener('click', function (ev) {
            var act = el.getAttribute('data-inv-act');
            if (act === 'share-whatsapp' || el.classList.contains('inv-btn-wa') || el.getAttribute('data-share-wa') === '1') {
                if (typeof window.ameMemoCapture === 'function') {
                    ev.preventDefault();
                    shareWhatsApp(el);
                }
            }
        });
    }

    function boot() {
        document.querySelectorAll(
            '.inv-btn-wa, [data-inv-act="share-whatsapp"], [data-share-wa="1"]'
        ).forEach(bind);

        var qs = new URLSearchParams(window.location.search);
        if (qs.get('wa') === '1' || qs.get('whatsapp') === '1') {
            window.setTimeout(function () {
                var btn = document.querySelector('[data-inv-act="share-whatsapp"], .inv-btn-wa');
                if (btn) {
                    btn.click();
                }
            }, 550);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();