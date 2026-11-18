/**
 * 10.98.349 — ক্রয় সাবমিট শুধু আসল ডেস্ক (amePcGo) চালায়।
 * পণ্য যোগের পর হিডেন/ভুল বাটনে ক্লিক বা খালি items_json পাঠানো বন্ধ।
 * সম্পন্ন মেমোর এডিট add.php?edit_memo= এ থাকে, জেনেরিক ইনভয়েস এডিটরে নয়।
 */
(function () {
    'use strict';
    if (window.AME_PURCHASE_DESK_349) {
        return;
    }
    window.AME_PURCHASE_DESK_349 = true;
    window.AME_PURCHASE_DESK_348 = true;
    window.AME_PURCHASE_DESK_346 = true;
    window.AME_PURCHASE_DESK_345 = true;
    window.AME_PURCHASE_DESK_343 = true;
    window.AME_PURCHASE_DESK_342 = true;
    window.AME_PURCHASE_INVOICE_FIX = true;

    var path = String(location.pathname || '').toLowerCase();
    // ফ্রন্ট অর্ডারের মুছুন ক্রয় ডেস্ক চালায় না। নাহলে নিচের সারি আবার যোগ হয়।
    if (path.indexOf('restock_invoice') !== -1) {
        return;
    }
    var isHistory = /purchase\/history/.test(path);
    var isAddPage = /purchase\/(add|edit|create)/.test(path);
    var isMemoPath = /^\/m(\/|$)/.test(path) || /\/memo/.test(path) || /m\.php/.test(path);
    var isEditorPage = /purchase\/invoice_editor/.test(path);
    var isPurchase =
        /\/modules\/purchase\//.test(path)
        || /\/modules\/workshop\//.test(path)
        || isMemoPath
        || (document.body && document.body.classList.contains('ame-purchase-terminal-page'));

    if (!isPurchase && !isHistory && !isMemoPath) {
        return;
    }

    var PC_SVG = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M3 4.5A1.5 1.5 0 0 1 4.5 3h15A1.5 1.5 0 0 1 21 4.5v10A1.5 1.5 0 0 1 19.5 16H14l1 2h2a1 1 0 1 1 0 2H7a1 1 0 1 1 0-2h2l1-2H4.5A1.5 1.5 0 0 1 3 14.5v-10Zm1.5 0v8h15v-8h-15Z"/></svg>';
    var HOME_SVG = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 3.2 3.6 10.2c-.4.3-.4.9 0 1.2.2.1.4.2.6.2h.8V20c0 .6.4 1 1 1h4.2v-5.2h3.6V21H19c.6 0 1-.4 1-1v-8.4h.8c.2 0 .4-.1.6-.2.4-.3.4-.9 0-1.2L12 3.2z"/></svg>';

    function $(sel, root) {
        return (root || document).querySelector(sel);
    }
    function $all(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }
    function bodyText() {
        return (document.body && document.body.innerText) || '';
    }
    function labelOf(el) {
        return String(el.textContent || el.value || el.getAttribute('aria-label') || el.id || '').replace(/\s+/g, ' ').trim();
    }
    function markPage() {
        if (!document.body) return;
        if (isHistory) document.body.classList.add('ame-purchase-history-page');
        if (isAddPage) document.body.classList.add('ame-purchase-add-page');
        if (isEditorPage) document.body.classList.add('ame-purchase-editor-page');
        if (isMemoView()) document.body.classList.add('ame-purchase-memo-page');
        document.body.classList.add('ame-purchase-desk');
    }
    function isPurchaseMemo() {
        var t = bodyText();
        if (/বিক্রয় মেমো/.test(t) && !/ক্রয় মেমো/.test(t) && !/ক্রয় সম্পন্ন/.test(t)) return false;
        return /ক্রয় মেমো|ক্রয় সম্পন্ন|মেমো নং|মেমো নম্বর|পণ্য ক্রয়/.test(t);
    }
    function isCompletedMemo() {
        return /খোলা নয়|ক্রয় সম্পন্ন —/.test(bodyText());
    }
    function isMemoView() {
        return Boolean(
            isMemoPath
            || $('#memo-container')
            || $('.invoice-box')
            || /মেমো নং|মেমো নম্বর|ক্রয় মেমো/.test(bodyText())
        );
    }

    function invoiceId() {
        var params = new URLSearchParams(location.search);
        var keys = ['id', 'invoice_id', 'purchase_id', 'pid', 'memo', 'mid', 'no'];
        for (var i = 0; i < keys.length; i++) {
            var v = params.get(keys[i]);
            if (v && /^\d+$/.test(v)) return v;
        }
        var m = path.match(/\/m\/(\d+)/) || path.match(/\/memo\/(\d+)/);
        if (m) return m[1];
        var el = $('[data-invoice-id], [data-purchase-id], input[name="invoice_id"], input[name="purchase_id"], #invoice_id');
        if (el) {
            var val = el.getAttribute('data-invoice-id') || el.getAttribute('data-purchase-id') || el.value;
            if (val && /^\d+$/.test(String(val))) return String(val);
        }
        var links = $all('a[href]');
        for (var j = 0; j < links.length; j++) {
            var mm = (links[j].getAttribute('href') || '').match(/(?:id|invoice_id|purchase_id)=(\d+)/);
            if (mm) return mm[1];
        }
        var tm = bodyText().match(/মেমো\s*(?:নং|নম্বর|#)?[^\d]{0,24}(\d+)/);
        return tm ? tm[1] : '';
    }

    function nativePurchaseDesk() {
        return !!(document.getElementById('purchaseForm') && document.getElementById('amePcCompleteBtn'));
    }

    function allowsNativeEdit(href) {
        return /edit_memo=|edit_id=|process_purchase_edit/.test(String(href || ''));
    }

    function memoFromPage() {
        var params = new URLSearchParams(location.search);
        var keys = ['memo', 'edit_memo', 'memo_no'];
        var i;
        for (i = 0; i < keys.length; i++) {
            var v = params.get(keys[i]);
            if (v) return v;
        }
        var tm = bodyText().match(/মেমো\s*(?:নং|নম্বর|#)?\s*[:#]?\s*([A-Za-z0-9][A-Za-z0-9_./-]{1,48})/);
        return tm ? tm[1] : '';
    }

    function editorUrl(id) {
        var memo = memoFromPage();
        if (memo) return '/modules/purchase/add.php?edit_memo=' + encodeURIComponent(memo);
        var n = String(id || invoiceId() || '');
        if (!n) return '/modules/purchase/invoice_editor.php';
        if (/^\d+$/.test(n)) return '/modules/purchase/invoice_editor.php?id=' + encodeURIComponent(n);
        return '/modules/purchase/add.php?edit_memo=' + encodeURIComponent(n);
    }

    function itemRows() {
        return $all('#rstkList .rstk-row, #cartBody tr, #itemBody tr, #itemsBody tr, #purchaseItems tbody tr, table.purchase-items tbody tr, .purchase-item-row').filter(function (row) {
            return !row.querySelector('th') && !row.classList.contains('empty');
        });
    }
    function itemCount() { return itemRows().length; }

    function unlockSearch() {
        $all('#p-search, #product_search, #productSearch, input[name="product_search"], input[placeholder*="পণ্য"], input[placeholder*="খুঁজ"]').forEach(function (el) {
            el.disabled = false;
            el.readOnly = false;
            el.removeAttribute('disabled');
            el.removeAttribute('readonly');
        });
        document.body.classList.remove('ame-purchase-locked');
    }
    function resetLineEditState() {
        window.__ameForceNewPurchaseLine = true;
        ['editingItemId', 'editItemId', 'editIndex', 'currentIndex', 'invoiceItemId'].forEach(function (key) {
            try { window[key] = typeof window[key] === 'number' ? -1 : null; } catch (e) {}
        });
        unlockSearch();
    }

    function applyDesktop(on) {
        var root = document.documentElement;
        root.classList.toggle('ame-phone-desktop', on);
        if (document.body) document.body.classList.toggle('ame-purchase-desktop', on);
        window.AME_SALE_DESKTOP_SITE = on;
        try { localStorage.setItem('ame-purchase-desktop', on ? '1' : '0'); } catch (e) {}
        $all('#amePurchaseDesktopBtn, #amePurchaseDesktopFab').forEach(function (btn) {
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }
    function desktopOn() {
        try { return localStorage.getItem('ame-purchase-desktop') === '1'; } catch (e) { return false; }
    }

    function injectChrome() {
        document.body.classList.add('ame-purchase-has-home');
        $all('.hdr-i-home, a.hdr-i-home, .hdr-i-pc, .hdr-i-desktop, [data-desktop], a[title*="ডেস্কটপ"], a[title*="কম্পিউটার"]').forEach(function (el) {
            el.hidden = false;
            el.style.setProperty('display', 'inline-flex', 'important');
            el.style.setProperty('visibility', 'visible', 'important');
            el.style.setProperty('opacity', '1', 'important');
        });
        if (!document.getElementById('amePurchaseHomeFab')) {
            var fab = document.createElement('a');
            fab.id = 'amePurchaseHomeFab';
            fab.className = 'ame-purchase-home-fab';
            fab.href = '/index.php';
            fab.setAttribute('aria-label', 'হোমে যান');
            fab.innerHTML = HOME_SVG + '<span>হোম</span>';
            document.body.appendChild(fab);
        }
        if (!document.getElementById('amePurchaseDesktopFab')) {
            var pc = document.createElement('button');
            pc.type = 'button';
            pc.id = 'amePurchaseDesktopFab';
            pc.className = 'ame-purchase-desktop-fab';
            pc.setAttribute('aria-label', 'কম্পিউটার / ডেস্কটপ মুড');
            pc.title = 'কম্পিউটার / ডেস্কটপ';
            pc.innerHTML = PC_SVG + '<span>কম্পিউটার</span>';
            pc.addEventListener('click', function () { applyDesktop(!desktopOn()); });
            document.body.appendChild(pc);
        }
        applyDesktop(desktopOn());
    }

    function unwindOldLift() {
        var bar = document.getElementById('amePurchaseSubmitTop');
        if (!bar) return;
        var host = bar.parentNode;
        Array.prototype.slice.call(bar.childNodes).forEach(function (node) {
            if (node.nodeType === 1 && (node.matches('button, input, a, label') || node.querySelector('button, input'))) {
                host.insertBefore(node, bar);
            }
        });
        bar.remove();
    }

    function isSubmitControl(el) {
        if (!el || el.id === 'amePurchaseTopSubmit' || el.id === 'pdSubmit' || el.id === 'popClose') {
            return false;
        }
        if (el.closest('.ame-purchase-option-box, .pd, table, .rstk-row, #search-res, #productHits, #ameMemoAddPanel, #chargePop')) {
            return false;
        }
        var label = labelOf(el);
        if (/হোম|ইতিহাস|নতুন ক্রয়|প্রিন্ট|শেয়ার|পণ্য যোগ|ইনভয়েস এডিট|কম্পিউটার|পেছনে|ডাউনলোড/.test(label)) {
            return false;
        }
        if (el.matches && el.matches('button[type="submit"], input[type="submit"]')) return true;
        return /সাবমিট|^Submit$|সেভ করুন|^সেভ$|সংরক্ষণ|মেমো আপডেট|আপডেট করুন|Update Memo|ক্রয় সম্পন্ন|কমপ্লিট/i.test(label);
    }

    function liveNode(el) {
        return !!(el && el.isConnected !== false && document.body && document.body.contains(el));
    }

    function nativeSubmits() {
        return $all('button, input[type="submit"], a.btn, .btn, [type="submit"]').filter(isSubmitControl);
    }

    function hideNativeSubmits() {
        if (nativePurchaseDesk()) {
            return;
        }
        nativeSubmits().forEach(function (el) {
            if (el.id === 'amePcCompleteBtn') {
                return;
            }
            el.classList.add('ame-purchase-src-hidden');
            el.setAttribute('data-ame-hidden-submit', '1');
        });
    }

    function purchaseForm(fromEl) {
        if (fromEl) {
            if (fromEl.form) return fromEl.form;
            if (fromEl.closest) {
                var near = fromEl.closest('form');
                if (near) return near;
            }
        }
        return $('form#purchaseForm, form#purchase-form, form#buyForm, form#rstkForm, form[action*="purchase"], #main-content form, form');
    }

    function isSubmitterType(el) {
        if (!el || !el.matches) return false;
        if (el.matches('input[type="submit"], input[type="image"]')) return true;
        if (el.matches('button[type="submit"]')) return true;
        if (el.matches('button') && !el.getAttribute('type')) return true;
        return false;
    }

    function pickSubmitter() {
        var hidden = $all('[data-ame-hidden-submit="1"]').filter(liveNode);
        var natives = nativeSubmits().filter(liveNode);
        var seen = [];
        var pool = hidden.concat(natives).filter(function (el) {
            if (!el || seen.indexOf(el) !== -1) return false;
            seen.push(el);
            return isSubmitControl(el) || el.getAttribute('data-ame-hidden-submit') === '1';
        });
        var withForm = pool.filter(function (el) { return !!(el.form || (el.closest && el.closest('form'))); });
        var use = withForm.length ? withForm : pool;
        var typed = use.filter(isSubmitterType);
        if (typed.length) return typed[0];
        if (use.length) return use[0];
        return $('form button[type="submit"], form input[type="submit"]');
    }

    function clickNativeSubmit(ev) {
        if (ev && ev.preventDefault) {
            ev.preventDefault();
            ev.stopPropagation();
        }
        var real = document.getElementById('amePcCompleteBtn');
        if (real) {
            real.disabled = false;
            real.removeAttribute('disabled');
            real.classList.remove('ame-purchase-src-hidden');
            real.style.pointerEvents = 'auto';
        }
        if (typeof window.amePcGo === 'function' && real) {
            window.amePcGo();
            return;
        }
        if (real) {
            real.click();
            return;
        }
        var pd = document.getElementById('pdSubmit');
        if (pd && isEditorPage) {
            pd.click();
            return;
        }
        var form = purchaseForm(null);
        if (form && typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        }
    }
    window.amePurchaseClickSubmit = clickNativeSubmit;

    function moneyText(n) {
        return '৳' + (Number(n) || 0).toLocaleString('en-BD', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function parseMoney(text) {
        var t = String(text || '').replace(/টাকা.*$/g, '').replace(/[৳,\s]/g, '');
        if (t === '—' || t === '-' || t === '') return null;
        var n = parseFloat(t);
        return isFinite(n) ? n : null;
    }

    function findTotalsBox() {
        var marked = document.getElementById('amePurchaseMoneyBox');
        if (marked) return marked;
        var nodes = $all('h1, h2, h3, h4, h5, .card-header, .box-header, legend, strong, b, div, section, p');
        for (var i = 0; i < nodes.length; i++) {
            var raw = (nodes[i].childNodes && nodes[i].childNodes.length)
                ? Array.prototype.slice.call(nodes[i].childNodes).filter(function (n) { return n.nodeType === 3; }).map(function (n) { return n.textContent; }).join('')
                : '';
            var t = String(raw || nodes[i].textContent || '').replace(/\s+/g, ' ').trim();
            if (t === 'ক্রয় হিসাব' || t === 'হিসাব') {
                var box = nodes[i].closest('.card, .box, .panel, .summary, section, form, .widget') || nodes[i].parentElement;
                if (box) {
                    box.id = box.id || 'amePurchaseMoneyBox';
                    return box;
                }
            }
        }
        var blocks = $all('.card, .box, .panel, .summary, #purchaseSummary, #billBox, #accBox, #purchaseAcc, .purchase-summary');
        for (var j = 0; j < blocks.length; j++) {
            var tx = blocks[j].innerText || '';
            if (/পণ্য মোট/.test(tx) && /ইনভয়েস মোট|সর্বমোট|বর্তমান/.test(tx)) {
                blocks[j].id = blocks[j].id || 'amePurchaseMoneyBox';
                return blocks[j];
            }
        }
        return null;
    }

    function readSupplierAmount() {
        var sels = [
            '#supplier_due', '#supDue', '#supplierDue', '#prev_due', '#previous_due',
            '#old_due', 'input[name="supplier_due"]', 'input[name="prev_due"]',
            'input[name="previous_due"]', '[data-supplier-due]', '[data-due]'
        ];
        for (var i = 0; i < sels.length; i++) {
            var el = $(sels[i]);
            if (!el) continue;
            var n = parseMoney(el.value != null && el.value !== '' ? el.value : el.textContent);
            if (n != null) return n;
        }
        var labels = $all('label, td, th, span, p, div, dt, strong, b, small');
        for (var k = 0; k < labels.length; k++) {
            if (labels[k].closest('.ame-purchase-money-supplier, .ame-purchase-option-box, .pd')) continue;
            var lab = String(labels[k].textContent || '').replace(/\s+/g, ' ').trim();
            if (!/^(সাপ্লায়ার|সরবরাহকারী|সাবেক বাকি|সাবেক পাওনা)[:\s]/.test(lab) && lab !== 'সাপ্লায়ার' && lab !== 'সরবরাহকারী') {
                continue;
            }
            var row = labels[k].closest('tr, .row, .form-group, li, .item, .d-flex') || labels[k].parentElement;
            if (!row) continue;
            var n2 = parseMoney(lab.replace(/^[^৳\d.—-]+/, ''));
            if (n2 == null) n2 = parseMoney(row.innerText.replace(/সাপ্লায়ার|সরবরাহকারী|সাবেক বাকি/g, ''));
            if (n2 != null) return n2;
        }
        return 0;
    }

    function hideSupplierAmountElsewhere(totals) {
        $all('.card, .box, .panel, .widget, section, form > div').forEach(function (card) {
            if (!totals || card === totals || totals.contains(card) || card.contains(totals)) return;
            var t = card.innerText || '';
            if (/সাপ্লায়ার \+|সাপ্লায়ার\s*\+|সাপ্লায়ার\+এই/.test(t) && t.length < 500) {
                card.classList.add('ame-purchase-src-hidden');
                card.setAttribute('data-ame-moved-supplier', '1');
                return;
            }
            if (!(/সরবরাহকারী তথ্য|পণ্যের নাম\/SKU|SKU\/OEM/.test(t) && /সাপ্লায়ার/.test(t))) return;
            $all('div, p, span, li, td, label', card).forEach(function (el) {
                if (el.querySelector('input, select, textarea')) return;
                var s = String(el.textContent || '').replace(/\s+/g, ' ').trim();
                if (/^(সাপ্লায়ার|মোট)[:\s]*[৳—\-\d.,]+$/.test(s) && s.length < 40) {
                    el.classList.add('ame-purchase-src-hidden');
                }
            });
        });
    }

    function placeSupplierInMoney() {
        var box = findTotalsBox();
        if (!box) return;
        var amount = readSupplierAmount();
        var row = box.querySelector('.ame-purchase-money-supplier');
        if (!row) {
            row = document.createElement('div');
            row.className = 'ame-purchase-money-supplier';
            row.innerHTML = '<span>সাপ্লায়ার</span><strong></strong>';
            var header = $all('h1, h2, h3, h4, .card-header, .box-header', box)[0];
            if (header && header.nextSibling) header.parentNode.insertBefore(row, header.nextSibling);
            else box.insertBefore(row, box.firstChild);
        }
        var strong = row.querySelector('strong');
        if (strong) strong.textContent = moneyText(amount);
        hideSupplierAmountElsewhere(box);
    }

    function shouldBuildBox() {
        if (nativePurchaseDesk()) return false;
        if (isHistory || isEditorPage) return false;
        return isAddPage || isMemoView() || isCompletedMemo() || isMemoPath || /restock/.test(path);
    }

    function killStaleMemoAdd() {
        $all('#ameMemoAddPanel, .ame-opt-add').forEach(function (el) { el.remove(); });
    }

    function buildOptionBox() {
        unwindOldLift();
        killStaleMemoAdd();
        if (nativePurchaseDesk()) {
            var nativeBox = document.getElementById('amePurchaseOptionBox');
            if (nativeBox) nativeBox.remove();
            var nativeTop = document.getElementById('amePurchaseTopSubmit');
            if (nativeTop) nativeTop.remove();
            return;
        }
        if (!shouldBuildBox()) return;
        var existing = document.getElementById('amePurchaseOptionBox');
        if (existing) {
            fillBox(existing);
            return;
        }
        var host = $('#main-content') || $('#memo-container') || $('.invoice-box') || document.body;
        var box = document.createElement('section');
        box.id = 'amePurchaseOptionBox';
        box.className = 'ame-purchase-option-box';
        if (host.firstChild) host.insertBefore(box, host.firstChild);
        else host.appendChild(box);
        fillBox(box);
    }

    function fillBox(box) {
        var completed = (isMemoView() || isCompletedMemo() || isMemoPath) && !isAddPage;
        if (completed) {
            if (!box.querySelector('#amePurchaseOpenEditor')) {
                box.innerHTML =
                    '<header class="ame-opt-head">'
                    + '<h2>সম্পন্ন ক্রয় ইনভয়েস</h2>'
                    + '<button type="button" id="amePurchaseDesktopBtn" class="ame-opt-pc" aria-label="কম্পিউটার / ডেস্কটপ মুড">'
                    + PC_SVG + '<span>কম্পিউটার</span></button>'
                    + '</header>'
                    + '<div class="ame-opt-editor-cta">'
                    + '<p>আগের মেমো-যোগ আপডেট বাতিল। বিক্রয় পেজের মতো খুলে পণ্য, পরিমাণ, রেট, ডিসকাউন্ট, সাপ্লায়ার এডিট করুন।</p>'
                    + '<a id="amePurchaseOpenEditor" class="ame-opt-btn ame-opt-btn-complete" href="' + editorUrl() + '">সম্পন্ন ইনভয়েস এডিট</a>'
                    + '</div>';
                var pc = $('#amePurchaseDesktopBtn', box);
                if (pc) pc.addEventListener('click', function () { applyDesktop(!desktopOn()); });
            } else {
                var link = $('#amePurchaseOpenEditor', box);
                if (link) link.setAttribute('href', editorUrl());
            }
            applyDesktop(desktopOn());
            return;
        }
        var grid = $('#ameOptGrid', box);
        if (!grid) {
            box.innerHTML =
                '<header class="ame-opt-head">'
                + '<h2>ক্রয় অপশন</h2>'
                + '<button type="button" id="amePurchaseDesktopBtn" class="ame-opt-pc" aria-label="কম্পিউটার / ডেস্কটপ মুড">'
                + PC_SVG + '<span>কম্পিউটার</span></button>'
                + '</header>'
                + '<div class="ame-opt-grid" id="ameOptGrid"></div>';
            var pc2 = $('#amePurchaseDesktopBtn', box);
            if (pc2) pc2.addEventListener('click', function () { applyDesktop(!desktopOn()); });
            grid = $('#ameOptGrid', box);
        }
        var b = document.getElementById('amePurchaseTopSubmit');
        if (grid && !b) {
            grid.innerHTML = '';
            b = document.createElement('button');
            b.id = 'amePurchaseTopSubmit';
            b.type = 'button';
            b.className = 'ame-opt-btn ame-opt-btn-complete ame-opt-submit-only';
            b.textContent = 'সাবমিট';
            grid.appendChild(b);
        }
        if (b) {
            b.disabled = false;
            b.removeAttribute('disabled');
            b.style.pointerEvents = 'auto';
            b.onclick = clickNativeSubmit;
            b.dataset.ameBound = '348';
        }
        applyDesktop(desktopOn());
        hideNativeSubmits();
        placeSupplierInMoney();
    }

    function productFromNode(node) {
        if (!node || !node.getAttribute) return null;
        var el = node.closest ? (node.closest('[data-pid], [data-product-id], [data-id], li, a, button, tr, .res-item') || node) : node;
        var pid = el.getAttribute('data-pid') || el.getAttribute('data-product-id') || el.getAttribute('data-id');
        var nameEl = $('.p-name, .name, b, strong', el);
        var name = el.getAttribute('data-name') || (nameEl && nameEl.textContent) || el.textContent;
        name = String(name || '').replace(/\s+/g, ' ').trim();
        if (!pid && !name) return null;
        return {
            id: String(pid || ''),
            name: name.slice(0, 180),
            code: String(el.getAttribute('data-code') || el.getAttribute('data-sku') || ''),
            price: Number(el.getAttribute('data-price') || el.getAttribute('data-purchase') || 0)
        };
    }

    function stripNewRowIds(row) {
        if (!row || !row.querySelectorAll) return;
        $all('input', row).forEach(function (el) {
            var name = String(el.getAttribute('name') || '').toLowerCase();
            if (/item_id|line_id|detail_id/.test(name)) { el.value = ''; }
        });
    }

    function rstkAdd(product) {
        var list = document.getElementById('rstkList');
        if (!list || !product) return false;
        var src = $('.rstk-row', list);
        var row = src ? src.cloneNode(true) : document.createElement('li');
        if (!src) {
            row.className = 'rstk-row';
            row.innerHTML = '<div class="rstk-row-top"><div><p class="rstk-name"></p></div><button type="button" class="rstk-del" data-rstk="del">×</button></div>'
                + '<input type="hidden" name="product_id[]" class="rstk-pid"><input type="hidden" name="qty[]" value="1"><input type="hidden" name="price[]"><input type="hidden" name="item_id[]" value="">';
        }
        stripNewRowIds(row);
        row.setAttribute('data-pid', String(product.id || product.name));
        row.setAttribute('data-price', String(product.price || 0));
        var nameEl = $('.rstk-name', row);
        if (nameEl) nameEl.textContent = product.name;
        $all('input[name="product_id[]"], .rstk-pid', row).forEach(function (el) { el.value = product.id; });
        $all('input[name="price[]"]', row).forEach(function (el) { el.value = String(product.price || 0); });
        $all('input[name="item_id[]"]', row).forEach(function (el) { el.value = ''; });
        list.appendChild(row);
        list.hidden = false;
        return true;
    }

    function forceAdd(product) {
        if (!product) return false;
        resetLineEditState();
        var before = itemCount();
        if (document.getElementById('rstkList')) {
            rstkAdd(product);
            if (typeof window.ameRstkRecalc === 'function') window.ameRstkRecalc();
        }
        return itemCount() > before;
    }

    function afterProductPick(product) {
        if (!product || !(product.name || product.id)) return;
        if (nativePurchaseDesk() || isEditorPage || isHistory) return;
        if ((isMemoView() || isCompletedMemo() || isMemoPath) && !isAddPage) {
            return;
        }
        resetLineEditState();
        var before = itemCount();
        window.setTimeout(function () {
            if (itemCount() <= before) forceAdd(product);
            else {
                var rows = itemRows();
                if (rows.length) stripNewRowIds(rows[rows.length - 1]);
            }
            buildOptionBox();
        }, 180);
    }

    function interceptEdit(ev) {
        if (isAddPage || isEditorPage || nativePurchaseDesk()) return;
        var t = ev.target;
        if (!t || !t.closest) return;
        var btn = t.closest('a, button');
        if (!btn) return;
        if (btn.closest('table thead, .ame-purchase-option-box, .pd')) return;
        var label = labelOf(btn);
        var href = String(btn.getAttribute('href') || '');
        if (allowsNativeEdit(href)) return;
        if (!/এডিট/.test(label) && !/edit/i.test(href) && !/খুলে এডিট/.test(label)) return;
        var memo = '';
        var mm = href.match(/[?&](?:memo|edit_memo)=([^&]+)/);
        if (mm) memo = decodeURIComponent(mm[1]);
        if (!memo) {
            var row = btn.closest('tr, .card, li, .item');
            var txt = row ? (row.innerText || '') : bodyText();
            var named = txt.match(/মেমো\s*(?:নং|নম্বর|#)?\s*[:#]?\s*([A-Za-z0-9][A-Za-z0-9_./-]{0,48})/);
            if (named) memo = named[1];
        }
        if (!(isHistory || isMemoView() || isCompletedMemo() || isPurchaseMemo())) return;
        if (!memo) return;
        ev.preventDefault();
        ev.stopPropagation();
        location.href = '/modules/purchase/add.php?edit_memo=' + encodeURIComponent(memo);
    }

    document.addEventListener('click', function (ev) {
        var t = ev.target;
        if (t && t.closest && isHistory) {
            var histBtn = t.closest('a, button');
            var href = histBtn ? String(histBtn.getAttribute('href') || '') : '';
            if (allowsNativeEdit(href)) {
                return;
            }
        }
        interceptEdit(ev);
        if (!t || !t.closest) return;
        if (t.closest('#ameMemoHits, .ame-purchase-option-box, .pd')) return;
        if (t.closest('#search-res, #productHits, .search-res, .product-suggest, [data-pid], .res-item')) {
            var product = productFromNode(t);
            if (product) afterProductPick(product);
        }
    }, true);

    window.ameRstkAddProduct = rstkAdd;
    window.amePurchaseAddProduct = forceAdd;

    function bustStalePurchaseCache() {
        try {
            if ('caches' in window) {
                caches.keys().then(function (keys) {
                    keys.forEach(function (k) { caches.delete(k); });
                });
            }
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.getRegistrations().then(function (regs) {
                    regs.forEach(function (r) { r.update(); });
                });
            }
        } catch (e) {}
    }

    function watchLift() {
        if (window.AME_PURCHASE_LIFT_WATCH) return;
        window.AME_PURCHASE_LIFT_WATCH = true;
        var scheduled = false;
        function kick() {
            if (scheduled) return;
            scheduled = true;
            window.setTimeout(function () {
                scheduled = false;
                buildOptionBox();
            }, 30);
        }
        [0, 300, 800, 1300, 2000, 3500].forEach(function (t) {
            window.setTimeout(kick, t);
        });
        if (document.body) {
            new MutationObserver(kick).observe(document.body, { childList: true, subtree: true });
        }
    }

    function boot() {
        markPage();
        injectChrome();
        resetLineEditState();
        buildOptionBox();
        watchLift();
        window.setTimeout(function () {
            markPage();
            injectChrome();
            buildOptionBox();
        }, 700);
    }
    bustStalePurchaseCache();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    window.addEventListener('load', boot);
})();
