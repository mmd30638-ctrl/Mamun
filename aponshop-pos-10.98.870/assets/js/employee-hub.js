/* 10.98.889 — কর্মী কাজ: ৬ সেক সতর্কতা */
(function () {
  var api = '/modules/hrm/hub_api.php';
  var csrf = window.AME_CSRF_TOKEN || '';
  var draw = document.getElementById('ehDraw');
  var panel = document.getElementById('ehPanel');
  var toast = document.getElementById('ehToast');
  var currentId = 0;
  var currentName = '';

  function money(n) {
    if (n === null || n === undefined || n === '') return '—';
    return '৳' + Number(n).toLocaleString('en-BD', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }
  function toastMsg(msg) {
    if (!toast) return;
    toast.textContent = msg;
    toast.style.display = 'block';
    setTimeout(function () { toast.style.display = 'none'; }, 2200);
  }
  function closeDraw() {
    if (draw) {
      draw.classList.remove('open');
      draw.setAttribute('aria-hidden', 'true');
    }
    document.body.classList.remove('eh-modal-open');
  }
  function headerH() {
    var h = document.querySelector('.master-header-fixed');
    if (h) {
      var r = h.getBoundingClientRect();
      if (r.height > 20) return Math.ceil(r.height);
    }
    var css = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--ame-header-h'));
    return css > 20 ? css : 60;
  }
  function fitDraw() {
    if (!draw || !panel || !draw.classList.contains('open')) return;
    var pad = window.innerWidth <= 900 ? 4 : 8;
    var top = headerH() + pad;
    draw.style.setProperty('top', top + 'px', 'important');
    draw.style.setProperty('bottom', pad + 'px', 'important');
    draw.style.setProperty('left', pad + 'px', 'important');
    draw.style.setProperty('right', pad + 'px', 'important');
    panel.style.setProperty('zoom', '1', 'important');
    panel.style.setProperty('width', 'min(960px, 100%)', 'important');
    panel.style.setProperty('max-height', '100%', 'important');
  }
  function enhancePopupAmounts(root) {
    if (!root) return;
    var moneyNames = /(^|_)(amount|salary|work_value|other_cost|snack|daily_snack_amount|house_rent|medical|transport|food|overtime|allowance|deduction|loan_cut|late_cut|leave_cut|fine_cut|other_cut)$/;
    root.querySelectorAll('input[type="number"][name]').forEach(function (input) {
      if (input.closest('#ehPayMode, #ehSalaryPay')) return;
      var name = String(input.name || '').toLowerCase();
      if (!moneyNames.test(name) || /percent|rate|days|quantity|qty/.test(name)) return;
      if (input.nextElementSibling && input.nextElementSibling.classList.contains('eh-amount-total')) return;
      var total = document.createElement('small');
      total.className = 'eh-amount-total';
      total.setAttribute('aria-live', 'polite');
      var refresh = function () {
        var value = Number(input.value || 0);
        total.textContent = 'মোট অ্যামাউন্ট: ' + money(isFinite(value) ? value : 0);
      };
      input.insertAdjacentElement('afterend', total);
      input.addEventListener('input', refresh);
      input.addEventListener('change', refresh);
      if (name === 'amount' && String(input.value).trim() === '') input.value = '0';
      input.addEventListener('focus', function () {
        var el = this;
        setTimeout(function () { el.select(); }, 0);
      });
      refresh();
    });
  }
  function openDraw(title, html) {
    if (!draw || !panel) return;
    panel.innerHTML = '<button type="button" class="eh-close" data-eh-close>×</button><h2>' + title + '</h2>' + html;
    enhancePopupAmounts(panel);
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'true');
    panel.setAttribute('aria-label', title);
    panel.scrollTop = 0;
    draw.setAttribute('aria-hidden', 'false');
    draw.classList.add('open');
    document.body.classList.add('eh-modal-open');
    requestAnimationFrame(function () {
      fitDraw();
      requestAnimationFrame(fitDraw);
    });
  }
  window.addEventListener('resize', fitDraw);
  window.addEventListener('orientationchange', function () { setTimeout(fitDraw, 80); });
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', fitDraw);
  }
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && draw && draw.classList.contains('open')) {
      closeDraw();
    }
  });
  function fd(extra) {
    var d = new FormData();
    d.append('csrf_token', csrf);
    Object.keys(extra || {}).forEach(function (k) { d.append(k, extra[k]); });
    return d;
  }
  function post(action, extra) {
    extra = extra || {};
    extra.action = action;
    return fetch(api + '?action=' + encodeURIComponent(action), {
      method: 'POST',
      body: fd(extra),
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); });
  }
  function get(action, extra) {
    var q = new URLSearchParams(extra || {});
    q.set('action', action);
    return fetch(api + '?' + q.toString(), { credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  function accountsOptions() {
    var acc = (window.EH_BOOT && window.EH_BOOT.accounts) || [];
    if (!acc.length) return '<option value="">কোনো অ্যাকাউন্ট নেই</option>';
    var html = '<option value="">অ্যাকাউন্ট বাছুন</option>';
    html += acc.map(function (a) {
      var label = a.label;
      if (!label) {
        var bal = Number(a.balance);
        var balTxt = isFinite(bal) ? (' · ৳' + bal.toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 })) : '';
        var type = a.account_type ? (' · ' + a.account_type) : '';
        label = (a.account_name || ('#' + a.id)) + type + balTxt;
      }
      return '<option value="' + a.id + '">' + esc(label) + '</option>';
    }).join('');
    return html;
  }
  function accountSelectHtml(required) {
    return '<select class="eh-account ame-account-select" name="account_id" data-ame-account-select="1" data-ame-direction="pay" data-ame-initial="" data-ame-sheet="desk"' + (required ? ' required' : '') + '>' + accountsOptions() + '</select>';
  }
  function setEmp(btn) {
    currentId = parseInt(btn.getAttribute('data-id') || '0', 10);
    currentName = btn.getAttribute('data-name') || '';
  }

  function paginateRows(rows, bodyId, pagerId, renderRow, emptyRow, pageSize) {
    rows = Array.isArray(rows) ? rows : [];
    pageSize = pageSize || 12;
    var page = 1;
    var pages = Math.max(1, Math.ceil(rows.length / pageSize));
    function render() {
      var body = document.getElementById(bodyId);
      var pager = document.getElementById(pagerId);
      if (!body || !pager) return;
      var start = (page - 1) * pageSize;
      var slice = rows.slice(start, start + pageSize);
      body.innerHTML = slice.length ? slice.map(renderRow).join('') : emptyRow;
      pager.innerHTML = '<button type="button" data-page-prev' + (page <= 1 ? ' disabled' : '') + '>আগে</button>'
        + '<span>পৃষ্ঠা ' + page + ' / ' + pages + ' · ' + rows.length + ' সারি</span>'
        + '<button type="button" data-page-next' + (page >= pages ? ' disabled' : '') + '>পরে</button>';
      var prev = pager.querySelector('[data-page-prev]');
      var next = pager.querySelector('[data-page-next]');
      if (prev) prev.onclick = function () { if (page > 1) { page--; render(); } };
      if (next) next.onclick = function () { if (page < pages) { page++; render(); } };
    }
    render();
  }

  document.addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-eh]');
    if (ev.target.closest('[data-eh-close]') || ev.target === draw) {
      closeDraw();
      return;
    }
    if (!t) return;
    var act = t.getAttribute('data-eh');
    setEmp(t);
    if (act === 'on' || act === 'off') {
      t.disabled = true;
      post('attendance', { id: String(currentId), state: act === 'on' ? 'on' : 'off' })
        .then(function (res) {
          if (!res.ok) throw new Error(res.error || 'ব্যর্থ');
          location.reload();
        })
        .catch(function (e) { toastMsg(e.message); t.disabled = false; });
      return;
    }
    if (act === 'work') return openWorkList();
    if (act === 'attendance') return openAttendance();
    if (act === 'work-new') return openWorkForm(0);
    if (act === 'work-edit') return openWorkForm(parseInt(t.getAttribute('data-work-id') || '0', 10));
    if (act === 'work-back') return openWorkList();
    if (act === 'ledger') return openLedger();
    if (act === 'report') return openReport();
    if (act === 'more') return openMore();
    if (act === 'new') return openPerson(0);
    if (act === 'edit') return openPerson(currentId);
    if (act === 'snack') return openSnack();
    if (act === 'advance') return openAdvance();
    if (act === 'deposit') return openDeposit();
    if (act === 'salary') return openSalary();
    if (act === 'delete') return openDelete();
    if (act === 'money-del') return deleteMoney(t);
    if (act === 'box' || act === 'box-edit') {
      ev.preventDefault();
      if (act === 'box' && t.getAttribute('data-box') === 'salary') {
        return openSalary();
      }
      return openMoneyBox(t);
    }
  });

  function nowTime() {
    return (window.EH_BOOT && EH_BOOT.nowTime) ? EH_BOOT.nowTime : '';
  }

  function openAttendance() {
    var today = (window.EH_BOOT && EH_BOOT.today) || '';
    openDraw('হাজিরা · ' + currentName, '<form class="eh-form" id="ehAttendance">'
      + '<input type="hidden" name="id" value="' + currentId + '">'
      + '<label>তারিখ</label><input type="date" name="att_date" value="' + today + '" required>'
      + '<label>হাজিরার অবস্থা</label><select name="state"><option value="on">উপস্থিত / চালু</option><option value="off">অনুপস্থিত / বন্ধ</option></select>'
      + '<p class="eh-meta">তারিখ ও অবস্থা বেছে নিচের বাটন চাপুন। একই দিনের হাজিরা আবার দিলে আগের সারিটিই সংশোধন হবে।</p>'
      + '<button class="eh-submit" type="submit">হাজিরা সাবমিট করুন</button></form>');
    bindForm('ehAttendance', 'attendance');
  }
  function timeShort(t) {
    t = String(t || '');
    return t.length >= 5 ? t.slice(0, 5) : t;
  }
  function payGrid(w) {
    return '<div class="eh-paygrid">'
      + '<div><span>কাজের মূল্য</span><b>' + money(w.work_value) + '</b></div>'
      + '<div><span>কর্মীর শতাংশ</span><b>' + Number(w.employee_percent || 0) + '%</b></div>'
      + '<div><span>কর্মীর আয়</span><b>' + money(w.employee_amount) + '</b></div>'
      + '<div><span>বোনাস</span><b>' + money(w.bonus_amount) + '</b></div>'
      + '<div><span>নাস্তা</span><b>' + money(w.snack) + '</b></div>'
      + '<div><span>অন্য খরচ</span><b>' + money(w.other_cost) + '</b></div>'
      + '<div><span>দোকানের অংশ</span><b>' + money(w.shop_contribution) + '</b></div>'
      + '</div>';
  }
  function workCard(w) {
    var when = (w.work_date || '') + (w.work_time ? ' · ' + timeShort(w.work_time) : '');
    var who = [w.customer_name, w.customer_phone, w.vehicle, w.vehicle_no].filter(Boolean).join(' · ');
    var kind = [w.service_type, w.description].filter(Boolean).join(' · ');
    var refs = [w.invoice_ref, w.memo_no, w.imei_serial].filter(Boolean).join(' · ');
    var editable = Number(w.editable == null ? 1 : w.editable) === 1;
    var open = editable
      ? '<button type="button" class="eh-work-item" data-eh="work-edit" data-work-id="' + w.id + '" data-id="' + currentId + '" data-name="' + esc(currentName) + '">'
      : '<div class="eh-work-item is-locked">';
    return open
      + '<b>' + esc(when) + (who ? ' · ' + esc(who) : '') + '</b>'
      + (kind ? '<span>' + esc(kind) + '</span>' : '')
      + (refs ? '<span>' + esc(refs) + '</span>' : '')
      + (w.source_label ? '<span class="eh-source">' + esc(w.source_label) + ' · ' + esc(w.payout_status || 'pending') + '</span>' : '')
      + payGrid(w)
      + (editable ? '<span>সংশোধন করতে চাপুন</span></button>' : '</div>');
  }
  function openWorkList() {
    openDraw('কাজ · ' + currentName, '<div id="ehWorkBox"><p class="eh-meta">লোড হচ্ছে…</p></div>');
    get('works', { id: String(currentId), range: 'month' }).then(function (res) {
      var box = document.getElementById('ehWorkBox');
      if (!box) return;
      if (!res.ok) {
        box.innerHTML = '<p class="eh-warn">' + esc(res.error || 'কাজ পাওয়া যায়নি') + '</p>';
        return;
      }
      var works = res.works || [];
      var html = '<button type="button" class="eh-submit" data-eh="work-new" data-id="' + currentId + '" data-name="' + esc(currentName) + '">+ নতুন কাজ</button>';
      if (!works.length) {
        html += '<p class="eh-meta">এই মাসে কাজ নেই। নতুন কাজ যোগ করুন — তারিখ ও সময় নিজে বেছে নিতে পারবেন।</p>';
      } else {
        html += '<p class="eh-meta">প্রতিটি কাজের বেতনের হিসাব নিচে। চাপলে সংশোধন করা যাবে।</p>';
      }
      html += '<div id="ehWorkRows"></div><div class="eh-pager" id="ehWorkPager"></div>';
      box.innerHTML = html;
      paginateRows(works, 'ehWorkRows', 'ehWorkPager', workCard, '<p class="eh-meta">এই মাসে কাজ নেই।</p>', 10);
    });
  }
  function openWorkForm(workId) {
    var fill = function (w) {
      w = w || {};
      var isEdit = !!(w.id);
      var title = (isEdit ? 'কাজ সংশোধন' : 'নতুন কাজ') + ' · ' + currentName;
      var tval = timeShort(w.work_time) || nowTime();
      openDraw(title, '<button type="button" class="eh-back" data-eh="work-back" data-id="' + currentId + '" data-name="' + esc(currentName) + '">← কাজের তালিকা</button>'
        + (isEdit ? '<div class="eh-detail"><p><b>বেতনের হিসাব</b></p>' + payGrid(w) + '</div>' : '')
        + '<form class="eh-form" id="ehWork">'
        + '<input type="hidden" name="id" value="' + (w.id || 0) + '">'
        + '<input type="hidden" name="employee_id" value="' + currentId + '">'
        + '<div class="row"><div><label>তারিখ</label><input type="date" name="work_date" value="' + esc(w.work_date || ((window.EH_BOOT && EH_BOOT.today) || '')) + '"></div>'
        + '<div><label>সময়</label><input type="time" name="work_time" value="' + esc(tval) + '"></div></div>'
        + '<label>ক্রেতা</label><input type="search" name="customer_name" id="ehCust" placeholder="নাম বা মোবাইল" autocomplete="off" value="' + esc(w.customer_name) + '">'
        + '<input type="hidden" name="customer_id" id="ehCustId" value="' + (w.customer_id || 0) + '">'
        + '<div class="eh-suggest" id="ehCustList"></div>'
        + '<label>কাস্টমারের মোবাইল</label><input name="customer_phone" id="ehCustPhone" inputmode="tel" value="' + esc(w.customer_phone) + '">'
        + '<div class="row"><div><label>গাড়ি</label><input name="vehicle" placeholder="গাড়ির নাম" value="' + esc(w.vehicle) + '"></div>'
        + '<div><label>গাড়ির নম্বর</label><input name="vehicle_no" placeholder="গাড়ির নম্বর" value="' + esc(w.vehicle_no) + '"></div></div>'
        + '<div class="row"><div><label>ইনভয়েস</label><input name="invoice_ref" placeholder="INV-..." value="' + esc(w.invoice_ref) + '"></div>'
        + '<div><label>মেমো নম্বর</label><input name="memo_no" placeholder="মেমো" value="' + esc(w.memo_no) + '"></div></div>'
        + '<label>IMEI / সিরিয়াল</label><input name="imei_serial" placeholder="IMEI বা সিরিয়াল" value="' + esc(w.imei_serial) + '">'
        + '<label>সেবা / মেরামত</label><input name="service_type" placeholder="ইঞ্জিন মেরামত" value="' + esc(w.service_type) + '">'
        + '<label>কাজের বিবরণ</label><textarea name="description">' + esc(w.description) + '</textarea>'
        + '<div class="row"><div><label>কাজের মূল্য</label><input name="work_value" type="number" step="0.01" required value="' + (w.work_value || '') + '"></div>'
        + '<div><label>কর্মীর শতাংশ</label><input name="employee_percent" type="number" step="0.01" value="' + (w.employee_percent != null ? w.employee_percent : 50) + '"></div></div>'
        + '<div class="row"><div><label>কর্মীর আয়</label><input name="employee_amount" type="number" step="0.01" placeholder="খালি থাকলে নিজেই হবে" value="' + (w.employee_amount != null && w.id ? w.employee_amount : '') + '"></div>'
        + '<div><label>বোনাস</label><input name="bonus_amount" type="number" step="0.01" value="' + (w.bonus_amount || 0) + '"></div></div>'
        + '<label>অতিরিক্ত নাস্তা</label><input name="snack" type="number" step="0.01" value="' + (w.snack || 0) + '">'
        + '<label>অন্য খরচ</label><input name="other_cost" type="number" step="0.01" value="' + (w.other_cost || 0) + '">'
        + '<button class="eh-submit" type="submit">' + (isEdit ? 'সংশোধন রাখুন' : 'কাজ রাখুন') + '</button></form>'
        + (isEdit ? '<button type="button" class="eh-submit danger" data-eh="money-del" data-kind="work" data-entry="' + w.id + '" data-stay="work" data-id="' + currentId + '" data-name="' + esc(currentName) + '">কাজ মুছুন</button>' : ''));
      bindCustomerSearch();
      bindForm('ehWork', 'work');
    };
    if (!workId) return fill({});
    get('work_item', { work_id: String(workId) }).then(function (res) {
      if (!res.ok || !res.work) {
        toastMsg(res.error || 'কাজ পাওয়া যায়নি');
        return openWorkList();
      }
      fill(res.work);
    });
  }
  function bindCustomerSearch() {
    var box = document.getElementById('ehCust');
    var list = document.getElementById('ehCustList');
    var timer;
    if (box) {
      box.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
          get('search_party', { q: box.value }).then(function (res) {
            var rows = (res.customers || []);
            list.style.display = rows.length ? 'block' : 'none';
            list.innerHTML = rows.map(function (c) {
              return '<button type="button" data-cid="' + c.id + '" data-cname="' + String(c.name || '').replace(/"/g, '') + '" data-cphone="' + String(c.phone || '').replace(/"/g, '') + '">' + c.name + ' · ' + (c.phone || '') + '</button>';
            }).join('');
          });
        }, 220);
      });
    }
    if (list) {
      list.addEventListener('click', function (e) {
        var b = e.target.closest('button');
        if (!b) return;
        document.getElementById('ehCustId').value = b.getAttribute('data-cid');
        box.value = b.getAttribute('data-cname');
        var phone = document.getElementById('ehCustPhone');
        if (phone) phone.value = b.getAttribute('data-cphone') || '';
        list.style.display = 'none';
      });
    }
  }

  function openSnack() {
    openDraw('নাস্তা · ' + currentName, '<form class="eh-form" id="ehSnack">'
      + '<input type="hidden" name="id" value="' + currentId + '">'
      + '<label class="eh-field"><span>ধরন</span><select name="kind"><option value="daily">দৈনিক</option><option value="extra">অতিরিক্ত</option><option value="manual">হাতের এন্ট্রি</option><option value="none">নাস্তা নেই</option></select></label>'
      + '<label class="eh-field"><span>তারিখ</span><input type="date" name="snack_date" value="' + ((window.EH_BOOT && EH_BOOT.today) || '') + '"></label>'
      + '<label class="eh-field"><span>টাকা</span><input name="amount" type="number" step="0.01" min="0" value="0" required></label>'
      + '<label class="eh-field"><span>নোট</span><input name="note"></label>'
      + '<button class="eh-submit" type="submit">নাস্তা রাখুন</button></form>'
      + '<div id="ehMoneyBox"></div>');
    bindForm('ehSnack', 'snack');
    fillMoneyBox('snack', 'snack');
  }

  function paintDrafts(hostId) {
    var host = document.getElementById(hostId);
    if (!host) return;
    get('drafts', { id: String(currentId) }).then(function (res) {
      if (!res.ok) {
        host.innerHTML = '<p class="eh-warn">' + esc(res.error || 'ড্রাফট দেখা যায়নি') + '</p>';
        return;
      }
      var rows = res.rows || [];
      var choice = document.getElementById('ehDraftChoice');
      if (!rows.length) {
        host.innerHTML = '<p class="eh-meta">সকালের ড্রাফট এখন নেই। একই কর্মীকে একাধিকবার অগ্রিম রাখা যাবে।</p>';
        if (choice) choice.style.display = 'none';
        return;
      }
      var html = '<div class="eh-money"><h3>সকালের ড্রাফট · ' + money(res.total) + '</h3>';
      rows.forEach(function (r) {
        html += '<div class="eh-money-row"><div><b>ড্রাফট</b><span>' + esc(r.advance_date || '') + ' · ' + money(r.amount) + (r.note ? ' · ' + esc(r.note) : '') + '</span></div></div>';
      });
      html += '<p class="eh-meta">এগুলো এখনো অ্যাকাউন্ট কাটেনি। বিকালে বেতনের সময় বেতনে সমন্বয় হবে, অথবা সেই অ্যাকাউন্টে যুক্ত হবে।</p></div>';
      host.innerHTML = html;
      if (choice) choice.style.display = '';
    }).catch(function () {
      host.innerHTML = '<p class="eh-warn">ড্রাফট লোড হয়নি।</p>';
    });
  }

  function openAdvance() {
    openDraw('অগ্রিম · ' + currentName, '<form class="eh-form" id="ehAdv">'
      + '<input type="hidden" name="id" value="' + currentId + '">'
      + '<input type="hidden" name="idempotency_token" value="adv-' + Date.now() + '-' + currentId + '">'
      + '<label class="eh-field"><span>ধরন</span><select name="kind"><option value="draft" selected>সকালের ড্রাফট (একাধিকবার)</option><option value="give">এখনই অ্যাকাউন্টে দিন</option><option value="collect">ফেরত নিন</option><option value="salary_cut">পুরনো অগ্রিম বেতনে সমন্বয়</option></select></label>'
      + '<p class="eh-meta">সকালের ড্রাফট অ্যাকাউন্ট কাটে না। বিকালে বেতন দিতে গিয়ে সমন্বয় অথবা অ্যাকাউন্টে যুক্ত করবেন।</p>'
      + '<label class="eh-field"><span>পরে যে অ্যাকাউন্টে যাবে</span>' + accountSelectHtml(false) + '</label>'
      + '<label class="eh-field"><span>টাকা</span><input name="amount" type="number" step="0.01" min="0" value="0" required></label>'
      + '<label class="eh-field"><span>তারিখ</span><input type="date" name="advance_date" value="' + ((window.EH_BOOT && EH_BOOT.today) || '') + '"></label>'
      + '<label class="eh-field"><span>নোট</span><input name="note" placeholder="সকালের ড্রাফট"></label>'
      + '<button class="eh-submit" type="submit">ড্রাফট রাখুন</button></form>'
      + '<div id="ehDrafts"></div>'
      + '<div id="ehMoneyBox"></div>');
    bindForm('ehAdv', 'advance');
    paintDrafts('ehDrafts');
    fillMoneyBox('advance', 'advance');
  }

  var boxTitles = { work: 'আজকের কাজ', salary: 'আজকের বেতন', advance: 'অগ্রিম', deposit: 'জমা', balance: 'ব্যালেন্স' };
  function openMoneyBox(btn) {
    var box = btn.getAttribute('data-box') || 'work';
    var editId = btn.getAttribute('data-entry') || '';
    var editKind = btn.getAttribute('data-kind') || '';
    var editAmt = btn.getAttribute('data-amount');
    openDraw((boxTitles[box] || 'টাকা') + ' · ' + currentName, '<p class="eh-meta">লোড হচ্ছে…</p>');
    get('box', { id: String(currentId), box: box }).then(function (res) {
      if (!res.ok) {
        openDraw((boxTitles[box] || 'টাকা') + ' · ' + currentName, '<p class="eh-warn">' + esc(res.error || 'বক্স খোলা যায়নি') + '</p>');
        return;
      }
      var current = editAmt !== null && editId ? editAmt : res.current;
      var cash = (box === 'advance' || box === 'deposit');
      var rows = res.entries || [];
      var list = '';
      rows.forEach(function (e) {
        list += '<div class="eh-money-row"><div><b>' + esc(e.label) + '</b><span>' + esc(e.date) + ' · ' + money(e.amount) + '</span></div>'
          + '<button type="button" class="eh-submit" data-eh="box-edit" data-box="' + esc(box) + '" data-entry="' + e.id + '" data-kind="' + esc(e.kind) + '" data-amount="' + e.amount + '" data-id="' + currentId + '" data-name="' + esc(currentName) + '">এডিট</button></div>';
      });
      openDraw((boxTitles[box] || 'টাকা') + ' · ' + currentName,
        '<p class="eh-meta">এখন আছে <b>' + money(res.current) + '</b>। সঠিক টাকা লিখে সমন্বয় রাখুন। কর্মী মুছবে না।</p>'
        + '<form class="eh-form" id="ehBox">'
        + '<input type="hidden" name="id" value="' + currentId + '">'
        + '<input type="hidden" name="box" value="' + esc(box) + '">'
        + '<input type="hidden" name="entry_id" value="' + esc(editId) + '">'
        + '<input type="hidden" name="entry_kind" value="' + esc(editKind) + '">'
        + (cash ? '<label class="eh-field"><span>অ্যাকাউন্ট</span>' + accountSelectHtml(false) + '</label><p class="eh-meta">অ্যাকাউন্ট বাছলে ক্যাশ নড়বে। না বাছলে শুধু এই বক্সের হিসাব সমন্বয় হবে।</p>' : '')
        + '<label class="eh-field"><span>সঠিক টাকা</span><input name="amount" type="number" step="0.01" value="' + esc(current) + '" required></label>'
        + '<label class="eh-field"><span>নোট</span><input name="note" value="সমন্বয়"></label>'
        + '<button class="eh-submit" type="submit">সমন্বয় রাখুন</button></form>'
        + '<div class="eh-money"><h3>এই বক্সের এন্ট্রি</h3>' + (list || '<p class="eh-meta">আলাদা এন্ট্রি নেই। উপরে সঠিক টাকা লিখলে বক্সটাই বদলাবে।</p>') + '</div>');
      bindForm('ehBox', 'adjust_box');
    }).catch(function (e) {
      openDraw((boxTitles[box] || 'টাকা') + ' · ' + currentName, '<p class="eh-warn">' + esc(e.message) + '</p>');
    });
  }
  function openDeposit() {
    openDraw('কর্মী জমা · ' + currentName, '<form class="eh-form" id="ehDep">'
      + '<input type="hidden" name="id" value="' + currentId + '">'
      + '<input type="hidden" name="idempotency_token" value="dep-' + Date.now() + '-' + currentId + '">'
      + '<p class="eh-meta">এটি বেতন বা অগ্রিম নয় — ব্যক্তিগত টাকা দোকানে রাখা।</p>'
      + '<label class="eh-field"><span>ধরন</span><select name="kind"><option value="deposit">জমা</option><option value="withdraw">উত্তোলন</option></select></label>'
      + '<label class="eh-field"><span>অ্যাকাউন্ট</span>' + accountSelectHtml(true) + '</label>'
      + '<label class="eh-field"><span>টাকা</span><input name="amount" type="number" step="0.01" min="0" value="0" required></label>'
      + '<label class="eh-field"><span>তারিখ</span><input type="date" name="deposit_date" value="' + ((window.EH_BOOT && EH_BOOT.today) || '') + '"></label>'
      + '<label class="eh-field"><span>নোট</span><input name="note"></label>'
      + '<button class="eh-submit" type="submit">জমা রাখুন</button></form>'
      + '<div id="ehMoneyBox"></div>');
    bindForm('ehDep', 'deposit');
    fillMoneyBox('deposit', 'deposit');
  }

  function money2(n) {
    var num = Number(n);
    if (!isFinite(num)) num = 0;
    return '৳' + num.toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function paintPendingPay(form) {
    var box = document.getElementById('ehPendingShare');
    if (!form || !box) return;
    var dateInput = form.querySelector('input[name="pay_date"]');
    var bonus = form.querySelector('input[name="bonus_amount"]');
    var snacks = form.querySelector('input[name="snacks_amount"]');
    var advCut = form.querySelector('input[name="advance_cut_amount"]');
    var advSel = form.querySelector('select[name="deduct_advance"]');
    var scopeInputs = form.querySelectorAll('input[name="pay_scope"]');
    var snackAllow = form.querySelector('input[name="snack_allowance"]');
    var state = { payable: 0, share: 0, base: 0, work_value: 0, work_bonus: 0, snack: 0, advance_due: 0, today_salary: 0, today_payable: 0, today_share: 0, today_base: 0, today_bonus: 0, today_snack: 0, today_work_value: 0, pay_label: '', note: '' };
    function payScope() {
      var picked = 'today';
      scopeInputs.forEach(function (radio) {
        if (radio.checked) picked = radio.value || 'today';
      });
      return picked;
    }
    function line(label, value) {
      return '<div class="eh-pending-line"><span>' + label + '</span><b>' + money2(value) + '</b></div>';
    }
    function render() {
      var todayMode = payScope() !== 'all';
      var share = todayMode ? Number(state.today_share || 0) : Number(state.share || 0);
      var workValue = todayMode ? Number(state.today_work_value || 0) : Number(state.work_value || 0);
      var base = todayMode ? Number(state.today_base || 0) : Number(state.base || 0);
      var workBonus = todayMode ? Number(state.today_bonus || 0) : Number(state.work_bonus || 0);
      var snackDue = todayMode ? Number(state.today_snack || 0) : Number(state.snack || 0);
      var extra = Number(bonus && bonus.value);
      var allow = Number(snackAllow && snackAllow.value);
      var snackCut = Number(snacks && snacks.value);
      var asked = Number(advCut && advCut.value);
      if (!isFinite(share) || share < 0) share = 0;
      if (!isFinite(workValue) || workValue < 0) workValue = 0;
      if (!isFinite(base) || base < 0) base = 0;
      if (!isFinite(workBonus) || workBonus < 0) workBonus = 0;
      if (!isFinite(snackDue) || snackDue < 0) snackDue = 0;
      if (!isFinite(extra) || extra < 0) extra = 0;
      if (!isFinite(allow) || allow < 0) allow = 0;
      if (!isFinite(snackCut) || snackCut < 0) snackCut = 0;
      if (!isFinite(asked) || asked < 0) asked = 0;
      var adv = 0;
      if (advSel && advSel.value === '1' && state.advance_due > 0.009) {
        var room = base + share + workBonus + extra + allow + snackDue - snackCut;
        if (room < 0) room = 0;
        var cap = Math.min(state.advance_due, room);
        adv = asked > 0.009 ? Math.min(asked, cap) : cap;
      }
      var net = base + share + workBonus + extra + allow + snackDue - snackCut - adv;
      if (!isFinite(net) || net < 0) net = 0;
      var html = line(todayMode ? 'আজকের কাজের বিল' : 'কাজের বিল', workValue) + line(todayMode ? 'আজকের ভাগ' : 'কর্মীর ভাগ', share);
      if (base > 0.009) html += line(state.pay_label || 'নির্ধারিত বেতন', base);
      if (workBonus > 0.009) html += line('কাজের বোনাস', workBonus);
      if (snackDue > 0.009) html += line('নাস্তা', snackDue);
      if (base > 0.009 && snackDue > 0.009) {
        html += '<p class="eh-meta">বেতন ' + money2(base) + ' + নাস্তা ' + money2(snackDue) + ' = ' + money2(base + snackDue) + '</p>';
      } else if (share > 0.009 && snackDue > 0.009) {
        html += '<p class="eh-meta">ভাগ ' + money2(share) + ' + নাস্তা ' + money2(snackDue) + ' = ' + money2(share + snackDue) + '</p>';
      }
      if (extra > 0.009) html += line('অতিরিক্ত বোনাস', extra);
      if (allow > 0.009) html += line('অতিরিক্ত নাস্তা', allow);
      if (snackCut > 0.009) html += line('নাস্তা কর্তন', -snackCut);
      if (Number(state.advance_due || 0) > 0.009) {
        html += '<p class="eh-meta">কর্মীর কাছে দোকানের পাওনা: সাবেক ' + money2(state.advance_previous || 0) + ' + আজকের অগ্রিম ' + money2(state.advance_today || 0) + ' = মোট ' + money2(state.advance_due) + '। এখান থেকে যত টাকা চান আজকের বেতনে সমন্বয় করা যাবে।</p>';
      }
      if (adv > 0.009) html += line('অগ্রিম সমন্বয়', -adv);
      html += '<div class="eh-pending-total"><span>মোট অ্যামাউন্ট</span><b>' + money2(net) + '</b></div>';
      if (todayMode && Number(state.payable || 0) > net + 0.009) {
        html += '<p class="eh-meta">আগের অপরিশোধিত ভাগ ' + money2(state.payable) + '। এই পেমেন্টে শুধু আজকের বেতন যাবে।</p>';
      }
      if (state.note) html += '<p class="eh-meta">' + esc(state.note) + '</p>';
      box.innerHTML = html;
    }
    function load() {
      var day = dateInput ? dateInput.value : '';
      box.textContent = 'প্রাপ্য বেতন লোড হচ্ছে…';
      get('pending_pay', { id: String(currentId), date: day }).then(function (res) {
        if (!document.getElementById('ehPendingShare')) return;
        if (!res.ok) {
          box.innerHTML = '<p class="eh-warn">' + esc(res.error || 'প্রাপ্য বেতন পাওয়া যায়নি') + '</p>';
          return;
        }
        state = res;
        render();
      }).catch(function () {
        if (document.getElementById('ehPendingShare')) {
          box.innerHTML = '<p class="eh-warn">প্রাপ্য বেতন লোড হয়নি।</p>';
        }
      });
    }
    if (dateInput) dateInput.addEventListener('change', load);
    scopeInputs.forEach(function (radio) {
      radio.addEventListener('change', render);
    });
    [bonus, snacks, advCut, snackAllow].forEach(function (input) {
      if (!input) return;
      input.addEventListener('input', render);
      input.addEventListener('change', render);
    });
    if (advSel) advSel.addEventListener('change', render);
    load();
  }

  function openSalary() {
    var today = (window.EH_BOOT && EH_BOOT.today) || '';
    openDraw('বেতন · ' + currentName, '<p class="eh-meta">বেতনের নিয়ম ও ইতিহাস লোড হচ্ছে…</p>');
    get('person', { id: currentId }).then(function (res) {
      if (!res.ok) throw new Error(res.error || 'কর্মী পাওয়া যায়নি');
      var p = res.person || {};
      var mode = p.payment_type === 'daily' ? 'daily' : 'percentage';
      var history = (res.pay_history || []).map(function (h) {
        return '<div class="eh-pay-period"><b>' + esc(h.pay_label) + ' · ' + esc(h.rate_label) + '</b>'
          + '<span>' + esc(h.effective_from) + ' থেকে ' + esc(h.effective_to || 'চলমান') + '</span>'
          + (h.note ? '<small>' + esc(h.note) + '</small>' : '') + '</div>';
      }).join('');
      openDraw('বেতন · ' + currentName, '<div class="eh-pay-history"><h3>বেতনের তারিখভিত্তিক ইতিহাস</h3>'
        + (history || '<p class="eh-meta">এখনো ইতিহাস নেই।</p>') + '</div>'
        + '<form class="eh-form" id="ehPayMode">'
        + '<input type="hidden" name="id" value="' + currentId + '">'
        + '<label>নতুন বেতনের ধরন</label><select name="payment_type">'
        + '<option value="percentage"' + (mode === 'percentage' ? ' selected' : '') + '>কাজের বিলের শতাংশ</option>'
        + '<option value="daily"' + (mode === 'daily' ? ' selected' : '') + '>নির্ধারিত দৈনিক বেতন</option></select>'
        + '<div class="row"><div><label>দৈনিক বেতন (৳)</label><input name="salary" type="number" min="0" step="0.01" value="' + Number(p.salary || 0) + '"></div>'
        + '<div><label>কাজের ভাগ (%)</label><input name="service_share_percent" type="number" min="0" max="100" step="0.01" value="' + Number(p.service_share_percent || 50) + '"></div></div>'
        + '<label>যে তারিখ থেকে কার্যকর</label><input name="effective_from" type="date" value="' + today + '" required>'
        + '<label>পরিবর্তনের কারণ/নোট</label><input name="note" placeholder="যেমন: ১০ অক্টোবর থেকে দৈনিক বেতন">'
        + '<p class="eh-meta">আগের নিয়ম আগের দিন পর্যন্ত থাকবে এবং এই তারিখ থেকে নতুন নিয়ম চলবে।</p>'
        + '<button class="eh-submit" type="submit">বেতনের নিয়ম পরিবর্তন করুন</button></form>'
        + '<form class="eh-form" id="ehSalaryPay" method="post" action="process_salary.php" style="margin-top:16px;padding-top:12px;border-top:1px solid #e2e8f0;">'
        + '<input type="hidden" name="csrf_token" value="' + (csrf || '') + '">'
        + '<input type="hidden" name="employee_id" value="' + currentId + '">'
        + '<input type="hidden" name="from_hub" value="1">'
        + '<label>বেতন দেওয়ার তারিখ</label><input type="date" name="pay_date" value="' + today + '" required>'
        + '<input name="days" type="hidden" value="1">'
        + '<div class="eh-pay-scope"><span>কতটুকু দেবেন</span>'
        + '<label><input type="radio" name="pay_scope" value="today" checked> শুধু আজকের বেতন</label>'
        + '<label><input type="radio" name="pay_scope" value="all"> সব অপরিশোধিত ভাগ</label></div>'
        + '<div id="ehPendingShare" class="eh-pending">প্রাপ্য বেতন লোড হচ্ছে…</div>'
        + '<div class="eh-account-field"><label class="eh-field"><span>যে অ্যাকাউন্ট থেকে দেবেন</span>' + accountSelectHtml(true) + '</label></div>'
        + '<label>অতিরিক্ত বোনাস</label><input name="bonus_amount" type="number" step="0.01" value="0">'
        + '<label>অতিরিক্ত নাস্তা (ভাতা)</label><input name="snack_allowance" type="number" step="0.01" min="0" value="0">'
        + '<label>নাস্তা কর্তন</label><input name="snacks_amount" type="number" step="0.01" value="0">'
        + '<div id="ehDraftBox"></div>'
        + '<div id="ehDraftChoice" style="display:none"><label>সকালের ড্রাফট</label><select name="draft_action"><option value="">এখনো ড্রাফটে রাখুন</option><option value="settle">বেতনে সমন্বয়</option><option value="post">অ্যাকাউন্টে যুক্ত</option></select>'
        + '<p class="eh-meta">সমন্বয় করলে সকালের টাকা আজকের বেতন থেকে কাটা হবে। অ্যাকাউন্টে যুক্ত করলে সাবেক অগ্রিম বাড়বে এবং সেই অ্যাকাউন্ট থেকে যাবে।</p></div>'
        + '<label>পুরনো অগ্রিম সমন্বয়</label><select name="deduct_advance"><option value="">না</option><option value="1">পূর্ণ সমন্বয়</option></select>'
        + '<label>আংশিক সমন্বয় টাকা</label><input name="advance_cut_amount" type="number" step="0.01" value="0" placeholder="খালি = পূর্ণ">'
        + '<p class="eh-meta">«শুধু আজকের বেতন» শুধু ওই দিনের অপরিশোধিত ভাগ কাটে। আগের পাওনা আলাদা চাইলে «সব অপরিশোধিত ভাগ» বাছুন। জমা নাস্তা পেমেন্টে যোগ হয়। অতিরিক্ত নাস্তা আরও যোগ, নাস্তা কর্তন কাটে।</p>'
        + '<button class="eh-submit" type="submit">বেতন সাবমিট</button></form>'
        + '<div id="ehMoneyBox"></div>');
      bindForm('ehPayMode', 'set_pay_mode');
      paintPendingPay(document.getElementById('ehSalaryPay'));
      paintDrafts('ehDraftBox');
      fillMoneyBox('', 'salary');
    }).catch(function (e) {
      openDraw('বেতন · ' + currentName, '<p class="eh-warn">' + esc(e.message) + '</p>');
    });
  }

  function moneyListHtml(entries, stay) {
    stay = stay || 'salary';
    var html = '<div class="eh-money"><h3>টাকার এন্ট্রি</h3>'
      + '<p class="eh-meta">ভুলে যেকোনো টাকার এন্ট্রি দিলে এখান থেকে মুছুন। অ্যাকাউন্টের টাকা ফিরে আসবে। ভুলে দেওয়া বেতন “বেতন ফেরত আনুন” চাপলে টাকা অ্যাকাউন্টে ফিরে আসবে এবং বেতন আবার বাকি হবে — পরে নতুন করে দিতে পারবেন।</p>';
    if (!entries || !entries.length) {
      html += '<p class="eh-meta">এখনো এন্ট্রি নেই।</p></div>';
      return html;
    }
    entries.forEach(function (e) {
      if (e.reversed) {
        html += '<div class="eh-money-row eh-money-reversed"><div><b>' + esc(e.label) + ' <em class="eh-rev-tag">ফেরত আনা হয়েছে</em></b><span>' + esc(e.date) + ' · ' + money(e.amount) + (e.reversed_at ? ' · ফেরত: ' + esc(e.reversed_at) : '') + '</span>' + (e.note ? '<span>' + esc(e.note) + '</span>' : '') + '</div></div>';
        return;
      }
      var isSalary = e.kind === 'salary';
      html += '<div class="eh-money-row"><div><b>' + esc(e.label) + '</b><span>' + esc(e.date) + ' · ' + money(e.amount) + '</span></div>';
      if (e.can_delete) {
        html += '<button type="button" class="eh-del" data-eh="money-del" data-kind="' + esc(e.kind) + '" data-amount="' + esc(String(e.amount)) + '" data-entry="' + e.id + '" data-stay="' + esc(stay) + '" data-id="' + currentId + '" data-name="' + esc(currentName) + '">' + (isSalary ? 'বেতন ফেরত আনুন' : 'মুছুন') + '</button>';
      } else {
        html += '<span class="eh-locked">বেতন মুছুন</span>';
      }
      html += '</div>';
    });
    return html + '</div>';
  }
  function fillMoneyBox(kind, stay) {
    var box = document.getElementById('ehMoneyBox');
    if (!box) return;
    box.innerHTML = '<p class="eh-meta">এন্ট্রি লোড হচ্ছে…</p>';
    get('money_entries', { id: String(currentId), kind: kind || '' }).then(function (res) {
      if (!document.getElementById('ehMoneyBox')) return;
      if (!res.ok) {
        document.getElementById('ehMoneyBox').innerHTML = '<p class="eh-warn">' + esc(res.error || 'এন্ট্রি পাওয়া যায়নি') + '</p>';
        return;
      }
      document.getElementById('ehMoneyBox').innerHTML = moneyListHtml(res.entries || [], stay || 'salary');
    }).catch(function () {
      var el = document.getElementById('ehMoneyBox');
      if (el) el.innerHTML = '<p class="eh-warn">এন্ট্রি লোড হয়নি।</p>';
    });
  }
  function deleteMoney(btn) {
    var isSalary = (btn.getAttribute('data-kind') || '') === 'salary';
    var reason = '';
    if (isSalary) {
      var amt = btn.getAttribute('data-amount') || '';
      var ans = prompt('ভুলে দেওয়া বেতন ফেরত আনা হবে।\n• ' + (amt ? '৳' + amt + ' ' : '') + 'অ্যাকাউন্টে ফেরত যাবে\n• বেতন আবার বাকি হিসেবে দেখাবে, পরে দিতে পারবেন\n\nকারণ লিখুন (না লিখলেও চলবে), তারপর OK চাপুন:', '');
      if (ans === null) return;
      reason = ans.trim();
    } else if (!confirm('এই টাকার এন্ট্রি মুছে যাবে এবং অ্যাকাউন্টের হিসাব ফিরে আসবে। মুছবেন?')) {
      return;
    }
    btn.disabled = true;
    post('delete_money', {
      id: String(currentId),
      kind: btn.getAttribute('data-kind') || '',
      entry_id: btn.getAttribute('data-entry') || '0',
      reason: reason
    }).then(function (res) {
      if (!res.ok) throw new Error(res.error || 'মুছা যায়নি');
      toastMsg(isSalary ? 'বেতন ফেরত এসেছে, আবার বাকি হয়েছে' : 'মুছে গেছে');
      var stay = btn.getAttribute('data-stay') || 'salary';
      location.href = 'employee_list.php?open=' + encodeURIComponent(stay) + '&emp=' + currentId;
    }).catch(function (e) {
      toastMsg(e.message);
      btn.disabled = false;
    });
  }

  function openPerson(id) {
    var askedId = parseInt(id || '0', 10);
    var askedName = currentName || '';
    var title = askedId ? ('প্রোফাইল · ' + askedName) : 'নতুন কর্মী';
    var fill = function (p) {
      p = p || {};
      if (askedId && askedName) p.name = askedName;
      var uid = p.user_id ? ('#' + p.user_id) : 'এখনো নেই';
      var photo = p.photo_url || '/assets/img/user-placeholder.png';
      var currentPay = p.payment_type === 'daily' ? 'daily' : 'percentage';
      openDraw(title, '<form class="eh-form" id="ehPerson" enctype="multipart/form-data" autocomplete="off">'
        + '<input type="hidden" name="id" value="' + (askedId || p.id || 0) + '">'
        + '<div class="eh-block"><p class="eh-ids">কর্মী ' + esc(p.employee_code || 'নতুন') + ' · লগইন ' + esc(uid) + ' — একই ব্যক্তি</p>'
        + '<div class="eh-photo-edit"><img src="' + esc(photo) + '" alt="" onerror="this.src=\'/assets/img/user-placeholder.png\'">'
        + '<label>ছবি<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label></div></div>'
        + '<div class="eh-block"><b>পরিচিতি</b>'
        + '<label>নাম</label><input name="name" required autocomplete="off" value="' + esc(p.name) + '">'
        + '<div class="row"><div><label>মোবাইল</label><input name="phone" required inputmode="tel" value="' + esc(p.phone) + '"></div>'
        + '<div><label>কর্মী আইডি</label><input name="employee_code" value="' + esc(p.employee_code) + '"></div></div>'
        + '<label>পদবী</label><input name="designation" value="' + esc(p.designation) + '" placeholder="ইঞ্জিনিয়ার / বিক্রয়">'
        + '<label>ঠিকানা</label><input name="address" value="' + esc(p.address) + '">'
        + '<div class="row"><div><label>যোগদান</label><input type="date" name="joining_date" value="' + esc(p.joining_date || ((window.EH_BOOT && EH_BOOT.today) || '')) + '"></div>'
        + '<div><label>অবস্থা</label><select name="status">'
        + opt('active', p.status || 'active') + 'সক্রিয়</option>'
        + opt('inactive', p.status) + 'বন্ধ</option></select></div></div></div>'
        + '<div class="eh-block"><b>কাজের ভূমিকা</b><div class="eh-roles">'
        + '<label><input type="checkbox" name="is_engineer" value="1"' + (Number(p.is_engineer) ? ' checked' : '') + '> ইঞ্জিনিয়ার</label>'
        + '<label><input type="checkbox" name="is_service" value="1"' + (Number(p.is_service) ? ' checked' : '') + '> সেবা</label>'
        + '<label><input type="checkbox" name="is_repair" value="1"' + (Number(p.is_repair) ? ' checked' : '') + '> মেরামত</label>'
        + '<label><input type="checkbox" name="is_sales" value="1"' + (Number(p.is_sales) ? ' checked' : '') + '> বিক্রয়</label></div>'
        + '<p class="eh-meta">ইঞ্জিনিয়ার টিক না থাকলে সার্ভিস তালিকায় যাবেন না। টিক থাকলেই ইঞ্জিনিয়ার।</p></div>'
        + '<div class="eh-block"><b>লগইন — একই প্রোফাইল</b>'
        + '<div class="row"><div><label>ইউজারনেম</label><input name="login_username" autocomplete="username" value="' + esc(p.login_name) + '"></div>'
        + '<div><label>লগইন আইডি</label><input value="' + esc(uid) + '" disabled></div></div>'
        + '<label>নতুন পাসওয়ার্ড</label><input name="login_password" type="password" autocomplete="new-password" placeholder="' + (p.user_id ? 'খালি রাখলে আগেরটাই থাকবে' : 'নতুন লগইনে আবশ্যক') + '">'
        + '<p class="eh-meta">কমপক্ষে ৮ অক্ষর, একটি অক্ষর ও একটি সংখ্যা।</p>'
        + '<div class="row"><div><label>ব্যবহারকারীর পদ</label><select name="login_role">'
        + [['', 'পদ নেই'], ['admin', 'প্রশাসক'], ['manager', 'ব্যবস্থাপক'], ['cashier', 'ক্যাশিয়ার'], ['staff', 'কর্মী'], ['sales', 'বিক্রয়'], ['engineer', 'ইঞ্জিনিয়ার'], ['accountant', 'হিসাবরক্ষক'], ['family', 'পরিবার'], ['viewer', 'শুধু দেখা']].map(function (r) {
          var cur = String(p.login_role || p.user_role || '');
          return '<option value="' + r[0] + '"' + (cur === r[0] ? ' selected' : '') + '>' + r[1] + '</option>';
        }).join('') + '</select></div>'
        + '<div><label>লগইন</label><select name="login_status">'
        + opt('1', String(p.user_id ? (Number(p.login_status) ? '1' : '0') : '1')) + 'চালু</option>'
        + opt('0', String(p.user_id ? (Number(p.login_status) ? '1' : '0') : '1')) + 'বন্ধ</option></select></div></div>'
        + '<input type="hidden" name="user_role" id="ehUserRole" value="' + esc(p.login_role || p.user_role || '') + '">'
        + '<p class="eh-meta">নাম ও মোবাইল কর্মী এবং ব্যবহারকারীতে একই থাকবে। বেতন শুধু এই পেজ থেকে।</p></div>'
        + '<div class="eh-block"><b>বেতন ও নাস্তা</b>'
        + '<label>বেতনের ধরন</label><select name="payment_type">'
        + opt('daily', currentPay) + 'দৈনিক</option>'
        + opt('percentage', currentPay) + 'কাজের বিলের শতাংশ</option></select>'
        + '<div class="row"><div><label>বেতন / দৈনিক রেট</label><input name="salary" type="number" step="0.01" value="' + (p.salary || 0) + '"></div>'
        + '<div><label>ভাগের শতাংশ</label><input name="service_share_percent" type="number" step="0.01" value="' + (p.service_share_percent || 50) + '"></div></div>'
        + '<label>বেতন পরিবর্তন কার্যকর হবে</label><input name="pay_effective_from" type="date" value="' + ((window.EH_BOOT && EH_BOOT.today) || '') + '">'
        + '<label>পরিবর্তনের নোট</label><input name="pay_change_note" placeholder="বেতনের ধরন/রেট না বদলালে ইতিহাসে নতুন সারি হবে না">'
        + '<label>নাস্তা</label><select name="snack_mode">'
        + opt('none', p.snack_mode) + 'নাস্তা নেই</option>'
        + opt('daily', p.snack_mode) + 'দৈনিক</option>'
        + opt('extra', p.snack_mode) + 'অতিরিক্ত</option>'
        + opt('manual', p.snack_mode) + 'হাতের এন্ট্রি</option></select>'
        + '<label>দৈনিক নাস্তার টাকা</label><input name="daily_snack_amount" type="number" step="0.01" value="' + (p.daily_snack_amount || 0) + '">'
        + '</div>'
        + '<button class="eh-submit" type="submit">রাখুন</button></form>');
      var roleSel = document.querySelector('#ehPerson select[name="login_role"]');
      var hiddenRole = document.getElementById('ehUserRole');
      if (roleSel && hiddenRole) {
        roleSel.addEventListener('change', function () { hiddenRole.value = roleSel.value; });
      }
      bindForm('ehPerson', 'save_person');
    };
    if (!id) return fill({});
    get('person', { id: String(askedId) }).then(function (res) {
      if (currentId !== askedId) return;
      if (!res.ok || !res.person || parseInt(res.person.id, 10) !== askedId) {
        openDraw(title, '<p class="eh-warn">' + esc(askedName || 'এই কর্মী') + ' — এই নামের প্রোফাইল মেলেনি। আবার নামের ওপর চাপুন।</p>');
        return;
      }
      fill(res.person || {});
    });
  }

  function opt(v, cur) {
    return '<option value="' + v + '"' + (String(cur || '') === v ? ' selected' : '') + '>';
  }
  function esc(s) {
    return String(s || '').replace(/[&<>"]/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c];
    });
  }

  function openMore() {
    var html = '<div class="eh-actions" style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">'
      + btn('edit', 'সংশোধন') + btn('salary', 'বেতনের ধরন') + btn('snack', 'নাস্তা')
      + btn('advance', 'অগ্রিম') + btn('deposit', 'জমা') + btn('ledger', 'হিসাব')
      + btn('report', 'খতিয়ান');
    if (!window.EH_BOOT || EH_BOOT.canDelete !== false) {
      html += '<div style="grid-column:1/-1">' + btn('delete', 'চিরতরে মুছুন') + '</div>';
    }
    openDraw(currentName, html + '</div>');
  }
  function btn(act, label) {
    var extra = act === 'delete' ? ' class="eh-forever"' : '';
    return '<button type="button"' + extra + ' data-eh="' + act + '" data-id="' + currentId + '" data-name="' + esc(currentName) + '">' + label + '</button>';
  }

  function openDelete() {
    if (window.EH_BOOT && EH_BOOT.canDelete === false) {
      toastMsg('চিরতরে মুছার অনুমতি নেই।');
      return;
    }
    openDraw('চিরতরে মুছুন', '<p class="eh-warn">এই কর্মী, ইউজার লগইন ও ইঞ্জিনিয়ার লাইভ প্রজেক্ট থেকে চিরতরে উঠবে। কোনো তালিকায় আর দেখাবে না। বিক্রয়, সেবা, মেরামত ও বেতনের খাতা থাকবে। ফেরত আনা যাবে না।</p>'
      + '<form class="eh-form" id="ehDel"><input type="hidden" name="id" value="' + currentId + '">'
      + '<button type="button" class="eh-submit" data-eh-close style="background:#e2e8f0;color:#0f172a;margin-bottom:8px;">বাতিল</button>'
      + '<button class="eh-submit danger" type="submit">হ্যাঁ, চিরতরে মুছুন</button></form>');
    bindForm('ehDel', 'delete');
  }

  function openLedger() {
    openDraw('হিসাব · ' + currentName, '<div id="ehLedWait">লোড হচ্ছে…</div>');
    get('ledger', { id: String(currentId), range: 'month' }).then(function (res) {
      if (!res.ok) { document.getElementById('ehLedWait').textContent = res.error || 'হিসাব পাওয়া যায়নি'; return; }
      var rows = res.lines || [];
      var html = '<div class="eh-export"><button type="button" data-print>ছাপুন</button><button type="button" data-csv>ছক নামান</button><button type="button" data-share>পাঠান</button></div>';
      html += '<table class="eh-table"><thead><tr><th>তারিখ</th><th>বিবরণ</th><th>ডেবিট</th><th>ক্রেডিট</th><th>ব্যালেন্স</th></tr></thead><tbody id="ehLedgerRows"></tbody></table>'
        + '<div class="eh-pager" id="ehLedgerPager"></div><p><b>শেষ জের:</b> ' + money(res.closing) + '</p>';
      document.getElementById('ehLedWait').outerHTML = html;
      paginateRows(rows, 'ehLedgerRows', 'ehLedgerPager', function (ln) {
        return '<tr><td>' + ln.date + '</td><td>' + esc(ln.description) + '</td><td>' + (ln.debit ? money(ln.debit) : '') + '</td><td>' + (ln.credit ? money(ln.credit) : '') + '</td><td>' + money(ln.balance) + '</td></tr>';
      }, '<tr><td colspan="5">এই মাসে লেনদেন নেই।</td></tr>', 12);
      bindExport(panel, 'ledger-' + currentId, rows);
    });
  }

  function openReport() {
    var ym = (window.EH_BOOT && EH_BOOT.month) || '';
    var today = (window.EH_BOOT && EH_BOOT.today) || '';
    openDraw('পেশাদার কর্মী স্টেটমেন্ট · ' + currentName,
      '<div class="eh-report-filter"><label>সময়</label><select id="ehReportRange"><option value="today">আজ</option><option value="yesterday">গতকাল</option><option value="week">এই সপ্তাহ</option><option value="month" selected>মাসিক</option><option value="custom">নিজের তারিখ</option></select>'
      + '<label>মাস</label><input type="month" id="ehMonth" value="' + ym + '">'
      + '<label>শুরু</label><input type="date" id="ehReportFrom" value="' + today + '">'
      + '<label>শেষ</label><input type="date" id="ehReportTo" value="' + today + '">'
      + '<button type="button" class="eh-submit eh-report-apply" id="ehReportApply">তারিখ অনুযায়ী দেখুন</button></div>'
      + '<div id="ehRepWait">লোড হচ্ছে…</div>');
    function load() {
      var range = document.getElementById('ehReportRange');
      var month = document.getElementById('ehMonth');
      var from = document.getElementById('ehReportFrom');
      var to = document.getElementById('ehReportTo');
      get('report', {
        id: String(currentId),
        range: range ? range.value : 'month',
        month: month ? month.value : ym,
        from: from ? from.value : '',
        to: to ? to.value : ''
      }).then(function (res) {
        var box = document.getElementById('ehRepWait');
        if (!box) return;
        if (!res.ok) { box.textContent = res.error || 'খতিয়ান পাওয়া যায়নি'; return; }
        var html = '<section class="eh-statement"><div class="eh-statement-head"><div><small>এক ব্যক্তি · এক প্রোফাইল · এক বেতন</small><h3>' + esc(res.employee.name) + '</h3><p>' + esc(res.employee.code) + ' · ' + esc(res.employee.pay_label) + '</p></div><div><b>' + esc(res.from) + '</b><span>থেকে</span><b>' + esc(res.to) + '</b></div></div>';
        html += '<div class="eh-export"><button type="button" data-print>ছাপুন</button><button type="button" data-csv>CSV নামান</button><button type="button" data-share>পাঠান</button></div>';
        var payHistory = (res.pay_history || []).map(function (h) {
          return '<div class="eh-pay-period"><b>' + esc(h.pay_label) + ' · ' + esc(h.rate_label) + '</b>'
            + '<span>' + esc(h.effective_from) + ' থেকে ' + esc(h.effective_to || 'চলমান') + '</span>'
            + (h.note ? '<small>' + esc(h.note) + '</small>' : '') + '</div>';
        }).join('');
        html += '<div class="eh-pay-history"><h3>বেতনের নিয়মের স্টেটমেন্ট</h3>' + (payHistory || '<p class="eh-meta">ইতিহাস নেই।</p>') + '</div>';
        html += '<div class="eh-stats">'
          + kv('উপস্থিত দিন', res.present_days) + kv('অনুপস্থিত', res.absent_days)
          + kv('কাজের মূল্য', money(res.work_value)) + kv('বেতন', money(res.salary))
          + kv('ভাগের টাকা', money(res.commission)) + kv('বোনাস', money(res.bonus))
          + kv('নাস্তা', money(res.snack))
          + kv('অগ্রিম', money(res.advance)) + kv('সমন্বয়', money(res.advance_adjustment))
          + kv('জমা', money(res.deposit)) + kv('উত্তোলন', money(res.withdrawal))
          + kv('শ্রম খরচ', money(res.labour_cost)) + kv('দোকানের অংশ', money(res.shop_contribution))
          + kv('পরিশোধযোগ্য', money(res.final_payable)) + kv('পরিশোধ হয়েছে', money(res.paid_amount))
          + kv('অবশিষ্ট', money(res.outstanding))
          + '</div>';
        html += '<h3 class="eh-section-title">দৈনিক হাজিরা ও বেতন</h3><div class="eh-table-wrap"><table class="eh-table"><thead><tr><th>তারিখ</th><th>অবস্থা</th><th>নির্দিষ্ট বেতন</th><th>কাজ</th><th>ভাগ</th><th>বোনাস</th></tr></thead><tbody id="ehReportDays"></tbody></table></div><div class="eh-pager" id="ehReportDaysPager"></div>'
          + '<h3 class="eh-section-title">গাড়ি, ক্রেতা, ইনভয়েস ও IMEI অনুযায়ী কাজ</h3><div class="eh-table-wrap"><table class="eh-table"><thead><tr><th>তারিখ</th><th>উৎস</th><th>ক্রেতা/মোবাইল</th><th>গাড়ি</th><th>ইনভয়েস/IMEI</th><th>কাজের বিল</th><th>হার</th><th>কর্মীর আয়</th><th>বোনাস</th><th>অবস্থা</th></tr></thead><tbody id="ehReportWorks"></tbody></table></div><div class="eh-pager" id="ehReportWorksPager"></div>';
        var works = res.works || [];
        html += '</section>';
        box.innerHTML = html;
        paginateRows(res.days || [], 'ehReportDays', 'ehReportDaysPager', function (d) {
          return '<tr><td>' + d.date + '</td><td>' + esc(d.note) + '</td><td>' + money(d.salary) + '</td><td>' + money(d.work_value) + '</td><td>' + money(d.commission) + '</td><td>' + money(d.bonus) + '</td></tr>';
        }, '<tr><td colspan="6">এই সময়ে হাজিরা নেই।</td></tr>', 12);
        paginateRows(works, 'ehReportWorks', 'ehReportWorksPager', function (w) {
          var refs = [w.invoice_ref, w.memo_no, w.imei_serial].filter(Boolean).join(' · ');
          return '<tr><td>' + esc(w.work_date) + '</td><td>' + esc((w.source_label || 'কর্মী পেজ') + ' · ' + (w.pay_label || '')) + '</td><td>' + esc([w.customer_name, w.customer_phone].filter(Boolean).join(' · ')) + '</td><td>' + esc([w.vehicle, w.vehicle_no].filter(Boolean).join(' · ')) + '</td><td>' + esc(refs) + '</td><td>' + money(w.work_value) + '</td><td>' + Number(w.statement_percent == null ? (w.employee_percent || 0) : w.statement_percent) + '%</td><td>' + money(w.statement_employee_amount == null ? w.employee_amount : w.statement_employee_amount) + '</td><td>' + money(w.bonus_amount) + '</td><td>' + esc(w.payout_status || 'pending') + '</td></tr>';
        }, '<tr><td colspan="10">এই সময়ে কাজ নেই।</td></tr>', 10);
        bindExport(box, 'worker-statement-' + currentId + '-' + res.from + '-' + res.to, works);
      });
    }
    function syncReportFields() {
      var range = document.getElementById('ehReportRange');
      var month = document.getElementById('ehMonth');
      var from = document.getElementById('ehReportFrom');
      var to = document.getElementById('ehReportTo');
      var value = range ? range.value : 'month';
      if (month) month.disabled = value !== 'month';
      if (from) from.disabled = value !== 'custom';
      if (to) to.disabled = value !== 'custom';
    }
    syncReportFields();
    load();
    var rangeInput = document.getElementById('ehReportRange');
    if (rangeInput) rangeInput.addEventListener('change', syncReportFields);
    var apply = document.getElementById('ehReportApply');
    if (apply) apply.addEventListener('click', load);
  }
  function kv(k, v) {
    return '<div><span>' + k + '</span><b>' + v + '</b></div>';
  }

  function bindForm(id, action) {
    var form = document.getElementById(id);
    if (!form) return;
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var hasFile = !!form.querySelector('input[type=file]');
      var send;
      if (hasFile) {
        var body = new FormData(form);
        body.set('csrf_token', csrf);
        body.set('action', action);
        form.querySelectorAll('input[type=checkbox]').forEach(function (c) {
          if (!c.checked) body.set(c.name, '0');
        });
        send = fetch(api + '?action=' + encodeURIComponent(action), {
          method: 'POST',
          body: body,
          credentials: 'same-origin'
        }).then(function (r) { return r.json(); });
      } else {
        var data = {};
        new FormData(form).forEach(function (v, k) { data[k] = v; });
        form.querySelectorAll('input[type=checkbox]').forEach(function (c) {
          if (!c.checked) data[c.name] = '0';
        });
        send = post(action, data);
      }
      function finishOk(res) {
        toastMsg(res.message || (action === 'delete' ? 'প্রজেক্ট থেকে চিরতরে মুছে গেছে' : 'রাখা হয়েছে'));
        location.reload();
      }
      function handleRes(res) {
        if (res && res.needs_confirm) {
          var ok = window.confirm(res.message || 'আবার সাবমিট করবেন?');
          if (!ok) {
            toastMsg('বাতিল করা হয়েছে।');
            return;
          }
          if (hasFile) {
            var body2 = new FormData(form);
            body2.set('csrf_token', csrf);
            body2.set('action', action);
            body2.set('confirm_repeat', '1');
            form.querySelectorAll('input[type=checkbox]').forEach(function (c) {
              if (!c.checked) body2.set(c.name, '0');
            });
            return fetch(api + '?action=' + encodeURIComponent(action), {
              method: 'POST',
              body: body2,
              credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).then(handleRes);
          }
          var data2 = {};
          new FormData(form).forEach(function (v, k) { data2[k] = v; });
          form.querySelectorAll('input[type=checkbox]').forEach(function (c) {
            if (!c.checked) data2[c.name] = '0';
          });
          data2.confirm_repeat = '1';
          return post(action, data2).then(handleRes);
        }
        if (!res.ok) throw new Error(res.error || 'রাখা যায়নি');
        finishOk(res);
      }
      send.then(handleRes).catch(function (err) { toastMsg(err.message); });
    });
  }

  function bindExport(root, filename, rows) {
    if (!root) return;
    root.onclick = function (e) {
      if (e.target.closest('[data-print]')) {
        window.print();
        return;
      }
      if (e.target.closest('[data-csv]') || e.target.closest('[data-pdf]')) {
        var csv = 'data:text/csv;charset=utf-8,' + encodeURIComponent(JSON.stringify(rows));
        if (rows && rows.length) {
          var keys = Object.keys(rows[0]);
          var body = keys.join(',') + '\n' + rows.map(function (r) {
            return keys.map(function (k) { return '"' + String(r[k] == null ? '' : r[k]).replace(/"/g, '""') + '"'; }).join(',');
          }).join('\n');
          csv = 'data:text/csv;charset=utf-8,' + encodeURIComponent(body);
        }
        var a = document.createElement('a');
        a.href = csv;
        a.download = filename + '.csv';
        a.click();
        return;
      }
      if (e.target.closest('[data-share]')) {
        var memo = document.querySelector('#ehPanel .eh-statement') || document.getElementById('ehPanel');
        if (memo && typeof window.ameMemoCapture === 'function') {
          memo.setAttribute('data-ame-capture', 'clone');
          if (!memo.id) memo.id = 'ehEmployeeMemo';
          window.ameMemoCapture('share', 'employee-' + currentId, memo.id);
        } else {
          toastMsg('PDF তৈরি হয়নি — আবার চেষ্টা করুন');
        }
      }
    };
  }

  var bootOpen = (window.EH_BOOT && EH_BOOT.open) || '';
  var bootEmp = (window.EH_BOOT && EH_BOOT.emp) || 0;
  if (bootOpen === 'new') {
    openPerson(0);
  } else if (bootEmp && bootOpen) {
    currentId = bootEmp;
    var first = document.querySelector('.eh-card[data-id="' + bootEmp + '"]');
    currentName = first ? (first.getAttribute('data-name') || '') : '';
    if (bootOpen === 'work') openWorkList();
    if (bootOpen === 'ledger') openLedger();
    if (bootOpen === 'report') openReport();
    if (bootOpen === 'advance') openAdvance();
    if (bootOpen === 'deposit') openDeposit();
    if (bootOpen === 'salary') openSalary();
    if (bootOpen === 'snack') openSnack();
    if (bootOpen === 'edit') openPerson(bootEmp);
    if (bootOpen === 'more') openMore();
  }
})();
