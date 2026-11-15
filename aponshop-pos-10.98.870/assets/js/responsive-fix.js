/**
 * Aponshop screen-fit — every page, every device.
 * Wide tables become stacked cards on phones. Nothing is clipped.
 */
(function () {
    'use strict';
    if (window.AME_RESPONSIVE_FIT) {
        return;
    }
    window.AME_RESPONSIVE_FIT = true;
    var runTimer = 0;
    var autoPagers = [];

    function setViewportState() {
        var root = document.documentElement;
        var width = Math.max(root.clientWidth || 0, window.innerWidth || 0);
        var height = window.visualViewport
            ? window.visualViewport.height
            : Math.max(root.clientHeight || 0, window.innerHeight || 0);
        root.style.setProperty('--ame-viewport-width', width + 'px');
        root.style.setProperty('--ame-viewport-height', height + 'px');
        root.style.setProperty('--ame-vh', (height * 0.01) + 'px');
        if (window.AME_SALE_DESKTOP_SITE || root.classList.contains('ame-phone-desktop')) {
            width = Math.max(width, 1366);
        }
        if (document.body) {
            document.body.classList.toggle('ame-screen-phone', width <= 760);
            document.body.classList.toggle('ame-screen-tablet', width > 760 && width < 1100);
            document.body.classList.toggle('ame-screen-desktop', width >= 1100);
        }
    }

    function isPosTable(table) {
        return !!(table.closest('.ame-pos-page')
            || table.closest('.ame-pos-terminal-page')
            || table.closest('body.ame-pos-terminal-page')
            || table.closest('.smt-dock')
            || document.body.classList.contains('ame-pos-terminal-page'));
    }

    function wrapTables() {
        var tables = document.querySelectorAll('table:not([data-ame-wrapped])');
        tables.forEach(function (table) {
            table.setAttribute('data-ame-wrapped', '1');
            var parent = table.parentElement;
            var already = parent && (
                parent.classList.contains('ame-auto-scroll-wrap')
                || parent.classList.contains('ame-pos-table-wrap')
                || parent.classList.contains('table-responsive')
            );
            if (already || isPosTable(table) || isInvoiceTable(table) || isInvoiceMemo()) {
                return;
            }
            var wrapper = document.createElement('div');
            wrapper.className = 'ame-auto-scroll-wrap';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        });
    }

    function stackWideTables() {
        var narrow = !(window.AME_SALE_DESKTOP_SITE || document.documentElement.classList.contains('ame-phone-desktop')) && window.innerWidth <= 760;
        document.querySelectorAll('table').forEach(function (table) {
            if (isPosTable(table) || isInvoiceTable(table) || isInvoiceMemo() || skipCustomerAllRows(table)) {
                return;
            }
            var heads = table.querySelectorAll('thead th');
            if (heads.length < 4) {
                table.classList.remove('ame-stack-table');
                return;
            }
            var labels = [];
            heads.forEach(function (th) {
                labels.push((th.textContent || '').replace(/\s+/g, ' ').trim());
            });
            table.querySelectorAll(':scope > tbody > tr, :scope > tfoot > tr').forEach(function (tr) {
                var cells = tr.children;
                for (var i = 0; i < cells.length; i++) {
                    if (cells[i].tagName === 'TD' && !cells[i].querySelector('table') && !cells[i].getAttribute('data-label') && labels[i]) {
                        cells[i].setAttribute('data-label', labels[i]);
                    }
                }
            });
            if (narrow) {
                table.classList.add('ame-stack-table');
            } else {
                table.classList.remove('ame-stack-table');
            }
        });
    }

    function clampWideBoxes() {
        var body = document.body;
        if (!body) {
            return;
        }
        var nodes = body.querySelectorAll(
            'body > *, #main-content > *, .card, [class*="card"], [class*="-wrap"], '
            + '[class*="container"], [class*="modal"], dialog, fieldset, canvas'
        );
        nodes.forEach(function (el) {
            if (!el.parentElement) return;
            if (el.querySelector && el.querySelector('table')) {
                el.style.maxWidth = '100%';
                el.style.minWidth = '0';
                el.style.boxSizing = 'border-box';
                return;
            }
            var tag = (el.tagName || '').toLowerCase();
            if (tag === 'table' || tag === 'html' || tag === 'body' || tag === 'script' || tag === 'style') {
                return;
            }
            if (el.classList.contains('ame-auto-scroll-wrap') || el.classList.contains('master-header-fixed') || el.classList.contains('custom-sidebar')) {
                return;
            }
            var parentWidth = el.parentElement.clientWidth || document.documentElement.clientWidth;
            var rect = el.getBoundingClientRect();
            if (rect.width > parentWidth + 4) {
                el.style.maxWidth = '100%';
                el.style.boxSizing = 'border-box';
                el.style.minWidth = '0';
            }
        });
    }

    function centerDialogs() {
        var width = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
        var height = window.visualViewport
            ? window.visualViewport.height
            : Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);
        document.querySelectorAll('dialog').forEach(function (dialog) {
            dialog.classList.toggle('ame-centered-native-dialog', dialog.hasAttribute('open'));
        });
        document.querySelectorAll(
            '.modal, [class*="modal"], [class*="popup"], [class*="dialog"], [class*="overlay"], [class*="backdrop"], '
            + '[id*="modal"], [id*="popup"], [id*="dialog"], [id*="overlay"], [id*="backdrop"], [role="dialog"]'
        ).forEach(function (overlay) {
            var style = getComputedStyle(overlay);
            var rect = overlay.getBoundingClientRect();
            var visible = style.display !== 'none' && style.visibility !== 'hidden'
                && Number(style.opacity || 1) > 0 && rect.width > 0 && rect.height > 0;
            var isOverlay = visible && style.position === 'fixed'
                && rect.width >= width * 0.55 && rect.height >= height * 0.55;
            overlay.classList.toggle('ame-centered-overlay', isOverlay);
            if (!isOverlay) return;
            var content = null;
            try {
                content = overlay.querySelector(
                    ':scope > .modal-dialog, :scope > [class*="modal-content"], '
                    + ':scope > [class*="popup-content"], :scope > [class*="dialog-content"], '
                    + ':scope > [class*="card"], :scope > [class*="box"], '
                    + ':scope > [class*="panel"], :scope > form'
                );
            } catch (e) {
                content = overlay.firstElementChild;
            }
            if (content && content !== overlay) {
                var contentWidth = Math.round(content.getBoundingClientRect().width || 0);
                if (contentWidth > 0) {
                    content.style.setProperty('--ame-dialog-width', contentWidth + 'px');
                }
                content.classList.add('ame-centered-dialog');
            }
        });
    }

    function addDateApplyButtons() {
        document.querySelectorAll('form:not([data-ame-date-checked])').forEach(function (form) {
            form.setAttribute('data-ame-date-checked', '1');
            var method = (form.getAttribute('method') || 'get').toLowerCase();
            if (method !== 'get' || !form.querySelector('input[type="date"], input[type="month"]')) return;
            if (form.querySelector('button[type="submit"], input[type="submit"], .ame-date-apply')) return;
            var button = document.createElement('button');
            button.type = 'submit';
            button.className = 'ame-date-apply';
            button.textContent = 'তারিখ অনুযায়ী দেখুন';
            form.appendChild(button);
        });
    }

    function skipCustomerAllRows(table) {
        var body = document.body;
        if (body && (
            body.classList.contains('ame-due-reminder-page')
            || body.classList.contains('ame-customer-directory')
        )) {
            return true;
        }
        if (!table) {
            return false;
        }
        if (table.getAttribute('data-ame-no-page') === '1') {
            return true;
        }
        if (table.classList.contains('ame-due-table')) {
            return true;
        }
        return !!(table.closest && (
            table.closest('.ame-due-table-wrap')
            || table.closest('.cus-list-scroll')
        ));
    }

    function paginateLongTables() {
        document.querySelectorAll('table:not([data-ame-page-checked])').forEach(function (table) {
            table.setAttribute('data-ame-page-checked', '1');
            var reportCard = table.closest('.srep2-table-card');
            if (isPosTable(table) || isInvoiceTable(table) || isInvoiceMemo()
                || skipCustomerAllRows(table)
                || table.closest('.eh-statement, form, [class*="pagination"], [class*="pager"]')
                || (reportCard && reportCard.querySelector('.srep2-pager'))) return;
            var body = table.tBodies && table.tBodies[0];
            if (!body) return;
            var rows = Array.prototype.filter.call(body.rows, function (row) {
                return !row.hasAttribute('hidden') && getComputedStyle(row).display !== 'none';
            });
            if (rows.length <= 20) return;
            var page = 1;
            var size = 20;
            var pages = Math.ceil(rows.length / size);
            var pager = document.createElement('div');
            pager.className = 'ame-auto-pager';
            function render(showAll) {
                rows.forEach(function (row, index) {
                    var visible = showAll || (index >= (page - 1) * size && index < page * size);
                    if (visible) row.style.removeProperty('display');
                    else row.style.setProperty('display', 'none', 'important');
                });
                if (showAll) return;
                pager.innerHTML = '<button type="button"' + (page <= 1 ? ' disabled' : '') + '>আগে</button>'
                    + '<span>পৃষ্ঠা ' + page + ' / ' + pages + ' · ' + rows.length + ' সারি</span>'
                    + '<button type="button"' + (page >= pages ? ' disabled' : '') + '>পরে</button>';
                var buttons = pager.querySelectorAll('button');
                buttons[0].onclick = function () { if (page > 1) { page--; render(false); } };
                buttons[1].onclick = function () { if (page < pages) { page++; render(false); } };
            }
            var anchor = table.closest('.ame-auto-scroll-wrap') || table;
            anchor.parentNode.insertBefore(pager, anchor.nextSibling);
            autoPagers.push({ render: render });
            render(false);
        });
    }

    function isInvoiceMemo() {
        var path = String(location.pathname || '').toLowerCase();
        if (/\/modules\/sales\/invoice/.test(path) || /invoice_a4\.php$/.test(path)) {
            return true;
        }
        return !!(document.getElementById('memo-container')
            || document.getElementById('spayMemo')
            || document.getElementById('invoiceBox')
            || document.getElementById('receiptCard')
            || document.querySelector('.memo-wrapper, .invoice-box, img.ame-print-logo'));
    }

    function isInvoiceTable(table) {
        return !!(table.closest('#memo-container, .memo-wrapper, .invoice-box')
            || table.classList.contains('memo-table')
            || table.classList.contains('main-table')
            || table.classList.contains('header-table')
            || table.classList.contains('summary-table'));
    }

    function skipHeavyFit() {
        var body = document.body;
        if (!body) {
            return false;
        }
        var path = String(location.pathname || '').toLowerCase();
        if (/\/modules\/customers\//.test(path)) {
            return true;
        }
        return body.classList.contains('ame-customer-directory')
            || body.classList.contains('ame-due-reminder-page')
            || body.classList.contains('ame-pos-terminal-page')
            || body.classList.contains('ame-customer-payment-page');
    }

    function clampHorizontalOverflow() {
        var doc = document.documentElement;
        var body = document.body;
        if (!body) {
            return;
        }
        var vw = window.innerWidth || doc.clientWidth || 0;
        var wide = doc.scrollWidth > vw + 2;
        body.classList.toggle('ame-mobile-clamp', wide);
    }

    function runAll() {
        setViewportState();
        clampHorizontalOverflow();
        if (skipHeavyFit()) {
            return;
        }
        if (isInvoiceMemo()) {
            return;
        }
        wrapTables();
        addDateApplyButtons();
        if (document.body && (
            document.body.classList.contains('ame-pos-terminal-page')
            || document.body.classList.contains('ame-purchase-terminal-page')
        )) {
            return;
        }
        centerDialogs();
        paginateLongTables();
        stackWideTables();
        clampWideBoxes();
    }

    function scheduleRun() {
        if (document.hidden) {
            return;
        }
        clearTimeout(runTimer);
        runTimer = setTimeout(runAll, 280);
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            scheduleRun();
        }
    });

    function mutationMatters(records) {
        var i;
        var n;
        for (i = 0; i < records.length; i++) {
            var record = records[i];
            if (record.type === 'attributes') {
                return true;
            }
            var node = record.target;
            if (!node || node.nodeType === 3) {
                node = node && node.parentElement;
            }
            if (!node || !node.closest) {
                return true;
            }
            if (node.closest('#cartBody, .ame-desk-marquee, #ameDeskClock, #clock-display, #search-res, #posCustomerSearchList')) {
                continue;
            }
            n = record.addedNodes.length + record.removedNodes.length;
            if (n > 0) {
                return true;
            }
        }
        return false;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runAll);
    } else {
        runAll();
    }
    window.addEventListener('load', runAll);
    window.addEventListener('resize', scheduleRun);
    window.addEventListener('orientationchange', scheduleRun);
    window.addEventListener('beforeprint', function () {
        autoPagers.forEach(function (pager) { pager.render(true); });
    });
    window.addEventListener('afterprint', function () {
        autoPagers.forEach(function (pager) { pager.render(false); });
    });
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', scheduleRun);
    }

    if ('MutationObserver' in window) {
        new MutationObserver(function (records) {
            if (skipHeavyFit()) {
                return;
            }
            if (mutationMatters(records)) {
                scheduleRun();
            }
        }).observe(document.documentElement, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'style', 'hidden', 'open']
        });
    }
})();
