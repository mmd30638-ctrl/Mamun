<?php
/**
 * ================================================================
 * UNIFIED REPORT ENGINE — ধাপ ২
 * ================================================================
 * পুরো Report Center-এর সব রিপোর্ট এই একই কেন্দ্রীয় ফাইল ব্যবহার
 * করবে, যাতে প্রতিটা রিপোর্টে বারবার একই কোড লিখতে না হয়।
 *
 * এই ফাইলে যা আছে:
 *  - report_date_filter()   — স্ট্যান্ডার্ড তারিখ-ফিল্টার ফর্ম
 *  - report_header()        — শিরোনাম, মেটাডেটা, প্রিন্ট/এক্সপোর্ট বাটন
 *  - report_export_csv()    — যেকোনো ডাটা এক-লাইনে CSV এক্সপোর্ট
 *  - report_empty_state()   — ডাটা না থাকলে অভিন্ন "খালি" বার্তা
 *  - report_footer()        — মেটাডেটা ফুটার (কে কখন তৈরি করলেন)
 *
 * ⚠️ গুরুত্বপূর্ণ নিয়ম (CSV এক্সপোর্ট ব্যবহার করলে অবশ্যই মানতে হবে):
 * report_export_csv() অবশ্যই partials/header.php ইনক্লুড করার **আগে**
 * কল করতে হবে — header.php HTML আউটপুট শুরু করে দেয়, তারপর আর
 * CSV ডাউনলোডের জন্য header() কল করা সম্ভব হয় না ("headers already
 * sent" এরর হবে)। প্যাটার্ন:
 *   require_once report_engine.php;
 *   [$start, $end] = report_get_date_range();
 *   if (export=csv) { ... report_export_csv(...); }   // header.php-এর আগে
 *   include header.php;
 * ================================================================
 */

require_once __DIR__ . '/config.php';

/**
 * তারিখ-রেঞ্জ যাচাই ও নিরাপদ ডিফল্ট মান — SQL Injection প্রতিরোধ করে,
 * সব রিপোর্টে অভিন্নভাবে ব্যবহারযোগ্য।
 */
function report_get_date_range($default_days = 30) {
    $start = trim($_GET['start_date'] ?? '');
    $end = trim($_GET['end_date'] ?? '');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
        $start = date('Y-m-d', strtotime("-{$default_days} days"));
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        $end = date('Y-m-d');
    }
    if (strtotime($start) > strtotime($end)) {
        [$start, $end] = [$end, $start];
    }
    return [$start, $end];
}

/**
 * স্ট্যান্ডার্ড তারিখ-ফিল্টার বার — সব রিপোর্টে একই দেখতে ও একইভাবে কাজ করবে।
 * $extra_html দিয়ে রিপোর্ট-নির্দিষ্ট অতিরিক্ত ফিল্টার (যেমন গ্রাহক/সাপ্লায়ার) যোগ করা যায়।
 */
function report_date_filter($start, $end, $extra_html = '') {
    ?>
    <form method="GET" class="rpt-filter-bar">
        <div>
            <label>শুরু তারিখ</label>
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start); ?>">
        </div>
        <div>
            <label>শেষ তারিখ</label>
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end); ?>">
        </div>
        <?php echo $extra_html; ?>
        <button type="submit" class="rpt-btn rpt-btn-primary">🔍 ফিল্টার করুন</button>
    </form>
    <?php
}

/**
 * রিপোর্টের শিরোনাম + মেটাডেটা + প্রিন্ট/CSV বাটন — একইভাবে সব রিপোর্টে বসবে।
 * $csv_url দিলে CSV এক্সপোর্ট বাটন দেখাবে (সাধারণত বর্তমান URL + &export=csv)।
 */
function report_header($title, $icon = '📊', $csv_url = null) {
    $generated_by = $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Unknown';
    ?>
    <style>
        .rpt-header-bar { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:16px; }
        .rpt-title { color:#003F8F; font-size:18px; font-weight:800; margin:0; }
        .rpt-meta { font-size:11px; color:#667085; margin-top:4px; }
        .rpt-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .rpt-btn {
            border:none; border-radius:10px; padding:9px 16px; font-size:12.5px; font-weight:700;
            cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px;
        }
        .rpt-btn-primary { background:#003F8F; color:#fff; }
        .rpt-btn-outline { background:#fff; color:#003F8F; border:2px solid #003F8F; }
        .rpt-filter-bar { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; background:#fff; padding:14px 16px; border-radius:12px; border:1px solid #D7DEE8; box-shadow:0 8px 20px rgba(15,23,42,.05); margin-bottom:18px; }
        .rpt-filter-bar label { display:block; font-size:11px; color:#667085; font-weight:700; margin-bottom:4px; }
        .rpt-filter-bar input, .rpt-filter-bar select { padding:9px 12px; border:2px solid #D7DEE8; border-radius:8px; font-size:13px; color:#111827; }
        .rpt-empty { text-align:center; color:#667085; padding:50px 20px; font-size:13px; }
        .rpt-footer-meta { font-size:10.5px; color:#667085; text-align:center; margin-top:20px; }
        @media print {
            .no-print { display:none !important; }
        }
    </style>
    <div class="rpt-header-bar no-print">
        <div>
            <h2 class="rpt-title"><?php echo $icon . ' ' . htmlspecialchars($title); ?></h2>
            <div class="rpt-meta">তৈরি করেছেন: <?php echo htmlspecialchars($generated_by); ?> • <?php echo date('d M Y, h:i A'); ?></div>
        </div>
        <div class="rpt-actions">
            <?php if ($csv_url): ?>
                <a href="<?php echo htmlspecialchars($csv_url); ?>" class="rpt-btn rpt-btn-outline">⬇️ CSV এক্সপোর্ট</a>
            <?php endif; ?>
            <button type="button" onclick="window.print()" class="rpt-btn rpt-btn-outline">🖨️ প্রিন্ট</button>
        </div>
    </div>
    <?php
}

/** ডাটা না থাকলে সব রিপোর্টে অভিন্ন বার্তা */
function report_empty_state($message = 'এই সময়সীমার মধ্যে কোনো তথ্য পাওয়া যায়নি।') {
    echo '<div class="rpt-empty">' . htmlspecialchars($message) . '</div>';
}

/** পেজের নিচে ছোট মেটাডেটা ফুটার */
function report_footer_meta($report_id) {
    echo '<div class="rpt-footer-meta">Report ID: ' . htmlspecialchars($report_id) . ' • ' . htmlspecialchars($_SERVER['HTTP_HOST'] ?? '') . '</div>';
}

/**
 * যেকোনো রিপোর্টের ডাটা এক-লাইনে CSV হিসেবে ডাউনলোড করানো।
 * ব্যবহার: report_export_csv('sales_summary', ['তারিখ','ইনভয়েস','পরিমাণ'], $rows);
 * এটি কল করার পর স্ক্রিপ্ট নিজে থেকেই exit করে।
 */
function report_export_csv($filename_prefix, array $headers, array $rows) {
    if (function_exists('log_activity')) {
        try {
            log_activity('Report Export', 'Reports', (string)$filename_prefix, [
                'action_type' => 'export',
                'entity_type' => 'report',
                'reference' => (string)$filename_prefix,
            ]);
        } catch (Throwable $e) {
        }
    }
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename_prefix) . '_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM — Excel-এ বাংলা ঠিকভাবে দেখানোর জন্য প্রয়োজনীয়
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}
