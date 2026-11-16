<div class="ame-due-edit-overlay" id="ameDueEditOverlay" aria-hidden="true">
    <div class="ame-due-edit-card" role="dialog" aria-modal="true" aria-labelledby="ameDueEditTitle">
        <h3 id="ameDueEditTitle">পরবর্তী পেমেন্টের তারিখ</h3>
        <div class="ame-due-edit-grid">
            <div class="ame-due-edit-field is-id">
                <label for="ameDueEditCode">আইডি</label>
                <input type="text" id="ameDueEditCode" readonly>
            </div>
            <div class="ame-due-edit-field is-name">
                <label for="ameDueEditName">নাম</label>
                <input type="text" id="ameDueEditName" readonly>
            </div>
            <div class="ame-due-edit-field is-due">
                <label for="ameDueEditTotal">বাকি</label>
                <input type="text" id="ameDueEditTotal" readonly>
            </div>
            <div class="ame-due-edit-field is-expect">
                <label for="ameDueEditExpected">প্রত্যাশিত টাকা</label>
                <input type="number" step="0.01" min="0" id="ameDueEditExpected" inputmode="decimal">
            </div>
            <div class="ame-due-edit-field is-left">
                <label for="ameDueEditRemaining">অবশিষ্ট বাকি</label>
                <input type="text" id="ameDueEditRemaining" readonly>
            </div>
            <div class="ame-due-edit-field is-date">
                <label for="ameDueEditDate">আবার নোটিফিকেশন দেখানোর তারিখ</label>
                <input type="date" id="ameDueEditDate" required>
            </div>
            <div class="ame-due-edit-field is-note">
                <label for="ameDueEditNote">নোট</label>
                <input type="text" id="ameDueEditNote">
            </div>
        </div>
        <div class="ame-due-edit-actions">
            <button type="button" class="ame-due-edit-close" id="ameDueEditClose">বন্ধ</button>
            <button type="button" class="ame-due-edit-save" id="ameDueEditSave">ওকে করুন</button>
        </div>
        <div class="ame-due-edit-msg" id="ameDueEditMsg"></div>
    </div>
</div>

<script>
(function () {
    if (window.AME_DUE_EDIT_BOOTED) { return; }
    window.AME_DUE_EDIT_BOOTED = true;

    var currentId = 0;
    var currentTotal = 0;
    var overlay = document.getElementById('ameDueEditOverlay');
    var csrf = <?php echo json_encode(function_exists('csrf_token') ? csrf_token() : '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function $(id) { return document.getElementById(id); }
    function money(n) { return String(Math.round((Number(n) || 0) * 100) / 100); }
    function setMsg(text, ok) {
        var msg = $('ameDueEditMsg');
        if (!msg) { return; }
        msg.style.color = ok ? '#15803d' : '#b91c1c';
        msg.textContent = text || '';
    }
    function recalc() {
        var expected = parseFloat(($('ameDueEditExpected') || {}).value) || 0;
        if ($('ameDueEditRemaining')) {
            $('ameDueEditRemaining').value = money(currentTotal - expected);
        }
    }
    function closeEdit() {
        if (!overlay) { return; }
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        currentId = 0;
    }
    function openEdit(btn) {
        if (!overlay || !btn) { return; }
        // পুরনো ক্যাশের runtime আগে কোনো customer-seat কার্ড বানিয়ে থাকলেও
        // edit action-এ সেটি রাখব না—শুধু কেন্দ্রের এই modal দৃশ্যমান থাকবে।
        Array.prototype.forEach.call(document.querySelectorAll('.ame-cus-seat'), function (card) {
            if (card && card.parentNode) { card.parentNode.removeChild(card); }
        });
        currentId = parseInt(btn.getAttribute('data-id') || '0', 10) || 0;
        currentTotal = parseFloat(btn.getAttribute('data-total') || '0') || 0;
        if ($('ameDueEditCode')) $('ameDueEditCode').value = btn.getAttribute('data-code') || '';
        if ($('ameDueEditName')) $('ameDueEditName').value = btn.getAttribute('data-name') || '';
        if ($('ameDueEditTotal')) $('ameDueEditTotal').value = money(currentTotal);
        if ($('ameDueEditExpected')) $('ameDueEditExpected').value = btn.getAttribute('data-expected') || '';
        if ($('ameDueEditNote')) $('ameDueEditNote').value = btn.getAttribute('data-note') || '';
        setMsg('', true);
        recalc();
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        var dateEl = $('ameDueEditDate');
        var dateVal = btn.getAttribute('data-date') || '';
        window.requestAnimationFrame(function () {
            if (dateEl) {
                dateEl.value = dateVal;
                try { dateEl.focus(); } catch (e) {}
            }
        });
    }
    function saveEdit() {
        var date = (($('ameDueEditDate') || {}).value || '').trim();
        if (!currentId) {
            setMsg('কাস্টমার পাওয়া যায়নি।', false);
            return;
        }
        if (!date) {
            setMsg('তারিখ দিন।', false);
            return;
        }
        setMsg('সংরক্ষণ হচ্ছে...', true);
        var fd = new FormData();
        fd.append('customer_id', String(currentId));
        fd.append('next_due_date', date);
        fd.append('next_due_amount', (($('ameDueEditExpected') || {}).value || ''));
        fd.append('due_note', (($('ameDueEditNote') || {}).value || ''));
        fd.append('csrf_token', csrf);
        fetch('/modules/customers/actions/set_reminder.php', { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.success) {
                    var kept = (data.next_due_date || date);
                    setMsg(data.hidden
                        ? ('তারিখ রাখা হয়েছে: ' + kept + ' — সেই দিন আবার আসবে।')
                        : ('তারিখ রাখা হয়েছে: ' + kept), true);
                    window.setTimeout(function () { location.reload(); }, 600);
                } else {
                    setMsg((data && data.message) ? data.message : 'সেভ হয়নি।', false);
                }
            })
            .catch(function () { setMsg('সার্ভার এরর। আবার চেষ্টা করুন।', false); });
    }

    window.openEdit = function (id, name, totalDue, expectedAmount, dueDate, note, code) {
        openEdit({
            getAttribute: function (key) {
                var map = {
                    'data-id': id,
                    'data-name': name,
                    'data-total': totalDue,
                    'data-expected': expectedAmount,
                    'data-date': dueDate,
                    'data-note': note,
                    'data-code': code
                };
                return map[key] != null ? String(map[key]) : '';
            }
        });
    };
    window.closeEdit = closeEdit;

    document.addEventListener('click', function (ev) {
        var t = ev.target;
        if (!t || !t.closest) { return; }
        var btn = t.closest('[data-due-edit]');
        if (btn) {
            ev.preventDefault();
            ev.stopPropagation();
            openEdit(btn);
            return;
        }
        if (t.id === 'ameDueEditClose' || t.id === 'ameDueEditOverlay') {
            ev.preventDefault();
            closeEdit();
        }
    });
    var exp = $('ameDueEditExpected');
    if (exp) { exp.addEventListener('input', recalc); }
    var save = $('ameDueEditSave');
    if (save) { save.addEventListener('click', saveEdit); }
})();
</script>
