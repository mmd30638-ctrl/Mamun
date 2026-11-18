<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/account_functions.php';
require_once __DIR__ . '/../customer_schema.php';
require_once __DIR__ . '/../../../includes/hire_functions.php';
if (is_file(__DIR__ . '/../../../includes/hire_term.php')) {
    require_once __DIR__ . '/../../../includes/hire_term.php';
}
if (is_file(__DIR__ . '/../../../includes/hire_due_notify.php')) {
    require_once __DIR__ . '/../../../includes/hire_due_notify.php';
}
ensure_customer_tables($db);
hire_boot($db);
if (function_exists('hire_term_boot')) {
    hire_term_boot($db);
}
if (function_exists('hire_due_notify_boot')) {
    hire_due_notify_boot($db);
}
$hirePage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$hireListOnly = in_array($hirePage, ['contracts.php', 'all.php', 'show.php', 'chuktis.php', 'now.php', 'owners.php'], true);
if (!$hireListOnly
    && hire_setting($db, 'accrue_on_page_load', '1') === '1'
    && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    try {
        hire_accrue_all($db);
    } catch (Throwable $e) {
        error_log('hire accrue: ' . $e->getMessage());
    }
}

function hire_h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        $text = function_exists('ame_l') ? ame_l($bn, $en) : $bn;
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

function hire_flash(): string
{
    if (isset($_SESSION['hire_zeroed_n'])) {
        $n = (int) $_SESSION['hire_zeroed_n'];
        unset($_SESSION['hire_zeroed_n']);
        $msg = $n > 0
            ? ('পুরনো ' . $n . 'টি ভাড়া চুক্তি খালি করা হয়েছে। নতুন চুক্তি দিয়ে আবার যোগ করুন।')
            : 'ভাড়া চুক্তি খালি। নতুন চুক্তি দিয়ে শুরু করুন।';
        return '<div class="hire-flash">' . hire_h($msg) . '</div>';
    }
    $ok = trim((string)($_GET['ok'] ?? ''));
    $err = trim((string)($_GET['err'] ?? ''));
    if ($ok === '' && $err === '') {
        return '';
    }
    if ($err !== '') {
        return '<div class="hire-flash hire-flash-err">' . hire_h($err) . '</div>';
    }
    $map = [
        'created' => ame_l('ভাড়া চুক্তি চালু হয়েছে। জামানত ওয়ালেটে আছে — কাস্টমার বকেয়ায় যোগ হয়নি।', 'Rental contract is active. Deposit sits in the wallet — it did not credit customer due.'),
        'paid' => ame_l('পেমেন্ট কাস্টমার খাতা থেকে মাইনাস হয়েছে।', 'Payment reduced the customer account.'),
        'used' => ame_l('জামানত থেকে সমন্বয় হয়েছে।', 'Deposit was applied.'),
        'refund' => ame_l('জামানত ফেরত দেওয়া হয়েছে।', 'Deposit refunded.'),
        'paused' => ame_l('ভাড়া সাময়িক বন্ধ। নতুন চার্জ হবে না।', 'Rental paused. No new charges.'),
        'resumed' => ame_l('ভাড়া আবার চালু।', 'Rental resumed.'),
        'returned' => ame_l('পণ্য ফেরত নেওয়া হয়েছে।', 'Product returned.'),
        'deactivated' => ame_l('চুক্তি ইনঅ্যাকটিভ। আগামী দিনের ভাড়া খাতায় যোগ হবে না। আগের হিসাব আছে।', 'Contract is inactive. Future daily rent will not post. Past ledger stays.'),
        'activated' => ame_l('চুক্তি আবার চালু। দৈনিক ভাড়া খাতায় যোগ হতে থাকবে।', 'Contract is active again. Daily rent will post to the ledger.'),
        'cancelled' => ame_l('চুক্তি বাতিল/আর্কাইভ হয়েছে। আর্থিক সারি মুছে যায়নি।', 'Contract archived. Financial rows were not deleted.'),
        'deleted' => ame_l('খালি চুক্তি সরানো হয়েছে। খাতার টাকা অপরিবর্তিত।', 'Empty contract removed. Ledger money was not changed.'),
        'saved' => ame_l('সংরক্ষণ হয়েছে।', 'Saved.'),
        'accrued' => ame_l('আজকের দৈনিক ভাড়া কাস্টমার খাতায় যোগ হয়েছে।', 'Today’s daily rent was posted to the customer account.'),
        'owner_paid' => ame_l('মালিককে পরিশোধ কাস্টমার খাতায় যায়নি।', 'Owner payment stayed off the customer ledger.'),
    ];
    return '<div class="hire-flash">' . ($map[$ok] ?? hire_h($ok)) . '</div>';
}
