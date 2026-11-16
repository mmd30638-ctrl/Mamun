(function () {
  if (window.AME_HIRE_DUE_BOOTED) return;
  window.AME_HIRE_DUE_BOOTED = true;

  var data = window.AME_HIRE_DUE || { rows: [], accounts: [], csrf: '', home: false };
  var badge = window.AME_HIRE_DUE_BADGE || { count: 0, href: "/modules/customers/hire/due-reminder.php" };
  var current = null;
  var apiUrl = "/modules/customers/hire/reminder_api.php";

  function money(n) {
    var x = Number(n) || 0;
    return "৳" + x.toFixed(2);
  }

  function byId(id) {
    return document.getElementById(id);
  }

  function csrf() {
    return (data && data.csrf) || window.AME_CSRF_TOKEN || "";
  }

  function findRow(id) {
    id = Number(id);
    var rows = (data && data.rows) || [];
    for (var i = 0; i < rows.length; i++) {
      if (Number(rows[i].id) === id) return rows[i];
    }
    return null;
  }

  function fillAccounts() {
    var sel = byId("hirePayAccount");
    if (!sel) return;
    var accounts = (data && data.accounts) || [];
    sel.innerHTML = '<option value="0">—</option>';
    accounts.forEach(function (a) {
      var opt = document.createElement("option");
      opt.value = a.id;
      opt.textContent = a.account_name || a.name || ("#" + a.id);
      sel.appendChild(opt);
    });
  }

  window.hireRecalcRemaining = function () {
    if (!current) return;
    var expected = parseFloat((byId("hireEditExpected") || {}).value) || 0;
    var rem = (Number(current.due) || 0) - expected;
    if (byId("hireEditRemaining")) byId("hireEditRemaining").value = money(rem);
  };

  window.hireOpenEdit = function (id, payFocus) {
    var row = typeof id === "object" ? id : findRow(id);
    if (!row) return;
    current = row;
    fillAccounts();
    if (byId("hireEditName")) byId("hireEditName").innerText = row.customer_name || "";
    if (byId("hireEditItem")) {
      byId("hireEditItem").innerText =
        (row.rental_no || "") + " · " + (row.item_name || "") + (row.customer_phone ? " · " + row.customer_phone : "");
    }
    if (byId("hireEditTotalDue")) byId("hireEditTotalDue").value = money(row.due);
    if (byId("hireEditUnpaidDays")) byId("hireEditUnpaidDays").value = String(row.unpaid_days || 0) + " দিন";
    if (byId("hireEditMonths")) byId("hireEditMonths").value = String(row.term_months || 0);
    if (byId("hireEditDays")) byId("hireEditDays").value = String(row.term_days || 0);
    if (byId("hireEditRate")) byId("hireEditRate").value = row.daily_rate || "";
    if (byId("hireEditExpected")) {
      byId("hireEditExpected").value =
        row.follow_amount !== null && row.follow_amount !== undefined && row.follow_amount !== ""
          ? row.follow_amount
          : row.due || "";
    }
    if (byId("hireEditDueDate")) byId("hireEditDueDate").value = row.follow_date || "";
    if (byId("hireEditNote")) byId("hireEditNote").value = row.follow_note || "";
    if (byId("hirePayAmount")) {
      byId("hirePayAmount").value =
        row.follow_amount !== null && row.follow_amount !== undefined && row.follow_amount !== ""
          ? row.follow_amount
          : row.due || "";
    }
    if (byId("hirePayDate")) {
      var today = (data && data.today) || new Date().toISOString().slice(0, 10);
      byId("hirePayDate").value = today;
    }
    if (byId("hireEditMsg")) byId("hireEditMsg").innerText = "";
    window.hireRecalcRemaining();
    var ov = byId("hireEditOverlay");
    if (ov) ov.classList.add("open");
    if (payFocus && byId("hirePayAmount")) byId("hirePayAmount").focus();
  };

  window.hireCloseEdit = function () {
    var ov = byId("hireEditOverlay");
    if (ov) ov.classList.remove("open");
    current = null;
  };

  function postForm(fields, done) {
    var fd = new FormData();
    Object.keys(fields).forEach(function (k) {
      if (fields[k] !== undefined && fields[k] !== null) fd.append(k, fields[k]);
    });
    fd.append("csrf_token", csrf());
    fetch(apiUrl, { method: "POST", body: fd })
      .then(function (res) {
        return res.json();
      })
      .then(done)
      .catch(function () {
        done({ success: false, message: "সার্ভার এরর।" });
      });
  }

  function setMsg(text, ok) {
    var msg = byId("hireEditMsg");
    if (!msg) return;
    msg.style.color = ok ? "#2E7D32" : "#C62828";
    msg.innerText = text;
  }

  function onSave() {
    if (!current) return;
    var date = (byId("hireEditDueDate") || {}).value;
    if (!date) {
      setMsg("তারিখ দেওয়া আবশ্যক।", false);
      return;
    }
    setMsg("সংরক্ষণ হচ্ছে...", true);
    postForm(
      {
        do: "reminder",
        contract_id: current.id,
        follow_date: date,
        follow_amount: (byId("hireEditExpected") || {}).value || "",
        follow_note: (byId("hireEditNote") || {}).value || "",
        start_date: current.start_date || "",
        term_months: (byId("hireEditMonths") || {}).value || "0",
        term_days: (byId("hireEditDays") || {}).value || "0",
        rent_rate: (byId("hireEditRate") || {}).value || "",
      },
      function (out) {
        if (out && out.success) {
          setMsg("✅ আপডেট হয়েছে। রিলোড হচ্ছে...", true);
          setTimeout(function () {
            location.reload();
          }, 700);
        } else {
          setMsg("❌ " + ((out && out.message) || "সমস্যা হয়েছে।"), false);
        }
      }
    );
  }

  function onPay() {
    if (!current) return;
    var amount = parseFloat((byId("hirePayAmount") || {}).value) || 0;
    if (amount <= 0) {
      setMsg("পেমেন্টের টাকা লিখুন।", false);
      return;
    }
    setMsg("টাকা নেওয়া হচ্ছে...", true);
    postForm(
      {
        do: "pay",
        contract_id: current.id,
        amount: amount,
        account_id: (byId("hirePayAccount") || {}).value || "0",
        payment_method: (byId("hirePayMethod") || {}).value || "CASH",
        pay_date: (byId("hirePayDate") || {}).value || "",
        note: (byId("hireEditNote") || {}).value || "রিমাইন্ডার থেকে পেমেন্ট",
        follow_date: (byId("hireEditDueDate") || {}).value || "",
        follow_amount: (byId("hireEditExpected") || {}).value || "",
        follow_note: (byId("hireEditNote") || {}).value || "",
      },
      function (out) {
        if (out && out.success) {
          setMsg("✅ টাকা নেওয়া হয়েছে। রিলোড হচ্ছে...", true);
          setTimeout(function () {
            location.reload();
          }, 700);
        } else {
          setMsg("❌ " + ((out && out.message) || "সমস্যা হয়েছে।"), false);
        }
      }
    );
  }

  function openPanel() {
    var ov = byId("ameHireDuePanel");
    if (ov) ov.classList.add("open");
  }

  function closePanel() {
    var ov = byId("ameHireDuePanel");
    if (ov) ov.classList.remove("open");
  }

  window.hireOpenDuePanel = openPanel;
  window.hireCloseDuePanel = closePanel;

  function bindClicks(root) {
    (root || document).addEventListener("click", function (ev) {
      var t = ev.target;
      if (!t || !t.closest) return;
      if (t.closest("[data-hire-open-panel]")) {
        ev.preventDefault();
        openPanel();
        return;
      }
      if (t.closest("[data-hire-close-panel]")) {
        ev.preventDefault();
        closePanel();
        return;
      }
      var pay = t.closest("[data-hire-pay]");
      if (pay) {
        ev.preventDefault();
        window.hireOpenEdit(pay.getAttribute("data-hire-pay"), true);
        return;
      }
      var edit = t.closest("[data-hire-edit]");
      if (edit) {
        ev.preventDefault();
        window.hireOpenEdit(edit.getAttribute("data-hire-edit"), false);
      }
    });
  }

  function renderHome() {
    if (!data.home) return;
    var rows = data.rows || [];
    if (!rows.length) return;
    var host = document.getElementById("main-content") || document.querySelector("main") || document.body;
    if (!host) return;
    var wrap = document.createElement("div");
    wrap.className = "hire-due-home";
    wrap.id = "ameHireDueHome";
    var due = Number(data.due) || 0;
    var days = Number(data.unpaid_days) || 0;
    var html =
      '<button type="button" class="hire-due-bell" data-hire-open-panel="1">' +
      "<div><h2>⏰ ভাড়া বকেয়া নোটিফিকেশন</h2><p>" +
      rows.length +
      " নোটিফিকেশন · বকেয়া " +
      money(due) +
      " · " +
      days +
      " দিন — ক্লিক করে এডিট খুলুন</p></div>" +
      '<span class="hire-due-count">' +
      rows.length +
      "</span></button>";
    html +=
      '<div class="hire-due-panel-overlay" id="ameHireDuePanel">' +
      '<div class="hire-due-panel hire-due-panel-wrap">' +
      '<button type="button" class="hire-due-panel-close" data-hire-close-panel="1" aria-label="বন্ধ">&times;</button>' +
      "<h3>এডিট — " +
      rows.length +
      " নোটিফিকেশন</h3>";
    rows.forEach(function (r) {
      var badgeMeta = (r.badge && r.badge.class) || "badge-overdue";
      var badgeText = (r.badge && r.badge.text) || "বকেয়া";
      var badgeDate = (r.badge && r.badge.date) || "—";
      html +=
        '<button type="button" class="hire-due-edit-row" data-hire-edit="' +
        r.id +
        '"><span><b>' +
        (r.customer_name || "") +
        "</b><small>" +
        (r.item_name || "") +
        " · " +
        (r.unpaid_days || 0) +
        " দিন · " +
        money(r.due) +
        ' · <span class="due-date-badge ' +
        badgeMeta +
        '">' +
        badgeText +
        ": " +
        badgeDate +
        "</span></small></span>" +
        '<span class="hire-due-edit-chip">এডিট</span></button>';
    });
    html += "</div></div>";
    wrap.innerHTML = html;
    host.insertBefore(wrap, host.firstChild);
    var ov = byId("ameHireDuePanel");
    if (ov) {
      ov.addEventListener("click", function (e) {
        if (e.target === ov) closePanel();
      });
    }
  }

  function renderBadge() {
    var count = Number(badge.count) || 0;
    if (count <= 0) return;
    if (document.querySelector(".hdr-hire-due")) return;
    var bell = document.querySelector(".hdr-i-bell, a.hdr-icon-btn[href*='due-reminder.php']");
    var a = document.createElement("a");
    a.className = "hdr-hire-due";
    a.href = badge.href || "/modules/customers/hire/due-reminder.php";
    a.title = "ভাড়া বকেয়া রিমাইন্ডার";
    a.innerHTML = "📦<span class=\"hdr-hire-due-badge\">" + count + "</span>";
    a.addEventListener("click", function (e) {
      if (data.home && typeof openPanel === "function") {
        e.preventDefault();
        openPanel();
      }
    });
    if (bell && bell.parentNode) {
      bell.parentNode.insertBefore(a, bell.nextSibling);
    } else {
      var bar = document.querySelector(".hdr-icons, .hdr-right, header");
      if (bar) bar.appendChild(a);
    }
  }

  function ready(fn) {
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", fn);
    else fn();
  }

  ready(function () {
    fillAccounts();
    bindClicks(document);
    var saveBtn = byId("hireSaveBtn");
    if (saveBtn) saveBtn.addEventListener("click", onSave);
    var payBtn = byId("hirePayBtn");
    if (payBtn) payBtn.addEventListener("click", onPay);
    renderHome();
    renderBadge();
  });
})();
