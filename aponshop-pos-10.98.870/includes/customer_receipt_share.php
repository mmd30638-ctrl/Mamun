<?php
/**
 * পেমেন্ট রসিদ পেজ — হোয়াটসঅ্যাপে মেমো PDF/ছবি (লাইভ payment-receive.php footer-এ include করুন)।
 */

if (!function_exists('ame_customer_payment_request')) {
    function ame_customer_payment_request(): bool
    {
        $hay = strtolower(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')
            . ' ' . (string) ($_SERVER['REQUEST_URI'] ?? '')));
        return (bool) preg_match('#/modules/customers/(payment|collect|pay|receive)#i', $hay);
    }
}

if (!function_exists('ame_customer_receipt_share_tags')) {
    function ame_customer_receipt_share_tags(): string
    {
        $ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1.0', ENT_QUOTES, 'UTF-8');
        $html = '<script src="/assets/js/ame-memo-file.js?v=' . $ver . '"></script>' . "\n"
            . '<script src="/assets/js/ame-receipt-actions.js?v=' . $ver . '"></script>' . "\n"
            . '<script src="/assets/js/ame-receipt-wa-share.js?v=' . $ver . '"></script>' . "\n";
        $html .= <<<'HTML'
<script>
(function () {
    if (window.__ameWaCaptureGuard) { return; }
    window.__ameWaCaptureGuard = 1;
    function memoOnPage() {
        return !!(document.getElementById('spayMemo')
            || document.getElementById('receiptCard')
            || document.getElementById('memo-container'));
    }
    document.addEventListener('click', function (ev) {
        if (!memoOnPage()) { return; }
        var el = ev.target && ev.target.closest
            ? ev.target.closest('a[href*="wa.me"],a[href*="whatsapp.com"],.inv-btn-wa,[data-inv-act="share-whatsapp"],[data-share-wa="1"]')
            : null;
        if (!el) { return; }
        var act = el.getAttribute('data-inv-act');
        if (act && act !== 'share-whatsapp') { return; }
        ev.preventDefault();
        ev.stopImmediatePropagation();
        if (typeof window.ameReceiptShareWhatsApp === 'function') {
            window.ameReceiptShareWhatsApp(el);
            return;
        }
        var tries = 0;
        (function waitFn() {
            tries++;
            if (typeof window.ameReceiptShareWhatsApp === 'function') {
                window.ameReceiptShareWhatsApp(el);
            } else if (tries < 80) {
                setTimeout(waitFn, 100);
            } else {
                window.alert('মেমো শেয়ার লোড হয়নি — পেজ রিফ্রেশ করুন।');
            }
        })();
    }, true);
})();
</script>
HTML;
        return $html;
    }
}