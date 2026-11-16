/**
 * SM40 — নামযুক্ত উইন্ডো/ট্যাবে খোলে; দোকানের যেকোনো পেজে ভেসে থাকা শর্টকাট দিয়ে
 * SM40-এ ফিরে যাওয়া যায় (ডেস্কটপ পপআপ + মোবাইল ট্যাব)।
 */
(function () {
  'use strict';

  var WIN_NAME = 'ame_sm40_player';
  var SESSION_KEY = 'ame_sm40_popup_v1';
  var LOCAL_KEY = 'ame_sm40_popup_v1';
  var popupRef = null;
  var lastUrl = '/sm40_listen.php';

  /** ফোন, ট্যাবলেট, টাচ ল্যাপটপ — কম্প্যাক্ট শর্টকাট; ডেস্কটপ/ল্যাপটপ মাউস — নিচে বড় বার */
  function useCompactDock() {
    try {
      if (window.matchMedia('(max-width: 900px)').matches) {
        return true;
      }
      if (window.matchMedia('(pointer: coarse)').matches) {
        return true;
      }
      if (window.matchMedia('(hover: none)').matches) {
        return true;
      }
    } catch (e) { /* ignore */ }
    return false;
  }

  function entryUrl(link) {
    var u = link && link.getAttribute('data-sm40-url');
    if (!u) {
      return lastUrl;
    }
  if (String(u).charAt(0) === '/' || String(u).indexOf('https://sm40.com') === 0) {
      return u;
    }
    return lastUrl;
  }

  function markOpened() {
    var ts = String(Date.now());
    try {
      sessionStorage.setItem(SESSION_KEY, ts);
    } catch (e) { /* ignore */ }
    try {
      localStorage.setItem(LOCAL_KEY, ts);
    } catch (e2) { /* ignore */ }
    try {
      document.body.classList.add('ame-sm40-dock-on');
    } catch (e3) { /* ignore */ }
    showDock();
    syncHdrLife();
  }

  function syncHdrLife() {
    var link = document.getElementById('ameSm40HomeLink');
    if (!link) {
      return;
    }
    var on = false;
    try {
      on = sessionStorage.getItem(SESSION_KEY) !== null || localStorage.getItem(LOCAL_KEY) !== null;
    } catch (e) { /* ignore */ }
    link.classList.toggle('ame-sm40-icon-live', on);
  }

  function clearOpened() {
    try {
      sessionStorage.removeItem(SESSION_KEY);
    } catch (e) { /* ignore */ }
    try {
      localStorage.removeItem(LOCAL_KEY);
    } catch (e2) { /* ignore */ }
    try {
      document.body.classList.remove('ame-sm40-dock-on');
    } catch (e3) { /* ignore */ }
    var dock = document.getElementById('ameSm40ListenPill');
    if (dock) {
      dock.hidden = true;
    }
    syncHdrLife();
  }

  function wasOpened() {
    try {
      if (sessionStorage.getItem(SESSION_KEY) !== null) {
        return true;
      }
    } catch (e) { /* ignore */ }
    try {
      return localStorage.getItem(LOCAL_KEY) !== null;
    } catch (e2) {
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
    url = url || lastUrl;
    lastUrl = url;
    var compact = useCompactDock();

    if (compact) {
      try {
        popupRef = window.open(url, WIN_NAME);
      } catch (e) {
        popupRef = null;
      }
      if (!popupRef) {
        try {
          popupRef = window.open(url, '_blank');
        } catch (e2) {
          popupRef = null;
        }
      }
    } else {
      try {
        popupRef = window.open(url, WIN_NAME, popupFeatures());
      } catch (e3) {
        popupRef = null;
      }
      if (!popupRef || popupRef.closed) {
        try {
          popupRef = window.open(url, WIN_NAME);
        } catch (e4) {
          popupRef = null;
        }
      }
    }

    if (!popupRef) {
      window.open(url, '_blank');
      markOpened();
      return false;
    }
    try {
      popupRef.focus();
    } catch (e5) { /* ignore */ }
    markOpened();
    syncHdrLife();
    return true;
  }

  function focusSm40() {
    if (popupRef && !popupRef.closed) {
      try {
        popupRef.focus();
        return;
      } catch (e) { /* ignore */ }
    }
    openSm40(lastUrl);
  }

  function dockCopy() {
    if (useCompactDock()) {
      return {
        title: 'SM40 শুনছেন',
        btn: 'SM40-তে ফিরে যান',
        hint: 'মোবাইল/ট্যাব/টাচ ডিভাইস: এই শর্টকাট থেকে SM40-এ ফিরুন। বিক্রয়·পেমেন্ট·অন্য পেজে কাজ চালিয়ে যান।'
      };
    }
    return {
      title: 'SM40 চালু',
      btn: 'SM40 চালু — শুনতে উইন্ডোতে ফিরে যান',
      hint: 'কম্পিউটার/ল্যাপটপ: অডিও আলাদা SM40 উইন্ডোতে চলবে; এখানে POS ও পেমেন্ট কাজ করুন।'
    };
  }

  function showDock() {
    var compact = useCompactDock();
    var copy = dockCopy();
    var dock = document.getElementById('ameSm40ListenPill');
    if (!dock) {
      dock = document.createElement('div');
      dock.id = 'ameSm40ListenPill';
      dock.className = 'ame-sm40-listen-pill' + (compact ? ' ame-sm40-listen-pill--mobile' : '');
      dock.setAttribute('role', 'status');
      dock.innerHTML =
        '<button type="button" class="ame-sm40-listen-btn" id="ameSm40ListenBtn" title="' + copy.title + '">'
        + '<span class="ame-sm40-listen-ic" aria-hidden="true">🎵</span>'
        + '<span class="ame-sm40-listen-txt">' + copy.btn + '</span>'
        + '</button>'
        + '<button type="button" class="ame-sm40-listen-close" id="ameSm40ListenClose" aria-label="শর্টকাট লুকান">×</button>'
        + '<p class="ame-sm40-listen-hint">' + copy.hint + '</p>';
      document.body.appendChild(dock);
      var btn = document.getElementById('ameSm40ListenBtn');
      if (btn) {
        btn.addEventListener('click', function () {
          focusSm40();
        });
      }
      var closeBtn = document.getElementById('ameSm40ListenClose');
      if (closeBtn) {
        closeBtn.addEventListener('click', function (ev) {
          ev.preventDefault();
          ev.stopPropagation();
          clearOpened();
        });
      }
    } else {
      dock.classList.toggle('ame-sm40-listen-pill--mobile', compact);
      var txt = dock.querySelector('.ame-sm40-listen-txt');
      var hint = dock.querySelector('.ame-sm40-listen-hint');
      if (txt) {
        txt.textContent = copy.btn;
      }
      if (hint) {
        hint.textContent = copy.hint;
      }
    }
    dock.hidden = false;
  }

  function bindLink() {
    var link = document.getElementById('ameSm40HomeLink');
    if (!link) {
      return;
    }
    lastUrl = entryUrl(link);
    link.addEventListener('click', function (ev) {
      if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey || ev.button === 1) {
        return;
      }
      ev.preventDefault();
      openSm40(entryUrl(link));
    });
  }

  function bindStorageSync() {
    window.addEventListener('storage', function (ev) {
      if (ev.key !== LOCAL_KEY) {
        return;
      }
      if (ev.newValue) {
        markOpened();
      } else {
        clearOpened();
      }
    });
  }

  function onLayoutChange() {
    if (wasOpened()) {
      showDock();
    }
  }

  function boot() {
    bindLink();
    bindStorageSync();
    window.addEventListener('resize', onLayoutChange);
    try {
      window.matchMedia('(max-width: 900px)').addEventListener('change', onLayoutChange);
    } catch (e) { /* ignore */ }
    if (wasOpened()) {
      try {
        document.body.classList.add('ame-sm40-dock-on');
      } catch (e) { /* ignore */ }
      showDock();
    }
    syncHdrLife();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();