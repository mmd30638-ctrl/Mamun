/* 10.98.891 — দ্রুত ডুপ্লিকেট কনফার্ম (রঙিন মোডাল) */
(function (global) {
  'use strict';

  var RAPID_WINDOW_SEC = (global.AME_RAPID_WINDOW_SEC && global.AME_RAPID_WINDOW_SEC > 0)
    ? parseInt(global.AME_RAPID_WINDOW_SEC, 10)
    : 15;

  var STYLE_ID = 'ame-resubmit-guard-style';

  function ensureStyles() {
    if (document.getElementById(STYLE_ID)) return;
    var link = document.createElement('link');
    link.id = STYLE_ID;
    link.rel = 'stylesheet';
    link.href = '/assets/css/ame-resubmit-guard.css?v=' + (global.AME_ASSET_VERSION || '1');
    document.head.appendChild(link);
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function formatSeconds(sec) {
    sec = Math.max(0, parseInt(sec, 10) || 0);
    if (sec > RAPID_WINDOW_SEC) return 'কিছুক্ষণ আগে';
    if (sec < 2) return 'এইমাত্র';
    return sec + ' সেকেন্ড আগে';
  }

  function linesFromMessage(message) {
    return String(message || '').split('\n').filter(function (l) { return l.trim() !== ''; });
  }

  function buildBody(message, meta) {
    meta = meta || {};
    var html = '';
    if (meta.kind === 'sale_product' || meta.code === 'AME_SALE_RAPID_PRODUCT') {
      var w = (meta.warnings && meta.warnings[0]) || {};
      html += '<div class="ame-rsg-pill ame-rsg-pill--product">পণ্য বিক্রয়</div>';
      html += '<p class="ame-rsg-lead">' + esc(w.product_name || 'পণ্য') + '</p>';
      html += '<dl class="ame-rsg-dl">';
      html += '<div><dt>টাকা</dt><dd>৳' + esc(Number(w.amount || 0).toFixed(2)) + '</dd></div>';
      if (w.qty > 0) {
        html += '<div><dt>পরিমাণ</dt><dd>' + esc(w.qty) + '</dd></div>';
      }
      html += '</dl>';
      html += '<p class="ame-rsg-meta">আগের মেমো: <strong>' + esc(w.invoice_number || ('#' + (w.sale_id || ''))) + '</strong></p>';
      html += '<p class="ame-rsg-when">⏱ ' + esc(formatSeconds(w.seconds_ago)) + '</p>';
    } else if (
      meta.kind === 'sale_service'
      || meta.code === 'AME_SALE_RAPID_SERVICE'
      || (meta.warnings && meta.warnings[0] && (meta.warnings[0].worker_name || meta.warnings[0].engineer_id))
    ) {
      var s = (meta.warnings && meta.warnings[0]) || {};
      html += '<div class="ame-rsg-pill ame-rsg-pill--work">কাজের বিল</div>';
      html += '<p class="ame-rsg-lead">' + esc(s.worker_name || 'কর্মী') + '</p>';
      html += '<dl class="ame-rsg-dl">';
      html += '<div><dt>কাজ</dt><dd>«' + esc(s.service_type || 'কাজের বিল') + '»</dd></div>';
      html += '<div><dt>টাকা</dt><dd>৳' + esc(Number(s.amount || 0).toFixed(2)) + '</dd></div>';
      html += '</dl>';
      html += '<p class="ame-rsg-meta">আগের মেমো: <strong>' + esc(s.invoice_number || ('#' + (s.sale_id || ''))) + '</strong></p>';
      html += '<p class="ame-rsg-when">⏱ ' + esc(formatSeconds(s.seconds_ago)) + '</p>';
    } else if (meta.preview) {
      var p = meta.preview;
      html += '<div class="ame-rsg-pill ame-rsg-pill--work">কর্মীর কাজ</div>';
      html += '<p class="ame-rsg-lead">' + esc(p.worker_name || '') + '</p>';
      html += '<dl class="ame-rsg-dl">';
      html += '<div><dt>কাজ</dt><dd>«' + esc(p.service_type || '') + '»</dd></div>';
      html += '<div><dt>টাকা</dt><dd>৳' + esc(Number(p.work_value || 0).toFixed(2)) + '</dd></div>';
      html += '</dl>';
    } else {
      linesFromMessage(message).forEach(function (line) {
        html += '<p class="ame-rsg-line">' + esc(line) + '</p>';
      });
    }
    html += '<p class="ame-rsg-ask">এখন আবার সাবমিট করবেন, নাকি বাতিল করবেন?</p>';
    return html;
  }

  function ask(message, meta) {
    ensureStyles();
    return new Promise(function (resolve) {
      var root = document.createElement('div');
      root.className = 'ame-rsg-root';
      root.setAttribute('role', 'presentation');
      root.innerHTML =
        '<div class="ame-rsg-backdrop" data-rsg-close></div>' +
        '<div class="ame-rsg-card" role="alertdialog" aria-modal="true" aria-labelledby="ame-rsg-title">' +
        '<div class="ame-rsg-glow"></div>' +
        '<header class="ame-rsg-head">' +
        '<span class="ame-rsg-icon" aria-hidden="true">⚡</span>' +
        '<div><h2 id="ame-rsg-title">দ্রুত দ্বিতীয় সাবমিট</h2>' +
        '<p class="ame-rsg-sub">' + RAPID_WINDOW_SEC + ' সেকেন্ডের মধ্যে একই তথ্য পুনরায় পাঠানো হয়েছে</p></div>' +
        '</header>' +
        '<div class="ame-rsg-body">' + buildBody(message, meta || {}) + '</div>' +
        '<div class="ame-rsg-actions">' +
        '<button type="button" class="ame-rsg-btn ame-rsg-btn--ghost" data-rsg-no>বাতিল</button>' +
        '<button type="button" class="ame-rsg-btn ame-rsg-btn--ok" data-rsg-yes>আবার সাবমিট</button>' +
        '</div>' +
        '</div>';
      document.body.appendChild(root);
      document.body.classList.add('ame-rsg-open');

      function done(ok) {
        root.remove();
        document.body.classList.remove('ame-rsg-open');
        resolve(!!ok);
      }
      var yesBtn = root.querySelector('[data-rsg-yes]');
      if (yesBtn) yesBtn.focus();
      root.addEventListener('click', function (ev) {
        if (ev.target.closest('[data-rsg-yes]')) done(true);
        if (ev.target.closest('[data-rsg-no]') || ev.target.closest('[data-rsg-close]')) done(false);
      });
      root.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') done(false);
      });
    });
  }

  function askFromServer(result) {
    if (!result) return Promise.resolve(false);
    return ask(result.message, {
      code: result.code,
      warnings: result.warnings,
      preview: result.preview
    });
  }

  global.AmeResubmitGuard = {
    ask: ask,
    askFromServer: askFromServer,
    formatSeconds: formatSeconds
  };
})(typeof window !== 'undefined' ? window : this);