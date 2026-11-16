<?php
if (!empty($GLOBALS['ame_pos_customer_data_ready'])) {
    return;
}
$GLOBALS['ame_pos_customer_data_ready'] = true;

if (!isset($escape) || !is_callable($escape)) {
    $escape = static function ($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    };
}

$is_english = (bool)(
    $is_english
    ?? $ame_pos_english
    ?? (function_exists('current_lang') && current_lang() === 'en')
);

if (empty($_SESSION['pos_customer_csrf'])) {
    try {
        $_SESSION['pos_customer_csrf'] = bin2hex(random_bytes(32));
    } catch (Throwable $error) {
        $_SESSION['pos_customer_csrf'] = hash('sha256', session_id() . microtime(true));
    }
}

if (!isset($ame_pos_customer_rows) || !is_array($ame_pos_customer_rows)) {
    $ame_pos_customer_rows = [];

    if (isset($customers) && $customers instanceof mysqli_result) {
        if ($customers->num_rows > 0) {
            $customers->data_seek(0);
        }
        while ($row = $customers->fetch_assoc()) {
            $ame_pos_customer_rows[] = $row;
        }
        if ($customers->num_rows > 0) {
            $customers->data_seek(0);
        }
    }
}

$customer_words = $is_english
    ? [
        'title' => 'Saved customers',
        'retail_short' => 'Retail',
        'registered_short' => 'Registered',
        'retail' => 'Retail / Walk-in customer',
        'select' => 'Select a registered customer',
        'add' => 'Add new customer',
        'add_short' => '+',
        'guest_title' => 'One-time invoice customer',
        'guest_name' => 'Customer name (optional)',
        'guest_phone' => 'Mobile number (optional)',
        'guest_note' => 'Invoice note (optional)',
        'registered' => 'Registered customer',
        'balance' => 'Current balance',
        'popup_title' => 'Quick customer registration',
        'name' => 'Customer name',
        'phone' => 'Mobile number',
        'address' => 'Address (optional)',
        'opening_type' => 'Opening balance type',
        'due' => 'Customer due',
        'advance' => 'Customer advance',
        'opening_amount' => 'Opening amount',
        'due_date' => 'Due date',
        'due_watch' => 'Remind in 15 days if still due',
        'due_watch_hint' => 'Tick this box. After 15 days, if this customer still owes money, it appears on the top notification bell.',
        'note' => 'Note (optional)',
        'cancel' => 'Cancel',
        'save' => 'Save customer',
        'done' => 'Done',
        'no_selection' => 'No customer selected — retail / walk-in',
        'search_ph' => 'Name, mobile or ID',
        'search_none' => 'No customer or supplier matched',
        'search_hint' => 'Type a name, mobile number or ID to search'
    ]
    : [
        'title' => 'সংরক্ষিত ক্রেতা',
        'retail_short' => 'খুচরা',
        'registered_short' => 'নিবন্ধিত',
        'retail' => 'খুচরা ক্রেতা (নিবন্ধন ছাড়া)',
        'select' => 'নিবন্ধিত ক্রেতা নির্বাচন করুন',
        'add' => 'নতুন ক্রেতা যোগ',
        'add_short' => '+',
        'guest_title' => 'একবারের ক্রেতা',
        'guest_name' => 'ক্রেতার নাম (ঐচ্ছিক)',
        'guest_phone' => 'মোবাইল নম্বর (ঐচ্ছিক)',
        'guest_note' => 'ইনভয়েস নোট (ঐচ্ছিক)',
        'registered' => 'নিবন্ধিত ক্রেতা',
        'balance' => 'বর্তমান ব্যালেন্স',
        'popup_title' => 'দ্রুত ক্রেতা নিবন্ধন',
        'name' => 'ক্রেতার নাম',
        'phone' => 'মোবাইল নম্বর',
        'address' => 'ঠিকানা (ঐচ্ছিক)',
        'opening_type' => 'প্রারম্ভিক ব্যালেন্সের ধরন',
        'due' => 'ক্রেতার বকেয়া',
        'advance' => 'ক্রেতার অগ্রিম',
        'opening_amount' => 'প্রারম্ভিক পরিমাণ',
        'due_date' => 'বকেয়া দেওয়ার তারিখ',
        'due_watch' => '১৫ দিন পর বাকি থাকলে ঘণ্টায় দেখাবে',
        'due_watch_hint' => 'টিক দিলে ১৫ দিন পরেও যদি এই কাস্টমারের কাছে বাকি থাকে, উপরের নোটিফিকেশন বারের ঘণ্টায় উঠবে।',
        'note' => 'নোট (ঐচ্ছিক)',
        'cancel' => 'বাতিল',
        'save' => 'ক্রেতা সংরক্ষণ',
        'done' => 'সম্পন্ন',
        'no_selection' => 'কোনো ক্রেতা নির্বাচিত নেই — খুচরা ক্রেতা',
        'search_ph' => 'নাম, মোবাইল নম্বর বা আইডি',
        'search_none' => 'কোনো ক্রেতা বা সাপ্লায়ার মেলেনি',
        'search_hint' => 'খুঁজতে নাম, মোবাইল নম্বর, আইডি বা সাপ্লায়ার নাম্বার লিখুন'
    ];
