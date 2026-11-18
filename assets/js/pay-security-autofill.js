/**
 * Visible payment security code -> matching input.
 * This is UI convenience only; PHP still verifies and consumes the code.
 */
(function () {
    'use strict';

    if (window.AME_PAY_SECURITY_AUTOFILL) {
        return;
    }
    window.AME_PAY_SECURITY_AUTOFILL = true;

    document.addEventListener('click', function (event) {
        var trigger = event.target && event.target.closest
            ? event.target.closest('[data-ame-pay-sec-fill]')
            : null;
        if (!trigger) {
            return;
        }
        var box = trigger.closest('[data-ame-pay-sec]');
        var input = box && box.querySelector('.ame-pay-sec-input[name="pay_security_code"]');
        var code = box ? String(box.getAttribute('data-security-code') || '').trim() : '';
        if (!input || !code) {
            return;
        }
        event.preventDefault();
        input.value = code;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        input.classList.add('is-autofilled');
        trigger.classList.add('is-filled');
        try {
            input.focus({ preventScroll: true });
        } catch (e) {
            input.focus();
        }
    });
})();
