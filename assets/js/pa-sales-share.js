/* 10.98.718 — পাতা মাঝখানে ও মোবাইলে পর্দা অনুযায়ী মাপ; 10.98.717 — শেয়ার সরাসরি (ডাউনলোড নয়): PDF আগে থেকে তৈরি, সই বদলালে নতুন করে; সই বোর্ড থেকেই সই+শেয়ার। 10.98.716 — ডিজিটাল সই বোর্ড (মোবাইলে আঙুল দিয়ে সই); 10.98.700 — পার্টনার বিক্রয় পেজ: প্রিন্ট, PDF (প্রতি পাতা আলাদা A4), ছবি, শেয়ার, হোয়াটসঅ্যাপ */
(function () {
  'use strict';
  var stage = document.getElementById('pspStage');
  if (!stage) return;
  var PAGE_W = 794, PAGE_H = 1123;
  var fileBase = (stage.getAttribute('data-file') || 'partner-sales').replace(/[^\w.\-]+/g, '_');
  var shareText = stage.getAttribute('data-text') || '';
  var busy = document.getElementById('pspBusy');
  var msg = document.getElementById('pspMsg');
  var prepEl = document.getElementById('pspPrep');
  var readyEl = document.getElementById('pspReady');
  var readyText = document.getElementById('pspReadyText');
  var shopName = stage.getAttribute('data-shop') || '';
  var sigVer = 0;
  var cache = { ver: -1, blob: null };
  var building = null;
  var rebuildTimer = 0;
  var pendingShare = null;

  function fit() {
    var wrap = stage.parentNode;
    var cs = window.getComputedStyle(wrap);
    var pad = (parseFloat(cs.paddingLeft) || 0) + (parseFloat(cs.paddingRight) || 0);
    var avail = Math.max(240, Math.min(wrap.clientWidth || window.innerWidth, window.innerWidth) - pad - 2);
    var coarse = !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches);
    var z = Math.min(coarse ? 2 : 1, avail / PAGE_W);
    stage.style.zoom = Math.abs(z - 1) > 0.01 ? String(z) : '';
  }
  fit();
  window.addEventListener('resize', fit);
  window.addEventListener('orientationchange', function () { setTimeout(fit, 250); });
  window.addEventListener('load', fit);


  /* ---- ডিজিটাল সই ---- */
  var sigKey = stage.getAttribute('data-sigkey') || 'doc';
  var modal = document.getElementById('pspSigModal');
  var cv = document.getElementById('pspSigCanvas');
  var sigTitle = document.getElementById('pspSigTitle');
  var activeSig = null;
  var drawn = false;
  var drawing = false;

  function storeKey(role) { return 'psp_sig_v2:' + sigKey + ':' + role; }
  function storeGet(role) { try { return window.localStorage.getItem(storeKey(role)) || ''; } catch (e) { return ''; } }
  function storeSet(role, val) {
    try {
      if (val) window.localStorage.setItem(storeKey(role), val); else window.localStorage.removeItem(storeKey(role));
    } catch (e) { /* ব্রাউজার স্টোরেজ বন্ধ থাকলে শুধু এই পাতায় সই থাকবে */ }
  }
  function paintSig(role, data) {
    var boxes = stage.querySelectorAll('.psp-sig[data-sig="' + role + '"]');
    Array.prototype.forEach.call(boxes, function (box) {
      var img = box.querySelector('.psp-sig-img');
      if (!img) return;
      if (data) { img.src = data; img.hidden = false; box.classList.add('signed'); }
      else { img.removeAttribute('src'); img.hidden = true; box.classList.remove('signed'); }
    });
  }
  Array.prototype.forEach.call(stage.querySelectorAll('.psp-sig'), function (box) {
    var role = box.getAttribute('data-sig');
    var saved = storeGet(role);
    if (saved) paintSig(role, saved);
  });

  function ctx2d() { return cv.getContext('2d'); }
  function clearPad() {
    var c = ctx2d();
    c.clearRect(0, 0, cv.width, cv.height);
    drawn = false;
  }
  function pos(e) {
    var r = cv.getBoundingClientRect();
    return { x: (e.clientX - r.left) * (cv.width / r.width), y: (e.clientY - r.top) * (cv.height / r.height) };
  }
  function openPad(box) {
    if (!modal || !cv) return;
    activeSig = box.getAttribute('data-sig');
    var nameEl = box.querySelector('.psp-sig-name');
    if (sigTitle) sigTitle.textContent = 'সই করুন — ' + (nameEl ? nameEl.textContent : '');
    modal.hidden = false;
    document.documentElement.style.overflow = 'hidden';
    clearPad();
    var c = ctx2d();
    c.lineWidth = 4; c.lineCap = 'round'; c.lineJoin = 'round'; c.strokeStyle = '#0b1e4d';
  }
  function closePad() {
    if (modal) modal.hidden = true;
    activeSig = null; drawing = false;
    document.documentElement.style.overflow = '';
  }
  function sigChanged() {
    sigVer++;
    cache = { ver: -1, blob: null };
    if (rebuildTimer) clearTimeout(rebuildTimer);
    rebuildTimer = setTimeout(function () { ensurePdf().catch(function () { /* শেয়ারের সময় আবার চেষ্টা হবে */ }); }, 500);
  }
  if (cv) {
    cv.style.touchAction = 'none';
    cv.addEventListener('pointerdown', function (e) {
      e.preventDefault();
      drawing = true; drawn = true;
      var p = pos(e), c = ctx2d();
      c.beginPath(); c.moveTo(p.x, p.y); c.lineTo(p.x + 0.1, p.y + 0.1); c.stroke();
      try { cv.setPointerCapture(e.pointerId); } catch (err) { /* পুরনো ব্রাউজার */ }
    });
    cv.addEventListener('pointermove', function (e) {
      if (!drawing) return;
      e.preventDefault();
      var p = pos(e), c = ctx2d();
      c.lineTo(p.x, p.y); c.stroke();
    });
    var stop = function () { drawing = false; };
    cv.addEventListener('pointerup', stop);
    cv.addEventListener('pointercancel', stop);
    cv.addEventListener('pointerleave', stop);
  }
  stage.addEventListener('click', function (e) {
    var box = e.target.closest ? e.target.closest('.psp-sig') : null;
    if (box) openPad(box);
  });
  stage.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    var box = e.target.closest ? e.target.closest('.psp-sig') : null;
    if (box) { e.preventDefault(); openPad(box); }
  });
  if (modal) {
    modal.addEventListener('click', function (e) {
      if (e.target === modal) { closePad(); return; }
      var b = e.target.closest ? e.target.closest('[data-sig-act]') : null;
      if (!b) return;
      var act = b.getAttribute('data-sig-act');
      if (act === 'clear') clearPad();
      else if (act === 'cancel') closePad();
      else if (act === 'remove') { storeSet(activeSig, ''); paintSig(activeSig, ''); closePad(); sigChanged(); }
      else if (act === 'save' || act === 'save-share') {
        if (!drawn) { note('আগে সই করুন।'); return; }
        var data = cv.toDataURL('image/png');
        storeSet(activeSig, data);
        paintSig(activeSig, data);
        note('');
        closePad();
        sigChanged();
        if (act === 'save-share') run('share');
      }
    });
  }

  function setBusy(on, text) {
    if (busy) { busy.hidden = !on; if (text) busy.textContent = text; }
    var btns = document.querySelectorAll('.psp-btn');
    for (var i = 0; i < btns.length; i++) btns[i].disabled = !!on;
  }
  function note(text) {
    if (!msg) return;
    msg.hidden = !text;
    msg.textContent = text || '';
  }

  function waitImages(root) {
    var imgs = Array.prototype.slice.call(root.querySelectorAll('img'));
    return Promise.all(imgs.map(function (img) {
      if (img.complete && img.naturalWidth) return null;
      return new Promise(function (res) {
        img.addEventListener('load', res, { once: true });
        img.addEventListener('error', res, { once: true });
        setTimeout(res, 3000);
      });
    }));
  }

  function capturePages() {
    if (typeof html2canvas !== 'function') return Promise.reject(new Error('html2canvas'));
    var holder = document.createElement('div');
    holder.id = 'pspCap';
    holder.style.cssText = 'position:fixed;left:-20000px;top:0;width:' + PAGE_W + 'px;zoom:1;background:#fff;';
    var pages = stage.querySelectorAll('.psp-page');
    Array.prototype.forEach.call(pages, function (p) {
      var c = p.cloneNode(true);
      c.style.boxShadow = 'none';
      c.style.border = '0';
      Array.prototype.forEach.call(c.querySelectorAll('.psp-sig-hint'), function (n) { n.parentNode.removeChild(n); });
      holder.appendChild(c);
    });
    document.body.appendChild(holder);
    var clones = Array.prototype.slice.call(holder.querySelectorAll('.psp-page'));
    var out = [];
    return waitImages(holder).then(function () {
      return clones.reduce(function (chain, el) {
        return chain.then(function () {
          return html2canvas(el, {
            scale: 2, backgroundColor: '#ffffff', useCORS: true, logging: false,
            width: PAGE_W, height: PAGE_H, windowWidth: PAGE_W, scrollX: 0, scrollY: 0
          }).then(function (cv) { out.push(cv); });
        });
      }, Promise.resolve());
    }).then(function () {
      document.body.removeChild(holder);
      return out;
    }, function (e) {
      if (holder.parentNode) holder.parentNode.removeChild(holder);
      throw e;
    });
  }

  function toPdf(canvases) {
    var lib = window.jspdf && window.jspdf.jsPDF;
    if (!lib) throw new Error('jspdf');
    var pdf = new lib({ orientation: 'p', unit: 'mm', format: 'a4' });
    canvases.forEach(function (cv, i) {
      if (i > 0) pdf.addPage('a4', 'portrait');
      pdf.addImage(cv.toDataURL('image/jpeg', 0.93), 'JPEG', 0, 0, 210, 297, undefined, 'FAST');
    });
    return pdf;
  }

  function stacked(canvases) {
    var w = canvases[0].width, h = 0, i;
    for (i = 0; i < canvases.length; i++) h += canvases[i].height;
    var cv = document.createElement('canvas');
    cv.width = w; cv.height = h;
    var ctx = cv.getContext('2d');
    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, w, h);
    var y = 0;
    for (i = 0; i < canvases.length; i++) { ctx.drawImage(canvases[i], 0, y); y += canvases[i].height; }
    return cv;
  }

  function download(href, name) {
    var a = document.createElement('a');
    a.href = href; a.download = name;
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    if (href.indexOf('blob:') === 0) setTimeout(function () { URL.revokeObjectURL(href); }, 5000);
  }

  function pdfBlob() {
    return capturePages().then(function (cvs) { return toPdf(cvs).output('blob'); });
  }

  function setPrep(state) {
    if (!prepEl) return;
    if (!state) { prepEl.hidden = true; return; }
    prepEl.hidden = false;
    prepEl.className = 'psp-prep' + (state === 'ok' ? ' ok' : '');
    prepEl.textContent = state === 'ok' ? 'শেয়ারের ফাইল প্রস্তুত' : (state === 'err' ? 'ফাইল তৈরি হয়নি — আবার চাপুন' : 'শেয়ারের ফাইল তৈরি হচ্ছে…');
  }

  function ensurePdf() {
    if (cache.blob && cache.ver === sigVer) return Promise.resolve(cache.blob);
    if (building) return building.then(function () { return ensurePdf(); });
    var v = sigVer;
    setPrep('busy');
    building = pdfBlob().then(function (blob) {
      building = null;
      if (v === sigVer) { cache = { ver: v, blob: blob }; setPrep('ok'); return blob; }
      return ensurePdf();
    }, function (e) { building = null; setPrep('err'); throw e; });
    return building;
  }

  function doShare(blob, kind) {
    var file = new File([blob], fileBase + '.pdf', { type: 'application/pdf' });
    var data = { files: [file], title: shopName + ' — ' + fileBase };
    if (kind === 'wa') data.text = shareText;
    if (!navigator.share || !navigator.canShare || !navigator.canShare(data)) {
      var e = new Error('unsupported'); e.name = 'UnsupportedError';
      return Promise.reject(e);
    }
    return navigator.share(data);
  }

  function hideReady() { if (readyEl) readyEl.hidden = true; pendingShare = null; }
  function showReady(blob, kind) {
    if (!readyEl) return;
    pendingShare = { blob: blob, kind: kind };
    if (readyText) readyText.textContent = 'ফাইল (সই সহ) তৈরি হয়েছে। নিচের বোতামে চাপলেই সরাসরি শেয়ার হবে।';
    readyEl.hidden = false;
  }

  function shareFailed(err, blob, kind) {
    var n = err && err.name;
    if (n === 'AbortError') return;
    if (n === 'NotAllowedError' || n === 'SecurityError') { showReady(blob, kind); return; }
    download(URL.createObjectURL(blob), fileBase + '.pdf');
    if (kind === 'wa') window.open('https://wa.me/?text=' + encodeURIComponent(shareText), '_blank', 'noopener');
    note('এই ব্রাউজারে ফাইল সরাসরি শেয়ার চালু নেই, তাই PDF নামানো হয়েছে। মোবাইলের Chrome/Safari-তে সরাসরি শেয়ার হবে।');
  }

  function shareNow(blob, kind) {
    var p;
    try { p = doShare(blob, kind); } catch (e) { p = Promise.reject(e); }
    return p.then(function () { note('শেয়ার সম্পন্ন।'); }, function (err) { shareFailed(err, blob, kind); });
  }

  function run(kind) {
    note('');
    if (kind === 'print') { window.print(); return; }
    if (kind === 'copy') {
      var done = function () { note('লেখা কপি হয়েছে। এখন পেস্ট করুন।'); };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(shareText).then(done, function () { note('কপি হয়নি।'); });
      } else {
        var t = document.createElement('textarea');
        t.value = shareText; t.style.cssText = 'position:fixed;left:-9999px';
        document.body.appendChild(t); t.select();
        try { document.execCommand('copy'); done(); } catch (e) { note('কপি হয়নি।'); }
        document.body.removeChild(t);
      }
      return;
    }
    if (kind === 'image') {
      setBusy(true, 'ছবি তৈরি হচ্ছে…');
      capturePages().then(function (cvs) {
        download(stacked(cvs).toDataURL('image/png'), fileBase + '.png');
      }).catch(function () { note('ছবি তৈরি হয়নি। প্রিন্ট থেকে PDF করুন।'); }).then(function () { setBusy(false); });
      return;
    }
    if (kind === 'pdf') {
      setBusy(true, 'PDF তৈরি হচ্ছে…');
      ensurePdf().then(function (blob) {
        download(URL.createObjectURL(blob), fileBase + '.pdf');
      }).catch(function () { note('PDF তৈরি হয়নি। প্রিন্ট থেকে PDF করুন।'); }).then(function () { setBusy(false); });
      return;
    }
    if (kind === 'share' || kind === 'wa') {
      if (cache.blob && cache.ver === sigVer) { shareNow(cache.blob, kind); return; }
      setBusy(true, 'শেয়ারের ফাইল তৈরি হচ্ছে… একটু অপেক্ষা করুন');
      ensurePdf().then(function (blob) {
        setBusy(false);
        return shareNow(blob, kind);
      }).catch(function () { setBusy(false); note('ফাইল তৈরি হয়নি। আবার চেষ্টা করুন বা PDF/ছবি নামিয়ে পাঠান।'); });
    }
  }

  if (readyEl) {
    var go = document.getElementById('pspReadyGo');
    var no = document.getElementById('pspReadyCancel');
    if (go) go.addEventListener('click', function () {
      var ps = pendingShare;
      if (!ps) { hideReady(); return; }
      readyEl.hidden = true;
      pendingShare = null;
      shareNow(ps.blob, ps.kind);
    });
    if (no) no.addEventListener('click', hideReady);
  }

  window.addEventListener('load', function () {
    setTimeout(function () { ensurePdf().catch(function () { /* শেয়ারের সময় আবার চেষ্টা হবে */ }); }, 1200);
  });

  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-psp]') : null;
    if (b) { e.preventDefault(); run(b.getAttribute('data-psp')); }
  });
})();
