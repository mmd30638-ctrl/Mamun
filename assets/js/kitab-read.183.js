(function () {
  'use strict';
  var el = document.getElementById('kr-data');
  if (!el) return;
  var D;
  try { D = JSON.parse(el.textContent || '{}'); } catch (e) { return; }
  var page = Math.max(1, D.page || 1);
  var total = Math.max(1, D.total || 1);
  var scale = 1.2;
  var mode = 'single';
  var pdfDoc = null;
  var lite = false;
  try { lite = localStorage.getItem('krLite') === '1'; } catch (e) {}
  var status = document.getElementById('krStatus');
  var sheet = document.getElementById('krSheet');
  var canvas = document.getElementById('krPdf');
  var canvas2 = document.getElementById('krPdf2');
  var img = document.getElementById('krScan');
  var leaf = document.getElementById('krLeaf');
  var empty = document.getElementById('krEmpty');
  var prog = document.getElementById('krProg');
  var desk = document.getElementById('krDesk');

  function say(msg) { if (status) status.textContent = msg || ''; }
  function post(fields) {
    var fd = new FormData();
    fd.append('csrf_token', D.csrf || '');
    fd.append('book_id', String(D.id));
    fd.append('ajax', '1');
    Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
    return fetch('/modules/family/library_process.php', {
      method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' }
    }).then(function (r) {
      return r.json().then(function (j) {
        if (!r.ok || !j.ok) throw new Error((j && j.error) || D.err);
        return j;
      });
    });
  }
  function pct() { return total > 0 ? Math.min(100, Math.round(page / total * 100)) : 0; }
  function paintProg() {
    if (prog) prog.textContent = page + ' / ' + total + ' — ' + pct() + '%';
    var jump = document.getElementById('krJump');
    if (jump) jump.value = String(page);
  }
  function imageFor(n) {
    var list = D.map || [];
    for (var i = 0; i < list.length; i++) if (list[i].no === n && list[i].image) return list[i].image;
    return n === D.page ? (D.image || '') : '';
  }
  function showEmpty() {
    if (canvas) canvas.hidden = true;
    if (canvas2) canvas2.hidden = true;
    if (img) img.hidden = true;
    if (leaf) leaf.hidden = true;
    if (empty) { empty.hidden = false; empty.textContent = D.err || ''; }
  }
  function renderCanvas(node, pdfPage) {
    var vp = pdfPage.getViewport({ scale: scale });
    var maxW = sheet ? sheet.clientWidth - 24 : 800;
    if (mode === 'double') maxW = Math.max(280, maxW / 2);
    if (vp.width > maxW && maxW > 0) {
      vp = pdfPage.getViewport({ scale: scale * (maxW / vp.width) });
    }
    node.width = vp.width;
    node.height = vp.height;
    node.hidden = false;
    return pdfPage.render({ canvasContext: node.getContext('2d'), viewport: vp }).promise;
  }
  function flip() {
    if (!desk || lite || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    desk.classList.remove('is-flip');
    void desk.offsetWidth;
    desk.classList.add('is-flip');
  }
  function draw() {
    paintProg();
    if (empty) empty.hidden = true;
    var src = imageFor(page);
    if (src && img) {
      if (canvas) canvas.hidden = true;
      if (canvas2) canvas2.hidden = true;
      if (leaf) leaf.hidden = true;
      img.hidden = false;
      img.src = src;
      return Promise.resolve();
    }
    if (D.pdf && window.pdfjsLib) {
      if (img) img.hidden = true;
      if (leaf) leaf.hidden = true;
      var open = pdfDoc ? Promise.resolve(pdfDoc) : window.pdfjsLib.getDocument({ url: D.pdf, withCredentials: true, disableAutoFetch: true }).promise.then(function (pdf) {
        pdfDoc = pdf;
        if (pdf.numPages > total) {
          total = pdf.numPages;
          post({ do: 'kitab_pages_count', page_count: String(total) }).catch(function () {});
        }
        return pdf;
      });
      return open.then(function (pdf) {
        var n = Math.min(Math.max(1, page), pdf.numPages);
        page = n;
        total = Math.max(total, pdf.numPages);
        return pdf.getPage(n).then(function (pg) { return renderCanvas(canvas, pg); }).then(function () {
          if (mode === 'double' && n < pdf.numPages) {
            return pdf.getPage(n + 1).then(function (pg2) { return renderCanvas(canvas2, pg2); });
          }
          if (canvas2) canvas2.hidden = true;
        });
      }).catch(function () { showEmpty(); });
    }
    if (page === D.page && D.ocr && leaf) {
      if (canvas) canvas.hidden = true;
      if (img) img.hidden = true;
      leaf.hidden = false;
      leaf.textContent = D.ocr;
      var box = document.getElementById('krOcr');
      if (box) box.textContent = D.ocr;
      return Promise.resolve();
    }
    showEmpty();
    return Promise.resolve();
  }
  function go(n, push) {
    n = Math.max(1, Math.min(total, n));
    if (n === page && push) return;
    page = n;
    flip();
    history.replaceState(null, '', 'read.php?id=' + D.id + '&p=' + page);
    post({ do: 'kitab_mark', page_no: String(page), total: String(total) }).catch(function () {});
    draw();
  }
  document.getElementById('krPrev').addEventListener('click', function () { go(page - 1, true); });
  document.getElementById('krNext').addEventListener('click', function () { go(page + 1, true); });
  document.getElementById('krGo').addEventListener('click', function () {
    go(parseInt(document.getElementById('krJump').value, 10) || 1, true);
  });
  document.getElementById('krZoomIn').addEventListener('click', function () { scale = Math.min(3, scale + 0.2); draw(); });
  document.getElementById('krZoomOut').addEventListener('click', function () { scale = Math.max(0.6, scale - 0.2); draw(); });
  document.getElementById('krFit').addEventListener('click', function () { scale = 1.2; draw(); });
  document.getElementById('krFull').addEventListener('click', function () {
    var node = desk;
    if (!document.fullscreenElement && node.requestFullscreen) node.requestFullscreen();
    else if (document.exitFullscreen) document.exitFullscreen();
  });
  document.getElementById('krSingle').addEventListener('click', function () { mode = 'single'; draw(); });
  document.getElementById('krDouble').addEventListener('click', function () { mode = 'double'; draw(); });
  document.getElementById('krNight').addEventListener('click', function () { desk.classList.toggle('is-night'); });
  document.getElementById('krLite').addEventListener('click', function () {
    lite = !lite;
    try { localStorage.setItem('krLite', lite ? '1' : '0'); } catch (e) {}
    say(lite ? (D.lite || '') : '');
  });
  document.getElementById('krSideBtn').addEventListener('click', function () {
    document.getElementById('krSide').classList.toggle('is-open');
  });
  document.getElementById('krState').addEventListener('change', function (ev) {
    post({ do: 'kitab_status', status: ev.target.value, page_no: String(page), total: String(total) }).catch(function (err) { say(err.message); });
  });
  desk.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-go]');
    if (!btn) return;
    go(parseInt(btn.getAttribute('data-go'), 10) || 1, true);
  });
  var find = document.getElementById('krFind');
  find.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var q = document.getElementById('krQ').value.trim();
    var box = document.getElementById('krHits');
    box.textContent = '';
    if (!q) return;
    post({ do: 'kitab_search', q: q }).then(function (j) {
      var hits = j.hits || [];
      if (!hits.length) { say(D.err); return; }
      say('');
      hits.forEach(function (hit) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = hit.page + ' · ' + (hit.snippet || '');
        b.addEventListener('click', function () { go(hit.page, true); });
        box.appendChild(b);
      });
    }).catch(function (err) { say(err.message || D.err); });
  });
  document.getElementById('krNoteForm').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var kind = (ev.submitter && ev.submitter.value) || 'note';
    var text = document.getElementById('krNoteText').value.trim();
    var title = document.getElementById('krMarkTitle').value.trim();
    var job = kind === 'mark'
      ? post({ do: 'kitab_fold', page_no: String(page), note: text, title: title })
      : post({ do: 'kitab_note', page_no: String(page), note_text: text });
    job.then(function () { location.replace('read.php?id=' + D.id + '&p=' + page); }).catch(function (err) { say(err.message); });
  });
  document.getElementById('krHiBtn').addEventListener('click', function () {
    var quote = '';
    try { quote = String(window.getSelection ? window.getSelection() : '').trim(); } catch (e) {}
    if (!quote) quote = document.getElementById('krNoteText').value.trim();
    post({ do: 'kitab_highlight', page_no: String(page), quote: quote }).then(function () {
      location.replace('read.php?id=' + D.id + '&p=' + page);
    }).catch(function (err) { say(err.message); });
  });
  document.getElementById('krTocForm').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var title = ev.target.querySelector('input').value.trim();
    post({ do: 'kitab_toc', title: title, page_no: String(page) }).then(function () {
      location.replace('read.php?id=' + D.id + '&p=' + page);
    }).catch(function (err) { say(err.message); });
  });
  var sx = 0;
  if (sheet) {
    sheet.addEventListener('touchstart', function (ev) { sx = ev.changedTouches[0].clientX; }, { passive: true });
    sheet.addEventListener('touchend', function (ev) {
      var dx = ev.changedTouches[0].clientX - sx;
      if (Math.abs(dx) > 50) {
        go(dx < 0 ? page + 1 : page - 1, true);
        return;
      }
      var box = sheet.getBoundingClientRect();
      var x = ev.changedTouches[0].clientX;
      go(x > box.left + box.width / 2 ? page + 1 : page - 1, true);
    }, { passive: true });
  }
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'ArrowRight') go(page + 1, true);
    if (ev.key === 'ArrowLeft') go(page - 1, true);
  });
  if (window.pdfjsLib) {
    window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
  }
  function boot() { post({ do: 'kitab_mark', page_no: String(page), total: String(total) }).catch(function () {}); draw(); }
  if (document.readyState === 'complete') boot();
  else window.addEventListener('load', boot);
})();
