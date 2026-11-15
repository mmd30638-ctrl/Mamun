<?php
/**
 * APONSHOP - GLOBAL CONFIGURATION
 */

// Load the private environment file before reading APP_ENV. A staging
// document root reads /.env.staging only and never the live .env.
require_once __DIR__ . '/env_bootstrap.php';
require_once __DIR__ . '/public_host_files.php';
$env_path = ame_env_path();
ame_apply_env(ame_read_env_file($env_path));
require_once __DIR__ . '/live_domain.php';
if (function_exists('ame_ensure_public_host_files')) {
    ame_ensure_public_host_files();
}
if (function_exists('ame_canonical_live_redirect')) {
    ame_canonical_live_redirect();
}

if (!defined('AME_PRIVATE_DATA_DIR')) {
    define('AME_PRIVATE_DATA_DIR', dirname(__DIR__, 2) . '/private_data');
}
$private_log_dir = AME_PRIVATE_DATA_DIR . '/logs';
if (!is_dir($private_log_dir)) {
    @mkdir($private_log_dir, 0700, true);
}

// ১. এরর হ্যান্ডলিং — প্রোডাকশনে স্ক্রিনে এরর দেখানো বন্ধ, শুধু লগ ফাইলে সংরক্ষণ হয়।
//    ডিবাগ করতে হলে সাময়িকভাবে .env-এ APP_ENV=development করুন — কখনো লাইভ সাইটে না।
$app_env = getenv('APP_ENV') ?: 'production';
if ($app_env === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}
ini_set('log_errors', 1);
ini_set('error_log', $private_log_dir . '/php_errors.log');
error_reporting(E_ALL);
require_once __DIR__ . '/error_monitor.php';

function ame_forwarded_proto_trusted(): bool
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }
    $isPublic = (bool)filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    );
    return !$isPublic;
}

function ame_is_https(): bool
{
    $force = strtolower((string)(getenv('FORCE_HTTPS') ?: ''));
    if (in_array($force, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    if (strtolower((string)($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https') {
        return true;
    }
    $fwd = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if (str_starts_with($fwd, 'https') && ame_forwarded_proto_trusted()) {
        return true;
    }
    return false;
}

// ২. সেশন ম্যানেজমেন্ট (নিরাপদ কুকি সেটিংসসহ)
if (session_status() === PHP_SESSION_NONE) {
    $is_https = ame_is_https();
    $cookie_path = '/';
    $session_name = 'PHPSESSID';
    if (function_exists('ame_is_staging_app') && ame_is_staging_app()) {
        $session_name = 'APONSHOP_STG';
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if (str_starts_with($script, '/staging/') || $script === '/staging') {
            $cookie_path = '/staging';
        }
    }
    session_name($session_name);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $cookie_path,
        'domain'   => '', // হোস্ট-অনলি — পুরোনো ডোমেন কুকিতে বসবে না
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ৩. টাইমজোন সেটিংস
date_default_timezone_set('Asia/Dhaka');

// ৩.১. সফটওয়্যার ভারসন ও CSS/JS ক্যাশ-বাস্টিং
// -------------------------------------------------------------
// header.php/footer.php-তে বেশ কয়েক জায়গায় CSS/JS লিংকের শেষে
// ?v=<এই ভ্যারিয়েবল> বসানো থাকে, কিন্তু আগে কোথাও এটা সেট করা হতো না —
// ফলে সবসময় ডিফল্ট মান '1.0' ব্যবহার হতো, অর্থাৎ CSS/JS বদলালেও পুরনো
// ব্রাউজার-ক্যাশ ভাঙত না। এখন এখান থেকে কেন্দ্রীয়ভাবে সেট করা হচ্ছে —
// ভবিষ্যতে CSS/JS বদলালে শুধু নিচের সংখ্যাটা বাড়ালেই যথেষ্ট।
define('AME_ASSET_VERSION', '10.98.914');
$sys_version = AME_ASSET_VERSION;

// স্পিড লক: SPEED.txt মুছবেন না। সেটিংস → স্পিড সিস্টেম। docs/performance-standards.md

// সফটওয়্যারের নিজস্ব সংস্করণ নম্বর (About/System Health পেজে দেখানো হয়) —
// আগে system_health.php-তে আলাদাভাবে '6.0' হার্ডকোড করা ছিল, এখন এখানেই
// কেন্দ্রীয়ভাবে রাখা হলো

if (!defined('AME_APP_VERSION')) {
    /* লিটারেল লাগে Easy Update preflight (পুরনো ame_easy_update_read_version_from_root)। */
    define('AME_APP_VERSION', '10.98.887');
}
if (function_exists('opcache_reset')) {
    $ameOpcMark = dirname(__DIR__) . '/storage/opc.ver';
    $ameOpcHave = is_file($ameOpcMark) ? trim((string) @file_get_contents($ameOpcMark)) : '';
    if ($ameOpcHave !== AME_APP_VERSION) {
        @opcache_reset();
        @file_put_contents($ameOpcMark, AME_APP_VERSION, LOCK_EX);
    }
}



// ৪. ডাটাবেস কানেকশন — প্রথমে shared env path (Easy Update db.php skip করলেও চলে)।
//    কাস্টমার/সাপ্লায়ার অ্যামাউন্ট এখানে লেখা হয় না।
require_once __DIR__ . '/ame_db_connect.php';
if (!isset($db) || !($db instanceof mysqli)) {
    $opened = ame_open_mysqli();
    if ($opened instanceof mysqli) {
        $db = $opened;
    }
}
$ame_page_now = basename((string)($_SERVER['PHP_SELF'] ?? ''));
if (!isset($db) || !($db instanceof mysqli)) {
    $db_file = __DIR__ . '/db.php';
    if (file_exists($db_file)) {
        require_once $db_file;
    }
}
if ((!isset($db) || !($db instanceof mysqli)) && $ame_page_now !== 'install.php' && PHP_SAPI !== 'cli') {
    header('Location: /install.php');
    exit();
}

// ৪.১. গ্লোবাল হেল্পার ফাংশন (admin_only, clean_input, log_activity, csrf_token, format_bdt)
require_once __DIR__ . '/lang_helper.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/memo_functions.php';
require_once __DIR__ . '/ledger_lock.php';
require_once __DIR__ . '/running_ledger.php';
require_once __DIR__ . '/operator_stamp.php';
require_once __DIR__ . '/audit_log.php';
// ⭐ ame_lock()/ame_unlock() (named DB lock) — customer/account balance
// রেস-কন্ডিশন ফিক্সের জন্য সব পেজে দরকার, তাই এখানে গ্লোবালি লোড করা হলো
require_once __DIR__ . '/integrity_functions.php';
require_once __DIR__ . '/pay_security.php';
require_once __DIR__ . '/password_policy.php';
require_once __DIR__ . '/login_lockout.php';
require_once __DIR__ . '/overlay_assets.php';
require_once __DIR__ . '/pos_title.php';
require_once __DIR__ . '/pos_skin.php';
require_once __DIR__ . '/theme_functions.php';
require_once __DIR__ . '/visual_design.php';
require_once __DIR__ . '/permission_functions.php';
require_once __DIR__ . '/provenance_functions.php';
require_once __DIR__ . '/agent_functions.php';
require_once __DIR__ . '/pulse_functions.php';
require_once __DIR__ . '/smart_functions.php';
require_once __DIR__ . '/store_functions.php';
require_once __DIR__ . '/workshop_functions.php';

if (isset($db) && $db instanceof mysqli) {
    // 10.98.741 প্যাচ first_install.php ছাড়া config বসিয়ে লাইভ লগইন ৫০০ দিত।
    // ফাইল না থাকলে দোকান চলবে; নতুন ইনস্টলে ফাইল থাকলে টেবিল বসবে।
    $ame_first_install = __DIR__ . '/first_install.php';
    if (is_file($ame_first_install)) {
        try {
            require_once $ame_first_install;
            if (function_exists('ame_install_needed') && ame_install_needed($db)) {
                ame_first_install($db);
            }
        } catch (Throwable $e) {
            error_log('First install boot skipped: ' . $e->getMessage());
        }
    }
    if (function_exists('ame_ledger_lock_boot')) {
        ame_ledger_lock_boot($db);
    }
    if (is_file(__DIR__ . '/engineer_bill_lock.php')) {
        require_once __DIR__ . '/engineer_bill_lock.php';
        if (function_exists('ame_engineer_bill_lock_boot')) {
            ame_engineer_bill_lock_boot(dirname(__DIR__));
        }
    }
    require_once __DIR__ . '/schema_sync.php';
    if (!ame_schema_version_ready($db)) {
        ame_ensure_auth_session_schema($db);
        ame_ensure_login_lockout_schema($db);
    }
    if (is_file(__DIR__ . '/balance_guard.php')) {
        require_once __DIR__ . '/balance_guard.php';
        ame_bg_context($db);
    }
    if (is_file(__DIR__ . '/perf_indexes.php')) {
        require_once __DIR__ . '/perf_indexes.php';
        ame_perf_boot_indexes($db);
    }
}

$theme = ame_theme_defaults();
$ame_module_key = '';
if (isset($db) && $db instanceof mysqli) {
    try {
        $ame_module_key = ame_current_module_key();
        $theme = ame_theme_for_request($db, $ame_module_key);
    } catch (Throwable $e) {
        error_log('Theme load failed: ' . $e->getMessage());
    }
}

// ৫. দোকানের সেটিংস লোড করা (মাল্টি-টেন্যান্ট: কোনো নির্দিষ্ট প্রতিষ্ঠানের নাম
//    এখানে হার্ডকোড করা নেই — settings টেবিলে যা সেভ থাকবে সেটাই ব্যবহার হবে)
$shop = ['shop_name' => 'আমার ব্যবসা প্রতিষ্ঠান']; // ইনস্টলের পর এডমিন নাম না দেওয়া পর্যন্ত ডিফল্ট
if (isset($db) && $db instanceof mysqli) {
    try {
        $schemaReady = function_exists('ame_schema_version_ready') && ame_schema_version_ready($db);
        if (!$schemaReady) {
        // settings টেবিল/কলাম না থাকলে নিজে থেকেই তৈরি/প্যাচ করে নেয়
        // (মোবাইল-অনলি ওয়ার্কফ্লোতে ম্যানুয়াল phpMyAdmin মাইগ্রেশনের দরকার নেই)
        $db->query("CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            shop_name VARCHAR(150) NOT NULL DEFAULT 'আমার ব্যবসা প্রতিষ্ঠান',
            shop_phone VARCHAR(30) DEFAULT NULL,
            shop_address TEXT DEFAULT NULL,
            shop_banner VARCHAR(255) DEFAULT NULL,
            shop_logo VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->query("CREATE TABLE IF NOT EXISTS dashboard_banners (
            id INT AUTO_INCREMENT PRIMARY KEY,
            image_name VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $existingCols = [];
        $colRes = $db->query("SHOW COLUMNS FROM settings");
        if ($colRes) {
            while ($c = $colRes->fetch_assoc()) { $existingCols[] = $c['Field']; }
        }
        if (!in_array('shop_logo', $existingCols, true)) {
            $db->query("ALTER TABLE settings ADD COLUMN shop_logo VARCHAR(255) DEFAULT NULL");
        }
        if (!in_array('shop_tagline', $existingCols, true)) {
            $db->query("ALTER TABLE settings ADD COLUMN shop_tagline VARCHAR(180) NULL DEFAULT NULL");
        }
        }

        $res = $db->query("SELECT * FROM settings LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $shop = $res->fetch_assoc();
            if (function_exists('ame_ensure_shop_branding')) {
                ame_ensure_shop_branding($db, $shop);
            }
        } else {
            $seed_name = function_exists('ame_shop_identity_name')
                ? ame_shop_identity_name()
                : 'আমার ব্যবসা প্রতিষ্ঠান';
            $seed_tag = function_exists('ame_shop_identity_tagline')
                ? ame_shop_identity_tagline()
                : '';
            $ins = $db->prepare('INSERT INTO settings (shop_name, shop_tagline) VALUES (?, ?)');
            if ($ins) {
                $ins->bind_param('ss', $seed_name, $seed_tag);
                $ins->execute();
                $ins->close();
            }
            $shop['shop_name'] = $seed_name;
            $shop['shop_tagline'] = $seed_tag;
        }
    } catch (Exception $e) {
        // টেবিল লোড ব্যর্থ হলেও সাইট বন্ধ হবে না, শুধু ডিফল্ট নাম দেখাবে
        error_log('Settings load failed: ' . $e->getMessage());
    }
}

// ৬. লগইন প্রটেকশন (Unauthorized Access Block)
$current_page = basename($_SERVER['PHP_SELF']);
$public_pages = [
    'login.php',
    'process_login.php',
    'manifest.php',
    'forgot_password.php',
    'process_forgot_password.php',
    'process_password_reset.php',
    'reset_password.php',
    'process_new_password.php',
    'verify.php',
    'sms_sim.php',
    'sms_sim_api.php',
    'download.php',
    'download_update.php',
    'one_click.php',
    'install.php',
    'pos_layout_designer.php',
    'layout_designer.php',
    'invoice.php',
    'invoice_digital.php',
    'invoice_a4.php',
];

$ame_cli_cron = PHP_SAPI === 'cli' && (defined('AME_DUTY_CRON') || defined('AME_DAILY_BACKUP_CRON'));
if (!isset($_SESSION['user_id']) && !in_array($current_page, $public_pages, true) && !$ame_cli_cron) {
    header("Location: /login.php");
    exit();
}

$ame_public_memos = ['invoice.php', 'invoice_digital.php', 'invoice_a4.php'];
if (!isset($_SESSION['user_id']) && in_array($current_page, $ame_public_memos, true)) {
    require_once __DIR__ . '/memo_access.php';
    $ame_memo_id = (int) ($_GET['id'] ?? 0);
    $ame_memo_token = (string) ($_GET['t'] ?? '');
    if (!ame_memo_token_ok($ame_memo_id, $ame_memo_token)) {
        header('Location: /login.php');
        exit();
    }
}

if (isset($_SESSION['user_id']) && !in_array($current_page, $public_pages, true)) {
    ame_enforce_account_session($db);
    $idle_limit = 20 * 60;
    $last = (int)($_SESSION['last_activity'] ?? time());
    if ((time() - $last) > $idle_limit) {
        ame_clear_session_cookie();
        header('Location: /login.php?timeout=1');
        exit();
    }
    // পটভূমির পিং সেশনকে জাগিয়ে রাখবে না। আসল পাতা খুললেই সময় এগোয়।
    $ame_idle_keepalive = in_array($current_page, ['session_ping.php'], true);
    if (!$ame_idle_keepalive) {
        $_SESSION['last_activity'] = time();
    }

    if (!empty($_SESSION['must_change_password'])) {
        $pw_allowed = [
            'change_password.php',
            'process_password.php',
            'logout.php',
        ];
        if (!in_array($current_page, $pw_allowed, true)) {
            header('Location: /modules/settings/change_password.php?required=1');
            exit();
        }
    }
    if (isset($db) && $db instanceof mysqli) {
        ame_enforce_page_permission($db);
        // কাস্টমার-কার্ডের মতো শুধু-পড়ার অনুরোধে স্কিমা সিঙ্ক, ডিউটি ও
        // ডাটাবেজ ব্যাকআপ চালানো যায় না। ওগুলো সেশন লক ধরে রাখলে পপআপ খুললেই
        // একই ব্যবহারকারীর বাকি সব পাতা অপেক্ষায় থাকে।
        $ame_read_only_request = defined('AME_READ_ONLY_REQUEST') && AME_READ_ONLY_REQUEST;
        try {
            if (!$ame_read_only_request) {
            if (!function_exists('ame_schema_sync_if_needed')) {
                require_once __DIR__ . '/schema_sync.php';
            }
            ame_schema_sync_if_needed($db);
            if (!ame_schema_version_ready($db)) {
                ensure_store_schema($db);
                ensure_workshop_schema($db);
                if (is_file(__DIR__ . '/rental_schema.php')) {
                    require_once __DIR__ . '/rental_schema.php';
                    ensure_rental_schema($db);
                }
            }
            ame_resolve_current_store($db);
            if (is_file(__DIR__ . '/attendance_functions.php')) {
                require_once __DIR__ . '/attendance_functions.php';
            }
            if (is_file(__DIR__ . '/staff_duty.php')) {
                require_once __DIR__ . '/staff_duty.php';
            }
            if (is_file(__DIR__ . '/engineer_bill_lock.php')) {
                require_once __DIR__ . '/engineer_bill_lock.php';
            }
            if (function_exists('ame_engineer_bill_boot')) {
                ame_engineer_bill_boot($db);
            }
            if (is_file(__DIR__ . '/engineer_statement_post.php')) {
                require_once __DIR__ . '/engineer_statement_post.php';
            }
            if (function_exists('ame_engineer_statement_boot')) {
                ame_engineer_statement_boot($db);
            }
            if (is_file(__DIR__ . '/worker_desk.php')) {
                require_once __DIR__ . '/worker_desk.php';
            }
            if (function_exists('ame_staff_duty_tick')) {
                ame_staff_duty_tick($db);
            }
            if (is_file(__DIR__ . '/daily_backup.php')) {
                require_once __DIR__ . '/daily_backup.php';
            }
            if (function_exists('ame_daily_backup_catchup')) {
                ame_daily_backup_catchup($db);
            }
            if (function_exists('ame_worker_guard_request')) {
                ame_worker_guard_request($db);
            }
            }
        } catch (Throwable $e) {
            error_log('Store bootstrap failed: ' . $e->getMessage());
        }
        // এক মডিউল বন্ধ থাকলে শুধু সেই মডিউলের পাতা থামে। বাকি মডিউল চলে।
        if (is_file(__DIR__ . '/module_guard.php')) {
            require_once __DIR__ . '/module_guard.php';
        }
        if (function_exists('ame_module_control_block')) {
            ame_module_control_block($db);
        }
    }
}

require_once __DIR__ . '/responsive_boot.php';
ame_responsive_boot();
