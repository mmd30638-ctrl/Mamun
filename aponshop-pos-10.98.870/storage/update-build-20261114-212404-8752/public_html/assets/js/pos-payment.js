'use strict';

/*
 * একাধিক অ্যাকাউন্টে বিভক্ত পরিশোধ Engine
 *
 * A hidden desk method can mirror the same account into a second row
 * with no money. Only a positive amount on a real account counts.
 */

function amePosPaymentAccountId(value) {
    const text = String(value == null ? '' : value).trim();
    if (text === '' || text === '0') {
        return '';
    }
    const id = Number(text);
    if (!Number.isFinite(id) || id <= 0) {
        return '';
    }
    return String(Math.trunc(id));
}

function amePosCollectPayments(rawRows, validate, words) {
    const payments = [];
    const selectedAccounts = new Set();
    const labels = words || {};

    (rawRows || []).forEach(function (row) {
        const accountId = amePosPaymentAccountId(row && row.account_id);
        const parsed = Number.parseFloat(row && row.amount);
        const amount = Number.isFinite(parsed) ? parsed : 0;

        if (amount <= 0) {
            return;
        }

        if (accountId === '') {
            if (validate) {
                throw new Error(
                    labels.accountRequired
                    || 'Select an account for every payment.'
                );
            }
            return;
        }

        if (validate && selectedAccounts.has(accountId)) {
            throw new Error(
                labels.duplicateAccount
                || 'The same account cannot be selected twice.'
            );
        }

        selectedAccounts.add(accountId);
        payments.push({
            account_id: Number(accountId),
            amount: amount,
            reference_number: String((row && row.reference_number) || '').trim()
        });
    });

    return payments;
}

function ameSaleMoney(value) {
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) {
        return 0;
    }
    return Number(parsed.toFixed(2));
}

/*
 * জমা (ঋণাত্মক সাবেক) বাকি নয়। রাউন্ডিং-সহ বিক্রয়ের সাথে সাবেক যোগ করে
 * শূন্যের নিচে নামলে আদায় ০। বাকি শুধু ধনাত্মক বকেয়া।
 */
function ameSalePaymentFigures(saleBill, previousDue, currentBillRemaining, dueAdjustment) {
    const bill = Math.max(0, ameSaleMoney(saleBill));
    const previous = ameSaleMoney(previousDue);
    const remaining = Math.max(0, ameSaleMoney(currentBillRemaining));
    const adjustment = Math.max(0, ameSaleMoney(dueAdjustment));
    const collect = Math.max(0, ameSaleMoney(bill + previous));
    const dueRaw = ameSaleMoney(previous + remaining - adjustment);
    return {
        collect: collect,
        due: dueRaw > 0.009 ? dueRaw : 0
    };
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        amePosPaymentAccountId: amePosPaymentAccountId,
        amePosCollectPayments: amePosCollectPayments,
        ameSaleMoney: ameSaleMoney,
        ameSalePaymentFigures: ameSalePaymentFigures
    };
}

if (typeof document !== 'undefined') {
(function () {
    const rowsBox = document.getElementById('posPaymentRows');
    const template = document.getElementById('posPaymentRowTemplate');
    const addButton = document.getElementById('addPosPayment');

    const isEnglish = (typeof window.AME_LANG === 'string' && window.AME_LANG !== '')
        ? window.AME_LANG === 'en'
        : document.querySelector(
            'a[href*="switch_language.php?lang=bn"]'
        ) !== null;

    const words = isEnglish
        ? {
            accountRequired: 'Select an account for every payment.',
            duplicateAccount: 'The same account cannot be selected twice.',
            paymentRequired: 'Enter at least one valid payment.',
            customerRequired: 'Select a registered customer.',
            retailDue: 'A retail sale cannot have an unpaid balance. Collect cash or add an exchange.',
            serviceRequired: 'Each engineer bill needs an engineer, description and amount.',
            empty: 'Add a product or service first.',
            creditLimit: 'This sale would exceed the customer credit limit. Collect more cash or raise the limit.',
            offline: 'Internet is unavailable. The sale was saved on this phone.',
            savedOffline: '📡 Offline — sale saved on this phone. It will upload when the internet returns.',
            processing: 'Processing sale...',
            failed: 'The sale could not be completed.',
            invalidResponse: 'The server returned an invalid response.'
        }
        : {
            accountRequired: 'প্রতিটি পরিশোধের জন্য একটি অ্যাকাউন্ট নির্বাচন করুন।',
            duplicateAccount: 'একই অ্যাকাউন্ট দুইবার নির্বাচন করা যাবে না।',
            paymentRequired: 'কমপক্ষে একটি সঠিক পরিশোধ যোগ করুন।',
            customerRequired: 'নিবন্ধিত ক্রেতা নির্বাচন করুন।',
            retailDue: 'রিটেইল বিক্রয়ে বকেয়া রাখা যাবে না। নগদ নিন অথবা এক্সচেঞ্জ দিন।',
            serviceRequired: 'প্রতিটি ইঞ্জিনিয়ার বিলে নাম, কাজের বিবরণ এবং টাকা দিন।',
            empty: 'প্রথমে একটি পণ্য অথবা সার্ভিস যোগ করুন।',
            creditLimit: 'ক্রেডিট লিমিট ছাড়িয়ে যাবে। আর নগদ নিন অথবা লিমিট বাড়ান।',
            offline: 'ইন্টারনেট নেই। বিক্রয় ফোনে সংরক্ষিত হয়েছে।',
            savedOffline: '📡 নেট নেই — বিক্রয় ফোনে জমা হয়েছে। নেট ফিরলে নিজে সার্ভারে যাবে।',
            processing: 'বিক্রয় প্রক্রিয়াধীন...',
            failed: 'বিক্রয় সম্পন্ন করা যায়নি।',
            invalidResponse: 'সার্ভার থেকে সঠিক উত্তর পাওয়া যায়নি।'
        };

    let submitting = false;

    function element(id) {
        return document.getElementById(id);
    }

    function number(value) {
        const parsed = Number.parseFloat(value);

        return Number.isFinite(parsed)
            ? parsed
            : 0;
    }

    function paymentRows(validate) {
        const raw = [];

        if (!rowsBox) {
            raw.push({
                account_id: element('deskPayMethod') ? element('deskPayMethod').value : '',
                amount: element('deskPaid') ? element('deskPaid').value : 0,
                reference_number: ''
            });
            raw.push({
                account_id: element('deskPayMethod2') ? element('deskPayMethod2').value : '',
                amount: element('deskPaid2') ? element('deskPaid2').value : 0,
                reference_number: ''
            });
            return amePosCollectPayments(raw, validate, words);
        }

        rowsBox.querySelectorAll('.pos-payment-row')
            .forEach(function (row) {
                const account = row.querySelector(
                    '.pos-payment-account'
                );
                const amountInput = row.querySelector(
                    '.pos-payment-amount'
                );
                const referenceInput = row.querySelector(
                    '.pos-payment-reference'
                );

                raw.push({
                    account_id: account ? account.value : '',
                    amount: amountInput ? amountInput.value : 0,
                    reference_number: referenceInput
                        ? referenceInput.value.trim()
                        : ''
                });
            });

        return amePosCollectPayments(raw, validate, words);
    }

    function enteredPaymentTotal() {
        let total = 0;

        if (!rowsBox) {
            return 0;
        }

        rowsBox
            .querySelectorAll('.pos-payment-amount')
            .forEach(function (input) {
                total += Math.max(
                    0,
                    number(input.value)
                );
            });

        return total;
    }

    function setMoney(id, amount) {
        const output = element(id);

        if (output) {
            output.textContent =
                '৳' + amount.toFixed(2);
        }
    }

    function syncDueAdjustUi(registered, extraPayment) {
        const dueSection = element('amePosDueSection');
        const adjustBox = element('ameAdjustDueBox');
        const adjustInput = element('adjust-due');

        if (dueSection) {
            dueSection.hidden = !registered;
        }

        if (adjustInput && !adjustInput.dataset.boundTouch) {
            adjustInput.dataset.boundTouch = '1';
            adjustInput.addEventListener('change', function () {
                adjustInput.dataset.userTouched = '1';
            });
        }

        const shouldOffer = registered && extraPayment > 0.009;

        if (adjustBox) {
            adjustBox.hidden = !shouldOffer;
        }

        if (!adjustInput) {
            return;
        }

        if (shouldOffer) {
            if (adjustInput.dataset.userTouched !== '1') {
                adjustInput.checked = true;
            }
        } else {
            adjustInput.checked = false;
            delete adjustInput.dataset.userTouched;
        }
    }

    function calculatePaymentSummary() {
        const subtotal = number(
            element('sub-total')?.value
        );

        const vatPercent = number(
            element('vat-per')?.value
        );

        const discount = number(
            element('discount')?.value
        );

        const rounding = number(
            element('rounding')?.value
        );

        const service = number(
            element('service_amount')?.value
        );

        const previousDue = number(
            element('prev-due')?.value
        );

        const vatAmount =
            (subtotal * vatPercent) / 100;

        const saleBill = Math.max(
            0,
            subtotal
            + vatAmount
            + service
            + rounding
            - discount
        );

        const exchangeTotal = typeof window.ameExchangeTotal === 'function'
            ? Math.max(0, number(window.ameExchangeTotal()))
            : 0;

        const netCashDue = Math.max(
            0,
            saleBill - exchangeTotal
        );

        const rowPay = enteredPaymentTotal();
        const deskPay = number(element('deskPaid')?.value)
            + number(element('deskPaid2')?.value);
        const paymentTotal = Math.max(rowPay, deskPay);

        const registered = (
            document.querySelector(
                'input[name="cus_type"]:checked'
            )?.value === 'Registered'
        );

        const currentBillRemaining = Math.max(
            0,
            saleBill - exchangeTotal - paymentTotal
        );

        const extraPayment = Math.max(
            0,
            paymentTotal - netCashDue
        );

        syncDueAdjustUi(registered, extraPayment);

        const adjustDue = Boolean(
            element('adjust-due')?.checked
        );

        const dueAdjustment = (
            registered && adjustDue
        )
            ? extraPayment
            : 0;

        const changeAmount = Math.max(
            0,
            extraPayment - dueAdjustment
        );

        const figures = ameSalePaymentFigures(
            saleBill,
            previousDue,
            currentBillRemaining,
            dueAdjustment
        );
        const payable = figures.collect;
        const dueToShow = figures.due;

        const payableNode = element('net-payable');
        if (payableNode) {
            payableNode.textContent = payable.toFixed(2) + ' ৳';
            payableNode.dataset.saleBill = saleBill.toFixed(2);
        }

        if (element('curr-due')) {
            element('curr-due').value =
                dueToShow.toFixed(2);
        }

        if (typeof window.ameUpdateDueStatus === 'function') {
            window.ameUpdateDueStatus();
        }

        if (element('cash-change')) {
            element('cash-change').value =
                changeAmount.toFixed(2);
        }

        const deskChange = element('deskChange');
        if (deskChange) {
            deskChange.textContent = changeAmount.toFixed(2);
        }
        const deskDueNode = element('deskDue');
        if (deskDueNode) {
            deskDueNode.textContent = dueToShow.toFixed(2);
            deskDueNode.classList.toggle('is-clear', dueToShow <= 0.009);
        }

        setMoney(
            'posPaymentTotal',
            paymentTotal
        );

        setMoney(
            'posPaymentRemaining',
            currentBillRemaining
        );

        setMoney(
            'posPaymentChange',
            changeAmount
        );

        setMoney('posExchangeValue', exchangeTotal);
        setMoney('posNetCashDue', netCashDue);
        setMoney(
            'posTotalSettled',
            Math.min(saleBill, paymentTotal + exchangeTotal)
        );

        document.querySelectorAll('.ame-pos-exchange-row').forEach(function (row) {
            row.hidden = exchangeTotal < 0.009;
        });

        return {
            subtotal: subtotal,
            vatPercent: vatPercent,
            discount: discount,
            rounding: rounding,
            service: service,
            saleBill: saleBill,
            grandTotal: payable,
            exchangeTotal: exchangeTotal,
            netCashDue: netCashDue,
            paymentTotal: paymentTotal,
            currentBillRemaining:
                currentBillRemaining,
            changeAmount: changeAmount
        };
    }

    /*
     * মূল POS হিসাবকে Split Payment হিসাব দিয়ে
     * নিরাপদভাবে প্রতিস্থাপন করা হচ্ছে।
     */
    window.calculateAll =
        calculatePaymentSummary;

    if (addButton && template && rowsBox) addButton.addEventListener(
        'click',
        function () {
            const fragment = template
                .content
                .cloneNode(true);

            rowsBox.appendChild(fragment);

            const newRow =
                rowsBox.lastElementChild;

            if (newRow) {
                newRow.querySelector(
                    '.pos-payment-account'
                )?.focus();
            }

            calculatePaymentSummary();
        }
    );

    if (rowsBox) rowsBox.addEventListener(
        'input',
        function (event) {
            if (
                event.target.matches(
                    '.pos-payment-amount'
                )
                || event.target.matches(
                    '.pos-payment-account'
                )
            ) {
                if (typeof window.calculateAll === 'function') {
                    window.calculateAll();
                } else {
                    calculatePaymentSummary();
                }
            }
        }
    );

    if (rowsBox) rowsBox.addEventListener(
        'change',
        function () {
            if (typeof window.calculateAll === 'function') {
                window.calculateAll();
            } else {
                calculatePaymentSummary();
            }
        }
    );

    if (rowsBox) rowsBox.addEventListener(
        'click',
        function (event) {
            const removeButton =
                event.target.closest(
                    '.pos-payment-remove'
                );

            if (!removeButton) {
                return;
            }

            removeButton
                .closest('.pos-payment-row')
                ?.remove();

            calculatePaymentSummary();
        }
    );

    window.submitFinalSale = function () {
        if (submitting) {
            return;
        }

        var saleMode = document.getElementById('amePosSaleMode');
        var draftOption = document.getElementById('amePosRecentDraft');
        var isDraftMemo = (
            (typeof window.amePosIsDraftMemo === 'function' && window.amePosIsDraftMemo())
            || (saleMode && String(saleMode.value || '').toLowerCase() === 'draft')
            || (draftOption && draftOption.classList.contains('is-active'))
        );

        if (isDraftMemo) {
            if (typeof window.ameSaveCurrentDraft === 'function') {
                window.ameSaveCurrentDraft();
            } else if (typeof window.ameSaveMemoDraft === 'function') {
                window.ameSaveMemoDraft();
            } else {
                var hiddenSave = document.getElementById('saveDraftButton');
                if (hiddenSave) {
                    hiddenSave.click();
                }
            }
            return;
        }

        submitting = true;

        const totals =
            calculatePaymentSummary();

        const customerType =
            document.querySelector(
                'input[name="cus_type"]:checked'
            )?.value || 'Retail';

        const registered =
            customerType === 'Registered';

        const customerId =
            element('customer_id')?.value || '';

        let services = [];

        try {
            services = typeof window.ameCollectServices === 'function'
                ? window.ameCollectServices(true)
                : [];
        } catch (error) {
            if (typeof window.ameShowServiceNotice === 'function') {
                window.ameShowServiceNotice(error);
            } else {
                alert(error.message || words.serviceRequired);
            }
            submitting = false;
            return;
        }

        const serviceTotal = services.reduce(function (sum, row) {
            return sum + number(row.amount);
        }, 0);

        let payments;

        try {
            payments = paymentRows(true);
        } catch (error) {
            alert(error.message);
            submitting = false;
            return;
        }

        if (
            (!Array.isArray(cart)
                || cart.length === 0)
            && serviceTotal <= 0
            && totals.service <= 0
        ) {
            alert(words.empty);
            submitting = false;
            return;
        }

        if (
            registered
            && !customerId
        ) {
            alert(words.customerRequired);
            submitting = false;
            return;
        }

        const saleBill = typeof totals.saleBill === 'number'
            ? totals.saleBill
            : totals.grandTotal;

        if (
            (totals.exchangeTotal || 0) - 0.009
            > saleBill
        ) {
            alert(
                isEnglish
                    ? 'Exchange value cannot exceed the sale total.'
                    : 'এক্সচেঞ্জ মূল্য বিক্রয়ের মোটের বেশি হতে পারে না।'
            );
            submitting = false;
            return;
        }

        if (
            !registered
            && (totals.paymentTotal + (totals.exchangeTotal || 0))
                < saleBill
        ) {
            alert(words.retailDue);
            submitting = false;
            return;
        }

        if (registered && customerId) {
            const option = element('customer_id')
                ? element('customer_id').options[
                    element('customer_id').selectedIndex
                  ]
                : null;
            const creditLimit = option
                ? Number(option.dataset.credit || 0)
                : 0;
            const prevDue = option
                ? Number(option.dataset.due || 0)
                : 0;
            const thisDue = Math.max(
                0,
                saleBill - (totals.paymentTotal + (totals.exchangeTotal || 0))
            );
            if (
                creditLimit > 0.009
                && prevDue + thisDue > creditLimit + 0.009
            ) {
                alert(words.creditLimit);
                submitting = false;
                return;
            }
        }

        if (
            totals.paymentTotal > 0
            && payments.length === 0
        ) {
            alert(words.paymentRequired);
            submitting = false;
            return;
        }

        if (
            totals.service > 0
            && services.length === 0
        ) {
            alert(words.serviceRequired);
            submitting = false;
            return;
        }

        const submitButton =
            document.querySelector(
                'button[onclick*="submitFinalSale"]'
            )
            || document.getElementById('amePosCompleteBtn');

        if (window.AmeSubmitWait) {
            window.AmeSubmitWait.show(submitButton);
        } else if (submitButton) {
            submitButton.disabled = true;
        }

        const requestData = {
            action: 'complete',

            /*
             * Draft থেকে Complete করলে একই Sale ID
             * Final Invoice-এ রূপান্তরিত হবে।
             */
            draft_id: Number(
                element('currentDraftId')?.value
                || 0
            ),

            cart: cart,

            customer_id: registered
                ? Number(customerId)
                : null,

            customer_type: customerType,

            subtotal: totals.subtotal,
            discount: totals.discount,
            vat_percent: totals.vatPercent,
            rounding: totals.rounding,
            service_amount: serviceTotal > 0
                ? serviceTotal
                : totals.service,
            services: services,
            engineer_id: services[0]
                ? services[0].engineer_id
                : null,
            service_type: services[0]
                ? services[0].service_type
                : '',
            payments: payments,
            exchange: typeof window.ameGetExchange === 'function'
                ? window.ameGetExchange()
                : [],

            adjust_due: Boolean(
                element('adjust-due')?.checked
            ),

            sms: Boolean(
                element('send-sms')?.checked
            ),

            // গাড়ির নম্বর (ঐচ্ছিক) — মেমোতে দেখানোর জন্য
            vehicle_no: (element('posVehicleNo')?.value || '').trim(),
            sale_date: (element('ameSaleDate')?.value || '').trim(),
            next_pay_date: (element('ameNextPayDate')?.value || '').trim(),
            next_pay_amount: (element('ameNextPayAmount')?.value || '').trim(),

            // নিরাপত্তা ফিক্স: process_sale.php এখন CSRF টোকেন যাচাই করে
            csrf_token: window.AME_CSRF_TOKEN || '',
            offline_client_ref: (window.AmeOfflineQueue && window.AmeOfflineQueue.makeRef)
                ? window.AmeOfflineQueue.makeRef()
                : ('OFF-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10))
        };

        function goCompletedInvoice(saleId) {
            const id = Number(saleId || 0);
            if (id > 0) {
                window.location.replace('invoice.php?id=' + encodeURIComponent(String(id)) + '&from=complete');
                return;
            }
            window.location.replace('pos.php?sold=1');
        }

        function finishQueuedSale(ref) {
            alert(
                (words.savedOffline
                    || '📡 ইন্টারনেট নেই — বিক্রয় ফোনে জমা হয়েছে। নেট ফিরলে নিজে সার্ভারে যাবে।')
                + '\n(রেফারেন্স: ' + ref + ')'
            );
            if (typeof window.ameClearPosSale === 'function') {
                window.ameClearPosSale();
            }
            window.location.replace('pos.php?offline_saved=1');
        }

        if (window.AmeOfflineQueue && typeof AmeOfflineQueue.sendSale === 'function') {
            var saleReq = AmeOfflineQueue.sendSale(requestData);
            if (window.AmeSubmitWait && window.AmeSubmitWait.follow) {
                saleReq = AmeSubmitWait.follow(saleReq);
            }
            function rapidSaleConfirm(res) {
                if (window.AmeResubmitGuard && typeof AmeResubmitGuard.askFromServer === 'function') {
                    return AmeResubmitGuard.askFromServer({
                        message: res.message,
                        code: res.code,
                        warnings: res.warnings
                    });
                }
                return Promise.resolve(window.confirm(res.message || 'আবার সাবমিট করবেন?'));
            }
            function onSaleQueueResult(res) {
                if (res && res.needs_confirm && res.message) {
                    return rapidSaleConfirm(res).then(function (proceed) {
                    if (!proceed) {
                        submitting = false;
                        if (window.AmeSubmitWait) {
                            window.AmeSubmitWait.hide(submitButton);
                        } else if (submitButton) {
                            submitButton.disabled = false;
                        }
                        return;
                    }
                    requestData.confirm_rapid_resubmit = true;
                    var retryReq = AmeOfflineQueue.sendSale(requestData);
                    if (window.AmeSubmitWait && window.AmeSubmitWait.follow) {
                        retryReq = AmeSubmitWait.follow(retryReq);
                    }
                    return retryReq.then(onSaleQueueResult);
                    });
                }
                if (res && res.ok && res.sale_id) {
                    if (typeof window.ameClearPosSale === 'function') {
                        window.ameClearPosSale();
                    }
                    goCompletedInvoice(res.sale_id);
                    return;
                }
                if (res && res.queued) {
                    finishQueuedSale(res.ref);
                    return;
                }
                alert((res && res.error) || words.failed);
                submitting = false;
                if (window.AmeSubmitWait) {
                    window.AmeSubmitWait.hide(submitButton);
                } else if (submitButton) {
                    submitButton.disabled = false;
                }
            }
            saleReq.then(onSaleQueueResult).catch(function () {
                submitting = false;
                if (window.AmeSubmitWait) {
                    window.AmeSubmitWait.hide(submitButton);
                } else if (submitButton) {
                    submitButton.disabled = false;
                }
                alert(words.failed);
            });
            return;
        }

        if (!navigator.onLine && window.AmeOfflineQueue) {
            finishQueuedSale(window.AmeOfflineQueue.queueSale(requestData));
            return;
        }

        function deliverSaleResult(result) {
            if (result && result.status === 'success') {
                if (typeof window.ameClearPosSale === 'function') {
                    window.ameClearPosSale();
                }
                goCompletedInvoice(result.sale_id);
                return;
            }
            throw new Error((result && result.message) || words.failed);
        }

        function postSaleOnce(payload) {
            return fetch('process_sale.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json; charset=UTF-8'
                },
                body: JSON.stringify(payload)
            }).then(function (response) {
                return response.text().then(function (responseText) {
                    let result;
                    try {
                        result = JSON.parse(responseText);
                    } catch (error) {
                        throw new Error(words.invalidResponse);
                    }
                    return { ok: response.ok, result: result };
                });
            });
        }

        function runSaleSubmit(payload) {
            return postSaleOnce(payload).then(function (response) {
                var result = response.result || {};
                if (result.status === 'confirm' && result.needs_confirm) {
                    var confirmCall = (window.AmeResubmitGuard && AmeResubmitGuard.askFromServer)
                        ? AmeResubmitGuard.askFromServer(result)
                        : Promise.resolve(window.confirm(result.message || 'আবার সাবমিট করবেন?'));
                    return confirmCall.then(function (proceed) {
                        if (!proceed) {
                            submitting = false;
                            if (window.AmeSubmitWait) {
                                window.AmeSubmitWait.hide(submitButton);
                            } else if (submitButton) {
                                submitButton.disabled = false;
                            }
                            return;
                        }
                        payload.confirm_rapid_resubmit = true;
                        return runSaleSubmit(payload);
                    });
                }
                deliverSaleResult(result);
            });
        }

        runSaleSubmit(requestData)
        .catch(function (error) {
            var isNetworkFailure =
                !navigator.onLine
                || error instanceof TypeError;

            if (isNetworkFailure && window.AmeOfflineQueue) {
                // ইন্টারনেট নেই — বিক্রয়টা ডিভাইসে সাময়িকভাবে জমা
                // রাখা হলো, নেট ফিরলেই স্বয়ংক্রিয়ভাবে সার্ভারে যাবে
                var ref = window.AmeOfflineQueue.queueSale(requestData);

                alert(
                    (words.savedOffline
                        || '📡 ইন্টারনেট সংযোগ নেই — বিক্রয়টি ডিভাইসে সংরক্ষিত হয়েছে, সংযোগ ফিরলে স্বয়ংক্রিয়ভাবে জমা হয়ে যাবে।')
                    + '\n(রেফারেন্স: ' + ref + ')'
                );

                if (typeof window.ameClearPosSale === 'function') {
                    window.ameClearPosSale();
                }
                window.location.replace('pos.php?offline_saved=1');
                return;
            }

            alert(
                error.message
                || words.failed
            );

            submitting = false;

            if (window.AmeSubmitWait) {
                window.AmeSubmitWait.hide(submitButton);
            } else if (submitButton) {
                submitButton.disabled = false;
            }
        });
    };

    calculatePaymentSummary();
})();
}