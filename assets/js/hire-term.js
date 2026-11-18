(function () {
  function pad(n) {
    return n < 10 ? "0" + n : String(n);
  }
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
    var text;
    try {
      text = rounded.toLocaleString("en-BD", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    } catch (e) {
      text = rounded.toFixed(2);
    }
    return "৳" + text;
  }
  window.ameHireTermBind = function (ids) {
    try {
    ids = ids || {};
    var start = document.getElementById(ids.start || "termStart");
    var months = document.getElementById(ids.months || "termMonths");
    var days = document.getElementById(ids.days || "termDays");
    var rate = document.getElementById(ids.rate || "termRate");
    var monthlyAmt = document.getElementById(ids.monthly || "termMonthly");
    var end = document.getElementById(ids.end || "termEnd");
    var preview = document.getElementById(ids.preview || "termPreview");
    var bill = document.getElementById(ids.bill || "termBill");
    if (!start && !months && !days && !rate && !monthlyAmt) return;
    var syncing = false;
    function dailyFromMonthly(n) {
      var v = Math.round((n / 30) * 100) / 100;
      return v > 0 ? v : 0.01;
    }
    function tick() {
      var m = Math.max(0, parseInt(months && months.value, 10) || 0);
      var d = Math.max(0, parseInt(days && days.value, 10) || 0);
      if (m === 0 && d === 0) d = 1;
      var s = start ? start.value : "";
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
          preview.textContent =
            m + " মাস " + d + " দিন · প্রতিদিন " + money(r) +
            " · আনুমানিক " + money(r * totalDays) +
            "। ফেরত চুক্তি থেকে ম্যানুয়ালি বন্ধ।";
        }
      }
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
      [start, months, days, rate, monthlyAmt].forEach(function (el) {
        if (el) el.addEventListener(ev, tick);
      });
    });
    if (start && bill) {
      start.addEventListener("change", function () {
        bill.value = start.value;
        tick();
      });
    }
    tick();
    } catch (e) {}
  };
  function bootHireTerm() {
    try { window.ameHireTermBind(); } catch (e) {}
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootHireTerm);
  } else {
    bootHireTerm();
  }
})();
