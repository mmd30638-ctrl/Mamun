# APONSHOP Permanent UI/UX Device Standard

একই প্রজেক্ট → একই URL → ডিভাইস অনুযায়ী সঠিক UI।

| ডিভাইস / মোড | লেআউট |
|---|---|
| Mobile Chrome (সাধারণ) | Mobile layout |
| Tablet | Tablet layout |
| Desktop | Desktop layout |
| Mobile Chrome “Desktop site” | Desktop layout (`html.ame-phone-desktop`) |

## বাধ্যতামূলক নিয়ম

1. মোবাইল Chrome-এ সাধারণভাবে খুললে সম্পূর্ণ মোবাইল অপটিমাইজড লেআউট।
2. Chrome “Desktop site” চালু থাকলে ডেস্কটপ সাইডবার, কলাম, টেবিল ও স্পেসিং। মোবাইল-only ন্যাভ থাকবে না। অপ্রয়োজনীয় হরাইজন্টাল স্ক্রল থাকবে না।
3. ট্যাবলেট স্ক্রিন নিজে থেকে মানিয়ে নেবে।
4. এই নিয়ম শুধু ড্যাশবোর্ড নয় — প্রতিটি পেজ, মডিউল, ফর্ম, লিস্ট, রিপোর্ট, POS, সেটিংস ও ভবিষ্যৎ পেজ।
5. নতুন আপডেট আগের রেসপন্সিভ বা Desktop Site কম্প্যাটিবিলিটি ভাঙতে পারবে না।

## টেকনিক্যাল

- `assets/js/ame-desktop-site.js` — সব পেজে আগে লোড। ফোনে Desktop site হলে `ame-phone-desktop` + `data-ame-device="pc"`।
- `assets/js/ame-viewport-tier.js` — গ্লোবাল ব্রেকপয়েন্ট: মোবাইল `<576px`, ট্যাবলেট `576–991px`, ডেস্কটপ `≥992px` (`data-ame-tier`)।
- `assets/css/ame-global-responsive-master.css` — সব পেজে **সব CSS-এর পরে** লোড; অপসারণ বা ওভাররাইড নিষিদ্ধ (`GLOBAL_RESPONSIVE_ARCHITECTURE.md`)।
- `assets/css/ame-device.css` — ফোন / ট্যাবলেট / পিসি / Desktop-site লেআউট।
- Viewport মেটা: `width=device-width, initial-scale=1.0, viewport-fit=cover`
- `html[data-ame-device="phone|tablet|pc"]`, `html[data-ame-tier="mobile|tablet|desktop"]`, `html.ame-phone-desktop` দিয়ে টার্গেট করুন।
- নতুন পেজে আগে এই স্ট্যান্ডার্ড মানা, তারপর ফিচার।
