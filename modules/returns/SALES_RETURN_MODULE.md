# বিক্রয় ফেরত মডিউল (Sales Return Module)

**সংস্করণ:** `AME_SALES_RETURN_MODULE_REV` (`sales_return_module.php`)  
**স্ট্যাটাস:** একীভূত মডিউল — আন্তর্জাতিক POS/ERP রিটার্ন ওয়ার্কফ্লোর সাথে সামঞ্জস্যপূর্ণ হাব + v2 লেনদেন ইঞ্জিন।

## সীমানা (অন্য মডিউল অপরিবর্তিত)

| অনুমোদিত | নিষেধ |
|----------|--------|
| `modules/returns/**` | `modules/sales/*`, `modules/purchase/*`, POS, HRM ইত্যাদি |
| `includes/return_v2_functions.php` (ইতিমধ্যে বিদ্যমান) | বিক্রয় `process_sale.php` বা লেজার কোর |
| `assets/css/sales-return-module.css` | — |
| সাইডবারে **এক** লিংক (`partials/sidebar-menu.php`) | সাব-মেনুতে আলাদা ৭ লিংক |

## এন্ট্রি পয়েন্ট

- সাইডবার: **বিক্রয় ফেরত** → `/modules/returns/index.php` → `v2/dashboard.php`
- পারমিশন: `m-sales-returns`

## ফিচার ম্যাপ (এক মডিউলের ভিতর)

| ID | পেজ | কাজ |
|----|-----|-----|
| `dashboard` | `v2/dashboard.php` | KPI, দ্রুত লিংক |
| `register` | `v2/register.php` | রেজিস্টার, ফিল্টার |
| `invoice` | `v2/create_invoice_return.php` | ইনভয়েস ভিত্তিক ফেরত |
| `manual` | `v2/create_manual_return.php` | হাতে লেখা ফেরত |
| `exchange` | `v2/create_exchange.php` | পণ্য বদল |
| `report` | `return_report.php` | সারাংশ রিপোর্ট |
| `legacy` | `history.php` | পুরনো v1 ইতিহাস |

সকল রুট কেন্দ্রীয়: `ame_sales_return_sections()` in `sales_return_module.php`.

## UI মানদণ্ড

1. প্রতিটি UI পেজে `ame_sales_return_include_nav()` — একই ট্যাব নেভ।
2. v2 লেনদেন: `sale_returns_v2` + `includes/return_v2_functions.php`.
3. স্টক, ক্রেতা লেজার, অ্যাকাউন্ট — শুধু v2 অ্যাকশন/API দিয়ে; বিক্রয় মডিউলে সরাসরি লিখবেন না।

## যাচাই তালিকা (ডেভেলপার / অডিট)

- [ ] সাইডবারে শুধু **এক** «বিক্রয় ফেরত» লিংক, সাব-মেনু নেই।
- [ ] `index.php` → ড্যাশবোর্ড রিডাইরেক্ট।
- [ ] সাতটি সেকশন ট্যাব থেকে খোলে যায়।
- [ ] নতুন ফিচার যোগ করলে **শুধু** `ame_sales_return_sections()` আপডেট।
- [ ] অন্য মডিউলের ফাইলে return-সংক্রান্ত লজিক যোগ করা হয়নি।

## ভবিষ্যত কাজ (এক মডিউলের ভিতর)

- v1 (`history.php`) শুধু পড়ার জন্য; নতুন এন্ট্রি v2 দিয়ে।
- ইচ্ছা করলে `view.php` PDF/প্রিন্ট একই নেভের অধীনে রাখা (ইতিমধ্যে register ট্যাব অ্যাক্টিভ)।

প্যাচ নোট: `Aponshop_10.98.962_PATCH.zip`