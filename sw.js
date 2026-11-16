// Aponshop offline service worker — visited pages + POS + payments
const cacheName = 'ame-pwa-v1098775';
const staticAssets = [
  './offline.html',
  './offline-fix.html',
  './offline-sale.html',
  './offline-payment.html',
  './offline-customers.html',
  './offline-products.html',
  './offline-product-add.html',
  './offline-purchase.html',
  './offline-expense.html',
  './offline-workshop.html',
  './offline-supplier-pay.html',
  './offline-transfer.html',
  './offline-pulse.html',
  './assets/css/theme.css',
  './assets/css/device-fit.css',
  './assets/css/fulldisplay-fix.css',
  './assets/css/premium-saas.css',
  './assets/css/pos-skins.css',
  './assets/css/pos-box-type.css',
  './assets/css/hdr-icons.css',
  './assets/css/ticker-voice.827.css',
  './assets/css/home-banner.css',
  './assets/css/inventory-first-home.710.css',
  './assets/css/pulse.css',
  './assets/css/workshop.css',
  './assets/css/agents.css',
  './assets/css/smart.css',
  './assets/css/responsive-fix.css',
  './assets/css/display-widgets.css',
  './assets/css/customer-payment.css',
  './assets/css/offline-app.css',
  './assets/css/submit-spinner.css',
  './assets/css/transfer-desk.css',
  './assets/css/app-shell.css',
  './assets/img/ma-logo.png',
  './assets/img/header-logo.png',
  './assets/img/icon-192.png',
  './assets/img/icon-512.png',
  './assets/img/icon-maskable-512.png',
  './assets/img/apple-touch-icon.png',
  './assets/js/app-install.js',
  './assets/js/display-widgets.js',
  './assets/js/offline-queue.js',
  './assets/js/offline-payment-queue.js',
  './assets/js/offline-pay-nav.js',
  './assets/js/offline-boot.js',
  './assets/js/pos-cart.js',
  './assets/js/pos-payment.js',
  './assets/js/double-submit-guard.js',
  './assets/js/edge-pulse.js',
  './assets/js/premium-saas.710.js',
  './assets/js/inventory-first-home.710.js',
  './assets/js/responsive-fix.js',
  './manifest.json'
];

function pathOf(url) {
  try {
    return new URL(url, self.location.origin).pathname || '';
  } catch (e) {
    return '';
  }
}

function isPaymentUrl(url) {
  const path = url.pathname || '';
  return path.indexOf('payment-receive.php') !== -1
    || path.indexOf('offline-payment.html') !== -1;
}

function isPosUrl(url) {
  const path = url.pathname || '';
  return path.indexOf('/modules/sales/pos.php') !== -1
    || path.indexOf('/offline-sale.html') !== -1
    || path.indexOf('/assets/js/pos-') !== -1
    || path.indexOf('/assets/css/pos-') !== -1;
}

function isHireListPath(path) {
  return path.indexOf('/modules/customers/hire/') !== -1
    || path.indexOf('/chuktis.php') !== -1
    || path.indexOf('/now.php') !== -1;
}

function isDownloadPath(path) {
  return path === '/download.php'
    || path === '/download_update.php'
    || path.indexOf('/downloads/') === 0
    || /\.zip$/i.test(path);
}

function isAuthPath(path) {
  return path === '/login.php'
    || path === '/logout.php'
    || path.indexOf('forgot_password') !== -1
    || path.indexOf('reset_password') !== -1
    || path.indexOf('process_login') !== -1
    || path.indexOf('process_forgot') !== -1
    || path.indexOf('process_password') !== -1
    || path.indexOf('process_new_password') !== -1;
}

function isWritePath(path) {
  return path.indexOf('process_sale.php') !== -1
    || path.indexOf('process_payment.php') !== -1
    || path.indexOf('process_purchase.php') !== -1
    || path.indexOf('process_product.php') !== -1
    || path.indexOf('process_pay.php') !== -1
    || path.indexOf('process_transfer.php') !== -1
    || path.indexOf('job_process.php') !== -1
    || path.indexOf('factory_reset') !== -1
    || path.indexOf('backup_download') !== -1
    || path.indexOf('/actions/process') !== -1;
}

function shouldCache(url, res) {
  const path = url.pathname || '';
  const cacheControl = (res && res.headers.get('Cache-Control')) || '';
  // PHP responses can contain authenticated business data. Keep only static
  // offline shells and assets in Cache Storage.
  if (path.endsWith('.php') || path.endsWith('/')) return false;
  if (/\b(no-store|private)\b/i.test(cacheControl)) return false;
  if (isAuthPath(path) || isWritePath(path)) return false;
  try {
    const finalPath = new URL(res.url).pathname;
    if (finalPath.endsWith('.php') || finalPath.endsWith('/')) return false;
    if (isAuthPath(finalPath) || isWritePath(finalPath)) return false;
  } catch (e) {}
  if (!assetBodyOk(path, res)) return false;
  return !!(res && res.ok);
}

function assetBodyOk(path, res) {
  const ct = String((res && res.headers && res.headers.get('content-type')) || '').toLowerCase();
  if (!ct) return true;
  if (ct.indexOf('text/html') !== -1) return false;
  if (path.endsWith('.css')) return ct.indexOf('text/css') !== -1;
  if (path.endsWith('.js')) return ct.indexOf('javascript') !== -1;
  return true;
}

function isHomePath(path) {
  return path === '/' || path === '/index.php' || path === '/m' || path === '/m/'
    || path === '/offline.html' || path === '/index.html';
}

function injectIfBoot(req, res) {
  try {
    const ct = String(res.headers.get('content-type') || '').toLowerCase();
    const path = new URL(req.url).pathname || '';
    if (isDownloadPath(path)) return Promise.resolve(res);
    if (ct && ct.indexOf('text/html') === -1) return Promise.resolve(res);
    const wantsHtml = req.mode === 'navigate' || ct.indexOf('text/html') !== -1;
    if (!wantsHtml || !res.ok) return Promise.resolve(res);
    if (path.indexOf('/assets/') === 0) return Promise.resolve(res);
    return res.text().then(function (html) {
      if (html.indexOf('inventory-first-home.710.js') !== -1) {
        return new Response(html, { status: res.status, statusText: res.statusText, headers: res.headers });
      }
      const snip = '\n<link rel="stylesheet" href="/assets/css/inventory-first-home.710.css">\n<script src="/assets/js/inventory-first-home.710.js" defer></script>\n';
      const out = /<\/head>/i.test(html) ? html.replace(/<\/head>/i, snip + '</head>') : html;
      const headers = new Headers(res.headers);
      headers.delete('content-length');
      return new Response(out, { status: res.status, statusText: res.statusText, headers: headers });
    });
  } catch (e) {
    return Promise.resolve(res);
  }
}

function hubHtml() {
  return `<!DOCTYPE html>
<html lang="bn"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>অফলাইন দোকান</title>
<style>
body{font-family:sans-serif;background:#eef3fb;margin:0;padding:16px;color:#0b1e4d}
.wrap{max-width:640px;margin:0 auto}
.card{background:#fff;border-radius:16px;padding:16px;box-shadow:0 8px 20px rgba(15,35,80,.07)}
h1{margin:0 0 8px;font-size:20px}
p{color:#64748b;line-height:1.5}
.btn{width:100%;margin-top:12px;padding:14px;border:0;border-radius:12px;background:linear-gradient(90deg,#2563eb,#22c55e);color:#fff;font-weight:800;cursor:pointer;display:block;text-align:center;text-decoration:none;box-sizing:border-box}
.ghost{background:#f8fbff;color:#0b1e4d;border:1px solid #dbe4f0}
.kpi{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.kpi div{border:1px solid #e8eef6;border-radius:12px;padding:10px}
.kpi b{display:block;font-size:20px}
.kpi span{font-size:12px;color:#64748b}
.grid.two{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:8px 0 16px}
.sec{font-size:11px;font-weight:800;letter-spacing:.06em;color:#64748b;margin:16px 0 6px;text-transform:uppercase}
</style></head><body>
<div class="wrap"><div class="card">
<h1>নেট নেই — পুরো দোকান ফোনে চলছে</h1>
<p>রিলোড করলেও এই মেনুই থাকবে। নেট ফিরলে সেভ সার্ভারে যাবে।</p>
<div class="kpi">
<div><span>অপেক্ষমাণ বিক্রয়</span><b id="sales">0</b></div>
<div><span>অপেক্ষমাণ পেমেন্ট</span><b id="pays">0</b></div>
<div><span>অপেক্ষমাণ অন্য কাজ</span><b id="writes">0</b></div>
<div><span>ফোনে পণ্য</span><b id="prods">0</b></div>
</div>
<a class="btn" href="/offline-sale.html">বিক্রয় (POS)</a>
<div class="sec">বিক্রয় ও কাস্টমার</div>
<div class="grid two">
<a class="btn ghost" href="/offline-payment.html">কাস্টমার পেমেন্ট</a>
<a class="btn ghost" href="/offline-customers.html">কাস্টমার ও বকেয়া</a>
<a class="btn ghost" href="/modules/sales/pos.php">পার্টস কাউন্টার</a>
<a class="btn ghost" href="/offline-pulse.html">আজকের অবস্থা</a>
</div>
<div class="sec">স্টক ও ক্রয়</div>
<div class="grid two">
<a class="btn ghost" href="/offline-products.html">পণ্য / স্টক / দাম</a>
<a class="btn ghost" href="/offline-product-add.html">নতুন পণ্য</a>
<a class="btn ghost" href="/offline-purchase.html">মাল ক্রয়</a>
<a class="btn ghost" href="/offline-supplier-pay.html">সাপ্লায়ার পেমেন্ট</a>
</div>
<div class="sec">খরচ, ওয়ার্কশপ, হিসাব</div>
<div class="grid two">
<a class="btn ghost" href="/offline-expense.html">খরচ</a>
<a class="btn ghost" href="/offline-workshop.html">ওয়ার্কশপ জব</a>
<a class="btn ghost" href="/offline-transfer.html">অ্যাকাউন্ট ট্রান্সফার</a>
<a class="btn ghost" href="/offline.html">দোকানের মেনু</a>
</div>
</div></div>
<script>
function reads(k){try{return JSON.parse(localStorage.getItem(k)||'[]')}catch(e){return []}}
function n(a,f){try{return (window[a]&&window[a][f])?window[a][f]():0}catch(e){return 0}}
document.getElementById('sales').textContent=n('AmeOfflineQueue','pendingCount')||reads('ame_offline_sales_queue').length;
document.getElementById('pays').textContent=n('AmeOfflinePay','pendingCount')||reads('ame_offline_pay_queue').length;
document.getElementById('writes').textContent=n('AmeOfflineWrite','pendingCount')||reads('ame_offline_write_queue').length;
document.getElementById('prods').textContent=reads('ame_offline_products').length;
</script>
<script src="/assets/js/offline-queue.js?v=10.87.0"></script>
<script src="/assets/js/offline-payment-queue.js?v=10.87.0"></script>
<script src="/assets/js/offline-boot.js?v=10.87.0"></script>
</body></html>`;
}

function htmlResponse(html) {
  return new Response(html, {
    status: 200,
    headers: { 'Content-Type': 'text/html; charset=utf-8' }
  });
}

function cssResponse(css) {
  return new Response(css == null ? '/* offline */' : css, {
    status: 200,
    headers: { 'Content-Type': 'text/css; charset=utf-8' }
  });
}

function jsResponse(js) {
  return new Response(js == null ? '/* offline */' : js, {
    status: 200,
    headers: { 'Content-Type': 'application/javascript; charset=utf-8' }
  });
}

self.addEventListener('install', e => {
  e.waitUntil((async () => {
    const cache = await caches.open(cacheName);
    await Promise.all(staticAssets.map(async (url) => {
      try {
        const res = await fetch(url, { credentials: 'same-origin', cache: 'reload' });
        const abs = new URL(url, self.location.origin);
        if (res && res.ok && shouldCache(abs, res)) {
          await cache.put(abs.href, res.clone());
          await cache.put(abs.pathname, res.clone());
        }
      } catch (err) {}
    }));
    self.skipWaiting();
  })());
});

self.addEventListener('activate', e => {
  e.waitUntil((async () => {
    if (self.registration.navigationPreload) {
      try { await self.registration.navigationPreload.enable(); } catch (err) {}
    }
    const keys = await caches.keys();
    await Promise.all(keys.map(key => key !== cacheName ? caches.delete(key) : null));
    self.clients.claim();
  })());
});

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') {
    return;
  }
  const url = new URL(req.url);
  if (url.origin !== location.origin) {
    return;
  }
  if (isDownloadPath(url.pathname) || isHireListPath(url.pathname)) {
    e.respondWith(fetch(req, { cache: 'no-store', credentials: 'same-origin' }));
    return;
  }
  if (isAuthPath(url.pathname)) {
    e.respondWith(networkOnlyAuth(req));
    return;
  }
  if (isPaymentUrl(url)) {
    e.respondWith(paymentPage(req, e.preloadResponse));
    return;
  }
  if (isPosUrl(url)) {
    e.respondWith(posPage(req, e.preloadResponse));
    return;
  }
  const isPhp = url.pathname.endsWith('.php') || url.pathname.endsWith('/');
  const isAppAsset = url.pathname.indexOf('/assets/') !== -1
      || url.pathname.endsWith('.html')
      || url.pathname.endsWith('.js')
      || url.pathname.endsWith('.css');
  if (isPhp) {
    e.respondWith(networkFirst(req, e.preloadResponse));
  } else if (isAppAsset) {
    e.respondWith(cacheFirst(req));
  } else {
    e.respondWith(cacheFirst(req));
  }
});

async function networkOnlyAuth(req) {
  try {
    return await fetch(req, { cache: 'no-store' });
  } catch (e) {
    return htmlResponse('<!DOCTYPE html><html lang="bn"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>অফলাইন</title></head><body style="font-family:sans-serif;padding:20px;text-align:center"><h1>নেট সংযোগ দরকার</h1><p>লগইন ও পাসওয়ার্ড রিসেটের জন্য ইন্টারনেট চালু করুন।</p></body></html>');
  }
}

async function cacheFirst(req) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(req);
  const path = new URL(req.url).pathname || '';
  if (cached && assetBodyOk(path, cached)) return cached;
  try {
    const fresh = await fetch(req);
    if (fresh && fresh.ok && shouldCache(new URL(req.url), fresh)) {
      cache.put(req, fresh.clone());
    }
    return fresh;
  } catch (e) {
    if (cached && assetBodyOk(path, cached)) return cached;
    return fallbackFor(req, cache);
  }
}

async function remember(cache, req, fresh) {
  if (req.method === 'GET' && fresh && shouldCache(new URL(req.url), fresh)) {
    try { await cache.put(req, fresh.clone()); } catch (e) {}
  }
  return fresh;
}

async function matchPage(cache, path) {
  return await cache.match(path, { ignoreSearch: true })
    || await cache.match(self.location.origin + path, { ignoreSearch: true })
    || await cache.match('.' + path)
    || null;
}

async function fromCache(cache, req) {
  const url = new URL(req.url);
  return await cache.match(req, { ignoreSearch: true, ignoreVary: true })
    || await matchPage(cache, url.pathname)
    || null;
}

async function fallbackFor(req, cache) {
  const url = new URL(req.url);
  const path = url.pathname || '';
  // Never return HTML for stylesheets/scripts — browsers then render an unstyled hub.
  if (path.endsWith('.css')) {
    return (await fromCache(cache, req)) || cssResponse();
  }
  if (path.endsWith('.js')) {
    return (await fromCache(cache, req)) || jsResponse();
  }
  if (isPaymentUrl(url)) {
    return (await cache.match('/offline-payment.html'))
      || htmlResponse(hubHtml());
  }
  if (isPosUrl(url)) {
    return (await cache.match('/offline-sale.html'))
      || (await cache.match('/offline.html'))
      || htmlResponse(hubHtml());
  }
  if (path.indexOf('/modules/customers/') !== -1) {
    return (await cache.match('/offline-customers.html'))
      || (await cache.match('/offline.html'))
      || htmlResponse(hubHtml());
  }
  if (path.indexOf('/modules/inventory/add_product.php') !== -1) {
    return (await fromCache(cache, req))
      || (await cache.match('/offline-product-add.html'))
      || htmlResponse(hubHtml());
  }
  if (path.indexOf('/modules/inventory/') !== -1) {
    return (await cache.match('/offline-products.html'))
      || htmlResponse(hubHtml());
  }
  if (path.indexOf('/modules/purchase/') !== -1) {
    return (await cache.match('/offline-purchase.html'))
      || htmlResponse(hubHtml());
  }
  if (path.indexOf('/modules/expenses/') !== -1) {
    return (await cache.match('/offline-expense.html'))
      || htmlResponse(hubHtml());
  }
  if (path.indexOf('/modules/workshop/') !== -1) {
    return (await cache.match('/offline-workshop.html'))
      || htmlResponse(hubHtml());
  }
  if (path.indexOf('/modules/suppliers/') !== -1) {
    return (await cache.match('/offline-supplier-pay.html'))
      || htmlResponse(hubHtml());
  }
  if (path.indexOf('/modules/accounts/') !== -1) {
    return (await cache.match('/offline-transfer.html'))
      || htmlResponse(hubHtml());
  }
  if (path.indexOf('/modules/pulse/') !== -1) {
    return (await cache.match('/offline-pulse.html'))
      || htmlResponse(hubHtml());
  }
  if (isHomePath(path) || path.indexOf('/offline-') === 0) {
    return (await matchPage(cache, path))
      || (await matchPage(cache, '/offline.html'))
      || htmlResponse(hubHtml());
  }
  const wantsHtml = req.mode === 'navigate'
    || ((req.headers.get('accept') || '').indexOf('text/html') !== -1);
  if (wantsHtml) {
    return (await matchPage(cache, '/offline.html'))
      || htmlResponse(hubHtml());
  }
  return (await fromCache(cache, req))
    || new Response('', { status: 404, statusText: 'offline-miss' });
}

async function takePreload(preloadP) {
  if (!preloadP) return null;
  try {
    return await preloadP;
  } catch (e) {
    return null;
  }
}

async function networkFirst(req, preloadP) {
  const cache = await caches.open(cacheName);
  try {
    const fresh = (await takePreload(preloadP)) || await fetch(req, { cache: 'no-store' });
    await remember(cache, req, fresh);
    return fresh;
  } catch (e) {
    const path = new URL(req.url).pathname || '';
    if (isHomePath(path)) {
      return (await matchPage(cache, '/offline.html')) || htmlResponse(hubHtml());
    }
    const cached = await fromCache(cache, req);
    if (cached) return cached;
    return fallbackFor(req, cache);
  }
}

async function paymentPage(req, preloadP) {
  const cache = await caches.open(cacheName);
  try {
    const fresh = (await takePreload(preloadP)) || await fetch(req);
    await remember(cache, req, fresh);
    return fresh;
  } catch (e) {
    return (await cache.match('/offline-payment.html'))
      || htmlResponse(hubHtml());
  }
}

async function posPage(req, preloadP) {
  const cache = await caches.open(cacheName);
  try {
    const fresh = (await takePreload(preloadP)) || await fetch(req, { cache: 'no-store' });
    await remember(cache, req, fresh);
    return fresh;
  } catch (e) {
    return (await cache.match('/offline-sale.html'))
      || (await cache.match('/offline.html'))
      || htmlResponse(hubHtml());
  }
}
