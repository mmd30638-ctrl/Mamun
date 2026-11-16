<?php
require_once __DIR__ . '/_boot.php';
hire_require($db, 'create');
$pre_cid = (int) ($_GET['customer_id'] ?? 0);
$cust = null;
if ($pre_cid > 0) {
    $st = $db->prepare('SELECT id, name, customer_code, phone, status FROM customers WHERE id = ?');
    if ($st) {
        $st->bind_param('i', $pre_cid);
        $st->execute();
        $cust = $st->get_result()->fetch_assoc();
        $st->close();
    }
    if ($cust && function_exists('ame_customer_row_operable') && !ame_customer_row_operable($cust)) {
        $cust = null;
        $pre_cid = 0;
    }
}
$accounts = hire_accounts($db);
$today = function_exists('hire_shop_today') ? hire_shop_today() : date('Y-m-d');
if (!function_exists('ame_contract_letter_text')) {
    $letterLib = __DIR__ . '/../../../includes/contract_letter.php';
    if (is_file($letterLib)) {
        require_once $letterLib;
    }
}
$hireLetter = function_exists('ame_hire_letter_desk')
    ? ame_hire_letter_desk()
    : (function_exists('ame_contract_letter_text') ? ame_contract_letter_text('', 'hire') : '');
$here = 'create';
include __DIR__ . '/../../../partials/header.php';
$av = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.754', ENT_QUOTES, 'UTF-8');
$picked = $cust ? ($cust['name'] . ' · ' . ($cust['customer_code'] ?? '')) : '';
$accLabel = static function (array $a): string {
    if (function_exists('ame_account_option_label')) {
        return (string) ame_account_option_label($a);
    }
    $name = trim((string) ($a['account_name'] ?? $a['name'] ?? ''));
    return $name !== '' ? $name : ('#' . (int) ($a['id'] ?? 0));
};
?>
<link rel="stylesheet" href="/assets/css/hire-desk.css?v=<?php echo $av; ?>">
<link rel="stylesheet" href="/assets/css/hire-pro.css?v=<?php echo $av; ?>">
<div class="hire-wrap hire-create-page">
  <?php echo hire_flash(); ?>
  <header class="hire-mast">
    <p class="hire-kicker">নতুন চুক্তি</p>
    <h1>ভাড়া চুক্তি</h1>
    <p>নিজস্ব বা অন্য মালিকের পণ্য — দৈনিক ভাড়া একই কাস্টমার খাতায় বসে। স্টক কম থাকলেও চুক্তি চালু হয়। শুরুর তারিখ থেকে খাতায় যোগ হয়। প্রতিদিন বাংলাদেশ রাত ১২টায় নতুন দিনের ভাড়া বসে। জামানত বকেয়ায় যোগ হয় না।</p>
  </header>
  <?php include __DIR__ . '/_nav.php'; ?>

  <form class="hire-create-form" method="post" action="actions.php" id="hireCreate" novalidate>
    <?php echo csrf_field(); ?>
    <input type="hidden" name="do" value="create">
    <input type="hidden" name="fail_back" value="create">
    <input type="hidden" name="customer_id" id="hireCustomerId" value="<?php echo $pre_cid; ?>">
    <input type="hidden" name="product_id" id="product_id" value="">
    <input type="hidden" name="rate_type" value="DAILY">
    <input type="hidden" name="billing_start" id="termBill" value="<?php echo hire_h($today); ?>">
    <input type="hidden" name="expected_return" id="termEnd" value="">
    <p class="hire-form-err" id="hireCreateErr" hidden></p>

    <section class="hire-step">
      <h2><span class="hire-step-num">১</span> কাস্টমার</h2>
      <label class="hire-search-list">নাম বা মোবাইল খুঁজুন
        <input type="search" id="custQ" name="customer_query" value="<?php echo hire_h($cust['name'] ?? ''); ?>" placeholder="লিখে তালিকা থেকে বেছে নিন" autocomplete="off">
        <div class="hire-suggest" id="custSug" hidden></div>
      </label>
      <p class="hire-picked<?php echo $picked === '' ? ' is-empty' : ''; ?>" id="custShown"><?php echo $picked !== '' ? hire_h($picked) : 'এখনো কাস্টমার বাছা হয়নি'; ?></p>
    </section>

    <section class="hire-step">
      <h2><span class="hire-step-num">২</span> পণ্যের ধরন</h2>
      <div class="hire-source-toggle" id="hireSourceToggle">
        <label class="is-on">
          <input type="radio" name="rental_source" value="OWN" checked>
          <strong>নিজস্ব পণ্য</strong>
          <span>দোকানের স্টক কাস্টমারকে ভাড়া</span>
        </label>
        <label>
          <input type="radio" name="rental_source" value="THIRD">
          <strong>মালিকের পণ্য</strong>
          <span>অন্যের পণ্য/নম্বর প্লেট ভাড়া নিয়ে কাস্টমারকে দেওয়া</span>
        </label>
      </div>
      <div class="hire-owner-box" id="hireOwnerBox" hidden>
        <p class="hire-muted">মালিকের পাওনা আলাদা খাতায় থাকবে — কাস্টমারের বকেয়ায় মিশবে না। মাসিক চুক্তি কাস্টমারের ব্যবহারের দিন দিয়ে কমবে না।</p>
        <input type="hidden" name="owner_id" id="hireOwnerId" value="0">
        <div class="hire-grid">
          <label class="hire-search-list">মালিক খুঁজুন
            <input type="search" id="ownerQ" placeholder="নাম বা মোবাইল" autocomplete="off">
            <div class="hire-suggest" id="ownerSug" hidden></div>
          </label>
          <label>মালিকের নাম
            <input name="owner_name" id="owner_name" maxlength="150" placeholder="নতুন হলে নাম লিখুন">
          </label>
          <label>মোবাইল
            <input name="owner_phone" id="owner_phone" maxlength="30" inputmode="tel">
          </label>
          <label>নম্বর প্লেট / পরিচয়
            <input name="vehicle_no" id="vehicle_no" maxlength="40" placeholder="ঐচ্ছিক">
          </label>
          <label>মালিককে দৈনিক/মাসিক (৳)
            <input type="number" step="0.01" min="0" name="owner_rate" id="owner_rate" value="0">
          </label>
          <label>মালিকের চক্র
            <select name="owner_cycle" id="owner_cycle">
              <option value="daily">দৈনিক</option>
              <option value="monthly">মাসিক</option>
              <option value="weekly">সাপ্তাহিক</option>
            </select>
          </label>
          <label>যে তারিখ থেকে মালিক ভাড়া পাবেন
            <input type="date" name="owner_start_date" id="ownerStartDate" value="<?php echo hire_h($today); ?>">
          </label>
          <p class="hire-muted" style="grid-column:1/-1">মালিক ও কাস্টমারের তারিখ দুটোই ডাটাবেজে থাকে। কাস্টমারের দৈনিক ভাড়া খাতায় অটো যোগ হয়।</p>
          <label>মালিককে দেওয়া জামানত (৳)
            <input type="number" step="0.01" min="0" name="owner_deposit" value="0">
          </label>
          <label>পণ্যের ধরন
            <select name="item_kind">
              <option value="product">পণ্য</option>
              <option value="plate">নম্বর প্লেট</option>
              <option value="vehicle">গাড়ি</option>
            </select>
          </label>
        </div>
      </div>
    </section>

    <section class="hire-step">
      <h2><span class="hire-step-num">৩</span> পণ্য</h2>
      <div class="hire-grid">
        <label class="hire-search-list">স্টক খুঁজুন
          <input type="search" id="prodQ" name="product_query" placeholder="নাম / কোড / সিরিয়াল" autocomplete="off">
          <div class="hire-suggest" id="prodSug" hidden></div>
        </label>
        <label>পণ্যের নাম
          <input name="item_name" id="item_name" maxlength="255" required>
        </label>
        <label>পরিমাণ
          <input type="number" step="0.001" min="0.001" name="qty" value="1">
        </label>
      </div>
    </section>

    <section class="hire-step">
      <h2><span class="hire-step-num">৪</span> মেয়াদ ও ভাড়া</h2>
      <div class="hire-grid hire-term-grid">
        <label>শুরু
          <input type="date" name="start_date" id="termStart" value="<?php echo hire_h($today); ?>" required>
        </label>
        <label>মাস
          <select name="term_months" id="termMonths">
            <?php for ($m = 0; $m <= 36; $m++): ?>
              <option value="<?php echo $m; ?>"><?php echo $m; ?> মাস</option>
            <?php endfor; ?>
          </select>
        </label>
        <label>দিন
          <select name="term_days" id="termDays">
            <?php for ($d = 0; $d <= 30; $d++): ?>
              <option value="<?php echo $d; ?>"<?php echo $d === 1 ? ' selected' : ''; ?>><?php echo $d; ?> দিন</option>
            <?php endfor; ?>
          </select>
        </label>
        <label>দৈনিক ভাড়া (৳)
          <input type="number" step="0.01" min="0" name="rent_rate" id="termRate" inputmode="decimal" placeholder="০">
        </label>
        <label>মাসিক ভাড়া (৳)
          <input type="number" step="0.01" min="0" name="monthly_amount" id="termMonthly" inputmode="decimal" placeholder="ঐচ্ছিক">
        </label>
      </div>
      <p class="hire-term-preview" id="termPreview">মাস, দিন ও ভাড়ার হার দিলে আনুমানিক মোট দেখাবে। প্রতিদিন খাতায় যোগ হবে। ফেরত পরে চুক্তি থেকে ম্যানুয়ালি বন্ধ করবেন।</p>
    </section>

    <details class="hire-step hire-more">
      <summary>জামানত (ঐচ্ছিক)</summary>
      <div class="hire-grid">
        <label>জামানত (৳)
          <input type="number" step="0.01" min="0" name="security_deposit" value="0">
        </label>
        <label>পেমেন্ট মাধ্যম
          <select name="payment_method">
            <option value="CASH">নগদ</option>
            <option value="BANK">ব্যাংক</option>
            <option value="MOBILE">মোবাইল ব্যাংকিং</option>
            <option value="OTHER">অন্যান্য</option>
          </select>
        </label>
        <label>অ্যাকাউন্ট
          <select name="account_id">
            <option value="0">পরে / নেই</option>
            <?php foreach ($accounts as $a): ?>
              <option value="<?php echo (int) $a['id']; ?>"><?php echo hire_h($accLabel($a)); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <input type="hidden" name="advance_usage" value="0">
      </div>
      <p class="hire-muted">জামানত ওয়ালেটে থাকে — কাস্টমার বকেয়ায় ক্রেডিট হয় না।</p>
    </details>

    <details class="hire-step hire-more">
      <summary>আরও তথ্য</summary>
      <div class="hire-grid">
        <label>SKU<input name="product_sku" id="product_sku"></label>
        <label>সিরিয়াল<input name="serial_no" id="serial_no"></label>
        <label>বারকোড<input name="barcode" id="barcode"></label>
        <label>ক্যাটাগরি<input name="product_category" id="product_category"></label>
        <label style="grid-column:1/-1;">নোট<textarea name="notes" rows="2"></textarea></label>
        <label style="grid-column:1/-1;">চুক্তির শর্ত
          <textarea name="contract_letter" id="hireLetter" rows="4"><?php echo htmlspecialchars($hireLetter, ENT_QUOTES, 'UTF-8'); ?></textarea>
        </label>
      </div>
      <input type="hidden" name="late_fee_type" value="none">
      <input type="hidden" name="late_fee_value" value="0">
    </details>

    <div class="hire-actions hire-create-actions">
      <button class="hire-btn hire-btn-primary hire-create-submit" type="submit" id="hireCreateGo">চুক্তি চালু করুন</button>
      <a class="hire-btn" href="index.php">ফিরে যান</a>
    </div>
  </form>
</div>
<script>
(function () {
  "use strict";
  function el(id) {
    try { return document.getElementById(id); } catch (e) { return null; }
  }
  function showErr(msg) {
    var box = el("hireCreateErr");
    if (!box) {
      window.alert(msg);
      return;
    }
    box.hidden = false;
    box.textContent = msg;
    try { box.scrollIntoView({ behavior: "smooth", block: "center" }); } catch (e) {}
  }
  function hideErr() {
    var box = el("hireCreateErr");
    if (box) {
      box.hidden = true;
      box.textContent = "";
    }
  }
  function bindSearch(inputId, boxId, url, onPick) {
    var inp = el(inputId);
    var box = el(boxId);
    if (!inp || !box) return;
    var t = null;
    inp.addEventListener("input", function () {
      clearTimeout(t);
      var q = String(inp.value || "").trim();
      if (q.length < 1) { box.hidden = true; return; }
      t = setTimeout(function () {
        fetch(url + encodeURIComponent(q), { credentials: "same-origin" })
          .then(function (r) { return r.json(); })
          .then(function (rows) {
            box.innerHTML = "";
            if (!Array.isArray(rows) || !rows.length) {
              box.hidden = true;
              return;
            }
            rows.forEach(function (row) {
              var b = document.createElement("button");
              b.type = "button";
              b.textContent = row.label || row.name || "";
              b.addEventListener("click", function () {
                try { onPick(row); } catch (e) {}
                box.hidden = true;
              });
              box.appendChild(b);
            });
            box.hidden = false;
          })
          .catch(function () { box.hidden = true; });
      }, 200);
    });
  }
  try {
    bindSearch("custQ", "custSug", "/modules/customers/hire/customer_search.php?q=", function (row) {
      var id = el("hireCustomerId");
      var shown = el("custShown");
      if (id) id.value = row.id;
      if (shown) {
        shown.textContent = row.label || "";
        shown.classList.remove("is-empty");
      }
      hideErr();
    });
    bindSearch("ownerQ", "ownerSug", "/modules/customers/hire/owner_search.php?q=", function (row) {
      var id = el("hireOwnerId");
      if (id) id.value = row.id;
      var n = el("owner_name");
      var p = el("owner_phone");
      var v = el("vehicle_no");
      if (n) n.value = row.name || "";
      if (p) p.value = row.phone || "";
      if (v && row.vehicle_no) v.value = row.vehicle_no;
      var osPick = el("ownerStartDate");
      if (osPick && row.entry_date && osPick.getAttribute("data-set") !== "1") osPick.value = row.entry_date;
      hideErr();
    });
    bindSearch("prodQ", "prodSug", "/modules/customers/hire/product_search.php?q=", function (row) {
      var map = {
        product_id: row.id,
        item_name: row.name,
        product_sku: row.sku || "",
        barcode: row.barcode || "",
        product_category: row.category || "",
        serial_no: row.serial || ""
      };
      Object.keys(map).forEach(function (key) {
        var node = el(key);
        if (node) node.value = map[key];
      });
      hideErr();
    });
  } catch (e) {}

  function pad(n) { return n < 10 ? "0" + n : String(n); }
  function iso(d) {
    return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate());
  }
  function addMonthsDays(start, months, days) {
    var p = String(start || "").split("-").map(Number);
    if (p.length < 3 || !p[0]) return "";
    var d = new Date(p[0], p[1] - 1, p[2]);
    if (isNaN(d.getTime())) return "";
    if (months) d.setMonth(d.getMonth() + months);
    if (days) d.setDate(d.getDate() + days);
    return iso(d);
  }
  function daysBetween(from, to) {
    var a = new Date(from + "T00:00:00");
    var b = new Date(to + "T00:00:00");
    if (isNaN(a.getTime()) || isNaN(b.getTime())) return 0;
    return Math.floor((b.getTime() - a.getTime()) / 86400000);
  }
  function money(n) {
    var rounded = Math.round(n * 100) / 100;
    return "৳" + rounded.toFixed(2);
  }
  function bindTerm() {
    var start = el("termStart");
    var months = el("termMonths");
    var days = el("termDays");
    var rate = el("termRate");
    var monthlyAmt = el("termMonthly");
    var end = el("termEnd");
    var preview = el("termPreview");
    var bill = el("termBill");
    var syncing = false;
    function dailyFromMonthly(n) {
      var v = Math.round((n / 30) * 100) / 100;
      return v > 0 ? v : 0.01;
    }
    function tick() {
      try {
        var m = Math.max(0, parseInt(months && months.value, 10) || 0);
        var d = Math.max(0, parseInt(days && days.value, 10) || 0);
        if (m === 0 && d === 0) d = 1;
        var s = start ? start.value : "";
        if (bill && s) bill.value = s;
        var endDate = addMonthsDays(s, m, d);
        if (end) end.value = endDate;
        var r = parseFloat(rate && rate.value) || 0;
        var mon = parseFloat(monthlyAmt && monthlyAmt.value) || 0;
        if (r <= 0 && mon > 0) {
          r = dailyFromMonthly(mon);
          if (rate && !syncing) {
            syncing = true;
            rate.value = r.toFixed(2);
            syncing = false;
          }
        }
        var totalDays = Math.max(1, daysBetween(s, endDate));
        if (preview) {
          if (!s || r <= 0) {
            preview.textContent = "মাস, দিন ও ভাড়ার হার দিলে আনুমানিক মোট দেখাবে। প্রতিদিন খাতায় যোগ হবে। ফেরত পরে চুক্তি থেকে ম্যানুয়ালি বন্ধ করবেন।";
          } else {
            preview.textContent = m + " মাস " + d + " দিন · প্রতিদিন " + money(r) + " · আনুমানিক " + money(r * totalDays) + "। ফেরত চুক্তি থেকে ম্যানুয়ালি বন্ধ।";
          }
        }
      } catch (e) {}
    }
    if (monthlyAmt && rate) {
      monthlyAmt.addEventListener("input", function () {
        if (syncing) return;
        var mon = parseFloat(monthlyAmt.value) || 0;
        if (mon > 0) {
          syncing = true;
          rate.value = dailyFromMonthly(mon).toFixed(2);
          syncing = false;
          tick();
        }
      });
      rate.addEventListener("input", function () {
        if (syncing) return;
        var r = parseFloat(rate.value) || 0;
        if (r > 0) {
          syncing = true;
          monthlyAmt.value = (Math.round(r * 30 * 100) / 100).toFixed(2);
          syncing = false;
        }
      });
    }
    ["change", "input"].forEach(function (ev) {
      [start, months, days, rate, monthlyAmt].forEach(function (node) {
        if (node) node.addEventListener(ev, tick);
      });
    });
    var ownerStart = el("ownerStartDate");
    if (ownerStart) {
      ownerStart.addEventListener("change", function () {
        ownerStart.setAttribute("data-set", "1");
      });
    }
    if (start && bill) {
      start.addEventListener("change", function () {
        bill.value = start.value;
        if (ownerStart && ownerStart.getAttribute("data-set") !== "1") ownerStart.value = start.value;
        tick();
      });
    }
    tick();
  }
  try { bindTerm(); } catch (e) {}

  function bindSource() {
    var box = el("hireOwnerBox");
    var wrap = el("hireSourceToggle");
    if (!wrap) return;
    function tick() {
      var on = wrap.querySelector('input[name="rental_source"]:checked');
      var third = on && on.value === "THIRD";
      if (box) box.hidden = !third;
      wrap.querySelectorAll("label").forEach(function (lab) {
        lab.classList.toggle("is-on", lab.contains(on));
      });
    }
    wrap.addEventListener("change", tick);
    tick();
  }
  try { bindSource(); } catch (e) {}

  var form = el("hireCreate");
  if (form) {
    form.addEventListener("submit", function (ev) {
      try {
        hideErr();
        var cid = parseInt((el("hireCustomerId") || {}).value || "0", 10) || 0;
        var itemNode = el("item_name");
        var item = String((itemNode || {}).value || "").trim();
        if (!item) {
          var plate = String((el("vehicle_no") || {}).value || "").trim();
          var oname = String((el("owner_name") || {}).value || "").trim();
          item = plate || oname;
          if (item && itemNode) itemNode.value = item;
        }
        var daily = parseFloat((el("termRate") || {}).value || "0") || 0;
        var monthly = parseFloat((el("termMonthly") || {}).value || "0") || 0;
        var msg = "";
        if (cid <= 0) msg = "কাস্টমার নির্বাচন করুন — নাম লিখে তালিকা থেকে বেছে নিন।";
        else if (!item) msg = "পণ্যের নাম দিন — স্টক থেকে খুঁজে বেছে নিন অথবা নাম লিখুন।";
        else if (daily <= 0 && monthly <= 0) msg = "ভাড়ার হার দিন — দৈনিক অথবা মাসিক টাকা লিখুন।";
        var srcEl = form.querySelector('input[name="rental_source"]:checked');
        var src = srcEl ? String(srcEl.value || "OWN") : "OWN";
        var ownerName = String((el("owner_name") || {}).value || "").trim();
        var ownerId = parseInt((el("hireOwnerId") || {}).value || "0", 10) || 0;
        if (!msg && src === "THIRD" && ownerId <= 0 && !ownerName) {
          msg = "মালিকের পণ্যে মালিকের নাম দিন — খুঁজে বেছে নিন অথবা নতুন নাম লিখুন।";
        }
        if (msg) {
          ev.preventDefault();
          showErr(msg);
          if (window.AmeSubmitWait && typeof window.AmeSubmitWait.hideAll === "function") {
            window.AmeSubmitWait.hideAll();
          }
        }
      } catch (e) {
        ev.preventDefault();
        showErr("ফর্ম পাঠানো যায়নি। আবার চেষ্টা করুন।");
      }
    });
  }
})();
</script>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
