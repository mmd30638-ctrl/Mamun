# APONSHOP 10.98.870

## ফিক্স

1. **পণ্য তালিকা — মোট অ্যামাউন্ট:** কার্ট টেবিলের নিচে `মোট অ্যামাউন্ট (৳)` সারি; সব লাইন মোটের যোগফল।
2. **ডান পেমেন্ট প্যানেল স্ক্রল:** `ame-pos-summary` কলামে `max-height` + ভিতরের `overflow-y: auto` যাতে সব পেমেন্ট অপশন দেখা ও স্ক্রল করা যায়।

## ইমপোর্ট (ইঞ্জিন রুম)

- **ফোনে ৯ MB সীমা:** `Aponshop_10.98.870_PATCH.zip` (~৪৫ KB) আপলোড করুন — **DB-ও ফেরান টিক দেবেন না।**
- **ওয়ান-ক্লিক ফুল স্ট্যাক:** `Aponshop_POS_STACK_UPDATE.zip` (~৯.৩ MB) — ৯ MB-এর উপরে হলে **লিংক থেকে সার্ভার নামান** ব্যবহার করুন।

## বিল্ড

```bash
php make_update.php           # Aponshop_POS_STACK_UPDATE.zip + preflight
php make_update.php --patch   # ছোট PATCH (manifest: tools/update_manifest.txt)
```