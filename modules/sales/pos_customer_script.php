<script>
(function () {
    var summary = document.getElementById('posCustomerSummary');
    var select = document.getElementById('customer_id');
    var retailRadio = document.getElementById('posRetailCustomerMode');
    var registeredRadio = document.getElementById('posRegisteredCustomerMode');

    var tabRegistered = document.getElementById('posTabRegistered');
    var tabGuest = document.getElementById('posTabGuest');

    var guestModal = document.getElementById('posGuestModal');
    var closeGuestModal = document.getElementById('closeGuestModal');
    var doneGuestModal = document.getElementById('doneGuestModal');

    var guestNameModal = document.getElementById('guestCustomerNameModal');
    var guestNameHidden = null;

    var searchInput = document.getElementById('posCustomerInfoField')
        || document.getElementById('posCustomerSearch');
    var suggestList = document.getElementById('posCustomerSearchList');
    var picker = document.getElementById('posCustomerBox')
        || (searchInput ? searchInput.closest('.pos-customer-picker') : null);
    var addBtn = document.getElementById('openQuickCustomer');
    var activeSuggest = -1;
    var registeredPicking = false;
    var partyFilter = '';
    var partyCache = {};
    var suggestToken = 0;
    var suggestTimer = 0;

    function ensureGuestNameHidden() {
        if (guestNameHidden) {
            return guestNameHidden;
        }
        guestNameHidden = document.createElement('input');
        guestNameHidden.type = 'text';
        guestNameHidden.id = 'guestCustomerName';
        guestNameHidden.hidden = true;

        var wrap = document.getElementById('guestCustomerFields');
        if (wrap) {
            wrap.appendChild(guestNameHidden);
        }
        return guestNameHidden;
    }

    ensureGuestNameHidden();

    function toolbarRegisteredEl() {
        return document.querySelector('input[name="cus_type_toolbar"][value="Registered"]');
    }

    function toolbarRetailEl() {
        return document.querySelector('input[name="cus_type_toolbar"][value="Retail"]');
    }

    function toolbarSupplierEl() {
        return document.querySelector('input[name="cus_type_toolbar"][value="Supplier"]');
    }

    function isSupplierUi() {
        var toolbarSupplier = toolbarSupplierEl();
        return partyFilter === 'supplier' || (toolbarSupplier && toolbarSupplier.checked);
    }

    function isRegisteredUi() {
        var toolbarRegistered = toolbarRegisteredEl();
        return registeredPicking
            || isSupplierUi()
            || (toolbarRegistered && toolbarRegistered.checked)
            || (registeredRadio && registeredRadio.checked);
    }

    function retailChoiceOn() {
        var toolbarRetail = toolbarRetailEl();
        if (toolbarRetail) {
            return toolbarRetail.checked;
        }
        return !!(retailRadio && retailRadio.checked);
    }

    function setRegisteredUi(on) {
        if (picker) {
            picker.classList.toggle('is-registered', !!on);
        }
        /*
         * নতুন ক্রেতা যোগ করার বোতাম এখন সবসময় দেখা যাবে (রিটেইল/নিবন্ধিত
         * দুই মোডেই) — আগে শুধু "নিবন্ধিত" মোডে দেখাত।
         */
        if (addBtn) {
            addBtn.hidden = false;
        }
        var savedListBtn = document.getElementById('ameSavedCustomerBtn');
        if (savedListBtn) {
            savedListBtn.hidden = !on;
        }
        if (searchInput) {
            var phSearch = searchInput.getAttribute('data-ph-search');
            var phSupplier = searchInput.getAttribute('data-ph-supplier');
            var phRetail = searchInput.getAttribute('data-ph-retail');
            if (on && isSupplierUi() && phSupplier) {
                searchInput.placeholder = phSupplier;
            } else if (on && phSearch) {
                searchInput.placeholder = phSearch;
            } else {
                searchInput.placeholder = phRetail || searchInput.placeholder;
            }
            searchInput.readOnly = false;
            if (on) {
                searchInput.removeAttribute('title');
            }
        }
    }

    function paintDeskCustomerBalance(opt) {
        var box = document.getElementById('ameDeskCustBal');
        var depositEl = document.getElementById('deskCustDeposit');
        var dueEl = document.getElementById('deskCustDue');
        if (!box) {
            return;
        }
        if (!opt || !opt.value) {
            box.hidden = true;
            if (depositEl) {
                depositEl.textContent = '0.00';
            }
            if (dueEl) {
                dueEl.textContent = '0.00';
            }
            return;
        }
        var amount = toAmount(opt.dataset ? opt.dataset.due : 0);
        var deposit = amount < -0.009 ? Math.abs(amount) : 0;
        var due = amount > 0.009 ? amount : 0;
        if (depositEl) {
            depositEl.textContent = deposit.toFixed(2);
        }
        if (dueEl) {
            dueEl.textContent = due.toFixed(2);
        }
        box.hidden = false;
    }

    function updateSummary() {
        var opt = select ? select.options[select.selectedIndex] : null;
        var registeredName = '';
        if (opt && select.value !== '') {
            registeredName = (opt.dataset && opt.dataset.name) || '';
            if (!registeredName) {
                registeredName = String(opt.textContent || '').split(' - ')[0].split(' (')[0].trim();
                if (registeredName) {
                    opt.dataset.name = registeredName;
                }
            }
        }
        var guestValue = guestNameHidden ? guestNameHidden.value.trim() : '';

        if (registeredName && opt) {
            summary.textContent = formatCustomerInfoLine(opt);
            summary.classList.add('has-value');
        } else if (guestValue) {
            summary.textContent = guestValue;
            summary.classList.add('has-value');
        } else {
            summary.textContent = summary.getAttribute('data-default') || '';
            summary.classList.remove('has-value');
        }

        var info = document.getElementById('registeredCustomerInfo');
        var nameEl = document.getElementById('selectedCustomerName');
        var balEl = document.getElementById('selectedCustomerBalance');

        var metaEl = document.getElementById('selectedCustomerMeta');
        var balTagEl = document.getElementById('selectedCustomerBalanceTag');

        if (registeredName && opt) {
            if (nameEl) {
                nameEl.textContent = registeredName;
            }
            if (metaEl) {
                var metaParts = [];
                if (opt.dataset.code) {
                    metaParts.push('আইডি: ' + opt.dataset.code);
                }
                if (opt.dataset.phone) {
                    metaParts.push('মোবাইল: ' + opt.dataset.phone);
                }
                metaEl.textContent = metaParts.join(' · ');
            }
            var balMeta = balanceMeta(opt.dataset.due || 0);
            if (balEl) {
                balEl.textContent = '৳' + balMeta.display.toFixed(2);
                balEl.style.color = balMeta.color;
            }
            if (balTagEl) {
                balTagEl.textContent = balMeta.tag === 'জমা আছে' || balMeta.tag === 'বকেয়া'
                    ? ' (' + balMeta.tag + ')'
                    : '';
                balTagEl.style.color = balMeta.color;
            }
            if (info) {
                info.style.display = 'block';
            }
        } else if (info) {
            info.style.display = 'none';
            if (metaEl) {
                metaEl.textContent = '';
            }
            if (balTagEl) {
                balTagEl.textContent = '';
            }
        }

        if (typeof window.fetchCusBalance === 'function') {
            window.fetchCusBalance();
        }
        if (typeof window.calculateAll === 'function') {
            window.calculateAll();
        }

        var infoField = document.getElementById('posCustomerInfoField');
        if (infoField) {
            if (registeredName && opt) {
                if (document.activeElement !== infoField) {
                    infoField.value = formatCustomerInfoLine(opt);
                }
                infoField.classList.add('has-customer');
                infoField.readOnly = false;
                infoField.title = '';
            } else {
                infoField.classList.remove('has-customer');
                infoField.readOnly = false;
                infoField.removeAttribute('title');
                if (document.activeElement !== infoField) {
                    infoField.value = guestValue || '';
                }
            }
        }

        if (typeof window.ameUpdateDueStatus === 'function') {
            window.ameUpdateDueStatus();
        }
        paintDeskCustomerBalance(registeredName && opt ? opt : null);

        var toolbarRetail = toolbarRetailEl();
        var toolbarRegistered = toolbarRegisteredEl();
        var toolbarSupplier = toolbarSupplierEl();
        if (toolbarRetail && toolbarRegistered) {
            if (registeredPicking || registeredName) {
                var pickedSupplier = !!(opt && opt.dataset && opt.dataset.isSupplier === '1'
                    && opt.dataset.isCustomer === '0');
                if (partyFilter === 'supplier' || (pickedSupplier && toolbarSupplier)) {
                    if (toolbarSupplier) {
                        toolbarSupplier.checked = true;
                    }
                    partyFilter = 'supplier';
                } else {
                    toolbarRegistered.checked = true;
                    partyFilter = 'customer';
                }
                toolbarRetail.checked = false;
            } else {
                toolbarRegistered.checked = false;
                if (toolbarSupplier) {
                    toolbarSupplier.checked = false;
                }
                toolbarRetail.checked = true;
                partyFilter = '';
            }
        }

        setRegisteredUi(registeredPicking || !!registeredName);
    }

    summary.setAttribute('data-default', summary.textContent);

    function syncCustomerMode() {
        if (!select || !retailRadio || !registeredRadio) {
            return;
        }
        var hasCustomer = select.value !== '';
        registeredRadio.checked = hasCustomer || registeredPicking;
        retailRadio.checked = !registeredRadio.checked;
    }

    function openModal(modal) {
        if (modal) {
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        }
    }

    function closeModal(modal) {
        if (modal) {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        }
    }

    function decodeEntities(value) {
        var text = String(value || '');
        if (text.indexOf('&') === -1) {
            return text;
        }
        var area = document.createElement('textarea');
        area.innerHTML = text;
        return area.value;
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function digits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function norm(value) {
        return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
    }

    function toAmount(value) {
        var parsed = parseFloat(String(value || '').replace(/,/g, ''));
        return isFinite(parsed) ? parsed : 0;
    }

    function balanceMeta(amount) {
        var n = toAmount(amount);
        if (n < -0.009) {
            return {
                tag: 'জমা আছে',
                label: 'জমা/পাওনা',
                color: '#2e7d32',
                display: Math.abs(n)
            };
        }
        if (n > 0.009) {
            return {
                tag: 'বকেয়া',
                label: 'বকেয়া',
                color: '#c62828',
                display: n
            };
        }
        return {
            tag: 'সমান',
            label: 'হিসাব',
            color: '#64748b',
            display: 0
        };
    }

    function formatCustomerInfoLine(opt) {
        if (!opt || !opt.value) {
            return '';
        }
        var name = decodeEntities((opt.dataset && opt.dataset.name) || '');
        var code = decodeEntities((opt.dataset && opt.dataset.code) || '');
        var phone = decodeEntities((opt.dataset && opt.dataset.phone) || '');
        var parts = [];
        if (name) {
            parts.push(name);
        }
        if (code) {
            parts.push('আইডি: ' + code);
        }
        if (phone) {
            parts.push('মোবাইল: ' + phone);
        }
        var meta = balanceMeta(opt.dataset ? opt.dataset.due : 0);
        if (meta.tag === 'জমা আছে') {
            parts.push('জমা/পাওনা ৳' + meta.display.toFixed(2));
        } else if (meta.tag === 'বকেয়া') {
            parts.push('বকেয়া ৳' + meta.display.toFixed(2));
        }
        return parts.join(' | ');
    }

    function updateDueStatus(inputId, labelId, statusId, prefix) {
        var input = document.getElementById(inputId);
        var label = labelId ? document.getElementById(labelId) : null;
        var status = statusId ? document.getElementById(statusId) : null;
        if (!input) {
            return;
        }
        var meta = balanceMeta(input.value);
        input.style.color = meta.color;
        if (label) {
            if (meta.tag === 'জমা আছে') {
                label.textContent = prefix + ' জমা/পাওনা (−)';
            } else if (meta.tag === 'বকেয়া') {
                label.textContent = prefix + ' বকেয়া (+)';
            } else {
                label.textContent = prefix + ' হিসাব';
            }
        }
        if (status) {
            if (meta.tag === 'জমা আছে') {
                status.textContent = 'জমা/পাওনা ৳' + meta.display.toFixed(2);
                status.style.color = meta.color;
            } else if (meta.tag === 'বকেয়া') {
                status.textContent = 'বকেয়া ৳' + meta.display.toFixed(2);
                status.style.color = meta.color;
            } else {
                status.textContent = '';
            }
        }
    }

    window.ameUpdateDueStatus = function () {
        updateDueStatus('prev-due', 'prev-due-label', 'prev-due-status', 'পূর্বের');
        updateDueStatus('curr-due', 'curr-due-label', 'curr-due-status', 'বর্তমান');
    };

    function hideSuggest() {
        if (!suggestList) {
            return;
        }
        suggestList.hidden = true;
        suggestList.innerHTML = '';
        activeSuggest = -1;
    }

    function customerButtonHtml(rec) {
        var meta = [];
        var code = decodeEntities(rec.code);
        var phone = decodeEntities(rec.phone);
        var name = decodeEntities(rec.name);
        if (code) {
            meta.push(code);
        }
        if (phone) {
            meta.push(phone);
        }
        if (rec.isSupplier) {
            meta.push('সরবরাহকারী');
        }
        if (rec.alwaysWholesale) {
            meta.push('পাইকারি ক্রেতা');
        }
        var balMeta = balanceMeta(rec.due);
        return '<li><button type="button" class="res-item" data-id="' + escapeHtml(rec.id) + '">'
            + '<span class="res-copy">'
            + '<span class="res-name">' + escapeHtml(name) + '</span>'
            + '<span class="res-meta">' + escapeHtml(meta.join(' | ')) + '</span>'
            + '</span>'
            + '<span class="res-price">৳' + escapeHtml(balMeta.display.toFixed(2)) + '</span>'
            + '</button></li>';
    }

    function customerAlwaysWholesaleFromOpt(opt) {
        return !!(opt && opt.dataset && opt.dataset.alwaysWholesale === '1');
    }

    function customerAlwaysWholesaleActive() {
        if (!select || !select.value) {
            return false;
        }
        return customerAlwaysWholesaleFromOpt(select.options[select.selectedIndex]);
    }

    function applyAlwaysWholesalePriceMode() {
        var force = customerAlwaysWholesaleActive();
        window.ameCustomerForceWholesale = force;
        var radios = document.querySelectorAll('input[name="price_mode"]');
        radios.forEach(function (radio) {
            var label = radio.closest('label');
            if (force) {
                if (radio.value !== 'wholesale') {
                    radio.disabled = true;
                    if (label) {
                        label.classList.add('ame-price-mode-locked');
                    }
                } else {
                    radio.checked = true;
                    radio.disabled = false;
                    if (label) {
                        label.classList.remove('ame-price-mode-locked');
                    }
                }
            } else {
                radio.disabled = false;
                if (label) {
                    label.classList.remove('ame-price-mode-locked');
                }
            }
        });
        if (force) {
            var wholesaleRadio = document.querySelector('input[name="price_mode"][value="wholesale"]');
            if (wholesaleRadio) {
                wholesaleRadio.checked = true;
            }
        }
        if (typeof window.renderCart === 'function') {
            window.renderCart();
        }
    }

    function ensurePartyOption(rec) {
        if (!select || !rec || !rec.id) {
            return null;
        }
        var id = String(rec.id);
        var opt = null;
        var i;
        for (i = 0; i < select.options.length; i++) {
            if (select.options[i].value === id) {
                opt = select.options[i];
                break;
            }
        }
        if (!opt) {
            opt = document.createElement('option');
            select.appendChild(opt);
        }
        opt.value = id;
        opt.dataset.name = rec.name || '';
        opt.dataset.rawName = rec.rawName || rec.name || '';
        opt.dataset.isSupplier = rec.isSupplier ? '1' : '0';
        opt.dataset.isCustomer = rec.isCustomer === false ? '0' : '1';
        opt.dataset.partyCode = rec.partyCode || '';
        opt.dataset.code = rec.code || '';
        opt.dataset.phone = rec.phone || '';
        opt.dataset.alt = rec.alt || '';
        opt.dataset.due = rec.due || '0.00';
        opt.dataset.credit = rec.credit || '0.00';
        opt.dataset.alwaysWholesale = (rec.alwaysWholesale || rec.always_wholesale) ? '1' : '0';
        opt.textContent = rec.name || '';
        partyCache[id] = rec;
        return opt;
    }

    function pickCustomer(id) {
        if (!select) {
            return;
        }
        var key = id ? String(id) : '';
        if (key && partyCache[key]) {
            ensurePartyOption(partyCache[key]);
        }
        registeredPicking = !!key;
        select.value = key;
        select.dispatchEvent(new Event('change'));
        hideSuggest();
        if (searchInput) {
            if (select.value) {
                var picked = select.options[select.selectedIndex];
                searchInput.value = formatCustomerInfoLine(picked);
            } else {
                searchInput.value = '';
            }
            searchInput.blur();
        }
        applyAlwaysWholesalePriceMode();
    }

    function recordFromOption(opt) {
        return {
            id: opt.value,
            name: decodeEntities((opt.dataset && opt.dataset.name) || ''),
            phone: decodeEntities((opt.dataset && opt.dataset.phone) || ''),
            alt: decodeEntities((opt.dataset && opt.dataset.alt) || ''),
            code: decodeEntities((opt.dataset && opt.dataset.code) || ''),
            isSupplier: (opt.dataset && opt.dataset.isSupplier === '1'),
            isCustomer: !(opt.dataset && opt.dataset.isCustomer === '0'),
            partyCode: decodeEntities((opt.dataset && opt.dataset.partyCode) || ''),
            due: (opt.dataset && opt.dataset.due) || '0.00',
            alwaysWholesale: customerAlwaysWholesaleFromOpt(opt)
        };
    }

    function matchesRecord(rec, query) {
        var q = norm(query);
        if (!q) {
            return false;
        }
        var qd = digits(query);
        var hay = norm([rec.name, rec.phone, rec.alt, rec.code, rec.partyCode, rec.id, rec.isSupplier ? 'সাপ্লায়ার supplier' : ''].join(' '));
        if (hay.indexOf(q) !== -1) {
            return true;
        }
        if (qd.length >= 3) {
            var phoneHay = digits(rec.phone) + '|' + digits(rec.alt) + '|' + digits(rec.id) + '|' + digits(rec.code);
            if (phoneHay.indexOf(qd) !== -1) {
                return true;
            }
        }
        return false;
    }

    function suggestButtons() {
        if (!suggestList) {
            return [];
        }
        return Array.prototype.slice.call(suggestList.querySelectorAll('button[data-id]'));
    }

    function markActiveSuggest() {
        var buttons = suggestButtons();
        buttons.forEach(function (btn, index) {
            if (index === activeSuggest) {
                btn.classList.add('is-active');
                btn.scrollIntoView({ block: 'nearest' });
            } else {
                btn.classList.remove('is-active');
            }
        });
    }

    function searchQueryFromField() {
        if (!searchInput || !select) {
            return searchInput ? searchInput.value : '';
        }
        var opt = select.options[select.selectedIndex];
        var formatted = opt && select.value ? formatCustomerInfoLine(opt) : '';
        if (formatted && searchInput.value.trim() === formatted.trim()) {
            return '';
        }
        return searchInput.value;
    }

    function currentPartyFilter() {
        return partyFilter || (isSupplierUi() ? 'supplier' : (isRegisteredUi() ? 'customer' : ''));
    }

    function paintSuggest(rows) {
        if (retailChoiceOn()) {
            hideSuggest();
            return;
        }
        if (!suggestList || !picker) {
            return;
        }
        var noneText = picker.getAttribute('data-none') || '';
        var html = '';
        var count = 0;
        (rows || []).forEach(function (rec) {
            if (!rec || !rec.id) {
                return;
            }
            partyCache[String(rec.id)] = rec;
            count += 1;
            html += customerButtonHtml(rec);
        });
        if (count === 0) {
            html = '<li class="pos-customer-suggest-empty">'
                + escapeHtml(noneText)
                + '</li>';
        }
        suggestList.innerHTML = html;
        suggestList.hidden = false;
        activeSuggest = count > 0 ? 0 : -1;
        markActiveSuggest();
    }

    function localSuggest(query, showAll) {
        var rows = [];
        var q = String(query || '').trim();
        var filter = currentPartyFilter();
        var i;
        if (!select) {
            return rows;
        }
        for (i = 0; i < select.options.length; i++) {
            var opt = select.options[i];
            if (!opt.value) {
                continue;
            }
            var rec = recordFromOption(opt);
            if (filter === 'supplier' && !rec.isSupplier) {
                continue;
            }
            if (filter === 'customer' && rec.isSupplier && !rec.isCustomer) {
                continue;
            }
            if (q !== '' && !matchesRecord(rec, q)) {
                continue;
            }
            rows.push(rec);
            if (rows.length >= 40) {
                break;
            }
        }
        return rows;
    }

    function renderSuggest(query) {
        if (retailChoiceOn()) {
            hideSuggest();
            return;
        }
        if (!suggestList || !select || !picker) {
            return;
        }

        var q = String(query || '').trim();
        var showAll = arguments.length > 1 && arguments[1];
        if (q === '' && !showAll) {
            hideSuggest();
            return;
        }

        suggestToken += 1;
        var token = suggestToken;
        clearTimeout(suggestTimer);
        suggestTimer = setTimeout(function () {
            var filter = currentPartyFilter();
            var url = 'pos_party_search.php?q=' + encodeURIComponent(q)
                + '&filter=' + encodeURIComponent(filter)
                + (showAll ? '&all=1' : '');
            fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('party search failed');
                    }
                    return response.json();
                })
                .then(function (rows) {
                    if (token !== suggestToken) {
                        return;
                    }
                    paintSuggest(Array.isArray(rows) ? rows : []);
                })
                .catch(function () {
                    if (token !== suggestToken) {
                        return;
                    }
                    paintSuggest(localSuggest(q, showAll));
                });
        }, showAll ? 0 : 160);
    }

    function applyActiveSuggest() {
        var buttons = suggestButtons();
        if (!buttons.length) {
            return;
        }
        var index = activeSuggest;
        if (index < 0 || index >= buttons.length) {
            index = 0;
        }
        pickCustomer(buttons[index].getAttribute('data-id') || '');
    }

    function enterPartySearch(kind) {
        registeredPicking = true;
        partyFilter = kind === 'supplier' ? 'supplier' : 'customer';
        if (registeredRadio) {
            registeredRadio.checked = true;
        }
        if (retailRadio) {
            retailRadio.checked = false;
        }
        var toolbarRegistered = toolbarRegisteredEl();
        var toolbarRetail = toolbarRetailEl();
        var toolbarSupplier = toolbarSupplierEl();
        if (toolbarRetail) {
            toolbarRetail.checked = false;
        }
        if (partyFilter === 'supplier') {
            if (toolbarSupplier) {
                toolbarSupplier.checked = true;
            }
        } else if (toolbarRegistered) {
            toolbarRegistered.checked = true;
        }
        if (tabRegistered) {
            tabRegistered.classList.add('active');
        }
        if (tabGuest) {
            tabGuest.classList.remove('active');
        }
        setRegisteredUi(true);
        hideSuggest();
        if (searchInput) {
            setTimeout(function () {
                searchInput.focus();
            }, 20);
        }
    }

    function enterRegisteredSearch() {
        enterPartySearch('customer');
    }

    function enterSupplierSearch() {
        enterPartySearch('supplier');
        if (searchInput) {
            searchInput.value = '';
        }
        renderSuggest('', true);
    }

    if (tabRegistered) {
        tabRegistered.addEventListener('click', function () {
            enterRegisteredSearch();
        });
    }

    if (tabGuest) {
        tabGuest.addEventListener('click', function () {
            tabGuest.classList.add('active');
            tabRegistered.classList.remove('active');
            guestNameModal.value = guestNameHidden.value || '';
            openModal(guestModal);
        });
    }

    if (closeGuestModal) {
        closeGuestModal.addEventListener('click', function () {
            closeModal(guestModal);
        });
    }

    if (doneGuestModal) {
        doneGuestModal.addEventListener('click', function () {
            guestNameHidden.value = guestNameModal.value.trim();

            if (guestNameHidden.value !== '' && select) {
                select.value = '';
                select.dispatchEvent(new Event('change'));
            }

            closeModal(guestModal);
            updateSummary();
        });
    }

    if (select) {
        select.addEventListener('change', function () {
            syncCustomerMode();
            if (select.value !== '') {
                guestNameHidden.value = '';
                if (guestNameModal) {
                    guestNameModal.value = '';
                }
            }
            updateSummary();
            applyAlwaysWholesalePriceMode();
        });
    }

    var savedBtn = document.getElementById('ameSavedCustomerBtn');
    if (savedBtn) {
        savedBtn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (retailChoiceOn()) {
                hideSuggest();
                return;
            }
            if (suggestList && !suggestList.hidden) {
                hideSuggest();
                return;
            }
            renderSuggest('', true);
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var typed = searchQueryFromField().trim();
            if (typed) {
                renderSuggest(typed);
            } else {
                hideSuggest();
            }
            if (select && select.value === '' && guestNameHidden) {
                guestNameHidden.value = searchInput.value.trim();
            }
        });
        searchInput.addEventListener('focus', function () {
            if (select && select.value) {
                searchInput.select();
            }
        });
        searchInput.addEventListener('keydown', function (event) {
            var buttons = suggestButtons();
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (suggestList.hidden) {
                    renderSuggest(searchQueryFromField());
                    buttons = suggestButtons();
                }
                if (!buttons.length) {
                    return;
                }
                activeSuggest = (activeSuggest + 1) % buttons.length;
                markActiveSuggest();
                return;
            }
            if (event.key === 'ArrowUp') {
                event.preventDefault();
                if (!buttons.length) {
                    return;
                }
                activeSuggest = (activeSuggest - 1 + buttons.length) % buttons.length;
                markActiveSuggest();
                return;
            }
            if (event.key === 'Enter') {
                if (!suggestList.hidden && buttons.length) {
                    event.preventDefault();
                    applyActiveSuggest();
                }
                return;
            }
            if (event.key === 'Escape') {
                hideSuggest();
            }
        });
    }

    if (suggestList) {
        suggestList.addEventListener('mousedown', function (event) {
            var btn = event.target.closest('button[data-id]');
            if (!btn) {
                return;
            }
            event.preventDefault();
            pickCustomer(btn.getAttribute('data-id') || '');
        });
    }

    document.addEventListener('click', function (event) {
        if (!picker || picker.contains(event.target)) {
            return;
        }
        hideSuggest();
        if (select && select.value && searchInput && document.activeElement !== searchInput) {
            var opt = select.options[select.selectedIndex];
            searchInput.value = formatCustomerInfoLine(opt);
        }
    });

    syncCustomerMode();
    updateSummary();
    applyAlwaysWholesalePriceMode();

    if (searchInput) {
        searchInput.addEventListener('click', function () {
            if (isRegisteredUi() && select && select.value) {
                searchInput.select();
            }
        });
    }

    document.querySelectorAll('input[name="cus_type_toolbar"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (radio.value === 'Registered') {
                enterRegisteredSearch();
                return;
            }
            if (radio.value === 'Supplier') {
                if (select) {
                    select.value = '';
                }
                enterSupplierSearch();
                updateSummary();
                return;
            }

            registeredPicking = false;
            partyFilter = '';
            if (select) {
                select.value = '';
                select.dispatchEvent(new Event('change'));
            }
            if (guestNameHidden) {
                guestNameHidden.value = '';
            }
            if (guestNameModal) {
                guestNameModal.value = '';
            }
            if (retailRadio) {
                retailRadio.checked = true;
            }
            if (searchInput) {
                searchInput.value = '';
            }
            hideSuggest();
            setRegisteredUi(false);
            updateSummary();
        });
    });

    var openQuick = document.getElementById('openQuickCustomer');
    var quickModal = document.getElementById('quickCustomerModal');
    var closeQuick = document.getElementById('closeQuickCustomer');
    var cancelQuick = document.getElementById('cancelQuickCustomer');
    var quickForm = document.getElementById('quickCustomerForm');
    var quickError = document.getElementById('quickCustomerError');

    function showQuickError(message) {
        if (!quickError) {
            return;
        }
        quickError.textContent = message || '';
        if (message) {
            quickError.classList.add('show');
        } else {
            quickError.classList.remove('show');
        }
    }

    function appendCustomerOption(customer) {
        if (!select || !customer || !customer.id) {
            return;
        }
        var existing = select.querySelector('option[value="' + String(customer.id) + '"]');
        if (existing) {
            pickCustomer(String(customer.id));
            return;
        }
        var opt = document.createElement('option');
        var code = customer.customer_code || String(customer.id);
        var due = Number(customer.receivable_balance || 0).toFixed(2);
        opt.value = String(customer.id);
        opt.dataset.name = customer.name || '';
        opt.dataset.code = code;
        opt.dataset.phone = customer.phone || '';
        opt.dataset.alt = customer.alt_phone || '';
        opt.dataset.due = due;
        opt.dataset.credit = '0.00';
        opt.dataset.alwaysWholesale = customer.always_wholesale || customer.alwaysWholesale ? '1' : '0';
        opt.textContent = (customer.name || '') + ' - ' + code + ' - ৳' + due;
        select.appendChild(opt);
        pickCustomer(opt.value);
    }

    if (openQuick) {
        openQuick.addEventListener('click', function () {
            showQuickError('');
            openModal(quickModal);
            var nameField = document.getElementById('quickCustomerName');
            if (nameField) {
                setTimeout(function () {
                    nameField.focus();
                }, 40);
            }
        });
    }

    function closeQuickModal() {
        showQuickError('');
        closeModal(quickModal);
    }

    if (closeQuick) {
        closeQuick.addEventListener('click', closeQuickModal);
    }
    if (cancelQuick) {
        cancelQuick.addEventListener('click', closeQuickModal);
    }

    if (quickForm) {
        quickForm.addEventListener('submit', function (event) {
            event.preventDefault();
            showQuickError('');

            var csrf = document.getElementById('posCustomerCsrf');
            var saveBtn = document.getElementById('saveQuickCustomer');
            var payload = {
                csrf_token: csrf ? csrf.value : '',
                name: (document.getElementById('quickCustomerName') || {}).value || '',
                phone: (document.getElementById('quickCustomerPhone') || {}).value || '',
                address: (document.getElementById('quickCustomerAddress') || {}).value || '',
                opening_type: (document.getElementById('quickCustomerOpeningType') || {}).value || 'due',
                opening_amount: (document.getElementById('quickCustomerOpeningAmount') || {}).value || '0',
                due_date: (document.getElementById('quickCustomerDueDate') || {}).value || '',
                due_watch: !!(document.getElementById('quickCustomerDueWatch') || {}).checked,
                note: (document.getElementById('quickCustomerNote') || {}).value || ''
            };

            if (saveBtn) {
                saveBtn.disabled = true;
            }

            fetch('quick_customer_api.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, data: data };
                });
            }).then(function (result) {
                if (!result.ok || !result.data || result.data.status !== 'success') {
                    showQuickError((result.data && result.data.message) || '');
                    return;
                }
                appendCustomerOption(result.data.customer || {});
                quickForm.reset();
                closeQuickModal();
            }).catch(function () {
                showQuickError('');
            }).then(function () {
                if (saveBtn) {
                    saveBtn.disabled = false;
                }
            });
        });
    }
})();
</script>
