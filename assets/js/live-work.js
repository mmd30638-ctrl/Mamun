(function () {
  var root = document.getElementById('lwRoot');
  if (!root) return;
  var data = window.LW_BOOT || {};
  var timer = null;
  var selected = parseInt((document.getElementById('lwUser') || {}).value || '0', 10) || 0;

  function money(n) {
    if (n === null || n === undefined || n === '') return '—';
    return '৳' + Number(n).toLocaleString('en-BD', { maximumFractionDigits: 0 });
  }
  function esc(s) {
    return String(s || '').replace(/[&<>"]/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c];
    });
  }
  function mode() {
    var on = document.querySelector('.lw-chip.on');
    return on ? on.getAttribute('data-mode') : 'live';
  }
  function params() {
    var q = new URLSearchParams();
    q.set('mode', mode());
    q.set('date', (document.getElementById('lwDate') || {}).value || '');
    q.set('month', (document.getElementById('lwMonth') || {}).value || '');
    q.set('year', (document.getElementById('lwYear') || {}).value || '');
    q.set('user', String(selected || 0));
    return q;
  }
  function paint(pack) {
    data = pack || data;
    var span = data.span || {};
    var k = data.kpis || {};
    var dateEl = document.getElementById('lwDateLabel');
    var nowEl = document.getElementById('lwNow');
    var tag = document.getElementById('lwLiveTag');
    var dot = document.querySelector('.lw-dot');
    if (dateEl) dateEl.textContent = span.from === span.to ? span.from : ((span.from || '') + ' → ' + (span.to || ''));
    if (nowEl) nowEl.textContent = data.now_clock || '';
    if (tag) {
      tag.textContent = span.live ? 'লাইভ' : 'স্থির দৃশ্য';
      tag.classList.toggle('is-live', !!span.live);
    }
    if (dot) dot.classList.toggle('on', !!span.live);
    var kpis = document.getElementById('lwKpis');
    if (kpis) {
      kpis.innerHTML =
        kpi('লাইভ এখন', k.live, true) +
        kpi('কাজ করছেন', k.active) +
        kpi('ব্যবহারকারী', k.people) +
        kpi('মোট কাজ', k.actions) +
        kpi('বিক্রয়', k.sales_n) +
        kpi('বিক্রয় টাকা', money(k.sales_amt));
    }
    paintPeople(data.people || []);
    paintChart(data);
    paintFeed(data.events || []);
  }
  function kpi(label, value, live) {
    return '<div class="lw-kpi' + (live ? ' is-live' : '') + '"><span>' + esc(label) + '</span><b>' + esc(String(value == null ? '0' : value)) + '</b></div>';
  }
  function paintPeople(rows) {
    var box = document.getElementById('lwPeople');
    if (!box) return;
    if (!rows.length) {
      box.innerHTML = '<div class="lw-empty">কোনো ব্যবহারকারী নেই।</div>';
      return;
    }
    box.innerHTML = rows.map(function (p) {
      var ava = p.photo
        ? '<span class="lw-ava"><img src="' + esc(p.photo) + '" alt=""></span>'
        : '<span class="lw-ava">' + esc(p.initials) + '</span>';
      var pill = p.live
        ? '<span class="lw-pill live">লাইভ</span>'
        : (p.n ? '<span class="lw-pill">' + esc(p.last_clock || 'কাজ') + '</span>' : '<span class="lw-pill">নিশ্চুপ</span>');
      return '<button type="button" class="lw-person' + (selected === p.id ? ' on' : '') + '" data-uid="' + p.id + '">'
        + ava
        + '<div><h3>' + esc(p.name) + '</h3><small>' + esc(p.label) + (p.designation ? ' · ' + esc(p.designation) : '') + '</small></div>'
        + '<div class="lw-meta"><b>' + p.n + '</b>' + pill + '</div></button>';
    }).join('');
  }
  function paintChart(pack) {
    var span = pack.span || {};
    var title = document.getElementById('lwChartTitle');
    var hint = document.getElementById('lwChartHint');
    var bars = document.getElementById('lwBars');
    var rows = pack.hours || [];
    if (span.chart === 'days') {
      if (title) title.textContent = 'দিনের ছন্দ';
      if (hint) hint.textContent = 'কোন দিনে কত কাজ';
      rows = pack.days || [];
    } else if (span.chart === 'months') {
      if (title) title.textContent = 'বছরের ছন্দ';
      if (hint) hint.textContent = 'কোন মাসে কত কাজ';
      rows = pack.months || [];
    } else {
      if (title) title.textContent = 'ঘণ্টার ছন্দ';
      if (hint) hint.textContent = 'সকাল থেকে রাত — কে কখন কাজ করলো';
    }
    if (!bars) return;
    if (!rows.length) {
      bars.innerHTML = '<div class="lw-empty">এই সময়ে কাজ নেই।</div>';
      return;
    }
    bars.innerHTML = rows.map(function (r) {
      var h = Math.max(6, Number(r.pct || 0));
      return '<div class="lw-bar"><i style="height:' + h + '%"></i><em>' + esc(r.label) + '</em></div>';
    }).join('');
  }
  function paintFeed(rows) {
    var box = document.getElementById('lwFeed');
    if (!box) return;
    if (!rows.length) {
      box.innerHTML = '<li class="lw-empty">এই ফিল্টারে কোনো কাজ নেই।</li>';
      return;
    }
    box.innerHTML = rows.map(function (e) {
      var extra = [e.module, e.detail].filter(Boolean).join(' · ');
      var amt = e.amount ? ' · ' + money(e.amount) : '';
      return '<li class="lw-item"><time>' + esc(e.clock) + '</time><div>'
        + '<b class="lw-type-' + esc(e.type) + '">' + esc(e.who) + ' — ' + esc(e.title) + '</b>'
        + '<span>' + esc(extra + amt) + '</span></div></li>';
    }).join('');
  }
  function load() {
    return fetch('/modules/reports/live_work_api.php?' + params().toString(), { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (pack) {
        if (!pack || !pack.ok) return;
        paint(pack);
      })
      .catch(function () {});
  }
  function pulse() {
    if (timer) clearInterval(timer);
    timer = null;
    if (mode() === 'live' && !document.hidden) {
      timer = setInterval(load, 8000);
    }
  }
  document.getElementById('lwModes').addEventListener('click', function (e) {
    var b = e.target.closest('[data-mode]');
    if (!b) return;
    document.querySelectorAll('.lw-chip').forEach(function (c) { c.classList.toggle('on', c === b); });
    load();
    pulse();
  });
  ['lwDate', 'lwMonth', 'lwYear', 'lwUser'].forEach(function (id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('change', function () {
      if (id === 'lwUser') selected = parseInt(el.value || '0', 10) || 0;
      if (id === 'lwDate') {
        document.querySelectorAll('.lw-chip').forEach(function (c) { c.classList.toggle('on', c.getAttribute('data-mode') === 'date'); });
      }
      if (id === 'lwMonth') {
        document.querySelectorAll('.lw-chip').forEach(function (c) { c.classList.toggle('on', c.getAttribute('data-mode') === 'month'); });
      }
      if (id === 'lwYear') {
        document.querySelectorAll('.lw-chip').forEach(function (c) { c.classList.toggle('on', c.getAttribute('data-mode') === 'year'); });
      }
    });
  });
  var apply = document.getElementById('lwApply');
  if (apply) {
    apply.addEventListener('click', function () {
      load();
      pulse();
    });
  }
  document.getElementById('lwPeople').addEventListener('click', function (e) {
    var b = e.target.closest('[data-uid]');
    if (!b) return;
    var id = parseInt(b.getAttribute('data-uid') || '0', 10) || 0;
    selected = selected === id ? 0 : id;
    var sel = document.getElementById('lwUser');
    if (sel) sel.value = String(selected);
    load();
  });
  document.addEventListener('visibilitychange', function () {
    if (document.hidden && timer) {
      clearInterval(timer);
      timer = null;
    } else {
      pulse();
      load();
    }
  });
  paint(data);
  pulse();
})();
