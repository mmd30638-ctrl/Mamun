<?php
/*
 * Recent Sales controls are bound here, inside pos.php HTML.
 * A cached pos-draft.js file cannot stop this from running.
 */
?>
<style>
html body.ame-pos-terminal-page #main-content .pos-draft-drawer .pos-recent-table .pos-recent-finish-btn {
  width: auto !important;
  min-width: 64px !important;
  height: auto !important;
  min-height: 28px !important;
  padding: 2px 8px !important;
  font-size: 12px !important;
  font-weight: 800 !important;
  border: 1px solid #0f766e !important;
  border-radius: 8px !important;
  color: #0f766e !important;
  background: #ffffff !important;
}
</style>
<script>
(function () {
    function byId(id) {
        return document.getElementById(id);
    }

    function hideFlyout() {
        var flyout = byId('amePosRecentFlyout');
        var toggle = byId('amePosRecentToggle');
        if (!flyout) {
            return;
        }
        flyout.classList.remove('is-open');
        flyout.setAttribute('hidden', 'hidden');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }
    }

    function openFlyout() {
        var flyout = byId('amePosRecentFlyout');
        var toggle = byId('amePosRecentToggle');
        if (!flyout) {
            return;
        }
        flyout.classList.add('is-open');
        flyout.removeAttribute('hidden');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
        }
    }

    function setSaleMode(isDraft) {
        var mode = byId('amePosSaleMode');
        if (!mode) {
            mode = document.createElement('input');
            mode.type = 'hidden';
            mode.id = 'amePosSaleMode';
            document.body.appendChild(mode);
        }
        mode.value = isDraft ? 'draft' : 'complete';
        syncMemoButton(isDraft);
        var side = byId('amePosDraftComplete');
        if (side) {
            var sideLabel = isDraft ? 'ড্রাফট সেভ করুন' : 'বিক্রয় সম্পন্ন করুন';
            side.setAttribute('title', sideLabel);
            side.setAttribute('aria-label', sideLabel);
        }
    }

    function setChoice(button) {
        var current = byId('amePosRecentCurrent');
        var flyout = byId('amePosRecentFlyout');
        var draftBtn = byId('amePosRecentDraft');
        if (current && button) {
            current.textContent = button.textContent.replace(/\s+/g, ' ').trim();
        }
        if (flyout) {
            flyout.querySelectorAll('.ame-pos-recent-option').forEach(function (option) {
                option.classList.toggle('is-active', option === button);
            });
        }
        setSaleMode(button === draftBtn);
    }

    function formatSaleTime(value) {
        if (!value) {
            return '';
        }
        var parsed = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(parsed.getTime())) {
            return String(value);
        }
        var day = String(parsed.getDate()).padStart(2, '0');
        var month = String(parsed.getMonth() + 1).padStart(2, '0');
        var year = String(parsed.getFullYear()).slice(-2);
        var hours = parsed.getHours();
        var minutes = String(parsed.getMinutes()).padStart(2, '0');
        var meridian = hours >= 12 ? 'pm' : 'am';
        hours = hours % 12;
        if (hours === 0) {
            hours = 12;
        }
        return day + '/' + month + '/' + year + ' ' + String(hours).padStart(2, '0') + ':' + minutes + ' ' + meridian;
    }

    function tableMessage(message) {
        var listBox = byId('draftList');
        if (!listBox) {
            return;
        }
        listBox.replaceChildren();
        var row = document.createElement('tr');
        var cell = document.createElement('td');
        cell.colSpan = 2;
        cell.className = 'pos-draft-empty';
        cell.textContent = message;
        row.appendChild(cell);
        listBox.appendChild(row);
    }

    function draftWho(draft) {
        var name = String((draft && draft.customer_name) || '').trim();
        if (name && name !== '-' && name.toLowerCase() !== 'retail customer') {
            return name;
        }
        var code = String((draft && draft.customer_code) || '').trim();
        if (code) {
            return code;
        }
        if (draft && Number(draft.customer_id) > 0) {
            return '#' + Number(draft.customer_id);
        }
        return 'খুচরা ক্রেতা';
    }

    function openDraftMemo(draftId) {
        var drawer = byId('draftDrawer');
        if (drawer) {
            drawer.classList.remove('open');
            drawer.setAttribute('aria-hidden', 'true');
        }
        if (typeof window.ameLoadDraft === 'function') {
            window.ameLoadDraft(draftId);
            return;
        }
        window.location.href = 'pos.php?draft=' + encodeURIComponent(draftId);
    }

    function renderDraftRows(drafts) {
        var listBox = byId('draftList');
        if (!listBox) {
            return;
        }
        listBox.replaceChildren();
        if (!Array.isArray(drafts) || drafts.length === 0) {
            tableMessage('কোনো সক্রিয় ড্রাফট পাওয়া যায়নি।');
            return;
        }
        drafts.forEach(function (draft) {
            var row = document.createElement('tr');
            row.className = 'pos-recent-draft-row';
            row.tabIndex = 0;
            row.title = 'এডিট';
            row.addEventListener('click', function (event) {
                if (event.target.closest('a, button')) {
                    return;
                }
                event.preventDefault();
                openDraftMemo(draft.id);
            });
            var custTd = document.createElement('td');
            custTd.className = 'pos-recent-cust';
            var nameLine = document.createElement('span');
            nameLine.className = 'pos-recent-cust-name';
            nameLine.textContent = draftWho(draft);
            custTd.appendChild(nameLine);
            row.appendChild(custTd);

            var actionCell = document.createElement('td');
            actionCell.className = 'pos-recent-actions';
            var actionBar = document.createElement('span');
            actionBar.className = 'pos-recent-action-bar';
            var printLink = document.createElement('a');
            printLink.href = 'invoice.php?id=' + encodeURIComponent(draft.id);
            printLink.target = '_blank';
            printLink.rel = 'noopener';
            printLink.className = 'pos-recent-icon-btn';
            printLink.textContent = '🖨️';
            printLink.title = 'প্রিন্ট';
            printLink.addEventListener('click', function (event) {
                event.stopPropagation();
            });
            actionBar.appendChild(printLink);
            var shareLink = document.createElement('a');
            shareLink.href = 'invoice.php?id=' + encodeURIComponent(draft.id) + '&share=1';
            shareLink.target = '_blank';
            shareLink.rel = 'noopener';
            shareLink.className = 'pos-recent-icon-btn';
            shareLink.textContent = '📤';
            shareLink.title = 'শেয়ার';
            shareLink.addEventListener('click', function (event) {
                event.stopPropagation();
            });
            actionBar.appendChild(shareLink);
            var editButton = document.createElement('button');
            editButton.type = 'button';
            editButton.className = 'pos-recent-icon-btn pos-recent-edit-btn';
            editButton.textContent = '✏️';
            editButton.title = 'এডিট';
            editButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                openDraftMemo(draft.id);
            });
            actionBar.appendChild(editButton);
            var finishButton = document.createElement('button');
            finishButton.type = 'button';
            finishButton.className = 'pos-recent-icon-btn pos-recent-finish-btn';
            finishButton.textContent = 'কমপ্লিট';
            finishButton.title = 'কমপ্লিট';
            finishButton.setAttribute('aria-label', 'কমপ্লিট');
            finishButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (typeof window.ameFinishDraftMemo === 'function') {
                    window.ameFinishDraftMemo(draft.id);
                }
            });
            actionBar.appendChild(finishButton);
            actionCell.appendChild(actionBar);
            row.appendChild(actionCell);
            listBox.appendChild(row);
        });
    }

    async function loadDraftTable() {
        tableMessage('ড্রাফট তালিকা লোড হচ্ছে...');
        try {
            var response = await fetch('draft_query.php?action=list', {
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-Token': window.AME_CSRF_TOKEN || ''
                }
            });
            var result = await response.json();
            if (!response.ok || result.status !== 'success') {
                throw new Error(result.message || 'ড্রাফট তালিকা লোড হয়নি।');
            }
            renderDraftRows(result.drafts);
        } catch (error) {
            tableMessage(error.message || 'ড্রাফট তালিকা লোড হয়নি।');
        }
    }

    function openDraftPopup(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
        }
        hideFlyout();
        if (typeof window.ameOpenDraftList === 'function') {
            window.ameOpenDraftList();
            return;
        }
        var drawer = byId('draftDrawer');
        if (drawer) {
            drawer.classList.add('open');
            drawer.setAttribute('aria-hidden', 'false');
        }
        loadDraftTable();
    }

    function openCompletePopup(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
        }
        hideFlyout();
        if (typeof window.ameOpenCompleteList === 'function') {
            window.ameOpenCompleteList();
            return;
        }
        window.location.href = 'history.php';
    }

    function openRecentPopup(event) {
        openDraftPopup(event);
    }

    function saveMemoDraft() {
        if (typeof window.ameSaveCurrentDraft === 'function') {
            window.ameSaveCurrentDraft();
            return;
        }
        var saveButton = byId('saveDraftButton');
        if (saveButton) {
            saveButton.click();
        }
    }

    function syncMemoButton(isDraft) {
        var btn = byId('amePosCompleteBtn');
        if (btn) {
            var draftLabel = btn.getAttribute('data-draft-label');
            var completeLabel = btn.getAttribute('data-complete-label');
            var icon = btn.querySelector('svg');
            var iconHtml = icon ? icon.outerHTML : '';
            btn.innerHTML = iconHtml + (isDraft
                ? (draftLabel || '💾 ড্রাফট সেভ করুন')
                : (completeLabel || 'বিক্রয় সম্পন্ন করুন'));
            btn.classList.toggle('is-draft', Boolean(isDraft));
        }
        var jump = byId('amePosPayJump');
        if (jump) {
            var draftJump = jump.getAttribute('data-draft-label');
            var completeJump = jump.getAttribute('data-complete-label');
            jump.textContent = isDraft
                ? (draftJump || '💳 পেমেন্ট ও ড্রাফট সেভ — নিচে যান')
                : (completeJump || '💳 পেমেন্ট ও বিক্রয় সম্পন্ন — নিচে যান');
        }
    }

    window.amePosSyncMemoButton = syncMemoButton;
    window.ameSaveMemoDraft = saveMemoDraft;

    var hist = byId('amePosTermHist');
    var toggle = byId('amePosRecentToggle');
    var draft = byId('amePosRecentDraft');
    var complete = byId('amePosRecentComplete');
    var draftComplete = byId('amePosDraftComplete');
    var closeBtn = byId('closeDraftsButton');
    var drawer = byId('draftDrawer');

    if (hist) {
        hist.addEventListener('click', openRecentPopup, true);
    }

    if (draftComplete) {
        draftComplete.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            hideFlyout();
            if (window.amePosIsDraftMemo && window.amePosIsDraftMemo()) {
                saveMemoDraft();
                return;
            }
            if (complete) {
                setChoice(complete);
            } else {
                setSaleMode(false);
            }
            if (typeof window.submitFinalSale === 'function') {
                window.submitFinalSale();
            }
        }, true);
    }

    if (toggle) {
        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            var flyout = byId('amePosRecentFlyout');
            if (flyout && flyout.classList.contains('is-open')) {
                hideFlyout();
                return;
            }
            openFlyout();
        }, true);
    }

    if (draft) {
        draft.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            setChoice(draft);
            syncMemoButton(true);
            hideFlyout();
        }, true);
    }

    if (complete) {
        complete.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            setChoice(complete);
            syncMemoButton(false);
            hideFlyout();
        }, true);
    }

    window.ameFinishDraftMemo = async function (draftId) {
        var drawer = byId('draftDrawer');
        if (drawer) {
            drawer.classList.remove('open');
            drawer.setAttribute('aria-hidden', 'true');
        }
        if (typeof window.ameLoadDraft !== 'function') {
            window.location.href = 'pos.php?draft=' + encodeURIComponent(draftId);
            return;
        }
        await window.ameLoadDraft(draftId);
        var idBox = byId('currentDraftId');
        if (!idBox || Number(idBox.value) !== Number(draftId)) {
            return;
        }
        if (complete) {
            setChoice(complete);
        } else {
            setSaleMode(false);
        }
        if (typeof window.submitFinalSale === 'function') {
            window.submitFinalSale();
        }
    };

    window.amePosIsDraftMemo = function () {
        var mode = byId('amePosSaleMode');
        if (mode && String(mode.value || '').toLowerCase() === 'draft') {
            return true;
        }
        if (draft && draft.classList.contains('is-active')) {
            return true;
        }
        var current = byId('amePosRecentCurrent');
        if (current) {
            var label = String(current.textContent || '').replace(/\s+/g, ' ').trim();
        if (label === 'ড্রাফট' || label.toLowerCase() === 'draft') {
            return true;
        }
        }
        var memoBtn = byId('amePosCompleteBtn');
        return Boolean(memoBtn && memoBtn.classList.contains('is-draft'));
    };

    var completeSaleBtn = byId('amePosCompleteBtn');
    if (completeSaleBtn) {
        completeSaleBtn.addEventListener('click', function (event) {
            if (!window.amePosIsDraftMemo()) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            saveMemoDraft();
        }, true);
    }

    if (typeof window.submitFinalSale === 'function') {
        var ameOriginalSubmitFinalSale = window.submitFinalSale;
        window.submitFinalSale = function () {
            if (window.amePosIsDraftMemo()) {
                saveMemoDraft();
                return;
            }
            return ameOriginalSubmitFinalSale.apply(this, arguments);
        };
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            if (drawer) {
                drawer.classList.remove('open');
                drawer.setAttribute('aria-hidden', 'true');
            }
        });
    }

    if (drawer) {
        drawer.addEventListener('click', function (event) {
            if (event.target === drawer) {
                drawer.classList.remove('open');
                drawer.setAttribute('aria-hidden', 'true');
            }
        });
    }

    document.addEventListener('click', function (event) {
        var flyout = byId('amePosRecentFlyout');
        var liveToggle = byId('amePosRecentToggle');
        if (!flyout || !liveToggle) {
            return;
        }
        if (!flyout.contains(event.target) && !liveToggle.contains(event.target)) {
            hideFlyout();
        }
    });

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(function (regs) {
            regs.forEach(function (reg) {
                reg.update();
            });
        });
    }
    if (window.caches && caches.keys) {
        caches.keys().then(function (keys) {
            keys.forEach(function (key) {
                if (key.indexOf('ame-pwa-') === 0 && key !== 'ame-pwa-v1098261') {
                    caches.delete(key);
                }
            });
        });
    }
})();
</script>
