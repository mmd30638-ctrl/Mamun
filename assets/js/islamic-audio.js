(function () {
  var root = document.getElementById('iaHome');
  if (!root) {
    return;
  }
  var tracks = [];
  try {
    tracks = JSON.parse(root.getAttribute('data-ia-tracks') || '[]');
  } catch (e) {
    tracks = [];
  }
  var fab = document.getElementById('iaFab');
  var panel = document.getElementById('iaPanel');
  var closeBtn = document.getElementById('iaClose');
  var listEl = document.getElementById('iaList');
  var playerHost = document.getElementById('iaPlayerHost');
  var emptyEl = document.getElementById('iaEmpty');
  var hasTracks = tracks.length > 0;
  if (!hasTracks && emptyEl) {
    emptyEl.hidden = false;
  }
  if (!hasTracks && playerHost) {
    playerHost.hidden = true;
  }

  var audio = document.createElement('audio');
  audio.preload = 'auto';
  audio.setAttribute('playsinline', '');
  audio.setAttribute('webkit-playsinline', '');
  audio.volume = 1;
  var currentId = 0;
  var raf = 0;
  var seekDragging = false;
  var ui = { btn: null, wave: null, timeEl: null, seek: null, load: null };
  var AUTO_KEY = 'ame-ia-autoplay-next-v1';
  var SESSION_KEY = 'ame-ia-session-v1';
  var persistLast = 0;
  var restoring = false;

  function fmt(sec) {
    sec = Math.max(0, Math.floor(sec || 0));
    var m = Math.floor(sec / 60);
    var s = sec % 60;
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
  }

  function find(id) {
    for (var i = 0; i < tracks.length; i++) {
      if (tracks[i].id === id) {
        return tracks[i];
      }
    }
    return null;
  }

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }

  function autoNextOn() {
    try {
      if (localStorage.getItem(AUTO_KEY) === '0') {
        return false;
      }
    } catch (e) { /* ignore */ }
    return true;
  }

  function setAutoNext(on) {
    try {
      localStorage.setItem(AUTO_KEY, on ? '1' : '0');
    } catch (e) { /* ignore */ }
    syncAutoNextToggle();
  }

  function trackIndexById(id) {
    for (var i = 0; i < tracks.length; i++) {
      if (tracks[i].id === id) {
        return i;
      }
    }
    return -1;
  }

  function nextTrackId(id) {
    if (!tracks.length) {
      return 0;
    }
    var idx = trackIndexById(id);
    if (idx < 0) {
      return tracks[0].id;
    }
    var ni = idx + 1;
    if (ni >= tracks.length) {
      ni = 0;
    }
    return tracks[ni].id;
  }

  function persistPlayback(forcePlaying) {
    if (restoring || !currentId) {
      return;
    }
    var playing = typeof forcePlaying === 'boolean' ? forcePlaying : !audio.paused;
    try {
      localStorage.setItem(SESSION_KEY, JSON.stringify({
        id: currentId,
        t: audio.currentTime || 0,
        playing: playing,
        ts: Date.now()
      }));
    } catch (e) { /* ignore */ }
  }

  function clearPlaybackSession() {
    try {
      localStorage.removeItem(SESSION_KEY);
    } catch (e) { /* ignore */ }
  }

  function syncAutoNextToggle() {
    var btn = document.getElementById('iaAutoNextBtn');
    if (!btn) {
      return;
    }
    var on = autoNextOn();
    btn.classList.toggle('is-on', on);
    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    btn.textContent = on ? 'একের পর এক: চালু' : 'একের পর এক: বন্ধ';
  }

  function ensureAutoNextToggle() {
    if (!panel || document.getElementById('iaAutoNextBtn')) {
      return;
    }
    var head = panel.querySelector('.ia-head');
    if (!head) {
      return;
    }
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ia-autoplay-toggle';
    btn.id = 'iaAutoNextBtn';
    btn.title = 'একটি শেষ হলে পরের অডিও স্বয়ংক্রিয় চলবে';
    btn.addEventListener('click', function () {
      setAutoNext(!autoNextOn());
    });
    var close = head.querySelector('.ia-close');
    if (close) {
      head.insertBefore(btn, close);
    } else {
      head.appendChild(btn);
    }
    syncAutoNextToggle();
  }

  function playNextAutomatic() {
    if (!autoNextOn() || !tracks.length) {
      return;
    }
    playTrack(nextTrackId(currentId));
  }

  function restorePlaybackIfNeeded() {
    if (!hasTracks) {
      return;
    }
    try {
      var raw = localStorage.getItem(SESSION_KEY);
      if (!raw) {
        return;
      }
      var s = JSON.parse(raw);
      if (!s || !s.id || !s.playing) {
        return;
      }
      if (Date.now() - (s.ts || 0) > 45 * 60 * 1000) {
        clearPlaybackSession();
        return;
      }
      if (!find(s.id)) {
        return;
      }
      restoring = true;
      playTrack(s.id);
      var seekTo = parseFloat(s.t) || 0;
      var onMeta = function () {
        audio.removeEventListener('loadedmetadata', onMeta);
        if (seekTo > 0.5) {
          audio.currentTime = Math.min(seekTo, Math.max(0, durMax() - 0.25));
        }
        audio.play().catch(function () {});
        restoring = false;
        persistPlayback(true);
      };
      audio.addEventListener('loadedmetadata', onMeta);
    } catch (e2) {
      restoring = false;
    }
  }

  function setLoading(on) {
    if (fab) {
      fab.classList.toggle('ia-fab--loading', on);
    }
    if (ui.btn) {
      ui.btn.classList.toggle('ia-play--loading', on);
    }
    if (ui.load) {
      ui.load.hidden = !on;
    }
  }

  function durMax() {
    var d = audio.duration;
    if (d && isFinite(d) && d > 0) {
      return d;
    }
    var t = find(currentId);
    return t && t.duration ? t.duration : 1;
  }

  function updateSeekFromAudio() {
    if (!ui.seek || seekDragging) {
      return;
    }
    var max = durMax();
    ui.seek.max = String(max);
    ui.seek.value = String(Math.min(max, audio.currentTime || 0));
    if (ui.timeEl) {
      ui.timeEl.textContent = fmt(audio.currentTime) + ' / ' + fmt(max);
    }
  }

  function bindSeek(seek) {
    seek.addEventListener('input', function () {
      seekDragging = true;
      var v = parseFloat(seek.value) || 0;
      audio.currentTime = v;
      if (ui.timeEl) {
        ui.timeEl.textContent = fmt(v) + ' / ' + fmt(durMax());
      }
    });
    seek.addEventListener('change', function () {
      seekDragging = false;
    });
    seek.addEventListener('pointerdown', function () {
      seekDragging = true;
    });
    seek.addEventListener('pointerup', function () {
      seekDragging = false;
    });
  }

  function tick() {
    updateSeekFromAudio();
    if (!audio.paused) {
      raf = requestAnimationFrame(tick);
    }
  }

  audio.addEventListener('loadstart', function () {
    setLoading(true);
  });
  audio.addEventListener('waiting', function () {
    setLoading(true);
  });
  audio.addEventListener('canplay', function () {
    setLoading(false);
    updateSeekFromAudio();
  });
  audio.addEventListener('playing', function () {
    setLoading(false);
  });
  audio.addEventListener('loadedmetadata', function () {
    updateSeekFromAudio();
  });
  audio.addEventListener('timeupdate', function () {
    updateSeekFromAudio();
    var now = Date.now();
    if (now - persistLast > 2500) {
      persistLast = now;
      persistPlayback();
    }
  });
  audio.addEventListener('play', function () {
    if (ui.btn) {
      ui.btn.textContent = '⏸';
    }
    if (ui.wave) {
      ui.wave.classList.add('is-playing');
    }
    cancelAnimationFrame(raf);
    tick();
    persistPlayback(true);
  });
  audio.addEventListener('pause', function () {
    if (ui.btn) {
      ui.btn.textContent = '▶';
    }
    if (ui.wave) {
      ui.wave.classList.remove('is-playing');
    }
    cancelAnimationFrame(raf);
    updateSeekFromAudio();
    persistPlayback(false);
  });
  audio.addEventListener('ended', function () {
    cancelAnimationFrame(raf);
    if (autoNextOn() && tracks.length > 0) {
      playNextAutomatic();
      return;
    }
    if (ui.btn) {
      ui.btn.textContent = '▶';
    }
    if (ui.wave) {
      ui.wave.classList.remove('is-playing');
    }
    clearPlaybackSession();
  });
  audio.addEventListener('error', function () {
    setLoading(false);
    if (ui.timeEl) {
      ui.timeEl.textContent = 'লোড ব্যর্থ — আবার চেষ্টা করুন';
    }
  });

  function renderPlayer(track) {
    if (!playerHost) {
      return;
    }
    playerHost.hidden = false;
    var dur = track.duration || 1;
    playerHost.innerHTML =
      '<p class="ia-now-title">' + escapeHtml(track.title) + '</p>' +
      '<p class="ia-now-meta">#' + track.serial + ' · ' + escapeHtml(track.speaker || track.category_label) + '</p>' +
      '<div class="ia-seek-wrap">' +
      '<input type="range" class="ia-seek" id="iaSeek" min="0" max="' + dur + '" value="0" step="0.25" ' +
      'aria-label="সামনে বা পিছনে টানুন">' +
      '<span class="ia-load-spin" id="iaLoadSpin" hidden aria-hidden="true"></span></div>' +
      '<div class="ia-controls">' +
      '<button type="button" class="ia-play" id="iaMainPlay" aria-label="প্লে/পজ">▶</button>' +
      '<div class="ia-wave" id="iaWave" aria-hidden="true">' +
      '<span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>' +
      '<span class="ia-time" id="iaTime">00:00 / ' + fmt(dur) + '</span></div>';
    ui.btn = document.getElementById('iaMainPlay');
    ui.wave = document.getElementById('iaWave');
    ui.timeEl = document.getElementById('iaTime');
    ui.seek = document.getElementById('iaSeek');
    ui.load = document.getElementById('iaLoadSpin');
    bindSeek(ui.seek);
    ui.btn.addEventListener('click', function () {
      if (audio.paused) {
        audio.play().catch(function () {});
      } else {
        audio.pause();
      }
    });
  }

  function renderList() {
    if (!listEl) {
      return;
    }
    listEl.innerHTML = '';
    tracks.forEach(function (t) {
      var li = document.createElement('li');
      var loadingCls = t.id === currentId && !audio.paused && audio.readyState < 3 ? ' ia-track--loading' : '';
      li.className = 'ia-track' + (t.id === currentId ? ' is-active' : '') + loadingCls;
      li.setAttribute('role', 'listitem');
      li.innerHTML =
        '<span class="ia-serial">#' + t.serial + '</span>' +
        '<div><p class="ia-track-title">' + escapeHtml(t.title) + '</p>' +
        '<p class="ia-track-sub">' + escapeHtml(t.speaker || t.category_label) + '</p></div>' +
        '<button type="button" class="ia-mini-play" aria-label="চালান">▶</button>';
      li.addEventListener('click', function (ev) {
        if (ev.target && ev.target.classList.contains('ia-mini-play')) {
          ev.stopPropagation();
        }
        playTrack(t.id);
      });
      listEl.appendChild(li);
    });
  }

  function playTrack(id) {
    var t = find(id);
    if (!t) {
      return;
    }
    if (currentId === id && !audio.paused) {
      audio.pause();
      renderList();
      return;
    }
    if (currentId !== id) {
      audio.pause();
      setLoading(true);
      audio.src = t.url;
      currentId = id;
      renderPlayer(t);
      renderList();
    }
    audio.play().catch(function () {
      setLoading(false);
    });
  }

  function openPanel() {
    panel.hidden = false;
    fab.setAttribute('aria-expanded', 'true');
    ensureAutoNextToggle();
  }
  function closePanel() {
    panel.hidden = true;
    fab.setAttribute('aria-expanded', 'false');
  }

  var POS_KEY = 'ame-ia-fab-pos-v1';
  var fabDrag = {
    active: false,
    moved: false,
    suppressClick: false,
    startX: 0,
    startY: 0,
    originLeft: 0,
    originTop: 0,
    w: 0,
    h: 0
  };

  function isDocked() {
    return root.classList.contains('ia-home--dock');
  }

  function clamp(n, min, max) {
    return Math.min(max, Math.max(min, n));
  }

  function applyFabPos(left, top) {
    var pad = 8;
    var vw = window.innerWidth || document.documentElement.clientWidth || 360;
    var vh = window.innerHeight || document.documentElement.clientHeight || 640;
    var w = fabDrag.w || (fab ? fab.offsetWidth : 120);
    var h = fabDrag.h || (fab ? fab.offsetHeight : 48);
    var x = clamp(left, pad, Math.max(pad, vw - w - pad));
    var y = clamp(top, pad, Math.max(pad, vh - h - pad));
    root.style.right = 'auto';
    root.style.bottom = 'auto';
    root.style.left = x + 'px';
    root.style.top = y + 'px';
    root.classList.add('ia-home--moved');
    return { x: x, y: y };
  }

  function saveFabPos(x, y) {
    try {
      localStorage.setItem(POS_KEY, JSON.stringify({ x: x, y: y }));
    } catch (e) { /* ignore */ }
  }

  function loadFabPos() {
    if (!isDocked() || !fab) {
      return;
    }
    try {
      var raw = localStorage.getItem(POS_KEY);
      if (!raw) {
        return;
      }
      var p = JSON.parse(raw);
      if (!p || typeof p.x !== 'number' || typeof p.y !== 'number') {
        return;
      }
      var rect = fab.getBoundingClientRect();
      fabDrag.w = rect.width;
      fabDrag.h = rect.height;
      applyFabPos(p.x, p.y);
    } catch (e2) { /* ignore */ }
  }

  function onFabDragMove(clientX, clientY) {
    if (!fabDrag.active) {
      return;
    }
    var dx = clientX - fabDrag.startX;
    var dy = clientY - fabDrag.startY;
    if (!fabDrag.moved && (Math.abs(dx) > 8 || Math.abs(dy) > 8)) {
      fabDrag.moved = true;
      fabDrag.suppressClick = true;
      if (fab) {
        fab.classList.add('ia-fab--dragging');
        fab.setPointerCapture(fabDrag.pointerId);
      }
      if (panel && !panel.hidden) {
        closePanel();
      }
    }
    if (!fabDrag.moved) {
      return;
    }
    var pos = applyFabPos(fabDrag.originLeft + dx, fabDrag.originTop + dy);
    saveFabPos(pos.x, pos.y);
  }

  function endFabDrag() {
    if (!fabDrag.active) {
      return;
    }
    fabDrag.active = false;
    if (fab) {
      fab.classList.remove('ia-fab--dragging');
      if (fab.hasPointerCapture && fab.hasPointerCapture(fabDrag.pointerId)) {
        try {
          fab.releasePointerCapture(fabDrag.pointerId);
        } catch (e) { /* ignore */ }
      }
    }
    fabDrag.pointerId = 0;
  }

  if (isDocked() && fab) {
    loadFabPos();
    fab.addEventListener('pointerdown', function (ev) {
      if (ev.button !== 0 && ev.pointerType !== 'touch') {
        return;
      }
      var rect = fab.getBoundingClientRect();
      fabDrag.active = true;
      fabDrag.moved = false;
      fabDrag.startX = ev.clientX;
      fabDrag.startY = ev.clientY;
      fabDrag.originLeft = rect.left;
      fabDrag.originTop = rect.top;
      fabDrag.w = rect.width;
      fabDrag.h = rect.height;
      fabDrag.pointerId = ev.pointerId;
    });
    fab.addEventListener('pointermove', function (ev) {
      onFabDragMove(ev.clientX, ev.clientY);
    });
    fab.addEventListener('pointerup', function () {
      endFabDrag();
    });
    fab.addEventListener('pointercancel', function () {
      endFabDrag();
    });
    window.addEventListener('resize', function () {
      if (!root.classList.contains('ia-home--moved')) {
        return;
      }
      var l = parseFloat(root.style.left) || 0;
      var t = parseFloat(root.style.top) || 0;
      var pos = applyFabPos(l, t);
      saveFabPos(pos.x, pos.y);
    });
  }

  if (fab && panel) {
    fab.addEventListener('click', function () {
      if (fabDrag.suppressClick) {
        fabDrag.suppressClick = false;
        return;
      }
      if (panel.hidden) {
        openPanel();
        if (hasTracks && !currentId && tracks[0]) {
          playTrack(tracks[0].id);
        }
      } else {
        closePanel();
      }
    });
  }
  if (closeBtn) {
    closeBtn.addEventListener('click', function () {
      closePanel();
    });
  }

  document.body.appendChild(audio);

  if (hasTracks) {
    renderList();
    ensureAutoNextToggle();
    restorePlaybackIfNeeded();
  }
})();