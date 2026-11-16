(function () {
    const cfg = window.PRODUCT_STUDIO || {};
    const form = document.getElementById('productCreateForm');
    if (!form) {
        return;
    }

    const purchase = document.getElementById('purchasePrice');
    const retail = document.getElementById('retailPrice');
    const wholesale = document.getElementById('wholesalePrice');
    const dealer = document.getElementById('dealerPrice');
    const retailProfit = document.getElementById('retailProfit');
    const wholesaleProfit = document.getElementById('wholesaleProfit');
    const dealerProfit = document.getElementById('dealerProfit');
    const profitStatus = document.getElementById('profitStatus');
    const saveButton = document.getElementById('productSaveButton');
    const codeInput = document.getElementById('productCodeInput');
    const barcodeInput = document.getElementById('productBarcode');
    const codeHint = document.getElementById('productCodeHint');
    const codeStatus = document.getElementById('productCodeStatus');
    const barcodeStatus = document.getElementById('productBarcodeStatus');
    const nameInput = document.getElementById('productName');
    const stockInput = document.getElementById('productStock');
    const imageInput = document.getElementById('productImageInput');
    const imagePreview = document.getElementById('productImagePreview');
    const imageEmpty = document.getElementById('productImageEmpty');
    const scoreRing = document.getElementById('psScoreRing');
    const scoreValue = document.getElementById('psScoreValue');
    const stickyScore = document.getElementById('psStickyScore');
    const missingList = document.getElementById('psMissingList');
    const DRAFT_KEY = 'ame_product_studio_draft_v1';
    let skuOk = true;
    let barcodeOk = true;
    let skuTimer = 0;
    let barcodeTimer = 0;
    let draftTimer = 0;

    function numberValue(el) {
        if (!el) {
            return 0;
        }
        const value = parseFloat(el.value);
        return Number.isFinite(value) ? value : 0;
    }

    function money(value) {
        return '৳' + value.toFixed(2);
    }

    function showProfit(el, value) {
        if (!el) {
            return;
        }
        el.textContent = money(value);
        el.classList.toggle('negative', value < 0);
    }

    function updateProfit() {
        const cost = numberValue(purchase);
        const r = numberValue(retail) - cost;
        const w = numberValue(wholesale) - cost;
        const d = numberValue(dealer) - cost;
        showProfit(retailProfit, r);
        showProfit(wholesaleProfit, w);
        showProfit(dealerProfit, d);
        const loss = r < 0 || w < 0 || d < 0;
        if (profitStatus) {
            profitStatus.textContent = loss ? cfg.lossWarning : cfg.profitOk;
            profitStatus.classList.toggle('warning', loss);
        }
        const pName = (nameInput && nameInput.value.trim()) || '—';
        const pSku = (codeInput && codeInput.value.trim()) || (cfg.isEdit ? '' : 'AUTO');
        const nameEl = document.getElementById('psPreviewName');
        const metaEl = document.getElementById('psPreviewMeta');
        const stockEl = document.getElementById('psPreviewStock');
        if (nameEl) {
            nameEl.textContent = pName;
        }
        if (metaEl) {
            metaEl.textContent = (pSku ? 'SKU ' + pSku : '') + (barcodeInput && barcodeInput.value ? ' · ' + barcodeInput.value : '');
        }
        const pr = document.getElementById('psPreviewRetail');
        const pw = document.getElementById('psPreviewWholesale');
        const pd = document.getElementById('psPreviewDealer');
        if (pr) pr.textContent = money(numberValue(retail));
        if (pw) pw.textContent = money(numberValue(wholesale));
        if (pd) pd.textContent = money(numberValue(dealer));
        if (stockEl) {
            stockEl.textContent = 'স্টক ' + numberValue(stockInput);
        }
        updateScore();
    }

    function filled(el) {
        return !!(el && String(el.value || '').trim());
    }

    function updateScore() {
        const labels = cfg.labels || {};
        const checks = [
            { ok: filled(nameInput), pts: 15, label: labels.name },
            { ok: cfg.isEdit ? filled(codeInput) : (form.querySelector('input[name="code_mode"]:checked') || {}).value === 'auto' || filled(codeInput), pts: 10, label: labels.sku },
            { ok: numberValue(purchase) > 0, pts: 10, label: labels.purchase },
            { ok: numberValue(retail) > 0, pts: 12, label: labels.retail },
            { ok: numberValue(wholesale) > 0, pts: 12, label: labels.wholesale },
            { ok: numberValue(dealer) > 0, pts: 11, label: labels.dealer },
            { ok: parseInt((form.category_id && form.category_id.value) || '0', 10) > 0, pts: 4, label: labels.category },
            { ok: parseInt((form.brand_id && form.brand_id.value) || '0', 10) > 0, pts: 3, label: labels.brand },
            { ok: filled(barcodeInput), pts: 4, label: labels.barcode },
            { ok: (imageInput && imageInput.files && imageInput.files.length > 0) || (imagePreview && imagePreview.getAttribute('src')), pts: 5, label: labels.photo },
            { ok: filled(stockInput), pts: 3, label: labels.stock },
            { ok: filled(form.oem_code), pts: 3, label: labels.oem },
            { ok: filled(form.unit_id) && form.unit_id.value !== '0', pts: 2, label: 'Unit' },
            { ok: filled(form.short_description), pts: 2, label: 'Desc' },
            { ok: filled(form.shelf_location), pts: 4, label: 'Shelf' }
        ];
        let score = 0;
        const missing = [];
        checks.forEach(function (item) {
            if (item.ok) {
                score += item.pts;
            } else if (item.label) {
                missing.push(item.label);
            }
        });
        if (score > 100) {
            score = 100;
        }
        if (scoreRing) {
            scoreRing.style.setProperty('--ps-score', String(score));
        }
        if (scoreValue) {
            scoreValue.textContent = score + '%';
        }
        if (stickyScore) {
            stickyScore.textContent = score + '%';
        }
        if (missingList) {
            missingList.innerHTML = '';
            missing.slice(0, 6).forEach(function (label) {
                const li = document.createElement('li');
                li.textContent = label;
                missingList.appendChild(li);
            });
        }
    }

    function setStatus(el, kind, text) {
        if (!el) {
            return;
        }
        el.className = 'ps-status' + (kind ? ' ' + kind : '');
        el.textContent = text || '';
    }

    function lookup(field, value, statusEl, onOk) {
        const body = new FormData();
        body.append('csrf_token', cfg.csrf || '');
        body.append('field', field);
        body.append('value', value);
        body.append('exclude_id', String(cfg.productId || 0));
        setStatus(statusEl, 'checking', cfg.checking);
        fetch(cfg.lookupUrl || 'product_lookup.php', {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        }).then(function (res) {
            return res.json();
        }).then(function (data) {
            if (!data || !data.success) {
                onOk(false);
                setStatus(statusEl, data && data.code === 'invalid' ? 'invalid' : 'failed', (data && data.message) || cfg.failed);
                return;
            }
            if (data.exists) {
                onOk(false);
                setStatus(statusEl, 'duplicate', field === 'barcode' ? cfg.duplicateBarcode : cfg.duplicateSku);
                if (statusEl && data.holder && data.holder.id) {
                    const link = document.createElement('a');
                    link.href = 'edit.php?id=' + encodeURIComponent(data.holder.id);
                    link.target = '_blank';
                    link.rel = 'noopener';
                    link.textContent = ' → #' + data.holder.id + ' ' + (data.holder.name || '') +
                        (data.holder.status && data.holder.status !== 'active' ? ' (' + data.holder.status + ')' : '');
                    statusEl.appendChild(link);
                }
                return;
            }
            onOk(true);
            setStatus(statusEl, 'available', cfg.available);
        }).catch(function () {
            onOk(false);
            setStatus(statusEl, 'failed', cfg.failed);
        });
    }

    function unlockSkuField() {
        if (!codeInput) {
            return;
        }
        codeInput.removeAttribute('readonly');
        codeInput.removeAttribute('disabled');
        codeInput.readOnly = false;
        codeInput.disabled = false;
        codeInput.required = false;
    }

    function selectManualMode() {
        const radio = form.querySelector('input[name="code_mode"][value="manual"]');
        if (radio && !radio.checked) {
            radio.checked = true;
        }
    }

    function updateCodeMode() {
        if (cfg.isEdit || !codeInput) {
            return;
        }
        unlockSkuField();
        const selected = form.querySelector('input[name="code_mode"]:checked');
        const manual = selected && selected.value === 'manual';
        if (!manual && !codeInput.value.trim()) {
            skuOk = true;
            setStatus(codeStatus, '', '');
        }
        codeInput.placeholder = manual ? cfg.manualPlaceholder : cfg.autoPlaceholder;
        if (codeHint) {
            codeHint.textContent = manual ? cfg.hintManual : cfg.hintAuto;
        }
        updateProfit();
    }

    function normalizeCode(value) {
        return String(value || '')
            .toUpperCase()
            .replace(/\s+/g, '-')
            .replace(/[^A-Z0-9._-]/g, '');
    }

    if (!cfg.isEdit) {
        form.querySelectorAll('input[name="code_mode"]').forEach(function (mode) {
            mode.addEventListener('change', updateCodeMode);
        });
        updateCodeMode();
    }

    if (codeInput) {
        unlockSkuField();
        ['focus', 'pointerdown', 'touchstart'].forEach(function (evt) {
            codeInput.addEventListener(evt, function () {
                unlockSkuField();
                if (codeInput.value.trim()) {
                    selectManualMode();
                    updateCodeMode();
                }
            }, { passive: true });
        });
        codeInput.addEventListener('input', function () {
            unlockSkuField();
            codeInput.value = normalizeCode(codeInput.value);
            if (codeInput.value.trim()) {
                selectManualMode();
            }
            window.clearTimeout(skuTimer);
            const val = codeInput.value.trim();
            if (val === '') {
                skuOk = true;
                setStatus(codeStatus, '', '');
                updateProfit();
                return;
            }
            if (val.length < 3) {
                skuOk = false;
                setStatus(codeStatus, 'invalid', cfg.invalidCode);
                updateProfit();
                return;
            }
            skuTimer = window.setTimeout(function () {
                lookup('sku', val, codeStatus, function (ok) {
                    skuOk = ok;
                });
            }, 350);
            updateProfit();
        });
    }

    if (barcodeInput) {
        barcodeInput.addEventListener('input', function () {
            barcodeInput.value = normalizeCode(barcodeInput.value).replace(/-/g, '');
            window.clearTimeout(barcodeTimer);
            const val = barcodeInput.value.trim();
            if (val === '') {
                barcodeOk = true;
                setStatus(barcodeStatus, '', '');
                updateProfit();
                return;
            }
            if (val.length < 3) {
                barcodeOk = false;
                setStatus(barcodeStatus, 'invalid', cfg.invalidCode);
                return;
            }
            barcodeTimer = window.setTimeout(function () {
                lookup('barcode', val, barcodeStatus, function (ok) {
                    barcodeOk = ok;
                });
            }, 350);
            updateProfit();
        });
    }

    const genBtn = document.getElementById('barcodeGenerateBtn');
    if (genBtn && barcodeInput) {
        genBtn.addEventListener('click', function () {
            const stamp = Date.now().toString(36).toUpperCase();
            barcodeInput.value = ('APN' + stamp).slice(0, 16);
            barcodeInput.dispatchEvent(new Event('input'));
        });
    }

    if (imageInput) {
        imageInput.addEventListener('change', function () {
            const file = imageInput.files && imageInput.files[0];
            if (!file) {
                updateScore();
                return;
            }
            const url = URL.createObjectURL(file);
            if (imagePreview) {
                imagePreview.src = url;
                imagePreview.hidden = false;
            }
            if (imageEmpty) {
                imageEmpty.hidden = true;
            }
            updateScore();
        });
    }

    function serializeDraft() {
        const data = {};
        Array.prototype.forEach.call(form.elements, function (el) {
            if (!el.name || el.type === 'file' || el.type === 'hidden' && el.name === 'csrf_token') {
                return;
            }
            if (el.type === 'radio') {
                if (el.checked) {
                    data[el.name] = el.value;
                }
                return;
            }
            if (el.type === 'checkbox') {
                data[el.name] = el.checked ? el.value : '';
                return;
            }
            data[el.name] = el.value;
        });
        return data;
    }

    function restoreDraft() {
        if (cfg.isEdit) {
            return;
        }
        let raw = '';
        try {
            raw = localStorage.getItem(DRAFT_KEY) || '';
        } catch (e) {
            return;
        }
        if (!raw) {
            return;
        }
        let data;
        try {
            data = JSON.parse(raw);
        } catch (e) {
            return;
        }
        Object.keys(data).forEach(function (name) {
            const els = form.querySelectorAll('[name="' + name + '"]');
            if (!els.length) {
                return;
            }
            els.forEach(function (el) {
                if (el.type === 'radio') {
                    el.checked = el.value === data[name];
                } else if (el.type === 'checkbox') {
                    el.checked = !!data[name];
                } else if (el.type !== 'file') {
                    el.value = data[name];
                }
            });
        });
        const banner = document.getElementById('psDraftBanner');
        if (banner) {
            banner.hidden = false;
        }
        updateCodeMode();
        updateProfit();
    }

    function saveDraft() {
        if (cfg.isEdit) {
            return;
        }
        try {
            localStorage.setItem(DRAFT_KEY, JSON.stringify(serializeDraft()));
        } catch (e) {}
    }

    form.addEventListener('input', function () {
        updateProfit();
        window.clearTimeout(draftTimer);
        draftTimer = window.setTimeout(saveDraft, 400);
    });
    form.addEventListener('change', function () {
        updateProfit();
        saveDraft();
    });

    const clearBtn = document.getElementById('psClearDraft');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            try {
                localStorage.removeItem(DRAFT_KEY);
            } catch (e) {}
            const banner = document.getElementById('psDraftBanner');
            if (banner) {
                banner.hidden = true;
            }
        });
    }

    form.addEventListener('submit', function (ev) {
        const typedSku = codeInput && codeInput.value.trim();
        if (!skuOk && typedSku) {
            ev.preventDefault();
            codeInput.focus();
            return;
        }
        if (!barcodeOk && barcodeInput && barcodeInput.value.trim()) {
            ev.preventDefault();
            barcodeInput.focus();
            return;
        }
        form.querySelectorAll('.ps-save').forEach(function (btn) {
            btn.disabled = true;
            btn.textContent = '⏳ ' + (cfg.saving || 'Saving');
        });
        try {
            localStorage.removeItem(DRAFT_KEY);
        } catch (e) {}
    });

    function quickAddLabel(type) {
        if (type === 'category') { return 'ক্যাটেগরি'; }
        if (type === 'brand') { return 'ব্র্যান্ড'; }
        if (type === 'unit') { return 'ইউনিট'; }
        return type;
    }

    function initQuickAdd() {
        const buttons = document.querySelectorAll('[data-quick-add]');
        if (!buttons.length) {
            return;
        }
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const type = btn.getAttribute('data-quick-add');
                const targetId = btn.getAttribute('data-target');
                const select = targetId ? document.getElementById(targetId) : null;
                if (!select) {
                    return;
                }
                const label = quickAddLabel(type);
                const name = window.prompt('নতুন ' + label + 'ের নাম লিখুন:');
                if (name === null) {
                    return;
                }
                const trimmed = name.trim();
                if (trimmed === '') {
                    window.alert('নাম লিখুন।');
                    return;
                }

                btn.disabled = true;
                const originalText = btn.textContent;
                btn.textContent = '…';

                const body = new FormData();
                body.append('csrf_token', cfg.csrf || '');
                body.append('type', type);
                body.append('name', trimmed);

                fetch('quick_add_taxonomy.php', {
                    method: 'POST',
                    body: body,
                    credentials: 'same-origin'
                }).then(function (res) {
                    return res.json();
                }).then(function (data) {
                    if (!data || !data.success) {
                        window.alert((data && data.message) || 'সেভ করা যায়নি।');
                        return;
                    }
                    const idStr = String(data.id);
                    let option = select.querySelector('option[value="' + idStr + '"]');
                    if (!option) {
                        option = document.createElement('option');
                        option.value = idStr;
                        option.textContent = data.name;
                        select.appendChild(option);
                    }
                    select.value = idStr;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }).catch(function () {
                    window.alert('সেভ করা যায়নি। ইন্টারনেট সংযোগ পরীক্ষা করুন।');
                }).finally(function () {
                    btn.disabled = false;
                    btn.textContent = originalText;
                });
            });
        });
    }

    initQuickAdd();
    restoreDraft();
    updateProfit();
})();
