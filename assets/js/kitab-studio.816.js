/**
 * Kitab studio 8.16 — one blank page at a time.
 * QR-style scan copies ALL text onto the open page. Text-only save (light).
 */
(function () {
  'use strict';
  if (window.__khStudio816) return;
  window.__khStudio816 = 1;

  var root = document.getElementById('khScan');
  if (!root) return;

  var cam = document.getElementById('khCam');
  var gal = document.getElementById('khGal');
  var leafBag = document.getElementById('khLeafBag');
  var listEl = document.getElementById('khPageList');
  var stage = document.getElementById('khOpenPage');
  var count = document.getElementById('khScanCount');
  var statusEl = document.getElementById('khScanStatus');
  var finder = document.getElementById('khFinder');
  var video = document.getElementById('khVideo');
  var liveOn = document.getElementById('khLiveOn');
  var liveOff = document.getElementById('khLiveOff');
  var shutter = document.getElementById('khShutter');
  var liveStatus = document.getElementById('khLiveStatus');
  var blankBtn = document.getElementById('khBlank');
  var form = document.getElementById('khBookForm');
  var start = parseInt(root.getAttribute('data-start') || '0', 10) || 0;
  var pages = [];
  var active = -1;
  var stream = null;
  var busy = false;
  var tessWait = null;

  function say(msg) {
    if (statusEl) statusEl.textContent = msg || '';
  }
  function liveSay(msg) {
    if (liveStatus) liveStatus.textContent = msg || '';
  }
  function nextNo() {
    var used = {};
    var i;
    for (i = 0; i < pages.length; i++) used[pages[i].no] = 1;
    i = start + 1;
    while (used[i]) i++;
    return i;
  }
  function loadTess() {
    if (window.Tesseract) return Promise.resolve();
    if (tessWait) return tessWait;
    tessWait = new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
      s.async = true;
      s.onload = resolve;
      s.onerror = function () {
        tessWait = null;
        reject(new Error('স্ক্যানার লোড হয়নি'));
      };
      document.head.appendChild(s);
    });
    return tessWait;
  }
  function copyText(text) {
    if (!text) return Promise.resolve(false);
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text).then(function () { return true; }).catch(function () { return false; });
    }
    return Promise.resolve(false);
  }
  function syncForm() {
    if (!leafBag) return;
    leafBag.innerHTML = '';
    pages.forEach(function (p) {
      var k = document.createElement('input');
      k.type = 'hidden';
      k.name = 'leaf_kind[]';
      k.value = 'text';
      var n = document.createElement('input');
      n.type = 'hidden';
      n.name = 'leaf_no[]';
      n.value = String(p.no || '');
      var t = document.createElement('textarea');
      t.name = 'leaf_text[]';
      t.hidden = true;
      t.value = p.text || '';
      leafBag.appendChild(k);
      leafBag.appendChild(n);
      leafBag.appendChild(t);
    });
    if (count) {
      count.textContent = pages.length
        ? ('পেজ ' + pages.length + ' খোলা · ' + pages.length + ' pages open')
        : 'এখনো কোনো পেজ খোলা হয়নি · No page open yet';
    }
  }
  function ensurePage() {
    if (active < 0 || !pages[active]) {
      openPage();
    }
    return pages[active];
  }
  function putText(text, fromScan) {
    var p = ensurePage();
    if (!p) return;
    p.text = text || '';
    var box = stage && stage.querySelector('.kh-paper-text');
    if (box) box.value = p.text;
    syncForm();
    copyText(p.text).then(function (ok) {
      say(
        (fromScan ? 'পেজ ' + p.no + ' — সব লেখা এসেছে' : 'পেজ ' + p.no + ' — লেখা বসানো হয়েছে') +
          (ok ? ' · কপি হয়েছে' : '') +
          ' · চাইলে এখানে শুধরে নিন, তারপর পরের পেজ খুলুন।'
      );
    });
  }
  function paintList() {
    if (!listEl) return;
    listEl.innerHTML = '';
    pages.forEach(function (p, idx) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'kh-page-chip' + (idx === active ? ' on' : '');
      b.textContent = String(p.no);
      b.addEventListener('click', function () {
        active = idx;
        paintStage();
        paintList();
      });
      listEl.appendChild(b);
    });
  }
  function paintStage() {
    if (!stage) {
      syncForm();
      return;
    }
    stage.innerHTML = '';
    if (active < 0 || !pages[active]) {
      syncForm();
      return;
    }
    var p = pages[active];
    var paper = document.createElement('article');
    paper.className = 'kh-paper';
    var head = document.createElement('div');
    head.className = 'kh-paper-head';
    var title = document.createElement('b');
    title.textContent = 'পেজ ' + p.no;
    var meta = document.createElement('small');
    meta.textContent = 'সাদা পেজ · লেখা বসান';
    head.appendChild(title);
    head.appendChild(meta);
    var type = document.createElement('textarea');
    type.className = 'kh-paper-text';
    type.rows = 10;
    type.value = p.text || '';
    type.placeholder = 'স্ক্যানের কপি এখানে বসবে — অথবা নিজে পেস্ট করুন…';
    type.addEventListener('input', function () {
      p.text = type.value;
      syncForm();
    });
    var actions = document.createElement('div');
    actions.className = 'kh-leaf-actions';
    var pasteBtn = document.createElement('button');
    pasteBtn.type = 'button';
    pasteBtn.className = 'kh-paste';
    pasteBtn.textContent = 'পেস্ট';
    pasteBtn.addEventListener('click', function () {
      if (!navigator.clipboard || !navigator.clipboard.readText) {
        type.focus();
        say('পাতায় লম্বা চাপ দিয়ে পেস্ট করুন।');
        return;
      }
      navigator.clipboard.readText().then(function (t) {
        putText((p.text ? p.text + '\n' : '') + t, false);
        type.focus();
      }).catch(function () {
        type.focus();
        say('পাতায় লম্বা চাপ দিয়ে পেস্ট করুন।');
      });
    });
    var copyBtn = document.createElement('button');
    copyBtn.type = 'button';
    copyBtn.className = 'kh-copy';
    copyBtn.textContent = 'কপি';
    copyBtn.addEventListener('click', function () {
      copyText(p.text || '').then(function (ok) {
        copyBtn.textContent = ok ? 'কপি হয়েছে' : 'কপি হয়নি';
        setTimeout(function () { copyBtn.textContent = 'কপি'; }, 1200);
      });
    });
    var dropBtn = document.createElement('button');
    dropBtn.type = 'button';
    dropBtn.className = 'kh-thumb-x kh-leaf-x';
    dropBtn.textContent = '×';
    dropBtn.addEventListener('click', function () {
      pages.splice(active, 1);
      active = pages.length ? pages.length - 1 : -1;
      paintList();
      paintStage();
    });
    var num = document.createElement('div');
    num.className = 'kh-paper-no';
    num.textContent = String(p.no);
    actions.appendChild(pasteBtn);
    actions.appendChild(copyBtn);
    actions.appendChild(dropBtn);
    paper.appendChild(head);
    paper.appendChild(type);
    paper.appendChild(actions);
    paper.appendChild(num);
    stage.appendChild(paper);
    type.focus();
    syncForm();
  }
  function openPage() {
    var no = nextNo();
    pages.push({ no: no, text: '' });
    active = pages.length - 1;
    paintList();
    paintStage();
    say('পেজ ' + no + ' খোলা। স্ক্যান করুন অথবা কপি-পেস্ট করুন। তারপর পরের পেজ।');
  }
  function frameCanvas() {
    if (!video || !video.videoWidth) return null;
    var max = 900;
    var w = video.videoWidth;
    var h = video.videoHeight;
    if (w > max || h > max) {
      var s = Math.min(max / w, max / h);
      w = Math.round(w * s);
      h = Math.round(h * s);
    }
    var c = document.createElement('canvas');
    c.width = w;
    c.height = h;
    c.getContext('2d').drawImage(video, 0, 0, w, h);
    return c;
  }
  function ocrSource(src, onProg) {
    return loadTess().then(function () {
      return window.Tesseract.recognize(src, 'ben+eng', {
        logger: function (m) {
          if (m && m.status === 'recognizing text' && typeof m.progress === 'number') {
            onProg && onProg(Math.round(m.progress * 100));
          }
        }
      });
    }).then(function (res) {
      return String((res && res.data && res.data.text) || '').replace(/\n{3,}/g, '\n\n').trim();
    });
  }
  function scanNow() {
    if (busy) return;
    var canvas = frameCanvas();
    if (!canvas) {
      liveSay('ক্যামেরা এখনো খোলেনি…');
      return;
    }
    busy = true;
    liveSay('সব লেখা কপি হচ্ছে…');
    say('স্ক্যান চলছে — ছবি সেভ হয় না, শুধু লেখা আসে।');
    ocrSource(canvas, function (pct) {
      liveSay('লেখা ' + pct + '%');
    }).then(function (text) {
      busy = false;
      if (!text) {
        liveSay('লেখা ধরা যায়নি — আবার তাক করুন');
        say('ফ্রেমে পাতা ভালো করে রেখে আবার স্ক্যান করুন। চাইলে নিজে পেস্টও করতে পারেন।');
        return;
      }
      putText(text, true);
      liveSay('কপি হয়েছে · পরের পাতা রাখুন অথবা পেজ খুলুন');
    }).catch(function (err) {
      busy = false;
      liveSay('স্ক্যান হয়নি');
      say((err && err.message) || 'স্ক্যান হয়নি — পেস্ট করেও বসাতে পারেন।');
    });
  }
  function scanFile(file) {
    if (!file) return;
    busy = true;
    say('ছবি থেকে লেখা কপি হচ্ছে…');
    ocrSource(file, function (pct) {
      say('লেখা ' + pct + '%');
    }).then(function (text) {
      busy = false;
      if (!text) {
        say('লেখা ধরা যায়নি — পেস্ট করে বসান।');
        ensurePage();
        paintStage();
        return;
      }
      putText(text, true);
    }).catch(function (err) {
      busy = false;
      say((err && err.message) || 'স্ক্যান হয়নি');
    });
  }
  function startLive() {
    loadTess().catch(function () {});
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      if (cam) cam.click();
      return;
    }
    ensurePage();
    paintList();
    paintStage();
    navigator.mediaDevices.getUserMedia({
      audio: false,
      video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 960 } }
    }).then(function (s) {
      stream = s;
      if (video) {
        video.srcObject = s;
        video.setAttribute('playsinline', 'true');
        video.muted = true;
        var play = video.play();
        if (play && play.catch) play.catch(function () {});
      }
      if (finder) finder.hidden = false;
      if (liveOn) liveOn.hidden = true;
      liveSay('পাতা ফ্রেমে রাখুন — স্ক্যান করলে সব লেখা কপি হবে');
    }).catch(function () {
      say('ক্যামেরা খোলেনি — গ্যালারি বা পেস্ট ব্যবহার করুন।');
      if (cam) cam.click();
    });
  }
  function stopLive() {
    if (stream) {
      stream.getTracks().forEach(function (t) { try { t.stop(); } catch (e) {} });
      stream = null;
    }
    if (video) video.srcObject = null;
    if (finder) finder.hidden = true;
    if (liveOn) liveOn.hidden = false;
  }

  if (blankBtn) blankBtn.addEventListener('click', openPage);
  if (liveOn) liveOn.addEventListener('click', startLive);
  if (liveOff) liveOff.addEventListener('click', stopLive);
  if (shutter) shutter.addEventListener('click', scanNow);
  if (cam) {
    cam.addEventListener('change', function () {
      if (cam.files && cam.files[0]) scanFile(cam.files[0]);
      cam.value = '';
    });
  }
  if (gal) {
    gal.addEventListener('change', function () {
      if (gal.files && gal.files[0]) scanFile(gal.files[0]);
      gal.value = '';
    });
  }
  if (form) form.addEventListener('submit', syncForm);
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) stopLive();
  });
  syncForm();
})();
