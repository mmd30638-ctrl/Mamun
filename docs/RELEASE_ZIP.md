# Release ZIP (locked)

User confirmed this format on 2026-09-29. Later the same day they locked the **phone** delivery:

«এখন যেভাবে দিয়েছেন সব সময় এভাবেই দেবেন যতগুলো আপডেট চাইবো এটা নোট করে রাখেন শক্তভাবে»

See `PHONE_ONE_CLICK_ZIP.md`. That file wins over every other ZIP instruction.

**Always deliver the one-click ZIP** after any live-site change. A commit alone is not the deliverable.

**Bump the version on every ZIP** (প্রতিটাতে বাড়বে). `/v.php` must show the new number after Easy Update.

**Inner name:** `Aponshop_<VERSION>_FULL_STACK.zip`  
**Inside:** `public_html/` at the root of that archive.

**The only phone link:**

```
https://filebin.net/archive/<bin>/zip
```

One file in the bin. Small patch (tens of KB). Not a direct Filebin file URL. Not two files. Not `127.0.0.1`.

Install: Settings → Tools → সহজ আপডেট → **লিংক থেকে সার্ভার নামিয়ে ইমপোর্ট**. Keep live `.env` and `uploads/`.
