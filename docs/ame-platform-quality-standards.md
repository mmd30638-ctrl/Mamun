# APONSHOP Platform Quality Standards (intl-100)

এই ফাইল প্রতিটি Easy Update ও কোড পরিবর্তনের জন্য **বাধ্যতামূলক চেকলিস্ট**।
কোড: `includes/ame_platform_contract.php` · CSS/JS: `assets/css/ame-platform-contract.css`, `assets/js/ame-platform-contract.js`.

## প্ল্যাটফর্ম (সব পেজ)

| পরিবেশ | পরীক্ষা |
|--------|--------|
| ডেস্কটপ ব্রাউজার | ১০২৪px+ লেআউট, হোভার |
| Android Chrome মোবাইল | ≤৭২০px, টাচ টার্গেট |
| Android Chrome **ডেস্কটপ সাইট** | `pointer: coarse` + চওড়া ভিউ — কার্ড/স্ক্রল ঠিক আছে কিনা |

`html` এ ক্লাস: `ame-platform-contract`, প্রয়োজনে `ame-mobile-desktop-mode` (JS সেট করে)।

## প্রতিটি আপডেটে

1. `AME_ASSET_VERSION` বাড়ান (`includes/config.php`)।
2. Easy Update — **DB restore টিক দেবেন না** (শুধু ফাইল)।
3. তিনটি প্ল্যাটফর্মে স্মোক টেস্ট।
4. পার্টি অ্যামাউন্ট — শুধু ক্রয় / পেমেন্ট / অ্যাডমিন (Party Amount Shield)।
5. মডিউল UI একরঙ/টাইপো — প্রাসঙ্গিক `*_ui.php` / ডিজাইন সিস্টেম ব্যবহার।

## সাপ্লায়ার মডিউল

- `supplier_module_guard.php` — ভিউ পেজ লেখে না।
- `supplier_due_ui.php` — কমলা payable / সবুজ credit প্রদর্শন।

## এজেন্ট / ডেভেলপার

নতুন কাজ শুরুর আগে পড়ুন: `includes/ame_platform_contract.php`, এই ফাইল, এবং `.cursor/rules/aponshop-platform-contract.mdc` (যদি থাকে)।