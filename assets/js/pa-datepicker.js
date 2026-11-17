/* পার্টনার হিসাব: বাংলা তারিখ/মাস বাছাই (মোবাইল + ডেস্কটপ) */
(function () {
  'use strict';

  function boot() {
    var root = document.querySelector('.pa-page');
    if (!root || root.getAttribute('data-pa-dp') === '1') return;
    root.setAttribute('data-pa-dp', '1');
    init(root);
  }

  function init(root) {

  var MONTHS = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
  var SHORT = ['জানু', 'ফেব্রু', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টে', 'অক্টো', 'নভে', 'ডিসে'];
  var DAYS = ['র', 'সো', 'ম', 'বু', 'বৃ', 'শু', 'শ'];
  var DIGITS = '০১২৩৪৫৬৭৮৯';

  function bn(n) {
    return String(n).replace(/\d/g, function (d) { return DIGITS.charAt(+d); });
  }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function esc(s) {
    return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; });
  }
  function today() {
    var d = new Date();
    return { y: d.getFullYear(), m: d.getMonth() + 1, d: d.getDate() };
  }
  function parse(value, kind) {
    var m = kind === 'month'
      ? /^(\d{4})-(\d{2})$/.exec(value || '')
      : /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
    if (!m) return null;
    return { y: +m[1], m: +m[2], d: kind === 'month' ? 1 : +m[3] };
  }
  function label(p, kind) {
    if (!p) return '';
    return kind === 'month'
      ? MONTHS[p.m - 1] + ' ' + bn(p.y)
      : bn(p.d) + ' ' + MONTHS[p.m - 1] + ' ' + bn(p.y);
  }

  var sheet = null;
  var state = null;

  function close() {
    if (state && state.btn) {
      state.btn.setAttribute('aria-expanded', 'false');
    }
    if (sheet) { sheet.parentNode.removeChild(sheet); sheet = null; }
    document.removeEventListener('keydown', onKey, true);
    document.body.classList.remove('pa-dp-open');
    state = null;
  }
  function onKey(e) {
    if (e.key === 'Escape') { e.preventDefault(); close(); }
  }

  function commit(p) {
    var st = state;
    if (!st) return;
    var input = st.input;
    var val = st.kind === 'month' ? (p.y + '-' + pad(p.m)) : (p.y + '-' + pad(p.m) + '-' + pad(p.d));
    var changed = input.value !== val;
    input.value = val;
    st.btn.querySelector('.pa-dp-text').textContent = label(p, st.kind);
    st.btn.classList.remove('is-empty');
    close();
    input.dispatchEvent(new Event('change', { bubbles: true }));
    var form = input.form;
    if (form && st.kind === 'month' && input.name === 'month' && form.classList.contains('pa-month-form') && changed) {
      if (typeof form.requestSubmit === 'function') form.requestSubmit(); else form.submit();
    }
  }
  function clearValue() {
    var st = state;
    if (!st) return;
    st.input.value = '';
    st.btn.querySelector('.pa-dp-text').textContent = st.placeholder;
    st.btn.classList.add('is-empty');
    close();
  }

  function render() {
    var st = state;
    var t = today();
    var sel = parse(st.input.value, st.kind);
    var html = '';
    if (st.view === 'months') {
      html += '<div class="pa-dp-head"><button type="button" class="pa-dp-nav" data-nav="-1" aria-label="আগের বছর">‹</button>'
        + '<span class="pa-dp-title">' + bn(st.y) + '</span>'
        + '<button type="button" class="pa-dp-nav" data-nav="1" aria-label="পরের বছর">›</button></div>';
      html += '<div class="pa-dp-months">';
      for (var i = 1; i <= 12; i++) {
        var cls = 'pa-dp-m';
        if (sel && sel.y === st.y && sel.m === i) cls += ' is-sel';
        if (t.y === st.y && t.m === i) cls += ' is-today';
        html += '<button type="button" class="' + cls + '" data-m="' + i + '">' + SHORT[i - 1] + '</button>';
      }
      html += '</div>';
    } else {
      html += '<div class="pa-dp-head"><button type="button" class="pa-dp-nav" data-nav="-1" aria-label="আগের মাস">‹</button>'
        + '<button type="button" class="pa-dp-title is-link" data-act="months">' + MONTHS[st.m - 1] + ' ' + bn(st.y) + ' ▾</button>'
        + '<button type="button" class="pa-dp-nav" data-nav="1" aria-label="পরের মাস">›</button></div>';
      html += '<div class="pa-dp-week">';
      DAYS.forEach(function (d) { html += '<span>' + d + '</span>'; });
      html += '</div><div class="pa-dp-days">';
      var first = new Date(st.y, st.m - 1, 1).getDay();
      var total = new Date(st.y, st.m, 0).getDate();
      for (var b = 0; b < first; b++) html += '<span class="pa-dp-blank"></span>';
      for (var d = 1; d <= total; d++) {
        var c = 'pa-dp-d';
        if (sel && sel.y === st.y && sel.m === st.m && sel.d === d) c += ' is-sel';
        if (t.y === st.y && t.m === st.m && t.d === d) c += ' is-today';
        html += '<button type="button" class="' + c + '" data-d="' + d + '">' + bn(d) + '</button>';
      }
      html += '</div>';
    }
    var foot = '<div class="pa-dp-foot">';
    foot += '<button type="button" class="pa-dp-act" data-act="today">' + (st.kind === 'month' ? 'এই মাস' : 'আজ') + '</button>';
    if (st.clearable) foot += '<button type="button" class="pa-dp-act" data-act="clear">মুছুন</button>';
    foot += '<button type="button" class="pa-dp-act pa-dp-close" data-act="cancel">বন্ধ</button></div>';
    st.body.innerHTML = html + foot;
  }

  function open(input, btn, kind, placeholder, clearable) {
    close();
    var sel = parse(input.value, kind) || today();
    state = {
      input: input, btn: btn, kind: kind, placeholder: placeholder, clearable: clearable,
      y: sel.y, m: sel.m, view: kind === 'month' ? 'months' : 'days'
    };
    sheet = document.createElement('div');
    sheet.className = 'pa-dp-overlay';
    sheet.innerHTML = '<div class="pa-dp-card" role="dialog" aria-modal="true">'
      + '<div class="pa-dp-top"><span>' + (kind === 'month' ? 'মাস বাছুন' : 'তারিখ বাছুন') + '</span>'
      + '<button type="button" class="pa-dp-x" data-act="cancel" aria-label="বন্ধ">×</button></div>'
      + '<div class="pa-dp-body"></div></div>';
    document.body.appendChild(sheet);
    document.body.classList.add('pa-dp-open');
    state.body = sheet.querySelector('.pa-dp-body');
    render();
    document.addEventListener('keydown', onKey, true);
    sheet.addEventListener('click', function (e) {
      var st = state;
      if (!st) return;
      if (e.target === sheet) { close(); return; }
      var el = e.target.closest('button');
      if (!el) return;
      var nav = el.getAttribute('data-nav');
      var act = el.getAttribute('data-act');
      if (nav) {
        var step = +nav;
        if (st.view === 'months') {
          st.y += step;
        } else {
          st.m += step;
          if (st.m < 1) { st.m = 12; st.y--; }
          if (st.m > 12) { st.m = 1; st.y++; }
        }
        render();
      } else if (act === 'months') {
        st.view = 'months';
        render();
      } else if (act === 'cancel') {
        close();
      } else if (act === 'clear') {
        clearValue();
      } else if (act === 'today') {
        var t = today();
        commit({ y: t.y, m: t.m, d: t.d });
      } else if (el.hasAttribute('data-m')) {
        st.m = +el.getAttribute('data-m');
        if (st.kind === 'month') {
          commit({ y: st.y, m: st.m, d: 1 });
        } else {
          st.view = 'days';
          render();
        }
      } else if (el.hasAttribute('data-d')) {
        commit({ y: st.y, m: st.m, d: +el.getAttribute('data-d') });
      }
    });
  }

  function normValue(raw, kind) {
    var v = String(raw || '').trim();
    if (kind === 'date' && /^\d{4}-\d{2}-\d{2}/.test(v)) {
      return v.slice(0, 10);
    }
    if (kind === 'month' && /^\d{4}-\d{2}/.test(v)) {
      return v.slice(0, 7);
    }
    return v;
  }

  function enhance(input) {
    if (input.getAttribute('data-pa-dp-done') === '1') return;
    var kind = input.type;
    if (kind !== 'date' && kind !== 'month') return;
    input.setAttribute('data-pa-dp-done', '1');
    input.value = normValue(input.value, kind);
    var required = input.required;
    var placeholder = kind === 'month' ? 'মাস বাছুন' : 'তারিখ বাছুন';
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pa-dp-btn' + (input.value ? '' : ' is-empty');
    btn.setAttribute('aria-haspopup', 'dialog');
    btn.setAttribute('aria-expanded', 'false');
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M7 2h2v2h6V2h2v2h2a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2V2zm12 8H5v9h14v-9zM5 6v2h14V6H5z"/></svg>'
      + '<span class="pa-dp-text">' + esc(input.value ? label(parse(input.value, kind), kind) : placeholder) + '</span>'
      + '<span class="pa-dp-chev" aria-hidden="true">▾</span>';
    var wrap = document.createElement('div');
    wrap.className = 'pa-dp-field';
    var host = input.parentNode;
    host.insertBefore(wrap, input);
    wrap.appendChild(input);
    wrap.appendChild(btn);
    input.required = false;
    input.type = 'hidden';
    input.setAttribute('autocomplete', 'off');
    function triggerOpen(e) {
      if (e && e.cancelable) e.preventDefault();
      if (e) e.stopPropagation();
      btn.setAttribute('aria-expanded', 'true');
      open(input, btn, kind, placeholder, !required && kind === 'date');
    }
    wrap.addEventListener('click', function (e) {
      if (e.target === input) return;
      triggerOpen(e);
    });
    if (host && host.tagName === 'LABEL') {
      host.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          triggerOpen(e);
        }
      });
    }
    if (input.form && required) {
      input.form.addEventListener('submit', function (e) {
        if (!input.value) {
          e.preventDefault();
          btn.classList.add('is-bad');
          triggerOpen(e);
        }
      });
    }
  }

    var inputs = root.querySelectorAll('input[type="date"], input[type="month"]');
    for (var i = 0; i < inputs.length; i++) enhance(inputs[i]);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
