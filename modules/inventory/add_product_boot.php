<?php
if (!isset($is_edit)) {
    $is_edit = false;
}
if (!isset($p) || !is_array($p)) {
    $p = [];
}

$language = function_exists('current_lang') && current_lang() === 'en' ? 'en' : 'bn';

if ($language === 'en') {
    $words = [
        'title_add' => 'Add New Product',
        'title_edit' => 'Edit Product',
        'subtitle_add' => 'Scroll for every option: three selling prices (K retail, P wholesale, D re-dealer), stock, photo, specs and warranty.',
        'subtitle_edit' => 'Update this product. Retail, wholesale and re-dealer prices stay mapped to POS K / P / D.',
        'jump_id' => 'Identity',
        'jump_catalog' => 'Catalog',
        'jump_specs' => 'Specs',
        'jump_media' => 'Photo',
        'jump_price' => 'Prices',
        'jump_stock' => 'Stock',
        'jump_more' => 'Warranty',
        'jump_seo' => 'Notes',
        'identity' => 'Product identity',
        'name' => 'Product name',
        'name_placeholder' => 'Example: Honda 12N9-BS battery',
        'barcode' => 'Barcode',
        'barcode_placeholder' => 'Scan or type barcode',
        'barcode_auto' => 'Auto barcode',
        'barcode_hint' => 'Leave empty to generate after save. Must be unique.',
        'type' => 'Product type',
        'type_part' => 'Spare part',
        'type_new_battery' => 'New battery',
        'type_old_battery' => 'Old battery / exchange',
        'type_accessory' => 'Accessory',
        'status' => 'Status',
        'status_draft' => 'Draft',
        'status_active' => 'Active',
        'status_inactive' => 'Inactive',
        'catalog' => 'Category & brand',
        'category' => 'Category',
        'sub_category' => 'Sub-category',
        'sub_placeholder' => 'Example: 12V motorcycle',
        'brand' => 'Brand',
        'manufacturer' => 'Manufacturer',
        'mfr_placeholder' => 'Factory / maker name',
        'unit' => 'Unit',
        'select' => 'Select',
        'specs' => 'Workshop specifications',
        'shelf' => 'Shelf / location',
        'shelf_placeholder' => 'Example: Rack-3, Shelf-B',
        'oem' => 'OEM / maker code',
        'oem_placeholder' => 'Factory part number',
        'supplier_code' => 'Supplier code',
        'supplier_placeholder' => 'Supplier part number',
        'model' => 'Model',
        'size' => 'Size',
        'color' => 'Color',
        'weight' => 'Weight (kg)',
        'short_desc' => 'Short description',
        'short_placeholder' => 'One or two lines for the counter',
        'full_desc' => 'Full description',
        'highlights' => 'Highlights (one per line)',
        'media' => 'Product photo',
        'photo' => 'Main photo',
        'photo_hint' => 'JPG, PNG or WEBP. Max 4 MB. Saved as an optimized JPEG.',
        'remove_photo' => 'Remove current photo',
        'pricing' => 'Three selling prices',
        'pricing_note' => 'These three prices are stored on the product. POS K = retail, P = wholesale, D = re-dealer.',
        'purchase' => 'Purchase cost (৳)',
        'retail' => 'Retail selling price (৳)',
        'wholesale' => 'Wholesale selling price (৳)',
        'dealer' => 'Re-dealer selling price (৳)',
        'vat' => 'VAT %',
        'discount' => 'Discount %',
        'moq' => 'Minimum order qty',
        'profit_preview' => 'Live profit per unit',
        'retail_profit' => 'Retail (K)',
        'wholesale_profit' => 'Wholesale (P)',
        'dealer_profit' => 'Re-dealer (D)',
        'profit_ok' => 'All three selling prices are above purchase cost.',
        'loss_warning' => 'Warning: one or more selling prices are below purchase cost.',
        'inventory' => 'Inventory & warehouse',
        'stock' => 'Opening stock',
        'stock_edit' => 'Current stock (this store)',
        'minimum_stock' => 'Low-stock alert',
        'more' => 'Warranty, origin & shipping weight',
        'warranty_months' => 'Warranty (months)',
        'warranty_type' => 'Warranty type',
        'warranty_placeholder' => 'Replacement / service / none',
        'return_policy' => 'Return / replacement note',
        'origin' => 'Country of origin',
        'hs' => 'HS code',
        'seo' => 'Tags, SEO & internal notes',
        'tags' => 'Tags / keywords',
        'reference' => 'Internal reference',
        'seo_title' => 'SEO title',
        'seo_keywords' => 'SEO keywords',
        'notes' => 'Internal notes (staff only)',
        'score' => 'Completeness',
        'score_hint' => 'Fill required prices and identity for a high score.',
        'preview' => 'Live preview',
        'save' => 'Save product',
        'save_edit' => 'Update product',
        'saving' => 'Saving…',
        'back' => 'Back to stock list',
        'draft_restored' => 'Unsaved draft restored on this phone.',
        'clear_draft' => 'Clear draft',
        'missing' => 'Still missing',
        'pos_map' => 'POS price map',
    ];
} else {
    $words = [
        'title_add' => 'নতুন পণ্য যোগ করুন',
        'title_edit' => 'পণ্য সম্পাদনা',
        'subtitle_add' => 'নিচে স্ক্রল করুন: খুচরা / পাইকারি / রি-ডিলার তিন মূল্য, স্টক, ছবি, স্পেক ও ওয়ারেন্টি — সব এক ফর্মে।',
        'subtitle_edit' => 'এই পণ্য আপডেট করুন। খুচরা, পাইকারি ও রি-ডিলার মূল্য POS-এর K / P / D-তেই থাকে।',
        'jump_id' => 'পরিচয়',
        'jump_catalog' => 'ক্যাটালগ',
        'jump_specs' => 'স্পেক',
        'jump_media' => 'ছবি',
        'jump_price' => 'মূল্য',
        'jump_stock' => 'স্টক',
        'jump_more' => 'ওয়ারেন্টি',
        'jump_seo' => 'নোট',
        'identity' => 'পণ্যের পরিচয়',
        'name' => 'পণ্যের নাম',
        'name_placeholder' => 'যেমন: Honda 12N9-BS ব্যাটারি',
        'barcode' => 'বারকোড',
        'barcode_placeholder' => 'স্ক্যান বা টাইপ করুন',
        'barcode_auto' => 'অটো বারকোড',
        'barcode_hint' => 'খালি রাখলে সেভের পর নিজে তৈরি হবে। ইউনিক হতে হবে।',
        'type' => 'পণ্যের ধরন',
        'type_part' => 'যন্ত্রাংশ',
        'type_new_battery' => 'নতুন ব্যাটারি',
        'type_old_battery' => 'পুরাতন ব্যাটারি / এক্সচেঞ্জ',
        'type_accessory' => 'আনুষঙ্গিক',
        'status' => 'স্ট্যাটাস',
        'status_draft' => 'খসড়া',
        'status_active' => 'সক্রিয়',
        'status_inactive' => 'নিষ্ক্রিয়',
        'catalog' => 'ক্যাটেগরি ও ব্র্যান্ড',
        'category' => 'ক্যাটেগরি',
        'sub_category' => 'সাব-ক্যাটেগরি',
        'sub_placeholder' => 'যেমন: ১২ ভোল্ট মোটরসাইকেল',
        'brand' => 'ব্র্যান্ড',
        'manufacturer' => 'প্রস্তুতকারক',
        'mfr_placeholder' => 'কারখানা / মেকারের নাম',
        'unit' => 'ইউনিট',
        'select' => 'নির্বাচন করুন',
        'specs' => 'ওয়ার্কশপ স্পেসিফিকেশন',
        'shelf' => 'তাক / লোকেশন',
        'shelf_placeholder' => 'যেমন: র‍্যাক-৩, শেলফ-B',
        'oem' => 'OEM / মেকার কোড',
        'oem_placeholder' => 'কারখানার পার্ট নম্বর',
        'supplier_code' => 'সাপ্লায়ার কোড',
        'supplier_placeholder' => 'সাপ্লায়ারের পার্ট নম্বর',
        'model' => 'মডেল',
        'size' => 'সাইজ',
        'color' => 'রঙ',
        'weight' => 'ওজন (কেজি)',
        'short_desc' => 'সংক্ষিপ্ত বিবরণ',
        'short_placeholder' => 'কাউন্টারের জন্য এক-দুই লাইন',
        'full_desc' => 'পূর্ণ বিবরণ',
        'highlights' => 'হাইলাইট (প্রতি লাইনে একটি)',
        'media' => 'পণ্যের ছবি',
        'photo' => 'মূল ছবি',
        'photo_hint' => 'JPG, PNG বা WEBP। সর্বোচ্চ ৪ এমবি। অপটিমাইজ করে JPEG সেভ হয়।',
        'remove_photo' => 'বর্তমান ছবি মুছুন',
        'pricing' => 'তিনটি বিক্রয় মূল্য',
        'pricing_note' => 'পণ্যে এই তিন মূল্য সংরক্ষিত থাকে। POS-এ K = খুচরা, P = পাইকারি, D = রি-ডিলার।',
        'purchase' => 'ক্রয় মূল্য (৳)',
        'retail' => 'খুচরা বিক্রয় মূল্য (৳)',
        'wholesale' => 'পাইকারি বিক্রয় মূল্য (৳)',
        'dealer' => 'রি-ডিলার বিক্রয় মূল্য (৳)',
        'vat' => 'ভ্যাট %',
        'discount' => 'ছাড় %',
        'moq' => 'সর্বনিম্ন অর্ডার পরিমাণ',
        'profit_preview' => 'প্রতি ইউনিটে লাভ',
        'retail_profit' => 'খুচরা (K)',
        'wholesale_profit' => 'পাইকারি (P)',
        'dealer_profit' => 'রি-ডিলার (D)',
        'profit_ok' => 'তিনটি বিক্রয়মূল্যই ক্রয়মূল্যের উপরে।',
        'loss_warning' => 'সতর্কতা: এক বা একাধিক বিক্রয়মূল্য ক্রয়মূল্যের চেয়ে কম।',
        'inventory' => 'স্টক ও গুদাম',
        'stock' => 'প্রারম্ভিক স্টক',
        'stock_edit' => 'বর্তমান স্টক (এই স্টোর)',
        'minimum_stock' => 'কম স্টক সতর্কতা',
        'more' => 'ওয়ারেন্টি, উৎস ও ওজন',
        'warranty_months' => 'ওয়ারেন্টি (মাস)',
        'warranty_type' => 'ওয়ারেন্টির ধরন',
        'warranty_placeholder' => 'রিপ্লেসমেন্ট / সার্ভিস / নেই',
        'return_policy' => 'রিটার্ন / রিপ্লেসমেন্ট নোট',
        'origin' => 'উৎস দেশ',
        'hs' => 'এইচএস কোড',
        'seo' => 'ট্যাগ, এসইও ও অভ্যন্তরীণ নোট',
        'tags' => 'ট্যাগ / কিওয়ার্ড',
        'reference' => 'অভ্যন্তরীণ রেফারেন্স',
        'seo_title' => 'এসইও টাইটেল',
        'seo_keywords' => 'এসইও কিওয়ার্ড',
        'notes' => 'অভ্যন্তরীণ নোট (শুধু স্টাফ)',
        'score' => 'সম্পূর্ণতা',
        'score_hint' => 'নাম, তিন মূল্য ও পরিচয় পূরণ করলে স্কোর বাড়ে।',
        'preview' => 'লাইভ প্রিভিউ',
        'save' => 'পণ্য সংরক্ষণ করুন',
        'save_edit' => 'পণ্য আপডেট করুন',
        'saving' => 'সংরক্ষণ হচ্ছে…',
        'back' => 'স্টক তালিকায় ফিরে যান',
        'draft_restored' => 'এই ফোনে আগের খসড়া ফিরে এসেছে।',
        'clear_draft' => 'খসড়া মুছুন',
        'missing' => 'এখনো খালি',
        'pos_map' => 'POS মূল্য ম্যাপ',
    ];
}

$escape = static function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

if (!function_exists('ame_mysqli_rows')) {
    require_once __DIR__ . '/../../includes/helpers.php';
}

if (function_exists('ame_ensure_category_archive')) {
    ame_ensure_category_archive($db);
}
$product_categories = ame_mysqli_rows($db->query(
    'SELECT id, name
     FROM categories
     WHERE (status = 1 OR status IS NULL)
       AND (archived = 0 OR archived IS NULL)
     ORDER BY name ASC'
));

$product_brands = ame_mysqli_rows($db->query(
    'SELECT id, name
     FROM brands
     ORDER BY name ASC'
));

$product_units = ame_mysqli_rows($db->query(
    'SELECT id, unit_name
     FROM product_units
     ORDER BY unit_name ASC'
));

$categories = $product_categories;
$brands = $product_brands;
$units = $product_units;
