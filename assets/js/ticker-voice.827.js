/**
 * Ticker 8.27 — shop line runs on EVERY page, not only payment / scrolling_text.
 * Native <marquee> is missing or frozen (ame-if-live display:block, saas-os overflow-x).
 * Always mount #ameRunDock with default shop text, then upgrade from page/localStorage.
 * Scroll with requestAnimationFrame (CSS animation is fallback only).
 */
(function () {
  'use strict';
  if (window.__ameTickerVoice827) return;
  window.__ameTickerVoice827 = 1;

  var STORE = 'ameRunLine827';
  function defaultLine() {
    var name = String(window.AME_SHOP_NAME || '').trim();
    var tag = String(window.AME_SHOP_TAGLINE || '').trim();
    if (name && tag) {
      return 'আজই বেড়ে উঠুন, কালকের জন্য — ' + name + ' · ' + tag + '।';
    }
    if (name) {
      return 'আজই বেড়ে উঠুন, কালকের জন্য — ' + name;
    }
    return 'আজই বেড়ে উঠুন, কালকের জন্য।';
  }
  var DEFAULT = defaultLine();
  var xPos = 0;
  var raf = 0;

  function path() {
    return String(window.location.pathname || '/').replace(/\/{2,}/g, '/');
  }
  function isLogin() {
    return path() === '/login.php'
      || !!document.querySelector('form[action*="process_login"]');
  }
  function isTickerEdit() {
    return /scrolling_text\.php/i.test(path());
  }
  function clean(s) {
    return String(s || '')
      .replace(/পুরুষ কণ্ঠে শুনুন|থামুন|পড়া শেষ।|চলমান লেখা শুনুন|উপরের লেখায় ট্যাপ করলেও শুনবেন/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }
  function remember(text) {
    var t = clean(text);
    if (!t || t.length < 8) return;
    try { localStorage.setItem(STORE, t); } catch (e) {}
    try { sessionStorage.setItem(STORE, t); } catch (e2) {}
  }
  function recalled() {
    var a = '';
    var b = '';
    try { a = clean(localStorage.getItem(STORE) || ''); } catch (e) {}
    try { b = clean(sessionStorage.getItem(STORE) || ''); } catch (e2) {}
    if (a.length >= b.length) return a;
    return b;
  }

  function skipped(el) {
    if (!el || !el.closest) return true;
    if (el.closest('.ame-run-dock, .ame-run-btns, script, style')) return true;
    if (el.closest('#ameTickerBar, .ame-tbar, .ame-tbar-ticker')) return true;
    if (el.id === 'ameTickerBar') return true;
    if (el.closest('.custom-sidebar, .sidebar, .menu-list, .menu-head, .sidebar-menu-body')) return true;
    var tag = String(el.tagName || '').toLowerCase();
    if (tag === 'marquee') return false;
    if (el.id === 'm-ticker') return true;
    if (el.querySelector && el.querySelector('input, textarea, table, select, button')) return true;
    return false;
  }

  function findSource() {
    var sels = [
      'marquee',
      '.hm-banner-wrap',
      '.scrolling-text',
      '#scrolling-text',
      '#scrollingText',
      '.scroll-text',
      '.notice-bar',
      '.news-bar',
      '.announcement',
      '[class*="scrolling"]',
      '[id*="scrolling"]',
      '.hm-ticker',
      '[class*="ticker"]',
      '[id*="ticker"]'
    ];
    var i;
    var j;
    var nodes;
    var el;
    var t;
    var best = null;
    var bestLen = 0;
    for (i = 0; i < sels.length; i++) {
      try { nodes = document.querySelectorAll(sels[i]); } catch (e) { continue; }
      for (j = 0; j < nodes.length; j++) {
        el = nodes[j];
        if (skipped(el)) continue;
        t = clean(el.textContent);
        if (t.length > bestLen && t.length >= 8) {
          best = el;
          bestLen = t.length;
        }
      }
    }
    return best;
  }

  function hideSource(el) {
    if (!el || isTickerEdit()) return;
    if (el.getAttribute('data-ame-run-hid') === '1') return;
    el.setAttribute('data-ame-run-hid', '1');
  }

  var speaking = false;
  var queue = [];
  var fullLine = '';
  window.__ameKeep = window.__ameKeep || [];

  if (!window.__ameResumeWatch && window.speechSynthesis) {
    window.__ameResumeWatch = setInterval(function () {
      try {
        if (speechSynthesis.speaking && speechSynthesis.paused) speechSynthesis.resume();
      } catch (e) {}
    }, 8000);
  }

  function refreshVoices() {
    return window.speechSynthesis ? speechSynthesis.getVoices() || [] : [];
  }
  if (window.speechSynthesis) {
    try { speechSynthesis.getVoices(); } catch (e) {}
    if (speechSynthesis.addEventListener) {
      speechSynthesis.addEventListener('voiceschanged', function () {
        try { speechSynthesis.getVoices(); } catch (e2) {}
      });
    }
  }
  function blob(v) {
    return ((v && v.name) || '') + ' ' + ((v && v.voiceURI) || '') + ' ' + ((v && v.lang) || '');
  }
  function isFemale(v) {
    return /female|woman|girl|zira|samantha|victoria|karen|moira|tessa|heera|kalpana|padma|susan|hazel|fiona|মহিলা|নারী/.test(blob(v).toLowerCase());
  }
  function isMale(v) {
    var n = blob(v).toLowerCase();
    if (isFemale(v)) return false;
    return /male|man\b|boy|david|mark|daniel|ravi|george|fred|richard|পুরুষ|wavenet-[bd]|standard-[bd]/.test(n);
  }
  function pickVoice() {
    var list = refreshVoices();
    var best = null;
    var bestS = -9999;
    list.forEach(function (v) {
      var n = blob(v).toLowerCase();
      var vl = ((v && v.lang) || '').toLowerCase();
      var s = 0;
      if (isFemale(v)) s -= 260;
      if (isMale(v)) s += 110;
      if (/^bn/.test(vl)) s += 130;
      else if (/^hi/.test(vl)) s += 60;
      if (/bengali|bangla|বাংলা/.test(n)) s += 55;
      if (s > bestS) {
        bestS = s;
        best = v;
      }
    });
    return best;
  }
  function chunks(text) {
    var raw = clean(text);
    if (!raw) return [];
    var parts = raw.split(/(?<=[।!?\n,])/);
    var out = [];
    var buf = '';
    parts.forEach(function (p) {
      if ((buf + p).length > 160) {
        if (buf) out.push(buf);
        buf = p;
      } else buf += p;
    });
    if (buf) out.push(buf);
    return out.length ? out : [raw];
  }

  function setStatus(msg) {
    var st = document.getElementById('ameTickerStatus');
    if (st) st.textContent = msg || '';
  }
  function stopGoogle() {
    if (window.__ameAudio) {
      try {
        window.__ameAudio.pause();
        window.__ameAudio.removeAttribute('src');
      } catch (e) {}
      window.__ameAudio = null;
    }
  }
  function stopTalk() {
    speaking = false;
    queue = [];
    window.__ameKeep = [];
    stopGoogle();
    try {
      if (window.speechSynthesis) speechSynthesis.cancel();
    } catch (e) {}
    setStatus('');
  }
  function playGoogle(text) {
    var parts = chunks(text).map(function (p) { return clean(p); }).filter(Boolean);
    if (!parts.length) return;
    stopGoogle();
    var audio = new Audio();
    window.__ameAudio = audio;
    var i = 0;
    function next() {
      if (!speaking) return;
      if (i >= parts.length) {
        speaking = false;
        setStatus('পড়া শেষ।');
        return;
      }
      var q = parts[i];
      i += 1;
      audio.src = 'https://translate.google.com/translate_tts?ie=UTF-8&client=tw-ob&tl=bn&q=' + encodeURIComponent(q);
      audio.play().then(function () {
        setStatus('চলমান লেখা পড়া হচ্ছে…');
      }).catch(function () {
        setStatus('সাউন্ড ব্লক। Chrome-এ সাইটের সাউন্ড অনুমতি দিন।');
        speaking = false;
      });
    }
    audio.onended = next;
    audio.onerror = function () {
      setStatus('এই ফোনে ভয়েস ফাইল আসেনি। Chrome ব্যবহার করুন, সাউন্ড চালু রাখুন।');
      speaking = false;
    };
    next();
  }
  function talkNext() {
    if (!speaking) return;
    if (!queue.length) {
      speaking = false;
      setStatus('পড়া শেষ।');
      return;
    }
    if (!window.speechSynthesis) {
      playGoogle(fullLine);
      return;
    }
    var u = new SpeechSynthesisUtterance(queue.shift());
    window.__ameKeep.push(u);
    var v = pickVoice();
    if (v) u.voice = v;
    u.lang = (v && v.lang) || 'bn-BD';
    u.rate = 0.92;
    u.volume = 1;
    u.pitch = isMale(v) ? 0.95 : 0.78;
    u.onend = function () { talkNext(); };
    u.onerror = function () {
      if (speaking) playGoogle(fullLine);
    };
    try {
      if (speechSynthesis.paused) speechSynthesis.resume();
      speechSynthesis.speak(u);
    } catch (e) {
      playGoogle(fullLine);
    }
  }
  function talk(text) {
    var body = clean(text);
    if (!body) {
      setStatus('শোনার মতো চলমান লেখা নেই।');
      return;
    }
    fullLine = body;
    speaking = true;
    queue = chunks(body);
    window.__ameKeep = [];
    stopGoogle();
    setStatus('চলমান লেখা পড়া হচ্ছে… সাউন্ড চালু আছে কি?');
    try {
      if (window.speechSynthesis) speechSynthesis.cancel();
    } catch (e) {}
    setTimeout(function () {
      if (!speaking) return;
      talkNext();
      setTimeout(function () {
        if (!speaking) return;
        var synthDead = !window.speechSynthesis
          || (!speechSynthesis.speaking && !speechSynthesis.pending);
        if (synthDead && !window.__ameAudio) playGoogle(fullLine);
      }, 800);
    }, 150);
  }

  function lineText() {
    var dock = document.getElementById('ameRunDock');
    if (dock) {
      var t = clean(dock.getAttribute('data-ame-line') || '');
      if (t) return t;
    }
    return recalled() || DEFAULT;
  }

  function pickText() {
    var src = findSource();
    var fromSrc = src ? clean(src.textContent) : '';
    var saved = recalled();
    var text = fromSrc || saved || DEFAULT;
    if (fromSrc && fromSrc.length >= 8) remember(fromSrc);
    return { src: src, text: text };
  }

  function fillLine(line, text) {
    if (!line) return;
    line.style.animation = 'none';
    line.innerHTML = '';
    var a = document.createElement('span');
    a.textContent = text + '   •   ';
    var b = a.cloneNode(true);
    line.appendChild(a);
    line.appendChild(b);
  }

  function loop() {
    raf = window.requestAnimationFrame(loop);
    var line = document.querySelector('#ameRunDock .ame-run-line');
    if (!line) return;
    var half = line.scrollWidth / 2;
    if (half < 12) return;
    xPos -= 0.85;
    if (-xPos >= half) xPos += half;
    line.style.transform = 'translate3d(' + xPos + 'px,0,0)';
  }

  function startLoop() {
    if (raf) return;
    loop();
  }

  function insertPoint() {
    return document.querySelector('.master-header-fixed')
      || document.querySelector('.premium-header')
      || document.querySelector('#header')
      || document.querySelector('header.header')
      || document.querySelector('header')
      || document.querySelector('#main-content')
      || document.body;
  }

  function seatTickerAboveIcons(header) {
    var ticker = document.getElementById('ameTickerBar');
    if (!ticker || !header) return ticker;
    if (ticker.parentNode !== header) {
      header.insertBefore(ticker, header.firstChild);
    }
    if (ticker.getAttribute('data-ame-line-lock') === '1') return ticker;
    header.style.setProperty('flex-wrap', 'wrap', 'important');
    header.style.setProperty('height', 'auto', 'important');
    ticker.style.setProperty('position', 'relative', 'important');
    ticker.style.setProperty('top', 'auto', 'important');
    ticker.style.setProperty('height', '40px', 'important');
    ticker.style.setProperty('min-height', '40px', 'important');
    ticker.style.setProperty('max-height', '40px', 'important');
    ticker.style.setProperty('line-height', '40px', 'important');
    ticker.style.setProperty('white-space', 'nowrap', 'important');
    ticker.style.setProperty('left', '0', 'important');
    ticker.style.setProperty('width', '100%', 'important');
    ticker.style.setProperty('order', '-10', 'important');
    ticker.style.setProperty('flex', '1 0 100%', 'important');
    ticker.setAttribute('data-ame-line-lock', '1');
    return ticker;
  }

  function padMain() {
    var header = document.querySelector('.master-header-fixed');
    var ticker = seatTickerAboveIcons(header);
    var dock = document.getElementById('ameRunDock');
    var salePage = document.body && document.body.classList.contains('ame-pos-terminal-page');
    if (dock && (ticker || salePage)) {
      dock.style.setProperty('display', 'none', 'important');
    } else if (dock && header && !ticker && !salePage) {
      if (dock.parentNode !== header) {
        header.insertBefore(dock, header.firstChild);
      }
      if (dock.getAttribute('data-ame-line-lock') !== '1') {
        header.style.setProperty('flex-wrap', 'wrap', 'important');
        header.style.setProperty('height', 'auto', 'important');
        dock.style.setProperty('display', 'block', 'important');
        dock.style.setProperty('position', 'relative', 'important');
        dock.style.setProperty('top', 'auto', 'important');
        dock.style.setProperty('height', '40px', 'important');
        dock.style.setProperty('min-height', '40px', 'important');
        dock.style.setProperty('max-height', '40px', 'important');
        dock.style.setProperty('line-height', '40px', 'important');
        dock.style.setProperty('white-space', 'nowrap', 'important');
        dock.style.setProperty('order', '-10', 'important');
        dock.style.setProperty('width', '100%', 'important');
        dock.style.setProperty('flex', '1 0 100%', 'important');
        dock.setAttribute('data-ame-line-lock', '1');
      }
    }
    var top = header ? Math.ceil(header.getBoundingClientRect().bottom) : 60;
    var clock = document.getElementById('hdr-live-clock');
    if (clock) {
      var clockStyle = window.getComputedStyle(clock);
      if (clockStyle.display !== 'none' && clockStyle.visibility !== 'hidden') {
        top = Math.max(top, Math.ceil(clock.getBoundingClientRect().bottom));
      }
    }
    if (top < 1) {
      top = header ? (header.offsetHeight || 60) : 60;
    }
    if (window.__ameChromeLock && Math.abs(top - window.__ameChromeLock) < 8) return;
    window.__ameChromeLock = top;
    document.documentElement.style.setProperty('--ame-header-h', top + 'px');
    var main = document.getElementById('main-content');
    if (main) {
      main.style.setProperty('padding-top', top + 'px', 'important');
    }
  }

  function place(dock) {
    var bar = document.getElementById('ameTickerBar');
    if (bar || isSaleDesk()) {
      dock.style.setProperty('display', 'none', 'important');
      padMain();
      return;
    }
    dock.style.display = '';
    var at = insertPoint();
    if (!dock || !at) return;
    if (at === document.body) {
      if (dock.parentNode !== document.body) {
        document.body.insertBefore(dock, document.body.firstChild);
      } else if (document.body.firstChild !== dock) {
        document.body.insertBefore(dock, document.body.firstChild);
      }
      padMain();
      return;
    }
    if (dock.parentNode !== at) {
      at.insertBefore(dock, at.firstChild);
    }
    padMain();
  }

  function wire(dock) {
    var play = dock.querySelector('#ameTickerPlay');
    var stop = dock.querySelector('#ameTickerStop');
    if (play && play.getAttribute('data-ame-wired') !== '1') {
      play.setAttribute('data-ame-wired', '1');
      play.addEventListener('click', function (ev) {
        ev.preventDefault();
        talk(lineText());
      });
    }
    if (stop && stop.getAttribute('data-ame-wired') !== '1') {
      stop.setAttribute('data-ame-wired', '1');
      stop.addEventListener('click', function (ev) {
        ev.preventDefault();
        stopTalk();
      });
    }
    var run = dock.querySelector('.ame-run-line');
    if (run && run.getAttribute('data-ame-tap') !== '1') {
      run.setAttribute('data-ame-tap', '1');
      run.addEventListener('click', function () { talk(lineText()); });
    }
  }

  function placeSaleLogo() {
    var group = document.querySelector('.master-header-fixed .hdr-btn-group');
    var box = document.querySelector('.master-header-fixed .hdr-brand-box');
    if (!group || !box) return;
    var img = box.querySelector('img');
    if (img) {
      img.classList.add('hdr-sale-logo');
      if (img.parentNode !== group) group.appendChild(img);
      img.style.setProperty('display', 'block', 'important');
      img.style.setProperty('visibility', 'visible', 'important');
      img.style.setProperty('height', '36px', 'important');
      img.style.setProperty('width', 'auto', 'important');
      img.style.setProperty('max-width', '128px', 'important');
      img.style.setProperty('margin', '0 0 0 auto', 'important');
      img.style.setProperty('background', 'transparent', 'important');
      img.style.setProperty('flex', '0 0 auto', 'important');
    }
    box.style.setProperty('display', 'none', 'important');
    var clock = document.querySelector('.master-header-fixed .hdr-clock');
    if (clock) clock.style.setProperty('display', 'none', 'important');
  }

  function killSaleLowerTicker() {
    var body = document.body;
    if (!body || !body.classList.contains('ame-sales-pro') || !body.classList.contains('ame-pos-terminal-page')) return false;
    if (body.classList.contains('ame-purchase-desk')) return false;
    placeSaleLogo();
    var old = document.getElementById('ameRunDock');
    if (old && old.parentNode) old.parentNode.removeChild(old);
    document.querySelectorAll('.ame-desk-marquee, .ame-desk-bar').forEach(function (node) {
      if (node.parentNode) node.parentNode.removeChild(node);
    });
    return true;
  }

  function ensureDock() {
    if (killSaleLowerTicker()) return null;
    var dock = document.getElementById('ameRunDock');
    if (dock) return dock;
    dock = document.createElement('div');
    dock.id = 'ameRunDock';
    dock.className = 'ame-run-dock';
    dock.innerHTML =
      '<div class="ame-run-host"><div class="ame-run-line"></div></div>' +
      '<div class="ame-run-btns">' +
      '<button type="button" id="ameTickerPlay">🔊 চলমান লেখা শুনুন</button>' +
      '<button type="button" id="ameTickerStop">থামুন</button>' +
      '<p id="ameTickerStatus">উপরের লেখায় ট্যাপ করলেও শুনবেন</p>' +
      '</div>';
    document.body.insertBefore(dock, document.body.firstChild);
    return dock;
  }

  function mount() {
    if (isLogin() || !document.body) return false;
    var picked = pickText();
    var text = picked.text || DEFAULT;
    var dock = ensureDock();
    if (!dock) {
      padMain();
      return true;
    }
    place(dock);
    if (dock.getAttribute('data-ame-line') !== text || !dock.querySelector('.ame-run-line span')) {
      dock.setAttribute('data-ame-line', text);
      fillLine(dock.querySelector('.ame-run-line'), text);
      xPos = 0;
    }
    if (isTickerEdit()) {
      document.querySelectorAll('marquee').forEach(function (m) {
        m.setAttribute('data-ame-keep', '1');
      });
    } else {
      if (picked.src) hideSource(picked.src);
      document.querySelectorAll('marquee').forEach(function (m) {
        if (skipped(m)) return;
        hideSource(m);
      });
    }
    wire(dock);
    startLoop();
    return true;
  }

  function isSaleDesk() {
    return !!(document.body && document.body.classList.contains('ame-pos-terminal-page'));
  }

  function boot() {
    mount();
    window.setTimeout(mount, 400);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  window.addEventListener('pageshow', function () {
    if (isSaleDesk()) return;
    raf = 0;
    mount();
    startLoop();
  });
  document.addEventListener('visibilitychange', function () {
    if (isSaleDesk() || document.hidden) return;
    raf = 0;
    startLoop();
  });
})();
