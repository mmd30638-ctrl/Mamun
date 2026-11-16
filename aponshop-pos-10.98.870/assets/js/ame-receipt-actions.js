'use strict';

(function () {
    function memoId() {
        if (document.getElementById('spayMemo')) {
            return 'spayMemo';
        }
        if (document.getElementById('receiptCard')) {
            return 'receiptCard';
        }
        return 'memo-container';
    }

    function fileBase() {
        var root = document.getElementById(memoId());
        if (root) {
            var no = root.getAttribute('data-receipt-no') || root.getAttribute('data-invoice-no');
            if (no) {
                return no;
            }
        }
        var bar = document.querySelector('[data-ame-file-base]');
        if (bar) {
            return bar.getAttribute('data-ame-file-base');
        }
        return 'receipt-' + Date.now();
    }

    function optsForAct(act, btn) {
        if (act === 'share-whatsapp') {
            return {
                phone: btn.getAttribute('data-wa-phone') || '',
                text: btn.getAttribute('data-wa-text') || 'পেমেন্ট রসিদ',
                preferPdf: btn.getAttribute('data-wa-pdf') !== '0'
            };
        }
        if (act === 'share-telegram') {
            return { telegramUrl: btn.getAttribute('data-tg-url') || '', text: btn.getAttribute('data-wa-text') || '' };
        }
        return null;
    }

    function bindActs() {
        document.querySelectorAll('[data-inv-act]').forEach(function (btn) {
            if (btn.getAttribute('data-ame-act-bound') === '1') {
                return;
            }
            btn.setAttribute('data-ame-act-bound', '1');
            btn.addEventListener('click', function (ev) {
                var act = btn.getAttribute('data-inv-act');
                if (!act || typeof window.ameMemoCapture !== 'function') {
                    return;
                }
                if (act === 'share-whatsapp') {
                    ev.preventDefault();
                }
                window.ameMemoCapture(act, btn.getAttribute('data-ame-file-base') || fileBase(), memoId(), optsForAct(act, btn));
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindActs);
    } else {
        bindActs();
    }
})();