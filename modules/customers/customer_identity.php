<?php
/** গ্রাহকের আইডি, মোবাইল ও এনআইডি মিল। পুরনো স্কিমা ফাইলে ফাংশন না থাকলেও সেভ চলে। */
function ame_customer_ascii_digits(string $raw): string
{
    $raw = strtr($raw, [
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
        '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
    ]);
    $digits = preg_replace('/\D+/', '', $raw);
    return is_string($digits) ? $digits : '';
}

function ame_customer_phone_key(string $raw): string
{
    $digits = ame_customer_ascii_digits($raw);
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '880') && strlen($digits) > 11) {
        $digits = substr($digits, 3);
        if ($digits !== '' && $digits[0] !== '0') {
            $digits = '0' . $digits;
        }
    }
    if (strlen($digits) === 10 && $digits[0] === '1') {
        $digits = '0' . $digits;
    }
    return $digits;
}

function ame_customer_phone_store(string $raw): ?string
{
    if (trim($raw) === '') {
        return null;
    }
    $key = ame_customer_phone_key($raw);
    if (strlen($key) >= 10 && strlen($key) <= 20) {
        return $key;
    }
    $trim = trim($raw);
    $trim = function_exists('mb_substr') ? mb_substr($trim, 0, 20, 'UTF-8') : substr($trim, 0, 20);
    return $trim === '' ? null : $trim;
}

function ame_customer_code_key(string $raw): string
{
    $raw = preg_replace('/\s+/u', '', trim($raw));
    $raw = is_string($raw) ? $raw : '';
    if ($raw === '') {
        return '';
    }
    return function_exists('mb_strtolower') ? mb_strtolower($raw, 'UTF-8') : strtolower($raw);
}

function ame_customer_nid_key(string $raw): string
{
    $raw = strtr(trim($raw), [
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
        '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
    ]);
    $raw = preg_replace('/[\s\-]+/u', '', $raw);
    return is_string($raw) ? $raw : '';
}

function ame_customer_nid_store(string $raw): string
{
    $key = ame_customer_nid_key($raw);
    if ($key === '') {
        return '';
    }
    return function_exists('mb_substr') ? mb_substr($key, 0, 30, 'UTF-8') : substr($key, 0, 30);
}

function ame_customer_identity_flags(mysqli $db, string $code, string $phone, string $nid, int $exceptId = 0): array
{
    $flags = ['code' => false, 'phone' => false, 'nid' => false, 'error' => false];
    $codeKey = ame_customer_code_key($code);
    $phoneKey = ame_customer_phone_key($phone);
    $nidKey = ame_customer_nid_key($nid);
    if ($codeKey === '' && $phoneKey === '' && $nidKey === '') {
        return $flags;
    }
    $res = $db->query('SELECT id, customer_code, phone, alt_phone, nid FROM customers');
    if (!$res) {
        $res = $db->query('SELECT id, customer_code, phone FROM customers');
    }
    if (!$res) {
        $flags['error'] = true;
        return $flags;
    }
    while ($row = $res->fetch_assoc()) {
        $id = (int) ($row['id'] ?? 0);
        if ($exceptId > 0 && $id === $exceptId) {
            continue;
        }
        if ($codeKey !== '' && ame_customer_code_key((string) ($row['customer_code'] ?? '')) === $codeKey) {
            $flags['code'] = true;
        }
        if ($phoneKey !== '') {
            $own = ame_customer_phone_key((string) ($row['phone'] ?? ''));
            $alt = ame_customer_phone_key((string) ($row['alt_phone'] ?? ''));
            if (($own !== '' && $own === $phoneKey) || ($alt !== '' && $alt === $phoneKey)) {
                $flags['phone'] = true;
            }
        }
        if ($nidKey !== '' && ame_customer_nid_key((string) ($row['nid'] ?? '')) === $nidKey) {
            $flags['nid'] = true;
        }
    }
    $res->free();
    return $flags;
}

function ame_customer_identity_taken(mysqli $db, string $code, string $phone, string $nid, int $exceptId = 0): ?string
{
    $flags = ame_customer_identity_flags($db, $code, $phone, $nid, $exceptId);
    if ($flags['error']) {
        return 'কাস্টমার মিলিয়ে দেখা যায়নি। আবার চেষ্টা করুন।';
    }
    $parts = [];
    if ($flags['code']) {
        $parts[] = 'এই আইডি নম্বরে আগে থেকে একজন কাস্টমার আছে। অন্য আইডি দিন।';
    }
    if ($flags['phone']) {
        $parts[] = 'এই মোবাইল নম্বরে আগে থেকে একজন কাস্টমার আছে। এক নম্বরে একাধিক কাস্টমার সেভ হবে না।';
    }
    if ($flags['nid']) {
        $parts[] = 'এই এনআইডি নম্বরে আগে থেকে একজন কাস্টমার আছে। এক এনআইডিতে একাধিক কাস্টমার সেভ হবে না।';
    }
    return $parts === [] ? null : implode(' ', $parts);
}
