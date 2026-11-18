/**
 * APONSHOP runtime 8.12
 * Fast device auto-setup + show ID/mobile when a customer is seated.
 */
(function () {
  'use strict';
  if (window.__ameRuntime812) return;
  window.__ameRuntime812 = true;

  var SETUP = 'ameOsSetup812';

  function viewport() {
    var meta = document.querySelector('meta[name="viewport"]');
    if (!meta) {
      meta = document.createElement('meta');
      meta.setAttribute('name', 'viewport');
      document.head.appendChild(meta);
    }
    if (!window.AME_SALE_DESKTOP_SITE) {
      meta.setAttribute('content', 'width=device-width, initial-scale=1, viewport-fit=cover');
    }
    var root = document.documentElement;
    var vv = window.visualViewport;
    var w = Math.round((vv && vv.width) || window.innerWidth || 0);
    var h = Math.round((vv && vv.height) || window.innerHeight || 0);
    root.style.setProperty('--ame-screen-w', w + 'px');
    root.style.setProperty('--ame-vh', (h * 0.01) + 'px');
    if (document.body) {
      document.body.classList.toggle('ame-screen-phone', w <= 760);
      document.body.classList.toggle('ame-screen-tablet', w >= 576 && w < 992);
      document.body.classList.toggle('ame-screen-desktop', w >= 992);
      document.body.classList.toggle('ame-screen-landscape', w > h);
      document.body.classList.add('ame-display-fit');
    }
  }

  function purgeStaleCaches() {
    if (!window.caches || !caches.keys) return;
    var keep = /ame-os-812|ame-pwa/;
    caches.keys().then(function (keys) {
      keys.forEach(function (key) {
        if (!keep.test(key) && /ame-|pwa-|workbox/i.test(key)) {
          caches.delete(key);
        }
      });
    }).catch(function () {});
  }

  function autoSetupOnce() {
    viewport();
    try {
      if (localStorage.getItem(SETUP) === '1') return;
      localStorage.setItem(SETUP, '1');
    } catch (e) {}
    purgeStaleCaches();
  }

  function esc(v) {
    return String(v || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function phoneOf(text) {
    var m = String(text || '').match(/01[0-9]{8,9}/);
    return m ? m[0] : '';
  }

  function infoFrom(el) {
    if (!el) return null;
    var name = (el.getAttribute('data-name') || el.dataset.name || '').trim();
    var code = (el.getAttribute('data-code') || el.dataset.code || '').trim();
    var phone = (el.getAttribute('data-phone') || el.dataset.phone || el.getAttribute('data-mobile') || '').trim();
    if (el.tagName === 'OPTION') {
      name = name || (el.dataset.name || '').trim();
      if (!name) {
        name = String(el.textContent || '').split(/[-·|]/)[0].trim();
      }
      code = code || (el.dataset.code || '').trim();
      phone = phone || (el.dataset.phone || '').trim() || phoneOf(el.textContent);
      if (!code) {
        var m = String(el.textContent || '').match(/(?:CUS-|ID:?\s*)([A-Za-z0-9\/_-]+)/i);
        if (m) code = m[1];
      }
    }
    if (!name && !code && !phone) return null;
    return { name: name, code: code, phone: phone };
  }

  function hostFor(el) {
    return (
      el.closest('.pos-customer-picker') ||
      el.closest('.cus-search-wrap') ||
      el.closest('.customer-search-wrap') ||
      el.closest('.cpay-search') ||
      el.closest('.search-box') ||
      el.parentElement
    );
  }

  function paintSeat(anchor, info) {
    if (!anchor || !info) return;
    var host = hostFor(anchor);
    if (!host) return;
    var card = host.parentElement
      ? host.parentElement.querySelector(':scope > .ame-cus-seat')
      : null;
    if (!card) {
      card = host.querySelector(':scope > .ame-cus-seat');
    }
    if (!card) {
      card = document.createElement('div');
      card.className = 'ame-cus-seat';
      if (host.parentElement && host.nextSibling) {
        host.parentElement.insertBefore(card, host.nextSibling);
      } else if (host.parentElement) {
        host.parentElement.appendChild(card);
      } else {
        host.appendChild(card);
      }
    }
    card.innerHTML =
      (info.name ? '<strong>' + esc(info.name) + '</strong>' : '') +
      '<span>আইডি: ' + esc(info.code || '—') + '</span>' +
      '<span>মোবাইল: ' + esc(info.phone || '—') + '</span>' +
      '<em>বসানো কাস্টমার</em>';
  }

  function fromSelect(sel) {
    if (!sel || sel.tagName !== 'SELECT' || !sel.options) return;
    var val = String(sel.value || '');
    if (!val || val === '0') return;
    var opt = sel.options[sel.selectedIndex];
    if (!opt) return;
    paintSeat(sel, infoFrom(opt));
  }

  function bindSelects() {
    var nodes = document.querySelectorAll(
      'select#customer_id, select#customerSelect, select#wsCustomer, select[name="customer_id"]'
    );
    for (var i = 0; i < nodes.length; i++) {
      if (nodes[i].tagName !== 'SELECT') continue;
      if (nodes[i].getAttribute('data-ame-seat') === '1') continue;
      nodes[i].setAttribute('data-ame-seat', '1');
      nodes[i].addEventListener('change', function () {
        fromSelect(this);
      });
      fromSelect(nodes[i]);
    }
  }

  document.addEventListener(
    'click',
    function (ev) {
      var item = ev.target && ev.target.closest
        ? ev.target.closest('.cus-suggest-item, .cpay-result, .sr-result-item[data-id], button[data-id][data-name], [data-ame-seat-src]')
        : null;
      if (!item) return;
      // বকেয়া নোটিফিকেশনের "সংশোধন" বাটনে customer-seat কার্ড দরকার নেই।
      // এটি edit modal-এর আগে capture phase-এ চলত বলে ডান পাশে দ্বিতীয় ছোট
      // পপআপ থেকে যেত। ওই action-এ শুধু মাঝের edit modal-ই খুলবে।
      if (item.hasAttribute('data-due-edit') || item.closest('[data-due-edit]')) return;
      if (item.hasAttribute('data-eh') || item.closest('[data-eh], .eh, body.ame-emp-hub')) return;
      var info = infoFrom(item);
      if (!info) {
        var id = item.getAttribute('data-id');
        var sel = document.getElementById('customer_id');
        if (sel && id) {
          var opt = sel.querySelector('option[value="' + id + '"]');
          info = infoFrom(opt);
        }
      }
      if (info) paintSeat(item, info);
    },
    true
  );

  function boot() {
    try {
      autoSetupOnce();
      bindSelects();
    } catch (e) {}
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  window.addEventListener('resize', viewport, { passive: true });
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', viewport, { passive: true });
  }
  window.addEventListener('orientationchange', function () {
    setTimeout(viewport, 60);
  });
})();
