'use strict';

/*
 * POS Draft Interface Controller
 */

(function () {
    const draftIdInput = document.getElementById(
        'currentDraftId'
    );

    const saveButton = document.getElementById(
        'saveDraftButton'
    );

    const openButton = document.getElementById(
        'openDraftsButton'
    );

    const closeButton = document.getElementById(
        'closeDraftsButton'
    );

    const drawer = document.getElementById(
        'draftDrawer'
    );

    const listBox = document.getElementById(
        'draftList'
    );

    const noteInput = document.getElementById(
        'draftNote'
    );

    const currentLabel = document.getElementById(
        'currentDraftLabel'
    );

    const paymentRowsBox = document.getElementById(
        'posPaymentRows'
    );

    const paymentTemplate = document.getElementById(
        'posPaymentRowTemplate'
    );

    if (
        !draftIdInput
        || !saveButton
        || !openButton
        || !drawer
        || !listBox
        || !paymentRowsBox
        || !paymentTemplate
    ) {
        return;
    }

    const initialPaymentRow = paymentRowsBox
        .querySelector('.pos-payment-row')
        ?.cloneNode(true);

    const isEnglish = (typeof window.AME_LANG === 'string' && window.AME_LANG !== '')
        ? window.AME_LANG === 'en'
        : document.querySelector(
            'a[href*="switch_language.php?lang=bn"]'
        ) !== null;

    const words = isEnglish
        ? {
            saving: 'Saving draft...',
            saved: 'Draft saved successfully.',
            savedTitle: 'Draft saved',
            savedHint: 'The memo is stored. Start the next sale when you are ready.',
            continueBtn: 'Start new sale',
            copyBtn: 'Copy',
            copiedBtn: 'Copied',
            ticketLabel: 'Draft no.',
            loading: 'Loading drafts...',
            empty: 'No active drafts were found.',
            failed: 'The draft operation failed.',
            load: 'Edit Draft',
            cancel: 'Cancel',
            customer: 'Customer',
            total: 'Total',
            updated: 'Updated',
            confirmCancel: 'Are you sure you want to cancel this draft?',
            activeDraft: 'Editing',
            invalidResponse: 'The server returned an invalid response.',
            completeLoading: 'Loading completed sales...',
            completeEmpty: 'No completed sales were found.',
            completeFailed: 'Completed sales could not be loaded.',
            print: 'Print',
            share: 'Share',
            soldAt: 'Date',
            edit: 'Edit',
            finish: 'Complete',
            walkin: 'Walk-in',
            prev: 'Previous',
            next: 'Next'
        }
        : {
            saving: 'ড্রাফট সংরক্ষণ হচ্ছে...',
            saved: 'ড্রাফট সফলভাবে সংরক্ষণ হয়েছে।',
            savedTitle: 'ড্রাফট সংরক্ষিত',
            savedHint: 'মেমো সেভ হয়েছে। নতুন বিক্রয় শুরু করতে পারেন।',
            continueBtn: 'নতুন বিক্রয় শুরু করুন',
            copyBtn: 'কপি',
            copiedBtn: 'কপি হয়েছে',
            ticketLabel: 'ড্রাফট নং',
            loading: 'ড্রাফট তালিকা লোড হচ্ছে...',
            empty: 'কোনো সক্রিয় ড্রাফট পাওয়া যায়নি।',
            failed: 'ড্রাফট কার্যক্রম সম্পন্ন করা যায়নি।',
            load: 'ড্রাফট সম্পাদনা',
            cancel: 'বাতিল',
            customer: 'ক্রেতা',
            total: 'মোট',
            updated: 'সর্বশেষ পরিবর্তন',
            confirmCancel: 'আপনি কি এই ড্রাফট বাতিল করতে চান?',
            activeDraft: 'সম্পাদনাধীন',
            invalidResponse: 'সার্ভার থেকে সঠিক উত্তর পাওয়া যায়নি।',
            completeLoading: 'সম্পূর্ণ বিক্রয় লোড হচ্ছে...',
            completeEmpty: 'কোনো সম্পূর্ণ বিক্রয় পাওয়া যায়নি।',
            completeFailed: 'সম্পূর্ণ বিক্রয় লোড করা যায়নি।',
            print: 'প্রিন্ট',
            share: 'শেয়ার',
            soldAt: 'তারিখ',
            edit: 'এডিট',
            finish: 'কমপ্লিট',
            walkin: 'খুচরা ক্রেতা',
            prev: 'আগে',
            next: 'পরে'
        };

    const DRAFT_PAGE_SIZE = 8;
    let draftCache = [];
    let draftPage = 0;

    let draftBusy = false;

    function element(id) {
        return document.getElementById(id);
    }

    function number(value) {
        const parsed = Number.parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    async function jsonRequest(url, options = {}) {
        const headers = Object.assign({
            'X-CSRF-Token': window.AME_CSRF_TOKEN || ''
        }, options.headers || {});
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: headers
        });

        const responseText = await response.text();
        let result;

        try {
            result = JSON.parse(responseText);
        } catch (error) {
            throw new Error(words.invalidResponse);
        }

        if (!response.ok || result.status !== 'success') {
            throw new Error(
                result.message || words.failed
            );
        }

        return result;
    }

    function collectPayments() {
        const deskFirst = number(element('deskPaid')?.value);
        const deskSecond = number(element('deskPaid2')?.value);
        if (
            (deskFirst > 0 || deskSecond > 0)
            && typeof window.ameSyncDeskPaid === 'function'
        ) {
            window.ameSyncDeskPaid();
        }

        const payments = [];

        paymentRowsBox
            .querySelectorAll('.pos-payment-row')
            .forEach(function (row) {
                const account = row.querySelector(
                    '.pos-payment-account'
                );

                const amount = row.querySelector(
                    '.pos-payment-amount'
                );

                const reference = row.querySelector(
                    '.pos-payment-reference'
                );

                const accountId = Number(
                    account?.value || 0
                );

                const paymentAmount = number(
                    amount?.value
                );

                if (
                    accountId > 0
                    && paymentAmount > 0
                ) {
                    payments.push({
                        account_id: accountId,
                        amount: paymentAmount,
                        reference_number:
                            reference?.value.trim() || ''
                    });
                }
            });

        return payments;
    }

    function currentCustomerType() {
        return document.querySelector(
            'input[name="cus_type"]:checked'
        )?.value || 'Retail';
    }

    function selectedCustomerId() {
        return Number(element('customer_id')?.value || 0);
    }

    function applyDraftCustomer(draft) {
        var customerSelect = element('customer_id');
        var customerId = Number(draft && draft.customer_id ? draft.customer_id : 0);
        var registered = customerId > 0
            || String((draft && draft.customer_type) || '').toLowerCase() === 'registered';

        if (customerSelect && customerId > 0) {
            var id = String(customerId);
            var existing = customerSelect.querySelector('option[value="' + id + '"]');
            if (!existing) {
                existing = document.createElement('option');
                existing.value = id;
                customerSelect.appendChild(existing);
            }
            var name = String((draft && draft.customer_name) || (existing.dataset && existing.dataset.name) || '').trim();
            if (!name && existing.textContent) {
                name = existing.textContent.split(' - ')[0].split(' (')[0].trim();
            }
            var code = String((draft && draft.customer_code) || (existing.dataset && existing.dataset.code) || id);
            var phone = String((draft && draft.customer_phone) || (existing.dataset && existing.dataset.phone) || '');
            var due = Number((draft && draft.customer_due) || (existing.dataset && existing.dataset.due) || 0).toFixed(2);
            if (!name) {
                name = code || ('#' + id);
            }
            existing.value = id;
            existing.dataset.name = name;
            existing.dataset.code = code;
            existing.dataset.phone = phone;
            existing.dataset.alt = String((draft && draft.customer_alt) || (existing.dataset && existing.dataset.alt) || '');
            existing.dataset.due = due;
            existing.dataset.credit = Number((draft && draft.customer_credit) || (existing.dataset && existing.dataset.credit) || 0).toFixed(2);
            if (!existing.dataset.isCustomer) {
                existing.dataset.isCustomer = '1';
            }
            existing.textContent = name;
            customerSelect.value = id;
            if (customerSelect.value !== id) {
                customerSelect.value = id;
            }
        } else if (customerSelect) {
            customerSelect.value = '';
        }

        var retailRadio = element('posRetailCustomerMode');
        var registeredRadio = element('posRegisteredCustomerMode');
        var hasRegistered = Boolean(customerSelect && customerSelect.value);
        if (retailRadio && registeredRadio) {
            registeredRadio.checked = hasRegistered || registered;
            retailRadio.checked = !registeredRadio.checked;
        }

        var toolbarRetail = document.querySelector('input[name="cus_type_toolbar"][value="Retail"]');
        var toolbarRegistered = document.querySelector('input[name="cus_type_toolbar"][value="Registered"]');
        var toolbarSupplier = document.querySelector('input[name="cus_type_toolbar"][value="Supplier"]');
        var pickedSupplier = false;
        if (hasRegistered && customerSelect) {
            var pickedOpt = customerSelect.options[customerSelect.selectedIndex];
            pickedSupplier = !!(pickedOpt && pickedOpt.dataset && pickedOpt.dataset.isSupplier === '1'
                && pickedOpt.dataset.isCustomer === '0');
        }
        if (toolbarRetail && toolbarRegistered) {
            if ((hasRegistered || registered) && pickedSupplier && toolbarSupplier) {
                toolbarSupplier.checked = true;
                toolbarRetail.checked = false;
            } else {
                toolbarRegistered.checked = hasRegistered || registered;
                toolbarRetail.checked = !toolbarRegistered.checked;
            }
        }

        if (customerSelect) {
            customerSelect.dispatchEvent(new Event('change'));
        }
        paintDraftCustomerField(draft);
    }

    function paintDraftCustomerField(draft) {
        var customerId = Number(draft && draft.customer_id ? draft.customer_id : 0);
        var infoField = element('posCustomerInfoField');
        var customerSelect = element('customer_id');
        if (customerId <= 0 || !infoField || !customerSelect) {
            return;
        }
        var opt = customerSelect.options[customerSelect.selectedIndex];
        if (!opt || String(opt.value) !== String(customerId)) {
            return;
        }
        var name = String((opt.dataset && opt.dataset.name) || '').trim();
        if (!name) {
            return;
        }
        if (!String(infoField.value || '').trim()) {
            infoField.value = name;
        }
        infoField.classList.add('has-customer');
    }

    function buildDraftRequest() {
        const customerId = selectedCustomerId();
        const registered = customerId > 0 || currentCustomerType() === 'Registered';

        return {
            action: 'save',
            csrf_token: window.AME_CSRF_TOKEN || '',
            draft_id: Number(
                draftIdInput.value || 0
            ),
            cart: typeof window.ameGetCart === 'function'
                ? window.ameGetCart()
                : (typeof cart !== 'undefined' && Array.isArray(cart) ? cart : []),
            customer_id: registered
                ? customerId
                : null,
            customer_type: registered
                ? 'Registered'
                : 'Retail',
            guest_customer_name: registered
                ? ''
                : (
                    element('guestCustomerName')?.value
                    || ''
                ).trim(),
            guest_customer_phone: registered
                ? ''
                : (
                    element('guestCustomerPhone')?.value
                    || ''
                ).trim(),
            guest_customer_note: registered
                ? ''
                : (
                    element('guestCustomerNote')?.value
                    || ''
                ).trim(),
            discount: number(
                element('discount')?.value
            ),
            vat_percent: number(
                element('vat-per')?.value
            ),
            rounding: number(
                element('rounding')?.value
            ),
            services: (function () {
                try {
                    return typeof window.ameCollectServices === 'function'
                        ? window.ameCollectServices(false)
                        : [];
                } catch (error) {
                    return [];
                }
            })(),
            service_amount: number(
                element('service_amount')?.value
            ),
            payments: collectPayments(),
            exchange: typeof window.ameGetExchange === 'function'
                ? window.ameGetExchange()
                : [],
            adjust_due: Boolean(
                element('adjust-due')?.checked
            ),
            draft_note: (
                noteInput?.value || ''
            ).trim(),
            // গাড়ির নম্বর — আগে এখানে পাঠানোই হতো না, তাই Draft সেভ হওয়ার পর
            // এডিট করার জন্য আবার লোড করলে ফিল্ডটা খালি দেখাত।
            vehicle_no: (
                element('posVehicleNo')?.value || ''
            ).trim()
        };
    }

    function showDraftNotice(options) {
        return new Promise(function (resolve) {
            var done = false;
            function finish() {
                if (done) {
                    return;
                }
                done = true;
                overlay.hidden = true;
                resolve();
            }

            var overlay = document.getElementById('ameDraftToast');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'ameDraftToast';
                overlay.className = 'ame-draft-toast';
                overlay.setAttribute('role', 'alertdialog');
                overlay.setAttribute('aria-modal', 'true');
                overlay.innerHTML =
                    '<div class="ame-draft-ticket">'
                    + '<div class="ame-draft-ticket-bar"></div>'
                    + '<div class="ame-draft-ticket-body">'
                    + '<div class="ame-draft-stamp" aria-hidden="true">'
                    + '<svg viewBox="0 0 48 48">'
                    + '<path class="ame-draft-check" d="M12 25 L21 34 L36 15"></path>'
                    + '</svg></div>'
                    + '<h3 class="ame-draft-title"></h3>'
                    + '<p class="ame-draft-hint"></p>'
                    + '<div class="ame-draft-code" hidden>'
                    + '<b></b>'
                    + '<button type="button" class="ame-draft-copy"></button>'
                    + '</div>'
                    + '<button type="button" class="ame-draft-ok"></button>'
                    + '</div></div>';
                document.body.appendChild(overlay);
            }

            var ticket = overlay.querySelector('.ame-draft-ticket');
            var stampPath = overlay.querySelector('.ame-draft-check');
            var titleEl = overlay.querySelector('.ame-draft-title');
            var hintEl = overlay.querySelector('.ame-draft-hint');
            var codeWrap = overlay.querySelector('.ame-draft-code');
            var codeEl = overlay.querySelector('.ame-draft-code b');
            var copyBtn = overlay.querySelector('.ame-draft-copy');
            var okBtn = overlay.querySelector('.ame-draft-ok');
            var isError = Boolean(options && options.error);

            ticket.classList.toggle('is-error', isError);
            titleEl.textContent = (options && options.title) || words.savedTitle;
            hintEl.textContent = (options && options.hint) || words.savedHint;
            okBtn.textContent = (options && options.ok) || words.continueBtn;

            if (stampPath) {
                stampPath.setAttribute(
                    'd',
                    isError ? 'M16 16 L32 32 M32 16 L16 32' : 'M12 25 L21 34 L36 15'
                );
                stampPath.style.animation = 'none';
                void stampPath.offsetWidth;
                stampPath.style.animation = '';
            }

            var code = (options && options.code) || '';
            if (code) {
                codeEl.textContent = (words.ticketLabel ? words.ticketLabel + ' · ' : '') + code;
                copyBtn.textContent = words.copyBtn;
                codeWrap.hidden = false;
                copyBtn.onclick = function (event) {
                    event.stopPropagation();
                    var text = code;
                    var copied = function () {
                        copyBtn.textContent = words.copiedBtn;
                    };
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(copied).catch(copied);
                    } else {
                        copied();
                    }
                };
            } else {
                codeWrap.hidden = true;
            }

            ticket.style.animation = 'none';
            overlay.hidden = false;
            void ticket.offsetWidth;
            ticket.style.animation = '';
            overlay.setAttribute('aria-label', titleEl.textContent);
            okBtn.focus();
            okBtn.onclick = finish;
            overlay.onclick = function (event) {
                if (event.target === overlay && isError) {
                    finish();
                }
            };

            if (!isError) {
                window.setTimeout(finish, (options && options.autoMs) || 2600);
            }
        });
    }

    async function saveDraft() {
        if (draftBusy) {
            return;
        }

        draftBusy = true;

        const oldText = saveButton.textContent;
        saveButton.disabled = true;
        saveButton.textContent =
            '⏳ ' + words.saving;

        try {
            const result = await jsonRequest(
                'draft_api.php',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type':
                            'application/json; charset=UTF-8'
                    },
                    body: JSON.stringify(
                        buildDraftRequest()
                    )
                }
            );

            var savedNumber = result.draft_number || '';

            if (typeof window.ameClearPosSale === 'function') {
                window.ameClearPosSale();
            }

            await showDraftNotice({
                title: words.savedTitle,
                hint: words.savedHint,
                code: savedNumber,
                ok: words.continueBtn
            });
            window.location.replace('pos.php?new=' + Date.now());
            return;
        } catch (error) {
            await showDraftNotice({
                error: true,
                title: words.failed,
                hint: error.message || words.failed,
                ok: isEnglish ? 'OK' : 'ঠিক আছে'
            });
        } finally {
            draftBusy = false;
            saveButton.disabled = false;
            saveButton.textContent = oldText;
        }
    }

    function showDrawer() {
        drawer.classList.add('open');
        drawer.setAttribute('aria-hidden', 'false');
        loadDraftList();
    }

    function hideDrawer() {
        drawer.classList.remove('open');
        drawer.setAttribute('aria-hidden', 'true');
    }

    function makeText(tag, text, className = '') {
        const node = document.createElement(tag);
        node.textContent = text;

        if (className) {
            node.className = className;
        }

        return node;
    }

    function formatSaleTime(value) {
        if (!value) {
            return '';
        }

        const parsed = new Date(String(value).replace(' ', 'T'));

        if (Number.isNaN(parsed.getTime())) {
            return String(value);
        }

        const day = String(parsed.getDate()).padStart(2, '0');
        const month = String(parsed.getMonth() + 1).padStart(2, '0');
        const year = String(parsed.getFullYear()).slice(-2);
        let hours = parsed.getHours();
        const minutes = String(parsed.getMinutes()).padStart(2, '0');
        const meridian = hours >= 12 ? 'pm' : 'am';

        hours = hours % 12;
        if (hours === 0) {
            hours = 12;
        }

        return day
            + '/'
            + month
            + '/'
            + year
            + ' '
            + String(hours).padStart(2, '0')
            + ':'
            + minutes
            + ' '
            + meridian;
    }

    function tableMessageRow(text) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 2;
        cell.className = 'pos-draft-empty';
        cell.textContent = text;
        row.appendChild(cell);
        return row;
    }

    function draftWho(draft) {
        const name = String((draft && draft.customer_name) || '').trim();
        if (name && name !== '-' && name.toLowerCase() !== 'retail customer') {
            return name;
        }
        const code = String((draft && draft.customer_code) || '').trim();
        if (code) {
            return code;
        }
        if (draft && Number(draft.customer_id) > 0) {
            return '#' + Number(draft.customer_id);
        }
        return words.walkin;
    }

    function openDraftMemo(draftId) {
        hideCompleteDrawer();
        loadDraft(draftId);
    }

    function draftPagerNode() {
        let pager = document.getElementById('draftListPager');
        if (pager) {
            return pager;
        }
        const footer = drawer.querySelector('.pos-recent-modal-footer');
        if (!footer) {
            return null;
        }
        pager = document.createElement('div');
        pager.id = 'draftListPager';
        pager.className = 'pos-recent-pager';
        const prev = document.createElement('button');
        prev.type = 'button';
        prev.className = 'pos-recent-page-btn';
        prev.dataset.dir = '-1';
        prev.textContent = words.prev;
        const label = document.createElement('span');
        label.className = 'pos-recent-page-label';
        const next = document.createElement('button');
        next.type = 'button';
        next.className = 'pos-recent-page-btn';
        next.dataset.dir = '1';
        next.textContent = words.next;
        pager.append(prev, label, next);
        pager.addEventListener('click', function (event) {
            const button = event.target.closest('button');
            if (!button || button.disabled) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            const pages = Math.max(1, Math.ceil(draftCache.length / DRAFT_PAGE_SIZE));
            const dir = Number(button.dataset.dir || 0);
            draftPage = Math.min(pages - 1, Math.max(0, draftPage + dir));
            paintDraftPage();
        });
        footer.insertBefore(pager, footer.firstChild);
        return pager;
    }

    function paintDraftPager() {
        const pager = draftPagerNode();
        if (!pager) {
            return;
        }
        const pages = Math.max(1, Math.ceil(draftCache.length / DRAFT_PAGE_SIZE));
        const show = draftCache.length > DRAFT_PAGE_SIZE;
        pager.hidden = !show;
        if (!show) {
            return;
        }
        const label = pager.querySelector('.pos-recent-page-label');
        if (label) {
            label.textContent = (draftPage + 1) + ' / ' + pages;
        }
        const prev = pager.querySelector('[data-dir="-1"]');
        const next = pager.querySelector('[data-dir="1"]');
        if (prev) {
            prev.disabled = draftPage <= 0;
        }
        if (next) {
            next.disabled = draftPage >= pages - 1;
        }
    }

    var highlightDraftId = '';

    function renderDraftList(drafts) {
        draftCache = Array.isArray(drafts) ? drafts : [];
        if (highlightDraftId) {
            const focusIdx = draftCache.findIndex(function (draft) {
                return String(draft.id) === String(highlightDraftId);
            });
            draftPage = focusIdx >= 0
                ? Math.floor(focusIdx / DRAFT_PAGE_SIZE)
                : 0;
        } else {
            draftPage = 0;
        }
        paintDraftPage();
    }

    function paintDraftPage() {
        listBox.replaceChildren();
        paintDraftPager();

        if (draftCache.length === 0) {
            listBox.appendChild(tableMessageRow(words.empty));
            return;
        }

        const start = draftPage * DRAFT_PAGE_SIZE;
        draftCache.slice(start, start + DRAFT_PAGE_SIZE).forEach(function (draft) {
            const row = document.createElement('tr');
            row.className = 'pos-recent-draft-row';
            if (
                highlightDraftId
                && String(draft.id) === String(highlightDraftId)
            ) {
                row.classList.add('is-reopened-highlight');
            }
            row.tabIndex = 0;
            row.title = words.edit;
            row.setAttribute('role', 'button');
            row.setAttribute('aria-label', words.edit + ' · ' + draftWho(draft));
            row.addEventListener('click', function (event) {
                if (event.target.closest('a, button')) {
                    return;
                }
                event.preventDefault();
                openDraftMemo(draft.id);
            });
            row.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openDraftMemo(draft.id);
                }
            });

            const customerCell = document.createElement('td');
            customerCell.className = 'pos-recent-cust';
            const nameLine = document.createElement('span');
            nameLine.className = 'pos-recent-cust-name';
            nameLine.textContent = draftWho(draft);
            customerCell.appendChild(nameLine);
            row.appendChild(customerCell);

            const actionCell = document.createElement('td');
            actionCell.className = 'pos-recent-actions';
            const actionBar = document.createElement('span');
            actionBar.className = 'pos-recent-action-bar';
            const printLink = document.createElement('a');
            printLink.href = 'invoice.php?id=' + encodeURIComponent(draft.id);
            printLink.target = '_blank';
            printLink.rel = 'noopener';
            printLink.className = 'pos-recent-icon-btn';
            printLink.title = words.print;
            printLink.setAttribute('aria-label', words.print);
            printLink.textContent = '🖨️';
            printLink.addEventListener('click', function (event) {
                event.stopPropagation();
            });
            const shareLink = document.createElement('a');
            shareLink.href = 'invoice.php?id=' + encodeURIComponent(draft.id) + '&share=1';
            shareLink.target = '_blank';
            shareLink.rel = 'noopener';
            shareLink.className = 'pos-recent-icon-btn';
            shareLink.title = words.share;
            shareLink.setAttribute('aria-label', words.share);
            shareLink.textContent = '📤';
            shareLink.addEventListener('click', function (event) {
                event.stopPropagation();
            });
            const loadButton = document.createElement('button');
            loadButton.type = 'button';
            loadButton.className = 'pos-recent-icon-btn pos-recent-edit-btn';
            loadButton.title = words.edit;
            loadButton.setAttribute('aria-label', words.edit);
            loadButton.textContent = '✏️';
            loadButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                openDraftMemo(draft.id);
            });
            const finishButton = document.createElement('button');
            finishButton.type = 'button';
            finishButton.className = 'pos-recent-icon-btn pos-recent-finish-btn';
            finishButton.title = words.finish;
            finishButton.setAttribute('aria-label', words.finish);
            finishButton.textContent = words.finish;
            finishButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (typeof window.ameFinishDraftMemo === 'function') {
                    window.ameFinishDraftMemo(draft.id);
                    return;
                }
                openDraftMemo(draft.id);
            });
            actionBar.append(printLink, shareLink, loadButton, finishButton);
            actionCell.appendChild(actionBar);
            row.appendChild(actionCell);
            listBox.appendChild(row);
        });
    }

    async function loadDraftList() {
        draftCache = [];
        draftPage = 0;
        paintDraftPager();
        listBox.replaceChildren();
        listBox.appendChild(tableMessageRow(words.loading));

        try {
            const result = await jsonRequest(
                'draft_query.php?action=list'
            );

            renderDraftList(result.drafts);
        } catch (error) {
            draftCache = [];
            draftPage = 0;
            paintDraftPager();
            listBox.replaceChildren();
            listBox.appendChild(
                tableMessageRow(error.message || words.failed)
            );
        }
    }

    function resetPaymentRows(payments) {
        paymentRowsBox.replaceChildren();

        const rows = (
            Array.isArray(payments)
            && payments.length > 0
        )
            ? payments
            : [null];

        rows.forEach(function (payment, index) {
            let row;

            if (index === 0 && initialPaymentRow) {
                row = initialPaymentRow.cloneNode(true);
            } else {
                const fragment = paymentTemplate
                    .content
                    .cloneNode(true);

                row = fragment.querySelector(
                    '.pos-payment-row'
                );
            }

            if (!row) {
                return;
            }

            const account = row.querySelector(
                '.pos-payment-account'
            );

            const amount = row.querySelector(
                '.pos-payment-amount'
            );

            const reference = row.querySelector(
                '.pos-payment-reference'
            );

            if (payment) {
                account.value = payment.account_id;
                amount.value = payment.amount;
                reference.value =
                    payment.reference_number || '';
            } else {
                account.value = '';
                amount.value = '0';
                reference.value = '';
            }

            paymentRowsBox.appendChild(row);
        });
    }

    function applyDraft(draft) {
        draftIdInput.value = draft.id;

        const registered = Number(draft.customer_id || 0) > 0;
        applyDraftCustomer(draft);

        const guestName = element('guestCustomerName');
        const guestPhone = element('guestCustomerPhone');
        const guestNote = element('guestCustomerNote');
        const guestNameModal = element('guestCustomerNameModal');
        const infoField = element('posCustomerInfoField');

        if (!registered) {
            var guestValue = draft.guest_customer_name || '';
            if (guestName) {
                guestName.value = guestValue;
            }
            if (guestNameModal) {
                guestNameModal.value = guestValue;
            }
            if (infoField && !infoField.readOnly) {
                infoField.value = guestValue;
            }
        }

        if (guestPhone) {
            guestPhone.value = registered
                ? ''
                : (draft.guest_customer_phone || '');
        }

        if (guestNote) {
            guestNote.value = registered
                ? ''
                : (draft.guest_customer_note || '');
        }

        element('discount').value =
            number(draft.discount);

        element('vat-per').value =
            number(draft.vat_percent);

        element('rounding').value =
            number(draft.rounding);

        element('adjust-due').checked =
            Boolean(draft.adjust_due);

        if (noteInput) {
            noteInput.value = draft.draft_note || '';
        }

        // গাড়ির নম্বর — Draft লোড করার সময় আগে এই ফিল্ডটা পূরণ করাই হতো না
        var vehicleNoField = element('posVehicleNo');
        if (vehicleNoField) {
            vehicleNoField.value = draft.vehicle_no || '';
        }

        var loadedServices = Array.isArray(draft.services) && draft.services.length
            ? draft.services
            : (draft.service ? [draft.service] : []);

        if (typeof window.ameSetServiceRows === 'function') {
            window.ameSetServiceRows(loadedServices);
        } else if (element('service_amount')) {
            var serviceSum = 0;
            loadedServices.forEach(function (row) {
                serviceSum += number(row.amount);
            });
            element('service_amount').value = serviceSum > 0
                ? serviceSum.toFixed(2)
                : '';
        }

        var loadedCart = (draft.items || []).map(function (item) {
            const savedPrice = number(item.saved_price);
            const isCustom = !!item.is_custom;

            return {
                id: isCustom ? null : Number(item.id),
                sku: item.sku || '',
                name: item.name,
                qty: number(item.qty),
                stock: isCustom ? null : number(item.stock),
                current_price: savedPrice,
                is_custom: isCustom,
                price_locked: true,
                unit: item.unit || '',

                /*
                 * Draft-এর সম্মত মূল্য UI-তে স্থির থাকবে।
                 * Complete করার সময় Server বর্তমান অনুমোদিত
                 * মূল্যের সঙ্গে পুনরায় যাচাই করবে।
                 */
                sale_price: savedPrice,
                wholesale_price: savedPrice,
                dealer_price: savedPrice
            };
        });

        if (typeof window.ameSetCart === 'function') {
            window.ameSetCart(loadedCart);
        } else {
            cart = loadedCart;
        }

        if (typeof window.ameSetExchange === 'function') {
            window.ameSetExchange(draft.exchange || []);
        }

        resetPaymentRows(draft.payments);
        renderCart();

        if (typeof window.ameApplyDeskDiscount === 'function') {
            window.ameApplyDeskDiscount(number(draft.discount));
        } else if (element('discount')) {
            element('discount').value = number(draft.discount);
        }

        if (typeof window.ameApplyDeskPayments === 'function') {
            window.ameApplyDeskPayments(draft.payments || []);
        }

        if (typeof window.calculateAll === 'function') {
            window.calculateAll();
        } else {
            calculateAll();
        }

        paintDraftCustomerField(draft);

        if (currentLabel) {
            currentLabel.textContent =
                words.activeDraft
                + ': '
                + draft.draft_number;

            currentLabel.hidden = false;
        }

        if (draftIcon && typeof setRecentChoice === 'function') {
            setRecentChoice(draftIcon);
        }
        if (typeof window.amePosSyncMemoButton === 'function') {
            window.amePosSyncMemoButton(true);
        }

        if (!window.__ameKeepDraftDrawerOpen) {
            hideDrawer();
        }
    }

    async function loadDraft(draftId) {
        try {
            const result = await jsonRequest(
                'draft_query.php?action=load&id='
                + encodeURIComponent(draftId)
            );

            if (!result || !result.draft) {
                throw new Error(words.failed);
            }

            applyDraft(result.draft);
        } catch (error) {
            alert(error.message || words.failed);
        }
    }

    window.ameLoadDraft = loadDraft;
    window.ameSaveCurrentDraft = saveDraft;

    async function cancelDraft(draftId) {
        if (!confirm(words.confirmCancel)) {
            return;
        }

        try {
            await jsonRequest('draft_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type':
                        'application/json; charset=UTF-8'
                },
                body: JSON.stringify({
                    action: 'cancel',
                    csrf_token: window.AME_CSRF_TOKEN || '',
                    draft_id: Number(draftId)
                })
            });

            if (
                Number(draftIdInput.value)
                === Number(draftId)
            ) {
                draftIdInput.value = '';

                if (currentLabel) {
                    currentLabel.hidden = true;
                    currentLabel.textContent = '';
                }
            }

            loadDraftList();
        } catch (error) {
            alert(error.message || words.failed);
        }
    }

    saveButton.addEventListener(
        'click',
        saveDraft
    );

    openButton.addEventListener(
        'click',
        function () {
            hideFlyout();
            hideCompleteDrawer();
            showDrawer();
        }
    );

    var termHist = document.getElementById('amePosTermHist');
    var recentToggle = document.getElementById('amePosRecentToggle');
    var recentCurrent = document.getElementById('amePosRecentCurrent');
    var flyout = document.getElementById('amePosRecentFlyout');
    var draftIcon = document.getElementById('amePosRecentDraft');
    var completeIcon = document.getElementById('amePosRecentComplete');
    var completeDrawer = document.getElementById('completeDrawer');
    var completeList = document.getElementById('completeList');
    var closeComplete = document.getElementById('closeCompleteButton');

    function setRecentChoice(button) {
        if (recentCurrent && button) {
            recentCurrent.textContent = button.textContent.trim();
        }
        if (!flyout) {
            return;
        }
        flyout.querySelectorAll('.ame-pos-recent-option').forEach(function (option) {
            option.classList.toggle('is-active', option === button);
        });
        var mode = document.getElementById('amePosSaleMode');
        if (mode) {
            mode.value = (button === draftIcon) ? 'draft' : 'complete';
        }
        if (typeof window.amePosSyncMemoButton === 'function') {
            window.amePosSyncMemoButton(button === draftIcon);
        }
    }

    function hideFlyout() {
        if (!flyout) {
            return;
        }
        flyout.classList.remove('is-open');
        flyout.setAttribute('hidden', 'hidden');
        if (recentToggle) {
            recentToggle.setAttribute('aria-expanded', 'false');
        }
    }

    function openFlyout() {
        if (!flyout) {
            return;
        }
        flyout.classList.add('is-open');
        flyout.removeAttribute('hidden');
        if (recentToggle) {
            recentToggle.setAttribute('aria-expanded', 'true');
        }
    }

    function toggleFlyout() {
        if (flyout && flyout.classList.contains('is-open')) {
            hideFlyout();
            return;
        }
        openFlyout();
    }

    function hideCompleteDrawer() {
        if (!completeDrawer) {
            return;
        }
        completeDrawer.classList.remove('open');
        completeDrawer.setAttribute('aria-hidden', 'true');
    }

    function renderCompleteList(sales) {
        if (!completeList) {
            return;
        }

        completeList.replaceChildren();

        if (!Array.isArray(sales) || sales.length === 0) {
            completeList.appendChild(tableMessageRow(words.completeEmpty));
            return;
        }

        sales.forEach(function (sale, index) {
            var row = document.createElement('tr');
            var sl = document.createElement('td');
            sl.textContent = String(index + 1);
            row.appendChild(sl);

            var timeTd = document.createElement('td');
            timeTd.className = 'pos-recent-time';
            var timeRaw = formatSaleTime(sale.sold_at);
            var timeBits = String(timeRaw).split(' ');
            var dateLine = document.createElement('span');
            dateLine.className = 'pos-recent-time-date';
            dateLine.textContent = timeBits[0] || timeRaw || '-';
            timeTd.appendChild(dateLine);
            if (timeBits.length > 1) {
                var clockLine = document.createElement('span');
                clockLine.className = 'pos-recent-time-clock';
                clockLine.textContent = timeBits.slice(1).join(' ');
                timeTd.appendChild(clockLine);
            }
            row.appendChild(timeTd);

            var invTd = document.createElement('td');
            invTd.className = 'pos-recent-inv';
            invTd.textContent = sale.invoice_number || ('#' + sale.id);
            row.appendChild(invTd);

            var custTd = document.createElement('td');
            custTd.className = 'pos-recent-cust';
            var nameLine = document.createElement('span');
            nameLine.className = 'pos-recent-cust-name';
            nameLine.textContent = sale.customer_name || '-';
            custTd.appendChild(nameLine);
            row.appendChild(custTd);

            var priceTd = document.createElement('td');
            priceTd.className = 'pos-recent-price';
            priceTd.textContent = number(sale.grand_total).toFixed(2);
            row.appendChild(priceTd);

            var actionCell = document.createElement('td');
            actionCell.className = 'pos-recent-actions';
            var printLink = document.createElement('a');
            printLink.href = 'invoice.php?id=' + encodeURIComponent(sale.id);
            printLink.target = '_blank';
            printLink.rel = 'noopener';
            printLink.className = 'pos-recent-icon-btn';
            printLink.title = words.print;
            printLink.setAttribute('aria-label', words.print);
            printLink.textContent = '🖨️';
            var shareLink = document.createElement('a');
            shareLink.href = 'invoice.php?id=' + encodeURIComponent(sale.id) + '&share=1';
            shareLink.target = '_blank';
            shareLink.rel = 'noopener';
            shareLink.className = 'pos-recent-icon-btn';
            shareLink.title = words.share;
            shareLink.setAttribute('aria-label', words.share);
            shareLink.textContent = '📤';
            var tgLink = document.createElement('a');
            tgLink.href = 'invoice.php?id=' + encodeURIComponent(sale.id) + '&tg=1';
            tgLink.target = '_blank';
            tgLink.rel = 'noopener';
            tgLink.className = 'pos-recent-icon-btn';
            tgLink.title = 'Telegram-এ ছবি সহ শেয়ার করুন';
            tgLink.setAttribute('aria-label', 'Telegram');
            tgLink.textContent = '✈️';
            actionCell.append(printLink, shareLink, tgLink);
            row.appendChild(actionCell);
            completeList.appendChild(row);
        });
    }

    async function showCompleteDrawer() {
        hideFlyout();
        hideDrawer();
        if (!completeDrawer || !completeList) {
            window.location.href = 'history.php';
            return;
        }

        completeDrawer.classList.add('open');
        completeDrawer.setAttribute('aria-hidden', 'false');
        completeList.replaceChildren();
        completeList.appendChild(tableMessageRow(words.completeLoading));

        try {
            var result = await jsonRequest('history_query.php');
            renderCompleteList(result.sales);
        } catch (error) {
            completeList.replaceChildren();
            completeList.appendChild(
                tableMessageRow(error.message || words.completeFailed)
            );
        }
    }

    if (termHist) {
        termHist.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            hideFlyout();
            hideCompleteDrawer();
            showDrawer();
        });
    }

    if (recentToggle) {
        recentToggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            toggleFlyout();
        });
    }

    if (draftIcon) {
        draftIcon.addEventListener('click', function (event) {
            event.preventDefault();
            setRecentChoice(draftIcon);
            hideFlyout();
        });
    }

    if (completeIcon) {
        completeIcon.addEventListener('click', function (event) {
            event.preventDefault();
            setRecentChoice(completeIcon);
            hideFlyout();
        });
    }

    window.ameOpenDraftList = function () {
        hideFlyout();
        hideCompleteDrawer();
        showDrawer();
    };

    /** রি-ওপেন / ড্রাফটে আনুন — সাম্প্রতিক বিক্রয় = ড্রাফট + তালিকা খোলা */
    window.amePosEnterDraftModule = function (openList) {
        if (draftIcon) {
            setRecentChoice(draftIcon);
        }
        if (typeof window.amePosSyncMemoButton === 'function') {
            window.amePosSyncMemoButton(true);
        }
        hideFlyout();
        hideCompleteDrawer();
        if (openList !== false) {
            showDrawer();
        }
    };

    window.ameOpenCompleteList = function () {
        hideFlyout();
        hideCompleteDrawer();
        showDrawer();
    };

    window.amePosIsDraftMemo = function () {
        var mode = document.getElementById('amePosSaleMode');
        if (mode && String(mode.value || '').toLowerCase() === 'draft') {
            return true;
        }
        return Boolean(draftIcon && draftIcon.classList.contains('is-active'));
    };

    window.amePosSetCompleteMemo = function () {
        if (completeIcon) {
            setRecentChoice(completeIcon);
        }
    };

    closeButton?.addEventListener(
        'click',
        hideDrawer
    );

    closeComplete?.addEventListener(
        'click',
        hideCompleteDrawer
    );

    drawer.addEventListener('click', function (event) {
        if (event.target === drawer) {
            hideDrawer();
        }
    });

    if (completeDrawer) {
        completeDrawer.addEventListener('click', function (event) {
            if (event.target === completeDrawer) {
                hideCompleteDrawer();
            }
        });
    }

    document.addEventListener('click', function (event) {
        if (!flyout || !recentToggle) {
            return;
        }
        if (
            !flyout.contains(event.target)
            && event.target !== recentToggle
            && !recentToggle.contains(event.target)
        ) {
            hideFlyout();
        }
    });

    window.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            hideDrawer();
            hideCompleteDrawer();
            hideFlyout();
        }
    });

    var urlParams = new URLSearchParams(window.location.search);
    var pendingDraft = urlParams.get('draft');
    highlightDraftId = pendingDraft || '';
    var openDrafts = urlParams.get('open_drafts');
    var reopenedFromInvoice = urlParams.get('reopened') === '1';
    var enterDraftModule = openDrafts === '1' || reopenedFromInvoice;

    function cleanReopenQueryFromUrl() {
        if (!enterDraftModule || !window.history || !window.history.replaceState) {
            return;
        }
        var clean = new URL(window.location.href);
        clean.searchParams.delete('open_drafts');
        clean.searchParams.delete('reopened');
        window.history.replaceState({}, '', clean.pathname + clean.search + clean.hash);
    }

    if (pendingDraft && enterDraftModule) {
        window.__ameKeepDraftDrawerOpen = true;
        if (typeof window.amePosEnterDraftModule === 'function') {
            window.amePosEnterDraftModule(false);
        }
    }

    if (pendingDraft) {
        loadDraft(pendingDraft).finally(function () {
            window.__ameKeepDraftDrawerOpen = false;
            loadDraftList().finally(function () {
                if (enterDraftModule) {
                    if (typeof window.amePosEnterDraftModule === 'function') {
                        window.amePosEnterDraftModule(true);
                    } else {
                        showDrawer();
                    }
                    cleanReopenQueryFromUrl();
                }
            });
        });
    } else if (enterDraftModule) {
        if (typeof window.amePosEnterDraftModule === 'function') {
            window.amePosEnterDraftModule(true);
        } else {
            showDrawer();
        }
        cleanReopenQueryFromUrl();
    }
})();