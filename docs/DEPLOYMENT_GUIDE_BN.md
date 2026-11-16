# APONSHOP 10.98.165 — নিরাপদ চালুর নির্দেশ

এই রিলিজে কোড সংশোধন আছে। গ্রাহকের আসল ডাটাবেজ, ছবি এবং পুরোনো SQL এই প্যাকেজে নেই। সেগুলো সার্ভারের webroot-এর বাইরে রাখতে হবে।

## ১. সার্ভারে ফোল্ডার

```text
/home/<USER>/domains/<DOMAIN>/.env
/home/<USER>/domains/<DOMAIN>/private_aponshop/original/
/home/<USER>/domains/<DOMAIN>/private_aponshop/migration_source/
/home/<USER>/domains/<DOMAIN>/private_aponshop/reports/
/home/<USER>/domains/<DOMAIN>/staging_public_html/
```

Hostinger অ্যাকাউন্ট-রুট (`/home/u718102670/`) হলে লাইভ `public_html/` এবং নতুন কোড `staging/` আলাদা রাখুন। `.env` লাইভের; `.env.staging` শুধু staging-এর।

`private_aponshop/original/`-এ অপরিবর্তিত ZIP ও `u718102670_aponshop_hrm.sql` রাখুন।

`migration_source/`-এ শুধু এই ফাইলগুলো রাখুন:

```text
split_mj_a82be6b341a61c49/product.sql
split_mj_a82be6b341a61c49/sale.sql
split_mj_a82be6b341a61c49/sale_item.sql
data/mae_balances_20260919.json
```

একই নামের দ্বিতীয় `split_mj_cf8f1da75f6a4e1c` কপি private backup-এ রেখে webroot থেকে সরিয়ে দিন।

## ২. কোড আপলোড

মোবাইল ওয়ান-ক্লিক ZIP: `deliverables/Aponshop_10.98.165_FULL_STACK.zip` (একই ফাইল `Aponshop_POS_STACK_UPDATE.zip`)। ভিতরে `public_html/` আছে। ফোনে নামিয়ে Hostinger File Manager-এ **ডোমেইন ফোল্ডারে** Extract + Overwrite করুন — `public_html`-এর ভিতরে নয়। Easy Update-এও এই ZIP চলে। `VERSION.txt`-এ `10.98.165` থাকতে হবে। SHA256: `1ad933499cac9e519fa42bfc6dd77ce824ebf97915f7b818289d92c7c2a087f2`।

ফোল্ডার প্যাকেজ চাইলে `deliverables/aponshop-10.98.165-clean.zip` খালি staging document root-এ extract করুন।

বিদ্যমান staging-এর এই ফোল্ডারগুলো মুছবেন না; নতুন কোডের উপরে সেগুলো বহাল থাকবে:

```text
uploads/customers/
uploads/employees/
uploads/family/
uploads/products/
uploads/user-documents/
```

## ৩. `.env`

লাইভ `.env` `public_html`-এর ঠিক এক ধাপ উপরে থাকবে, permission `600`। Staging ও `*.hostingersite.com` `.env.staging` পড়ে — আগে প্যারেন্ট ফোল্ডার, Hostinger temporary domain-এ PHP প্যারেন্ট না পড়লে `public_html/.env.staging`। `.htaccess` `.env*`-কে ব্রাউজার থেকে ব্লক করে। নমুনা `.env.staging.example`-এ আছে:

```dotenv
DB_NAME=u718102670_aponshop_stg
DB_USER=u718102670_aponshop_stg
```

`DB_PASS` শুধু সার্ভারের `.env.staging`-এ লিখুন, চ্যাটে নয়। Production-এ চারটি flag-ই `0` রাখুন:

```dotenv
AME_ALLOW_INSTALL_WIPE=0
ALLOW_WEB_DB_RESTORE=0
AME_ALLOW_REMOTE_INSTALL=0
AME_ENABLE_MIGRATION_TOOLS=0
```

## ৪. ডাটাবেজ

প্রথমে আলাদা staging database-এ মূল SQL restore করুন। তারপর এই ক্রমে চালান:

```bash
mariadb -h localhost -u DB_USER -p STAGING_DB < migrations/001_schema_repair.sql
```

এরপর private source দিয়ে dry-run:

```bash
php migrations/002_import_legacy_sale_items.php \
  --source-dir=/home/<USER>/domains/<DOMAIN>/private_aponshop/migration_source/split_mj_a82be6b341a61c49 \
  --balances-json=/home/<USER>/domains/<DOMAIN>/private_aponshop/migration_source/data/mae_balances_20260919.json \
  --report=/home/<USER>/domains/<DOMAIN>/private_aponshop/reports/legacy-items.json
```

Report-এ unresolved row না থাকলে `--apply` যোগ করে একই কমান্ড চালান। unresolved থাকলে script নিজে থেকেই থেমে যাবে। তখন mapping review না করে `--allow-unresolved` ব্যবহার করবেন না।

একই private source দিয়ে আলাদা test database-এ মাপা ফল:

```text
source sale_item row          159,998
mapped ও import হওয়া item     159,849
unresolved item               28
inactive source row (বাদ)     121
দ্বিতীয়বার চালালে নতুন insert  0
আগে থেকে থাকা sale_items      3
শেষে মোট sale_items           159,852
item ছাড়া summary-only sale   1,676
```

২৮টি unresolved row দুটি legacy product-এর: product `159` source-এ নেই, product `656`-এর নাম/SKU একাধিক বর্তমান product-এ মিলে যায়। এ দুটি না মেলানো পর্যন্ত default `--apply` থেমে থাকবে। `--allow-unresolved` শুধু এই ২৮টি row বাদ দিয়ে বাকিগুলো লিখতে চাইলে, এবং শুধু staging-এ review-এর পর, ব্যবহার করুন।

Schema verification-এ structural error `0`। মালিকের সিদ্ধান্ত (২৪ সেপ্টেম্বর ২০২৬): খালি supplier status সব `active`। Product `708` মুছে ফেলা হবে না — ক্যাটালগ row না থাকায় `003` একটি recovery নামে পণ্যটা ফিরিয়ে `-41` স্টক রাখে। নাম/দাম অনুমান করা হয়নি; ইনভেন্টরিতে পরে নাম বদলান।

`migrations/003_data_repairs.sql` এখন এই সিদ্ধান্ত নিয়েই চালানো যায়।

শেষে:

```bash
mariadb -h localhost -u DB_USER -p STAGING_DB < verification/verify_database.sql
```

`error_count` যেখানে আছে, সবখানে `0` না হলে production-এ যাবেন না।

## ৫. Staging যাচাই

- `/` login page-এ যায়।
- Admin login, password reset, permission।
- নতুন sale, purchase, customer payment, supplier payment।
- পুরোনো invoice-এ recovered item দেখা।
- Salary/advance ও account report।
- এই URL সরাসরি খুললে 403/404 হতে হবে:

```text
/data/
/sql/aponshop_full_schema.sql
/migrations/001_schema_repair.sql
/includes/config.php
```

## ৬. Live

কোড আপডেট। লাইভ ডাটাবেজ `u718102670_aponshop_hrm` ইমপোর্ট বা ড্রপ করবেন না। স্টেজিং SQL লাইভে বসাবেন না। `/home/u718102670/.env` বদলাবেন না। `includes/.env.staging` লাইভ `public_html`-এ কপি করবেন না।

`deliverables/Aponshop_10.98.167_LIVE.zip`-এর ভিতরে ফাইল সরাসরি আছে (`login.php`, `includes/`)। লাইভ `public_html`-এর ভিতরে Extract + Overwrite। `uploads/` মুছবেন না। স্ক্রিনের ভার্সন `10.98.167` হতে হবে।

১. hPanel Backup অথবা phpMyAdmin থেকে `u718102670_aponshop_hrm` এক্সপোর্ট নিন।
২. File Manager-এ লাইভ `public_html` জিপ করে ডাউনলোড রাখুন।
৩. `Aponshop_10.98.167_LIVE.zip` সেই `public_html`-এ আপলোড করে Extract, Overwrite।
৪. `https://mavo12.online/login.php` খুলে লাইভ ইউজার দিয়ে ঢুকে একটা পুরনো ইনভয়েস দেখুন।
৫. ভাঙলে ব্যাকআপ জিপ আবার Extract করুন। `.env` আগেরটাই থাকবে।

Google Drive-এর মূল লিংক দুটি Private রাখুন। Database password, admin password এবং API key কখনো চ্যাটে বা Git-এ দেবেন না।
