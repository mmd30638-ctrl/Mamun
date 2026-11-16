# APONSHOP Performance Standard — FAST + LIGHT + SECURE + SCALABLE

> **স্থায়ী লক:** শপে `সেটিংস → স্পিড সিস্টেম` এবং রুটে `SPEED.txt`। মুছবেন না।
> লাইভ স্ট্যাক (১০.৯৮.৪৬৬+): gzip, POS-only CSS, লোগো ~৪৭ KB, COUNT ইনডেক্স, keep-warm ৪৫স, বাকি/স্টক/লেজার ক্যাশ নয়, ছোট PATCH ZIP।
> ভবিষ্যতে **প্রতিটি Update/Module** এই স্ট্যান্ডার্ড মেনে করতে হবে।
> নতুন ফিচার দেওয়ার আগে যাচাই: "এটা কি DB load, CPU/RAM, storage বা page speed বাড়াচ্ছে?"
> বাড়ালে optimize করে তারপর update। পুরনো page slow করা যাবে না।

## 1. Database
- Search/Filter/JOIN/Sort কলামে index বাধ্যতামূলক। নতুন কলামে সার্চ হলে `includes/perf_indexes.php`-এর তালিকায় যোগ করো।
- `SELECT *` নিষেধ — শুধু দরকারি কলাম।
- N+1 নিষেধ — লুপে query নয়, JOIN/GROUP BY/in-ব্যাচ।
- Write পথে transaction + prepared statement।
- রক্ষণাবেক্ষণ: `maintenance.php` (রাতের cron) index নিশ্চিত করে।

## 2. Pagination
- History/list পেজে date-range বাধ্যতামূলক + `LIMIT` (সর্বোচ্চ 800, লক্ষ্য ≤100/page)।
- Search ছাড়া full-history load নিষেধ। বড় তালিকায় AJAX/fetch।

## 3. Dashboard
- প্রথম লোডে শুধু summary। Heavy report আলাদা tab/পেজে।
- একই query এক request-এ একবার (request-local reuse/memoization)। Dashboard summary প্রতিবার live query হবে; persistent cache নয়।

## 4. PHP
- `display_errors=0` (production), `log_errors=1`। User-এর সামনে PHP error নয়।
- একই function/request-এ duplicate কাজ নয়। অপ্রয়োজনীয় include/external call নয়।

## 5. Cache — শুধু STATIC ডাটা
- শুধু Category, Settings, Permission, Banner → `includes/perf_cache.php`।
- **Dashboard summary, Sales, Purchase, Stock, Ledger, Due, Payment ও Account Balance কখনো cache নয়** (সবসময় live data)।
- Write-পথে সংশ্লিষ্ট key `ame_perf_cache_forget()` করতে হবে।

## 6. Image & Files
- Upload-এ resize (সর্বোচ্চ 1280px) + compress (JPEG q≤82)। বিশাল original রাখা নয়।
- Duplicate: content-hash নাম (`cus_<hash>.jpg`, `slide_<hash>.ext`) — একই ফাইল একবার।
- File delete-এ অন্য row ব্যবহার করছে কিনা দেখে মুছতে হবে।
- Temp/failed upload ২৪ ঘণ্টা পর auto-clean (business data কখনো নয়)।

## 7. Logs
- Production-এ debug log নয়। Error log আকার-সীমায়: 5MB+ হলে rotate, সর্বশেষ ৩টা।
- প্রতি request-এ অপ্রয়োজনীয় log নয়।

## 8. Backup
- প্রতি রাতে cron (`maintenance.php`, 0 2 * * *) → webroot-এর বাইরে `.sql.gz`।
- Retention ৭ দিন। Lock ফাইল — একসাথে একটাই backup (live slow নয়)।
- Chunked read + multi-row INSERT + streaming gzip (RAM-safe)। Dump-এ `SET NAMES utf8mb4` + SQL_MODE save/restore।
- DB + file backup আলাদা। Restore পরীক্ষা ছাড়া backup-ইঞ্জিন বদলানো নয়।

## 9. Frontend
- CSS/JS-এ `?v=AME_ASSET_VERSION` বাধ্যতামূলক। একই lib দুইবার নয়।
- ছবিতে `loading="lazy"` (প্রথম স্ক্রিন ছাড়া)। ভারী font/icon lib নয়।
- Mobile + PC দুটোতেই দ্রুত — বড় DOM/table এড়িয়ে চলো।

## 10. AJAX
- Product/Customer/Supplier search, ledger, report — fetch + সীমিত ফলাফল (≤40)।
- পুরো page reload নয় যেখানে আংশিক load যথেষ্ট।

## 11. Security + Performance
- Prepared statement, CSRF, permission, secure session — কিন্তু এক request-এ একই check একবার (cache/unset নয়, memoize)।

## 12. Monitoring
- `php tools/perf_check.php` — critical query সময়, missing index, বড় টেবিল, storage, 24h error।
- বড় update-এর আগে/পরে চালিয়ে তুলনা করো।

## 13. Auto Cleanup (নিরাপদ)
- `maintenance.php`: expired cache, tmp, failed upload, বড় log rotate।
- **Customer/Supplier/Product/Sales/Purchase/Payment/Ledger কখনো auto-delete নয়।**

## 14. Reports
- Date-range বাধ্যতামূলক (ডিফল্ট আজ/স্বল্প)। Full-year/full-history ডিফল্ট নয়।
- Export-এ memory-safe loop + LIMIT (বর্তমান: 3000 সারি)।

## 15. Architecture
- Reusable function (`includes/`) — duplicate code নয়। Query আর business logic আলাদা।
- একই কাজে দুই code-path নয়।

## 16–18. Update Policy / Testing / Golden Rule
- নতুন module-এ উপরের ১–১৫ চেকলিস্ট প্রযোজ্য।
- বড় update-এর পর test: Dashboard, Sales, Purchase, Product, Customer, Supplier, Ledger, Reports, Search (mobile + PC)।
- নীতি: **কম resource, কম query, দ্রুত load, বেশি data-তেও smooth।**
