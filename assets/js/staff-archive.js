/**
 * 10.98.337 — আর্কাইভ + চিরতরে সরানো + EMP কোড + সুপার অ্যাডমিন রক্ষা।
 * SQL DELETE নয়: archived_at / purged_at। কোড ও খাতা থাকে।
 */
(function () {
    'use strict';
    if (window.AME_STAFF_ARCHIVE) {
        return;
    }
    window.AME_STAFF_ARCHIVE = true;

    var archived = {};
    var purged = {};
    var purgedCodes = {};
    var saUsers = {};
    var canPurge = false;
    var path = String(location.pathname || '').toLowerCase();
    var onHrm = /\/modules\/hrm\//.test(path);

    function pageText() {
        var h = document.querySelector('h1, h2, .page-title, .card-title, .card-header, .page-header');
        return ((h && h.textContent) || '') + ' ' + (document.title || '');
    }

    function isArchiveView() {
        if (/employee_archive|staff_archive|archived/.test(path)) return true;
        return /সরানো কর্মী|কর্মী আর্কাইভ/.test(pageText());
    }

    function token() {
        return window.AME_CSRF_TOKEN || '';
    }

    function hideEl(el) {
        if (!el) return;
        el.hidden = true;
        el.style.display = 'none';
        el.setAttribute('data-ame-purged-row', '1');
    }

    function rowId(el) {
        if (!el) return '';
        return el.getAttribute('data-employee-id')
            || el.getAttribute('data-emp-id')
            || el.getAttribute('data-staff-id')
            || el.getAttribute('data-id')
            || el.value
            || '';
    }

    function shouldHideId(id, forArchivePage) {
        if (!id) return false;
        var key = String(id);
        if (purged[key]) return true;
        if (forArchivePage) return false;
        return Boolean(archived[key]);
    }

    function applyHidden() {
        var archivePage = isArchiveView();
        document.querySelectorAll('option, [data-employee-id], [data-emp-id], [data-staff-id], tr, li').forEach(function (el) {
            if (el.tagName === 'OPTION' && el.closest) {
                var accountSel = el.closest('select');
                if (accountSel && (
                    accountSel.matches('select.ame-account-select, select[data-ame-account-select="1"], select[name="account_id"], select.eh-account')
                    || accountSel.closest('#ehDraw, #ehPanel, .eh-form, .wd-form, .eh-account-field')
                )) {
                    return;
                }
            }
            var id = rowId(el);
            var code = (el.tagName === 'TR' || el.tagName === 'LI') ? extractCode(el) : '';
            var hideByCode = Boolean(code && purgedCodes[code] && (el.tagName === 'TR' || el.tagName === 'LI'));
            var hideById = id && shouldHideId(id, archivePage && (el.tagName === 'TR' || el.tagName === 'LI'));
            if (!hideById && !hideByCode) return;
            if (el.tagName === 'OPTION') {
                el.disabled = true;
                el.hidden = true;
                if (el.selected) el.selected = false;
                return;
            }
            if (onHrm && (el.tagName === 'TR' || el.tagName === 'LI')) {
                hideEl(el);
            }
        });
        document.querySelectorAll('a[href*="employee_"], a[href*="emp_id="], a[href*="id="]').forEach(function (a) {
            var m = String(a.getAttribute('href') || '').match(/(?:id|emp_id|employee_id)=(\d+)/i);
            if (!m) return;
            var id = m[1];
            var row = a.closest('tr, li, .card, .ame-emp-card, article');
            if (!row) return;
            if (purged[id] || rowIsPurged(row)) {
                hideEl(row);
                return;
            }
            if (archivePage) return;
            if (archived[id] && onHrm && /list|index/.test(path)) {
                hideEl(row);
            }
        });
        rewriteArchiveStatus();
    }

    function rewriteArchiveStatus() {
        if (!isArchiveView()) return;
        document.querySelectorAll('td, span, small, .badge, .status').forEach(function (el) {
            if (el.querySelector && el.querySelector('a, button, input')) return;
            var t = String(el.textContent || '').replace(/\s+/g, ' ').trim();
            if (t === 'সক্রিয় রয়েছে' || t === 'সক্রিয়') {
                el.textContent = 'সরানো হয়েছে';
                el.style.color = '#b45309';
                el.style.fontWeight = '800';
            }
        });
    }

    function normCode(c) {
        return String(c || '').replace(/\s+/g, '').toUpperCase();
    }

    function extractCode(el) {
        var root = (el && el.closest && el.closest('tr, li, .card, article, .ame-emp-card')) || el;
        var t = (root && (root.textContent || '')) || '';
        var m = t.match(/\bEMP[-_]?\d+\b/i);
        return m ? normCode(m[0]) : '';
    }

    function rowIsPurged(el) {
        var id = rowId(el);
        if (id && purged[String(id)]) return true;
        var code = extractCode(el);
        return Boolean(code && purgedCodes[code]);
    }

    function extractId(el) {
        if (!el) return 0;
        var href = el.getAttribute && (el.getAttribute('href') || el.getAttribute('data-href') || '');
        var m = String(href).match(/(?:delete|remove|restore|id|emp_id|employee_id)=(\d+)/i);
        if (m) return parseInt(m[1], 10) || 0;
        var form = el.closest && el.closest('form');
        if (form) {
            var hid = form.querySelector('[name="id"], [name="employee_id"], [name="emp_id"], [name="delete_id"]');
            if (hid && hid.value) return parseInt(hid.value, 10) || 0;
        }
        var row = el.closest && el.closest('tr, [data-employee-id], [data-id], li, .card, article');
        if (row) {
            var did = row.getAttribute('data-employee-id') || row.getAttribute('data-emp-id') || row.getAttribute('data-id');
            if (did) return parseInt(did, 10) || 0;
            var link = row.querySelector('a[href*="id="], a[href*="emp_id="], a[href*="employee_id="]');
            if (link) {
                var hm = String(link.getAttribute('href') || '').match(/(?:id|emp_id|employee_id)=(\d+)/i);
                if (hm) return parseInt(hm[1], 10) || 0;
            }
            var inp = row.querySelector('input[name="id"], input[name="employee_id"], input[name="emp_id"]');
            if (inp && inp.value) return parseInt(inp.value, 10) || 0;
        }
        return 0;
    }

    function postStaff(doWhat, id, code) {
        var body = new URLSearchParams();
        body.set('do', doWhat);
        if (id) body.set('id', String(id));
        if (code) body.set('code', code);
        if (token()) body.set('csrf_token', token());
        return fetch('/modules/hrm/archive_api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }

    function applyLists(data) {
        archived = {};
        purged = {};
        purgedCodes = {};
        saUsers = {};
        if (data && Array.isArray(data.ids)) {
            data.ids.forEach(function (id) { archived[String(id)] = true; });
            window.AME_ARCHIVED_STAFF_IDS = data.ids;
        }
        if (data && Array.isArray(data.purged)) {
            data.purged.forEach(function (id) { purged[String(id)] = true; });
            window.AME_PURGED_STAFF_IDS = data.purged;
        }
        if (data && Array.isArray(data.purgedCodes)) {
            data.purgedCodes.forEach(function (c) { purgedCodes[normCode(c)] = true; });
        }
        if (data && Array.isArray(data.superAdminUserIds)) {
            data.superAdminUserIds.forEach(function (id) { saUsers[String(id)] = true; });
        }
        canPurge = Boolean(data && data.canPurge);
        window.AME_CAN_PURGE_STAFF = canPurge;
        window.AME_IS_SUPER_ADMIN = Boolean(data && data.isSuperAdmin);
        applyHidden();
        injectPurgeButtons();
    }

    function loadIds() {
        var watch = onHrm || isArchiveView();
        try {
            var raw = sessionStorage.getItem('ame_staff_arch_v1');
            if (raw) {
                var box = JSON.parse(raw);
                if (box && box.data && box.t && (Date.now() - box.t) < 600000) {
                    applyLists(box.data);
                    applyHidden();
                    if (!watch) return;
                }
            }
        } catch (e) {}
        fetch('/modules/hrm/archive_api.php', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) return;
                applyLists(data);
                applyHidden();
                try {
                    sessionStorage.setItem('ame_staff_arch_v1', JSON.stringify({ t: Date.now(), data: data }));
                } catch (e) {}
            })
            .catch(function () {});
    }

    function isHardDeleteTarget(el) {
        if (!el || !el.closest) return false;
        if (el.getAttribute && el.getAttribute('data-ame-hard-delete') === '1') return true;
        if (el.classList && el.classList.contains('ame-hard-delete-btn')) return true;
        var form = el.closest('form');
        if (form && form.getAttribute('data-ame-hard-delete') === '1') return true;
        if (form && form.querySelector && form.querySelector('[name="hard_delete"]')) return true;
        var href = String((el.getAttribute && (el.getAttribute('href') || el.getAttribute('action'))) || '').toLowerCase();
        var text = String((el.textContent || (el.getAttribute && (el.getAttribute('aria-label') || el.title)) || '')).replace(/\s+/g, ' ');
        return /process_employee_delete|একদম\s*ডিলিট|একদম/.test(href + ' ' + text);
    }

    function looksLikeStaffDelete(el) {
        if (!el || !el.closest) return false;
        if (isHardDeleteTarget(el)) return false;
        if (el.getAttribute('data-eh') === 'money-del' || el.classList.contains('eh-del')) return false;
        if (el.closest('#ehDraw, #ehPanel, .eh-money, .eh-form, .wd-form')) return false;
        var href = String(el.getAttribute('href') || el.getAttribute('action') || '').toLowerCase();
        var text = String(el.textContent || el.getAttribute('aria-label') || el.title || '').replace(/\s+/g, ' ');
        if (/process_employee_delete|data-ame-hard-delete|একদম/.test(href + text)) {
            return false;
        }
        if (/employee_delete|delete_employee|remove_employee/.test(href)) {
            return true;
        }
        if (/(?:[?&](?:action|do|op)=delete)/.test(href) && /employee/.test(href + path)) {
            return true;
        }
        if (!/employee_list|employee_edit/.test(path)) {
            return false;
        }
        return /মুছুন|মুছে ফেল|delete|remove/.test(text) && !/চিরতরে/.test(text);
    }

    function looksLikeRestore(el) {
        var text = String(el.textContent || el.getAttribute('aria-label') || el.title || '').replace(/\s+/g, ' ').trim();
        return /ফেরত আনুন|প্রজেক্টে ফেরত/.test(text) && !/চিরতরে/.test(text);
    }

    function archiveInstead(id, ev) {
        if (!id) return false;
        if (ev) {
            ev.preventDefault();
            ev.stopPropagation();
        }
        if (!window.confirm('কর্মীকে প্রজেক্ট থেকে সরানো হবে। কোড, বেতন ও হাজিরা খাতা মুছে যাবে না। আর্কাইভ তালিকায় থাকবে।')) {
            return true;
        }
        postStaff('archive', id, '')
            .then(function (data) {
                if (!data || !data.ok) {
                    window.alert((data && data.message) || 'সরানো যায়নি। কোড মুছা হয়নি।');
                    return;
                }
                applyLists(data);
                loadIds();
                window.alert(data.message || 'আর্কাইভে গেছে।');
            })
            .catch(function () {
                window.alert('নেটওয়ার্ক সমস্যা। কোনো কোড মুছা হয়নি।');
            });
        return true;
    }

    function purgeStaff(id, code, ev) {
        if (ev) {
            ev.preventDefault();
            ev.stopPropagation();
        }
        if (!id && !code) return;
        if (!window.confirm('চিরতরে প্রজেক্ট থেকে মুছে যাবে। নাম আর কোনো তালিকায় দেখাবে না। কোড, বেতন ও হাজিরা খাতা থাকবে। এগোবেন?')) {
            return;
        }
        if (!window.confirm('নিশ্চিত? এটা ফেরত আনা যাবে না। কোড মুছে যাবে না।')) {
            return;
        }
        postStaff('purge', id, code)
            .then(function (data) {
                if (!data || !data.ok) {
                    window.alert((data && data.message) || 'চিরতরে সরানো যায়নি। কোড মুছা হয়নি।');
                    return;
                }
                if (id) purged[String(id)] = true;
                if (code) purgedCodes[normCode(code)] = true;
                applyLists(data);
                loadIds();
                window.alert(data.message || 'প্রজেক্ট তালিকা থেকে চিরতরে সরানো হয়েছে।');
            })
            .catch(function () {
                window.alert('নেটওয়ার্ক সমস্যা। কোনো কোড মুছা হয়নি।');
            });
    }

    function injectPurgeButtons() {
        if (!canPurge || !isArchiveView()) return;
        document.querySelectorAll('a, button, input[type="submit"]').forEach(function (el) {
            if (!looksLikeRestore(el)) return;
            var row = el.closest('tr, li, .card, article, .ame-emp-card, .employee-item') || el.parentElement;
            if (!row) return;
            if (row.querySelector && row.querySelector('.ame-staff-purge')) return;
            var id = extractId(el) || extractId(row);
            var code = extractCode(row);
            if (!id && !code) return;
            if ((id && purged[String(id)]) || (code && purgedCodes[normCode(code)])) {
                hideEl(row.tagName === 'TR' || row.tagName === 'LI' ? row : row.closest('tr, li') || row);
                return;
            }
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ame-staff-purge';
            btn.textContent = 'চিরতরে মুছুন';
            btn.addEventListener('click', function (ev) {
                purgeStaff(id, code, ev);
            });
            var cell = el.parentElement || row;
            if (el.nextSibling) {
                cell.insertBefore(btn, el.nextSibling);
            } else {
                cell.appendChild(btn);
            }
        });
    }

    document.addEventListener('click', function (ev) {
        var t = ev.target;
        if (!t || !t.closest) return;
        var el = t.closest('a, button, input[type="submit"]');
        if (!el) return;
        if (isHardDeleteTarget(el)) return;
        if (el.classList && el.classList.contains('ame-staff-purge')) return;
        var id = extractId(el);
        if (looksLikeRestore(el) && ((id && purged[String(id)]) || rowIsPurged(el))) {
            ev.preventDefault();
            ev.stopPropagation();
            window.alert('এই কর্মী চিরতরে প্রজেক্ট তালিকা থেকে সরানো। কোড ও খাতা মুছে যায়নি, ফেরত আনা যায় না।');
            return;
        }
        if (/\/modules\/settings\/user/.test(path) && saUsers[String(id)] && /মুছুন|মুছে|delete|remove/.test(String(el.textContent || '').toLowerCase())) {
            ev.preventDefault();
            ev.stopPropagation();
            window.alert('সুপার অ্যাডমিন ইউজার অন্য অ্যাডমিন মুছতে পারে না।');
            return;
        }
        if (!looksLikeStaffDelete(el)) return;
        if (/চিরতরে/.test(String(el.textContent || ''))) return;
        if (id) archiveInstead(id, ev);
    }, true);

    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!form || !onHrm) return;
        if (form.getAttribute('data-ame-hard-delete') === '1') return;
        if (form.querySelector && form.querySelector('[name="hard_delete"]')) return;
        var doField = form.querySelector('[name="do"]');
        if (doField && String(doField.value || '') === 'purge') return;
        var action = String(form.getAttribute('action') || '').toLowerCase();
        if (/process_employee_delete/.test(action)) return;
        var del = form.querySelector('[name="delete"], [name="do"][value="delete"], [name="action"][value="delete"]');
        if (!del && !/delete/.test(action)) return;
        var id = extractId(form) || extractId(del);
        if (id) {
            ev.preventDefault();
            archiveInstead(id, ev);
        }
    }, true);

    var obsTimer = 0;
    function scheduleInject() {
        window.clearTimeout(obsTimer);
        obsTimer = window.setTimeout(function () {
            applyHidden();
            injectPurgeButtons();
            rewriteArchiveStatus();
        }, 120);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadIds);
    } else {
        loadIds();
    }
    if (onHrm || isArchiveView()) {
        window.addEventListener('load', function () {
            applyHidden();
            injectPurgeButtons();
        });
        if (document.documentElement) {
            new MutationObserver(scheduleInject).observe(document.documentElement, {
                childList: true,
                subtree: true
            });
        }
    }
})();
