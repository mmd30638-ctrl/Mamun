'use strict';

(function () {
  var customerBtn = document.getElementById('smtPosCustomer');
  var payBtn = document.getElementById('smtPosPay');
  var voiceBtn = document.getElementById('smtPosVoice');
  var camBtn = document.getElementById('smtPosCam');
  var voiceSheet = document.getElementById('smtVoiceSheet');
  var camSheet = document.getElementById('smtCamSheet');
  var voiceStatus = document.getElementById('smtVoiceStatus');
  var voiceHeard = document.getElementById('smtVoiceHeard');
  var voiceInput = document.getElementById('smtVoiceText');
  var listenBtn = document.getElementById('smtVoiceListen');
  var applyBtn = document.getElementById('smtVoiceApply');
  var video = document.getElementById('smtCamVideo');
  var fileInput = document.getElementById('smtCamFile');
  var stream = null;
  var scanTimer = null;
  var recActive = null;

  function openSheet(el) {
    if (el) {
      el.classList.add('open');
      el.setAttribute('aria-hidden', 'false');
    }
  }

  function closeSheet(el) {
    if (el) {
      el.classList.remove('open');
      el.setAttribute('aria-hidden', 'true');
    }
  }

  function setStatus(text) {
    if (voiceStatus) {
      voiceStatus.textContent = text;
    }
  }

  function bnDigits(text) {
    var map = { '০': '0', '১': '1', '২': '2', '৩': '3', '৪': '4', '৫': '5', '৬': '6', '৭': '7', '৮': '8', '৯': '9' };
    return String(text || '').replace(/[০-৯]/g, function (ch) { return map[ch] || ch; });
  }

  function parseBnSale(text) {
    var raw = bnDigits(String(text || '').replace(/\s+/g, ' ').trim());
    var work = raw.replace(/[।.!?,]+/g, ' ');
    work = work.replace(/\s*(?:বিক্রি করো|বিক্রি করুন|বিক্রি কর|সেল করো|সেল কর|দাও|দিয়ে দাও|করে দাও|করে)\s*/g, ' ').trim();
    var customer = '';
    var m = work.match(/^(.+?)কে\s+/);
    if (m) {
      customer = m[1].trim();
      work = work.slice(m[0].length).trim();
    }
    var qty = 1;
    var price = null;
    m = work.match(/(\d+(?:\.\d+)?)\s*(?:টা|টি|পিস|কেজি|kg)?/);
    if (m) {
      qty = Number(m[1]) || 1;
      work = (work.slice(0, m.index) + ' ' + work.slice(m.index + m[0].length)).trim();
    }
    m = work.match(/(\d+(?:\.\d+)?)\s*টাকা/);
    if (m) {
      price = Number(m[1]);
      work = work.replace(/(\d+(?:\.\d+)?)\s*টাকা/g, ' ').trim();
    }
    var product = work.replace(/^(এবং|আর|একটা|একটি)\s+/, '').replace(/\s+/g, ' ').trim();
    return { raw: raw, customer: customer, product: product, qty: qty > 0 ? qty : 1, price: price };
  }

  function matchCustomer(name) {
    if (!name) {
      return null;
    }
    var sel = document.getElementById('customer_id');
    if (!sel) {
      return null;
    }
    var want = name.replace(/\s+/g, '').toLowerCase();
    var i;
    for (i = 0; i < sel.options.length; i++) {
      var opt = sel.options[i];
      var label = ((opt.dataset && opt.dataset.name) || opt.textContent || '').replace(/\s+/g, '').toLowerCase();
      if (opt.value && label.indexOf(want) !== -1) {
        return { id: opt.value, name: (opt.dataset && opt.dataset.name) || opt.textContent };
      }
    }
    return null;
  }

  function searchLocalProduct(q) {
    if (!q || q.length < 1) {
      return Promise.resolve([]);
    }
    return fetch('search_product.php?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : []; })
      .catch(function () { return []; });
  }

  function fillSearch(code) {
    var input = document.getElementById('p-search');
    if (!input || !code) {
      return;
    }
    input.value = code;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    if (typeof searchProducts === 'function') {
      searchProducts();
    }
  }

  function applyVoiceResult(data) {
    var parsed = data.parsed || {};
    var customer = data.customer || null;
    var products = data.products || [];
    var qty = Number(parsed.qty || 1);
    var price = parsed.price;
    var product = products[0] || null;
    var bits = [];

    if (customer && document.getElementById('customer_id')) {
      var sel = document.getElementById('customer_id');
      sel.value = String(customer.id);
      sel.dispatchEvent(new Event('change', { bubbles: true }));
      var reg = document.getElementById('posRegisteredCustomerMode');
      if (reg) {
        reg.checked = true;
      }
      bits.push('ক্রেতা: ' + customer.name);
    } else if (parsed.customer) {
      var guestTab = document.getElementById('posTabGuest');
      if (guestTab) {
        guestTab.click();
      }
      setTimeout(function () {
        var g = document.getElementById('guestCustomerNameModal') || document.getElementById('guestCustomerName');
        if (g) {
          g.value = parsed.customer;
          g.dispatchEvent(new Event('input', { bubbles: true }));
        }
      }, 80);
      bits.push('ক্রেতা: ' + parsed.customer);
    }

    if (product && typeof addToCart === 'function') {
      var customPrice = price != null && Math.abs(Number(price) - Number(product.sale_price || 0)) > 0.009;
      if (customPrice && typeof addCustomItemToCart === 'function') {
        addCustomItemToCart(product.name, price, qty);
      } else {
        addToCart(product);
        if (qty > 1) {
          var qtyInputs = document.querySelectorAll('#cart-body input[type="number"]');
          var lastQty = qtyInputs[qtyInputs.length - 1];
          if (lastQty) {
            lastQty.value = String(qty);
            lastQty.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }
      }
      bits.push(product.name + ' × ' + qty + (price != null ? ' @ ' + price : ''));
    } else if (parsed.product && typeof addCustomItemToCart === 'function' && price != null) {
      addCustomItemToCart(parsed.product, price, qty);
      bits.push(parsed.product + ' × ' + qty + ' @ ' + price);
    } else if (parsed.product) {
      fillSearch(parsed.product);
      bits.push('সার্চ: ' + parsed.product + ' — তালিকা থেকে ট্যাপ করুন');
    }

    var note = document.getElementById('draftNote');
    if (note && parsed.raw) {
      note.value = parsed.raw;
    }

    setStatus(bits.length
      ? ('ড্রাফট তৈরি: ' + bits.join(' · ') + '। সেল হয়নি — নিচে বিক্রয় সম্পন্ন চাপুন।')
      : 'বোঝা যায়নি। এভাবে বলুন বা লিখুন: রহিমকে ৩টা নাট ৩০ টাকা করে বিক্রি করো।');
  }

  function runVoiceText(text) {
    text = String(text || '').trim();
    if (!text) {
      setStatus('আগে কিছু বলুন বা লিখুন।');
      return;
    }
    if (voiceHeard) {
      voiceHeard.textContent = 'শুনেছি: ' + text;
    }
    if (voiceInput) {
      voiceInput.value = text;
    }
    setStatus('পণ্য মিলিয়ে দেখছি…');

    var parsed = parseBnSale(text);
    var localCustomer = matchCustomer(parsed.customer);

    function finish(products) {
      applyVoiceResult({
        parsed: parsed,
        customer: localCustomer,
        products: products || []
      });
    }

    var token = window.AME_CSRF_TOKEN || '';
    fetch('/modules/smart/ask.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json; charset=UTF-8',
        'X-CSRF-TOKEN': token
      },
      body: JSON.stringify({ q: text, mode: 'voice' })
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (data && data.parsed) {
        if (!data.customer && localCustomer) {
          data.customer = localCustomer;
        }
        applyVoiceResult(data);
        return;
      }
      return searchLocalProduct(parsed.product).then(finish);
    }).catch(function () {
      searchLocalProduct(parsed.product).then(finish);
    });
  }

  function speechEngine() {
    return window.SpeechRecognition || window.webkitSpeechRecognition || null;
  }

  function startListen() {
    var SR = speechEngine();
    if (!SR) {
      setStatus('এই ফোনে মাইক চলে না। নিচে লিখে «ড্রাফট তৈরি» চাপুন। Chrome অ্যাপে খুললে মাইক চলবে।');
      if (voiceInput) {
        voiceInput.focus();
      }
      return;
    }
    if (recActive) {
      try { recActive.abort(); } catch (e) {}
      recActive = null;
    }
    var langs = ['bn-BD', 'bn-IN', 'bn'];
    var idx = 0;

    function tryLang() {
      var rec = new SR();
      rec.lang = langs[idx];
      rec.interimResults = true;
      rec.maxAlternatives = 3;
      rec.continuous = false;
      recActive = rec;
      if (voiceBtn) {
        voiceBtn.classList.add('listen');
      }
      rec.onresult = function (ev) {
        var last = ev.results[ev.results.length - 1];
        var text = (last && last[0] && last[0].transcript) || '';
        if (voiceHeard) {
          voiceHeard.textContent = text ? ('শুনছি: ' + text) : '';
        }
        if (last && last.isFinal && text) {
          runVoiceText(text);
        }
      };
      rec.onerror = function (ev) {
        var err = (ev && ev.error) || '';
        if (err === 'language-not-supported' && idx < langs.length - 1) {
          idx += 1;
          tryLang();
          return;
        }
        if (err === 'not-allowed') {
          setStatus('মাইকের অনুমতি দিন। ফোনের পারমিশন থেকে মাইক চালু করুন, অথবা নিচে লিখুন।');
        } else if (err === 'no-speech') {
          setStatus('কিছু শোনা যায়নি। আবার «শুনুন» চাপুন, অথবা লিখুন।');
        } else if (err === 'network') {
          setStatus('নেট লাগবে ভয়েসের জন্য। নেট না থাকলে নিচে লিখুন।');
        } else {
          setStatus('মাইক চলেনি। নিচে লিখে «ড্রাফট তৈরি» চাপুন।');
        }
        if (voiceInput) {
          voiceInput.focus();
        }
      };
      rec.onend = function () {
        recActive = null;
        if (voiceBtn) {
          voiceBtn.classList.remove('listen');
        }
      };
      try {
        rec.start();
        setStatus('শুনছি… এখন বলুন। যেমন: রহিমকে ৩টা নাট ৩০ টাকা করে বিক্রি করো।');
      } catch (e) {
        setStatus('মাইক চালু হয়নি। নিচে লিখে ড্রাফট করুন।');
        if (voiceInput) {
          voiceInput.focus();
        }
      }
    }

    tryLang();
  }

  function stopCam() {
    if (scanTimer) {
      clearInterval(scanTimer);
      scanTimer = null;
    }
    if (stream) {
      stream.getTracks().forEach(function (t) { t.stop(); });
      stream = null;
    }
    if (video) {
      video.srcObject = null;
    }
  }

  if (customerBtn) {
    customerBtn.addEventListener('click', function () {
      var tab = document.getElementById('posTabRegistered');
      if (tab) {
        tab.click();
        return;
      }
      var panel = document.querySelector('.pos-customer-panel');
      if (panel) {
        panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
  }

  if (payBtn) {
    payBtn.addEventListener('click', function () {
      var panel = document.querySelector('.pos-payment-panel') || document.getElementById('amePosPay') || document.getElementById('net-payable');
      if (panel) {
        panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
        panel.style.outline = '3px solid #0b7a74';
        setTimeout(function () { panel.style.outline = ''; }, 1600);
      }
    });
  }

  if (voiceBtn) {
    voiceBtn.addEventListener('click', function () {
      openSheet(voiceSheet);
      startListen();
    });
  }
  if (listenBtn) {
    listenBtn.addEventListener('click', startListen);
  }
  if (applyBtn) {
    applyBtn.addEventListener('click', function () {
      runVoiceText(voiceInput ? voiceInput.value : '');
    });
  }
  if (voiceInput) {
    voiceInput.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') {
        ev.preventDefault();
        runVoiceText(voiceInput.value);
      }
    });
  }
  var voiceClose = document.getElementById('smtVoiceClose');
  if (voiceClose) {
    voiceClose.addEventListener('click', function () {
      if (recActive) {
        try { recActive.abort(); } catch (e) {}
      }
      closeSheet(voiceSheet);
    });
  }
  if (voiceSheet) {
    voiceSheet.addEventListener('click', function (ev) {
      if (ev.target === voiceSheet) {
        closeSheet(voiceSheet);
      }
    });
  }

  function useCode(code) {
    if (!code) {
      return;
    }
    fillSearch(code);
    closeSheet(camSheet);
    stopCam();
  }

  function startDetector() {
    if (!video || !('BarcodeDetector' in window)) {
      return;
    }
    var det = new window.BarcodeDetector({ formats: ['qr_code', 'ean_13', 'ean_8', 'code_128', 'code_39'] });
    scanTimer = setInterval(function () {
      det.detect(video).then(function (codes) {
        if (codes && codes[0] && codes[0].rawValue) {
          useCode(codes[0].rawValue);
        }
      }).catch(function () {});
    }, 500);
  }

  if (camBtn) {
    camBtn.addEventListener('click', function () {
      openSheet(camSheet);
      if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(function (s) {
          stream = s;
          if (video) {
            video.srcObject = s;
            video.play();
          }
          startDetector();
        }).catch(function () {
          if (fileInput) {
            fileInput.click();
          }
        });
      } else if (fileInput) {
        fileInput.click();
      }
    });
  }

  if (fileInput) {
    fileInput.addEventListener('change', function () {
      var f = fileInput.files && fileInput.files[0];
      if (!f) {
        return;
      }
      if (!('BarcodeDetector' in window)) {
        alert('এই ফোনে অটো স্ক্যান নেই। কোডটা সার্চ বক্সে টাইপ করুন, অথবা Chrome ব্যবহার করুন।');
        return;
      }
      var url = URL.createObjectURL(f);
      var img = new Image();
      img.onload = function () {
        var det = new window.BarcodeDetector({ formats: ['qr_code', 'ean_13', 'ean_8', 'code_128'] });
        det.detect(img).then(function (codes) {
          URL.revokeObjectURL(url);
          if (codes && codes[0]) {
            useCode(codes[0].rawValue);
          } else {
            alert('কোড পড়া যায়নি। আলো বাড়িয়ে আবার চেষ্টা করুন।');
          }
        }).catch(function () {
          alert('স্ক্যান ব্যর্থ।');
        });
      };
      img.src = url;
    });
  }

  var camClose = document.getElementById('smtCamClose');
  if (camClose) {
    camClose.addEventListener('click', function () {
      closeSheet(camSheet);
      stopCam();
    });
  }
})();
