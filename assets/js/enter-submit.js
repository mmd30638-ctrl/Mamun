/**
 * Enter runs the page's submit or complete button.
 * An open search list, a textarea, and a real form submit button are left alone.
 */
(function () {
    'use strict';

    if (window.AME_ENTER_SUBMIT) {
        return;
    }
    window.AME_ENTER_SUBMIT = true;

    function visible(el) {
        if (!el || el.disabled) {
            return false;
        }
        if (el.hidden || (el.closest && el.closest('[hidden]'))) {
            return false;
        }
        var style = window.getComputedStyle(el);
        if (style.display === 'none' || style.visibility === 'hidden') {
            return false;
        }
        var rect = el.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
    }

    function listOpen(el) {
        if (!el) {
            return false;
        }
        if (el.hidden || (el.closest && el.closest('[hidden]'))) {
            return false;
        }
        return el.childElementCount > 0 && visible(el);
    }

    function suggestOpen(target) {
        if (!target || !target.getAttribute) {
            return false;
        }
        if (target.getAttribute('aria-expanded') === 'true') {
            return true;
        }
        var controls = target.getAttribute('aria-controls');
        if (controls) {
            var box = document.getElementById(controls);
            if (listOpen(box)) {
                return true;
            }
        }
        if (target.id === 'p-search') {
            var hits = document.getElementById('search-res');
            if (hits && hits.childElementCount > 0) {
                return true;
            }
        }
        if (target.id === 'posCustomerInfoField') {
            var people = document.getElementById('posCustomerSearchList');
            if (people && !people.hidden && people.childElementCount > 0) {
                return true;
            }
        }
        return false;
    }

    function actionIn(root) {
        if (!root || !root.querySelectorAll) {
            return null;
        }
        var selectors = [
            '#amePosCompleteBtn',
            '#amePcCompleteBtn',
            '#qaSubmitBtn',
            '#qsSubmitBtn',
            '#cpayConfirmBtn',
            '#pdSubmit',
            '#go',
            '#goTxn',
            'button.ame-pos-complete',
            'button.cpay-confirm',
            'button.modal-save',
            'button.btn-submit',
            'button[type="submit"]',
            'input[type="submit"]'
        ];
        var s;
        var i;
        for (s = 0; s < selectors.length; s++) {
            var nodes = root.querySelectorAll(selectors[s]);
            for (i = 0; i < nodes.length; i++) {
                if (visible(nodes[i]) && safeAction(nodes[i])) {
                    return nodes[i];
                }
            }
        }
        return null;
    }

    function safeAction(button) {
        if (!button || button.getAttribute('data-ame-enter') === 'off') {
            return false;
        }
        if (button.getAttribute('data-ame-enter') === 'on') {
            return true;
        }
        var haystack = [
            button.name,
            button.value,
            button.id,
            button.className,
            button.getAttribute('formaction'),
            button.getAttribute('data-confirm-type'),
            button.textContent,
            button.form ? button.form.className : '',
            button.form ? button.form.getAttribute('data-confirm-type') : ''
        ].join(' ').toLowerCase();
        return !/(delete|remove|destroy|purge|cancel|reverse|void|danger|destructive|hard-delete|বাতিল|মুছ|ডিলিট)/i.test(haystack);
    }

    function nativeSubmit(form, button) {
        if (!form || !button) {
            return false;
        }
        var type = (button.getAttribute('type') || button.type || '').toLowerCase();
        if (type !== 'submit') {
            return false;
        }
        if (button.form && button.form !== form) {
            return false;
        }
        var first = form.querySelector('button[type="submit"], input[type="submit"]');
        return first === button;
    }

    function pageComplete() {
        var ids = [
            'amePosCompleteBtn',
            'amePcCompleteBtn',
            'ameDeskComplete',
            'pbCompleteLaunch',
            'qaSubmitBtn',
            'qsSubmitBtn',
            'cpayConfirmBtn',
            'pdSubmit',
            'go',
            'goTxn'
        ];
        var i;
        var el;
        for (i = 0; i < ids.length; i++) {
            el = document.getElementById(ids[i]);
            if (visible(el) && safeAction(el)) {
                return el;
            }
        }
        var nodes = document.querySelectorAll('button.ame-pos-complete, button.cpay-confirm');
        var n;
        for (n = 0; n < nodes.length; n++) {
            if (visible(nodes[n]) && safeAction(nodes[n])) {
                return nodes[n];
            }
        }
        return null;
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' || event.repeat) {
            return;
        }
        if (event.shiftKey || event.altKey || event.ctrlKey || event.metaKey || event.isComposing) {
            return;
        }
        if (event.defaultPrevented) {
            return;
        }
        var target = event.target;
        if (!target || !target.tagName) {
            return;
        }
        var tag = target.tagName;
        if (tag === 'TEXTAREA' || tag === 'BUTTON' || tag === 'A' || tag === 'SELECT' || tag === 'SUMMARY') {
            return;
        }
        if (target.isContentEditable) {
            return;
        }
        var type = (target.type || '').toLowerCase();
        if (type === 'checkbox' || type === 'radio' || type === 'file' || type === 'button' || type === 'submit' || type === 'reset' || type === 'hidden') {
            return;
        }
        if (suggestOpen(target)) {
            return;
        }

        var dialog = target.closest ? target.closest('[role="dialog"]') : null;
        if (dialog && !visible(dialog)) {
            dialog = null;
        }
        var form = target.form || (target.closest ? target.closest('form') : null);
        var button = null;
        var typing = tag === 'INPUT';
        if (!typing) {
            button = dialog ? actionIn(dialog) : pageComplete();
            if (!button) {
                return;
            }
            event.preventDefault();
            button.click();
            return;
        }
        if (dialog) {
            button = actionIn(dialog);
            if (!button) {
                return;
            }
        } else {
            if (!form) {
                button = pageComplete();
                if (!button) {
                    return;
                }
            } else {
                button = actionIn(form) || pageComplete();
            }
        }
        if (!button) {
            if (form && form.querySelector('button[type="submit"], input[type="submit"]')) {
                event.preventDefault();
            }
            return;
        }
        if (nativeSubmit(form, button)) {
            return;
        }

        event.preventDefault();
        button.click();
    });
})();
