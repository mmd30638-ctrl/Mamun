/**
 * SM40 — আলাদা পপআপ উইন্ডোতে খোলে যাতে হোম/দোকান ট্যাবে কাজ করতে গিয়েও
 * SM40-এ চালু অডিও/ভিডিও ব্রাউজার সেই উইন্ডোতে চলতে পারে (same-origin সীমা বাইরে নিয়ন্ত্রণ নেই)।
 */
(function () {
  'use strict';

  var WIN_NAME = 'ame_sm40_player';
  var STORAGE_KEY = 'ame_sm40_popup_v1';
  var popupRef = null;

  function isHome() {
    return document.body && document.body.classList.contains('ame-home-gallery');
  }

  function entryUrl(link) {
    var u = link && link.getAttribute('data-sm40-url');
    return u && String(u).indexOf('https://sm40.com') === 0 ? u : 'https://sm40.com/welcome?lang=bengali';
  }

  function markOpened() {
    try {
      sessionStorage.setItem(STORAGE_KEY, String(Date.now()));
    } catch (e) { /* ignore */ }
    showPill();
  }

  function wasOpened() {
    try {
      return sessionStorage.getItem(STORAGE_KEY) !== null;
    } catch (e) {
      return false;
    }
  }

  function popupFeatures() {
    var w = 420;
    var h = Math.min(820, Math.max(560, window.screen.availHeight - 80));
    var left = Math.max(0, window.screenX + window.outerWidth - w - 24);
    var top = Math.max(0, window.screenY + 48);
    return 'popup=yes,width=' + w + ',height=' + h + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes';
  }

  function openSm40(url) {
    url = url || 'https://sm40.com/welcome?lang=bengali';
    try {
      popupRef = window.open(url, WIN_NAME, popupFeatures());
    } catch (e) {
      popupRef = null;
    }
    if (!popupRef || popupRef.closed) {
      window.open(url, '_blank');
      return false;
    }
    try {
      popupRef.focus();
    } catch (e2) { /* ignore */ }
    markOpened();
    return true;
  }

  function focusSm40() {
    if (popupRef && !popupRef.closed) {
      try {
        popupRef.focus();
        return;
      } catch (e) { /* ignore */ }
    }
    openSm40();
  }

  function showPill() {
    if (!isHome()) {
      return;
    }
    var pill = document.getElementById('ameSm40ListenPill');
    if (!pill) {
      pill = document.createElement('div');
      pill.id = 'ameSm40ListenPill';
      pill.className = 'ame-sm40-listen-pill';
      pill.setAttribute('role', 'status');
      pill.innerHTML =
        '<button type="button" class="ame-sm40-listen-btn" id="ameSm40ListenBtn">'
        + '<span class="ame-sm40-listen-ic" aria-hidden="true">🎵</span>'
        + '<span class="ame-sm40-listen-txt">SM40 চালু — শুনতে উইন্ডোতে ফিরে যান</span>'
        + '</button>'
        + '<p class="ame-sm40-listen-hint">দোকান অ্যাপে কাজ চালিয়ে যান; অডিও SM40 উইন্ডোতে বাজবে</p>';
      document.body.appendChild(pill);
      var btn = document.getElementById('ameSm40ListenBtn');
      if (btn) {
        btn.addEventListener('click', function () {
          focusSm40();
        });
      }
    }
    pill.hidden = false;
  }

  function bindLink() {
    var link = document.getElementById('ameSm40HomeLink');
    if (!link) {
      return;
    }
    link.addEventListener('click', function (ev) {
      if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey || ev.button === 1) {
        return;
      }
      ev.preventDefault();
      openSm40(entryUrl(link));
    });
  }

  function boot() {
    bindLink();
    if (wasOpened()) {
      showPill();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();