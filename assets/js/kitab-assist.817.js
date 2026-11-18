/**
 * Kitab assist 8.17 — light name sense + spelling chips, and voice playback.
 * No extra servers. Uses known names from this shop + speechSynthesis.
 */
(function () {
  'use strict';
  if (window.__khAssist817) return;
  window.__khAssist817 = 1;

  function dist(a, b) {
    a = String(a || '');
    b = String(b || '');
    if (a === b) return 0;
    if (Math.abs(a.length - b.length) > 2) return 9;
    var i, j, prev, cur, tmp;
    prev = [];
    for (j = 0; j <= b.length; j++) prev[j] = j;
    for (i = 1; i <= a.length; i++) {
      cur = [i];
      for (j = 1; j <= b.length; j++) {
        cur[j] = Math.min(
          prev[j] + 1,
          cur[j - 1] + 1,
          prev[j - 1] + (a.charAt(i - 1) === b.charAt(j - 1) ? 0 : 1)
        );
      }
      tmp = prev;
      prev = cur;
      cur = tmp;
    }
    return prev[b.length];
  }
  function norm(s) {
    return String(s || '').replace(/\s+/g, ' ').trim();
  }

  var known = { titles: [], authors: [], publishers: [], books: [], people: [] };
  var knownEl = document.getElementById('kh-known');
  if (knownEl) {
    try {
      var parsed = JSON.parse(knownEl.textContent || '{}');
      known.titles = parsed.titles || [];
      known.authors = parsed.authors || [];
      known.publishers = parsed.publishers || [];
      known.books = parsed.books || [];
      known.people = parsed.people || [];
    } catch (e) {}
  }
  var SEED_TITLES = ['নূরানী কায়দা', 'আমপারা', 'কুরআন শরীফ', 'সহীহ বুখারী', 'সহীহ মুসলিম', 'রিয়াদুস সালিহীন', 'তাফসীরে ইবনে কাসীর', 'হাদিস শরীফ', 'পারা ৩০'];
  var SEED_AUTHORS = ['ইমাম বুখারী', 'ইমাম মুসলিম', 'ইমাম নববী', 'ইবনে কাসীর'];
  function uniq(list) {
    var seen = {};
    var out = [];
    list.forEach(function (x) {
      var k = norm(x);
      if (!k || seen[k]) return;
      seen[k] = 1;
      out.push(k);
    });
    return out;
  }
  known.titles = uniq(SEED_TITLES.concat(known.titles));
  known.authors = uniq(SEED_AUTHORS.concat(known.authors));
  known.publishers = uniq(known.publishers);
  known.people = uniq(known.people);
  var ALL_NAMES = uniq(known.titles.concat(known.authors, known.publishers, known.people));

  function rank(q, list) {
    q = norm(q).toLowerCase();
    if (q.length < 1) return [];
    var hits = [];
    list.forEach(function (item) {
      var low = item.toLowerCase();
      var d = dist(q, low);
      var score = 9;
      if (low === q) score = 0;
      else if (low.indexOf(q) === 0) score = 1;
      else if (low.indexOf(q) !== -1) score = 2;
      else if (q.length >= 3 && d <= 2) score = 3 + d;
      if (score < 9) hits.push({ item: item, score: score, d: d });
    });
    hits.sort(function (a, b) { return a.score - b.score; });
    var out = [];
    var seen = {};
    hits.forEach(function (h) {
      if (seen[h.item]) return;
      seen[h.item] = 1;
      out.push(h);
    });
    return out.slice(0, 6);
  }
  function fillBook(title) {
    var hit = (known.books || []).filter(function (b) {
      return norm(b.title).toLowerCase() === norm(title).toLowerCase();
    })[0];
    if (!hit) return;
    var author = document.querySelector('#khBookForm [name="author"]');
    var pub = document.querySelector('#khBookForm [name="publisher"]');
    var sub = document.querySelector('#khBookForm [name="subject"]');
    if (author && hit.author && !norm(author.value)) author.value = hit.author;
    if (pub && hit.publisher && !norm(pub.value)) pub.value = hit.publisher;
    if (sub && hit.subject) sub.value = hit.subject;
  }
  function attachField(input, list, box, isTitle) {
    if (!input || !box) return;
    input.setAttribute('spellcheck', 'true');
    input.setAttribute('lang', 'bn');
    input.setAttribute('autocomplete', 'off');
    var t = 0;
    function draw() {
      var q = norm(input.value);
      var hits = rank(q, list);
      box.innerHTML = '';
      if (!q || !hits.length) return;
      hits.forEach(function (h) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'kh-smart-chip' + (h.d > 0 && h.d <= 2 && h.score >= 3 ? ' fix' : '');
        b.textContent = (h.d > 0 && h.d <= 2 && h.score >= 3 ? 'সংশোধন: ' : '') + h.item;
        b.addEventListener('mousedown', function (ev) {
          ev.preventDefault();
          input.value = h.item;
          box.innerHTML = '';
          if (isTitle) fillBook(h.item);
        });
        box.appendChild(b);
      });
    }
    input.addEventListener('input', function () {
      clearTimeout(t);
      t = setTimeout(draw, 80);
    });
    input.addEventListener('blur', function () {
      var q = norm(input.value);
      if (q.length < 4) return;
      var hits = rank(q, list);
      var fix = hits.filter(function (h) { return h.d === 1; })[0];
      if (fix && fix.item !== q) {
        input.value = fix.item;
        if (isTitle) fillBook(fix.item);
      } else if (isTitle) {
        fillBook(q);
      }
    });
  }

  var form = document.getElementById('khBookForm');
  if (form) {
    var title = form.querySelector('[name="title"]');
    var author = form.querySelector('[name="author"]');
    var pub = form.querySelector('[name="publisher"]');
    attachField(title, known.titles, document.getElementById('khHintTitle'), true);
    attachField(author, uniq(known.authors.concat(known.people)), document.getElementById('khHintAuthor'), false);
    attachField(pub, known.publishers, document.getElementById('khHintPub'), false);
    ['notes', 'source', 'location_note', 'language'].forEach(function (name) {
      var el = form.querySelector('[name="' + name + '"]');
      if (el) {
        el.setAttribute('spellcheck', 'true');
        el.setAttribute('lang', 'bn');
      }
    });
    var open = document.getElementById('khOpenPage');
    var pageHint = document.getElementById('khHintPage');
    function wirePaper(ta) {
      if (!ta || ta.getAttribute('data-kh-name')) return;
      ta.setAttribute('data-kh-name', '1');
      ta.setAttribute('spellcheck', 'true');
      ta.setAttribute('lang', 'bn');
      var wait = 0;
      function draw() {
        if (!pageHint) return;
        var q = lastToken(ta.value);
        var hits = rank(q, ALL_NAMES);
        pageHint.innerHTML = '';
        if (!q || q.length < 2 || !hits.length) return;
        hits.forEach(function (h) {
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'kh-smart-chip' + (h.d > 0 && h.d <= 2 && h.score >= 3 ? ' fix' : '');
          b.textContent = (h.d > 0 && h.d <= 2 && h.score >= 3 ? 'সংশোধন: ' : '') + h.item;
          b.addEventListener('mousedown', function (ev) {
            ev.preventDefault();
            ta.value = String(ta.value || '').replace(/[^\s,।.!?;:()«»"']+$/, h.item);
            pageHint.innerHTML = '';
          });
          pageHint.appendChild(b);
        });
      }
      ta.addEventListener('input', function () {
        clearTimeout(wait);
        wait = setTimeout(draw, 80);
      });
      ta.addEventListener('blur', function () {
        var next = fixNamesInText(ta.value);
        if (next !== ta.value) ta.value = next;
        if (pageHint) pageHint.innerHTML = '';
      });
    }
    if (open) {
      var mo = new MutationObserver(function () {
        wirePaper(open.querySelector('.kh-paper-text'));
      });
      mo.observe(open, { childList: true, subtree: true });
      wirePaper(open.querySelector('.kh-paper-text'));
    }
  }

  function lastToken(s) {
    var m = String(s || '').match(/[^\s,।.!?;:()«»"']+$/);
    return m ? m[0] : '';
  }
  function fixNamesInText(text) {
    return String(text || '').replace(/[^\s,।.!?;:()«»"']+/g, function (tok) {
      if (tok.length < 4) return tok;
      var hits = rank(tok, ALL_NAMES);
      var fix = hits.filter(function (h) { return h.d === 1 && h.item !== tok; })[0];
      return fix ? fix.item : tok;
    });
  }

  var speaking = false;
  var queue = [];
  function refreshVoices() {
    return window.speechSynthesis ? speechSynthesis.getVoices() || [] : [];
  }
  if (window.speechSynthesis) {
    speechSynthesis.getVoices();
    if (speechSynthesis.addEventListener) {
      speechSynthesis.addEventListener('voiceschanged', function () {
        speechSynthesis.getVoices();
      });
    } else {
      speechSynthesis.onvoiceschanged = function () {
        speechSynthesis.getVoices();
      };
    }
  }
  function voiceBlob(v) {
    return ((v && v.name) || '') + ' ' + ((v && v.voiceURI) || '') + ' ' + ((v && v.lang) || '');
  }
  function isFemaleVoice(v) {
    return /female|woman|girl|zira|samantha|victoria|karen|moira|tessa|heera|kalpana|padma|susan|hazel|fiona|নারী|মহিলা|মহিলা/.test(voiceBlob(v).toLowerCase());
  }
  function isMaleVoice(v) {
    var n = voiceBlob(v).toLowerCase();
    if (isFemaleVoice(v)) return false;
    return /male|man\b|boy|david|mark|daniel|ravi|george|fred|richard|purush|পুরুষ|wavenet-[bd]|standard-[bd]|neural2-[bd]|news-[bd]/.test(n);
  }
  function scoreVoice(v, lang) {
    var n = voiceBlob(v).toLowerCase();
    var vl = ((v && v.lang) || '').toLowerCase();
    var score = 0;
    if (isFemaleVoice(v)) score -= 260;
    if (isMaleVoice(v)) score += 110;
    if (vl.indexOf(lang) === 0) score += 130;
    else if (lang === 'bn' && /^bn/.test(vl)) score += 130;
    else if (lang === 'bn' && /^hi/.test(vl)) score += 60;
    else if (lang === 'en' && /^en/.test(vl)) score += 70;
    if (/bengali|bangla|বাংলা/.test(n)) score += 55;
    if (v && v.default) score += 2;
    return score;
  }
  function pickVoice(lang) {
    var list = refreshVoices();
    if (!list.length) return null;
    var want = (lang || 'bn').toLowerCase();
    var best = null;
    var bestS = -9999;
    list.forEach(function (v) {
      var s = scoreVoice(v, want);
      if (s > bestS) {
        bestS = s;
        best = v;
      }
    });
    return best;
  }
  function whenVoices(cb) {
    if (refreshVoices().length) {
      cb();
      return;
    }
    var n = 0;
    var t = setInterval(function () {
      n += 1;
      if (refreshVoices().length || n > 25) {
        clearInterval(t);
        cb();
      }
    }, 80);
  }
  function chunks(text) {
    var raw = String(text || '').replace(/\s+/g, ' ').trim();
    if (!raw) return [];
    var parts = raw.split(/(?<=[।!?\n])/);
    var out = [];
    var buf = '';
    parts.forEach(function (p) {
      if ((buf + p).length > 180) {
        if (buf) out.push(buf);
        buf = p;
      } else buf += p;
    });
    if (buf) out.push(buf);
    return out;
  }
  function stopTalk() {
    speaking = false;
    queue = [];
    if (window.speechSynthesis) speechSynthesis.cancel();
    var st = document.getElementById('khVoiceStatus') || document.getElementById('krStatus');
    if (st && st.id === 'khVoiceStatus') st.textContent = '';
  }
  function talkNext(lang, status) {
    if (!speaking || !queue.length) {
      speaking = false;
      if (status) status.textContent = 'পড়া শেষ।';
      return;
    }
    var u = new SpeechSynthesisUtterance(queue.shift());
    var v = pickVoice(lang);
    if (v) u.voice = v;
    u.lang = (v && v.lang) || (lang === 'en' ? 'en-US' : 'bn-BD');
    u.rate = 0.92;
    u.pitch = isMaleVoice(v) ? 0.95 : 0.78;
    u.onend = function () { talkNext(lang, status); };
    u.onerror = function () { talkNext(lang, status); };
    speechSynthesis.speak(u);
  }
  function talk(text, lang, status) {
    if (!window.speechSynthesis) {
      if (status) status.textContent = 'এই ফোনে ভয়েস চলে না।';
      return;
    }
    var body = String(text || '').replace(/\s+/g, ' ').trim();
    if (!body) {
      if (status) status.textContent = 'শোনার মতো বইয়ের লেখা নেই।';
      return;
    }
    stopTalk();
    queue = chunks(body);
    speaking = true;
    if (status) status.textContent = 'পুরুষ কণ্ঠে বইয়ের লেখা পড়া হচ্ছে…';
    whenVoices(function () {
      if (!speaking) return;
      talkNext(lang || 'bn', status);
    });
  }
  function langOf() {
    var l = document.querySelector('#khBookForm [name="language"]');
    var v = l ? String(l.value || '') : '';
    return /en|ইং/i.test(v) ? 'en' : 'bn';
  }
  function studioText() {
    var all = [];
    document.querySelectorAll('textarea[name="leaf_text[]"]').forEach(function (ta) {
      if (ta.value.trim()) all.push(ta.value.trim());
    });
    var open = document.querySelector('#khOpenPage .kh-paper-text');
    if (open && open.value.trim() && all.indexOf(open.value.trim()) === -1) all.push(open.value.trim());
    return all.join('\n\n');
  }

  var play = document.getElementById('khVoicePlay');
  var playAll = document.getElementById('khVoiceAll');
  var stop = document.getElementById('khVoiceStop');
  var vstat = document.getElementById('khVoiceStatus');
  if (play) {
    play.addEventListener('click', function () {
      var ta = document.querySelector('#khOpenPage .kh-paper-text');
      talk(ta ? ta.value : '', langOf(), vstat);
    });
  }
  if (playAll) {
    playAll.addEventListener('click', function () {
      talk(studioText(), langOf(), vstat);
    });
  }
  if (stop) stop.addEventListener('click', stopTalk);

  var dataEl = document.getElementById('kr-data');
  if (dataEl) {
    var D = {};
    try { D = JSON.parse(dataEl.textContent || '{}'); } catch (e) {}
    var krPlay = document.getElementById('krVoicePlay');
    var krAll = document.getElementById('krVoiceAll');
    var krStop = document.getElementById('krVoiceStop');
    var krSt = document.getElementById('krStatus');
    function pageText() {
      var box = document.getElementById('krOcr');
      return (box && box.textContent) || D.ocr || '';
    }
    function allText() {
      var list = D.pages || [];
      var bits = [];
      list.forEach(function (pg) {
        var t = norm(pg.text || '');
        if (t) bits.push('পেজ ' + pg.no + '। ' + t);
      });
      var now = norm(pageText());
      if (!bits.length && now) bits.push(now);
      return bits.join('\n\n');
    }
    var bookLang = /en|ইং/i.test(String(D.language || '')) ? 'en' : 'bn';
    if (krPlay) krPlay.addEventListener('click', function () { talk(pageText(), bookLang, krSt); });
    if (krAll) krAll.addEventListener('click', function () { talk(allText(), bookLang, krSt); });
    if (krStop) krStop.addEventListener('click', stopTalk);
    if (window.speechSynthesis) speechSynthesis.getVoices();
  }
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) stopTalk();
  });
})();
