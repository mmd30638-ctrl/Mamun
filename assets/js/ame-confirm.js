/**
 * ব্র্যান্ডেড কনফার্ম মডাল — window.confirm()-এর বদলে ব্যবহার করার জন্য।
 *
 * পুরনো লাইন:
 *   <form onsubmit="return confirm('ডিলিট করবেন?');">
 * নতুন লাইন:
 *   <form data-confirm="ডিলিট করবেন?">
 *
 * বাংলা/ইংরেজি দুটোই দেখাতে চাইলে মাঝে ব্ল্যাংক লাইন দিন:
 *   data-confirm="ডিলিট করবেন?

Delete this?"
 *
 * বিপজ্জনক/অপরিবর্তনযোগ্য কাজের জন্য (লাল বাটন):
 *   data-confirm="..." data-confirm-type="danger"
 *
 * form ছাড়া সাধারণ লিংক/বাটনেও কাজ করে — <a href="..." data-confirm="...">
 */
(function () {
  "use strict";

  function splitMessage(raw) {
    var text = String(raw || "").trim();
    var parts = text.split(/\n\s*\n/);
    if (parts.length >= 2) {
      return { bn: parts[0].trim(), en: parts.slice(1).join(" ").trim() };
    }
    // পুরনো confirm() মেসেজগুলোর একাংশ " / " দিয়ে বাংলা/ইংরেজি আলাদা করত
    var slash = text.split(" / ");
    if (slash.length === 2) {
      return { bn: slash[0].trim(), en: slash[1].trim() };
    }
    return { bn: text, en: "" };
  }

  function ameConfirm(message, opts) {
    opts = opts || {};
    var msg = splitMessage(message);
    var isDanger = opts.type === "danger";

    return new Promise(function (resolve) {
      var backdrop = document.createElement("div");
      backdrop.className = "ame-cf-backdrop";

      var isWithdraw = opts.type === "withdraw"
        || !!(document.body && document.body.classList.contains("ame-withdraw-page"));
      var card = document.createElement("div");
      card.className = "ame-cf-card"
        + (isDanger ? " is-danger" : "")
        + (isWithdraw ? " is-withdraw" : "");
      if (isWithdraw) {
        backdrop.classList.add("ame-cf-withdraw");
      }

      var icon = isWithdraw ? "৳" : (isDanger ? "⚠️" : "❓");
      var cancelLabel = isWithdraw ? "না, থাক · Keep" : "বাতিল · Cancel";
      var okLabel = isWithdraw ? "হ্যাঁ, তুলুন · Pay out" : "নিশ্চিত · OK";

      card.innerHTML =
        '<div class="ame-cf-bar"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>' +
        (isWithdraw ? '<p class="ame-cf-kicker">APONSHOP · জমা উত্তোলন</p>' : "") +
        '<div class="ame-cf-body">' +
          '<div class="ame-cf-icon">' + icon + "</div>" +
          '<p class="ame-cf-bn"></p>' +
          '<p class="ame-cf-en" style="' + (msg.en ? "" : "display:none;") + '"></p>' +
        "</div>" +
        '<div class="ame-cf-actions">' +
          '<button type="button" class="ame-cf-btn is-cancel"></button>' +
          '<button type="button" class="ame-cf-btn is-ok"></button>' +
        "</div>";
      card.querySelector(".is-cancel").textContent = cancelLabel;
      card.querySelector(".is-ok").textContent = okLabel;

      card.querySelector(".ame-cf-bn").textContent = msg.bn;
      if (msg.en) {
        card.querySelector(".ame-cf-en").textContent = msg.en;
      }

      backdrop.appendChild(card);
      document.body.appendChild(backdrop);
      requestAnimationFrame(function () {
        backdrop.classList.add("is-open");
      });

      function close(result) {
        backdrop.classList.remove("is-open");
        setTimeout(function () {
          if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
        }, 160);
        resolve(result);
      }

      card.querySelector(".is-cancel").addEventListener("click", function () {
        close(false);
      });
      card.querySelector(".is-ok").addEventListener("click", function () {
        close(true);
      });
      backdrop.addEventListener("click", function (e) {
        if (e.target === backdrop) close(false);
      });
      document.addEventListener("keydown", function escOnce(e) {
        if (e.key === "Escape") {
          document.removeEventListener("keydown", escOnce);
          close(false);
        }
      });
    });
  }

  window.ameConfirm = ameConfirm;

  // data-confirm সহ <form> সাবমিট হলে আটকে, মডাল দেখিয়ে, নিশ্চিত হলে আসল সাবমিট চালানো
  document.addEventListener(
    "submit",
    function (e) {
      var form = e.target;
      if (!(form instanceof HTMLFormElement)) return;
      var msg = form.getAttribute("data-confirm");
      if (msg === null || form.__ameConfirmed) return;
      e.preventDefault();
      ameConfirm(msg, { type: form.getAttribute("data-confirm-type") || "" }).then(function (ok) {
        if (ok) {
          form.__ameConfirmed = true;
          if (typeof form.requestSubmit === "function") {
            form.requestSubmit();
          } else {
            HTMLFormElement.prototype.submit.call(form);
          }
          return;
        }
        form.__ameConfirmed = false;
        if (window.AmeSubmitWait && typeof window.AmeSubmitWait.hideAll === "function") {
          window.AmeSubmitWait.hideAll();
        }
      });
    },
    true
  );

  // data-confirm সহ <a>/<button type="button"> ক্লিক করলে (form-বহির্ভূত)
  document.addEventListener("click", function (e) {
    var el = e.target.closest("[data-confirm]");
    if (!el || el instanceof HTMLFormElement) return;
    if (el.closest("form") && el.type === "submit") return; // এটা form-এর submit ইভেন্টে হ্যান্ডেল হবে
    if (el.__ameConfirmed) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    ameConfirm(el.getAttribute("data-confirm"), { type: el.getAttribute("data-confirm-type") || "" }).then(function (ok) {
      if (!ok) return;
      el.__ameConfirmed = true;
      if (el.tagName === "A" && el.href) {
        window.location.href = el.href;
      } else {
        el.click();
      }
    });
  });
})();
