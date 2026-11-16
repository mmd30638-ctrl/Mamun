<?php
/**
 * ইনপুট স্যানিটাইজ করে ডাটাবেজে সংরক্ষণের জন্য প্রস্তুত করে।
 *
 * নিরাপত্তা ফিক্স: আগে এই ফাংশন htmlspecialchars() দিয়ে HTML-এনকোড করে
 * DB-তে সংরক্ষণ করত (যেমন "AT&T" সেভ হতো "AT&amp;T" হিসেবে) — এতে সার্চ,
 * এক্সপোর্ট, রিপোর্ট সবজায়গায় ভুল ডাটা দেখাত। এখন শুধু ট্রিম ও
 * real_escape_string করা হয় (prepared statement-এর পাশাপাশি অতিরিক্ত
 * নিরাপত্তা স্তর হিসেবে) — HTML escaping এখন আউটপুটের সময় করতে হবে,
 * htmlspecialchars() দিয়ে (esc_html() হেল্পার ব্যবহার করতে পারেন)।
 */
function clean_input($data) {
    global $db;
    if ($data === null) return "";
    $data = trim((string)$data);
    return ($db instanceof mysqli) ? $db->real_escape_string($data) : $data;
}

function ame_webroot_path(): string
{
    return dirname(__DIR__);
}

/** পুরনো POS ও APONSHOP — ছবি যে ফোল্ডারে থাকতে পারে */
function ame_customer_photo_dirs(): array
{
    $root = ame_webroot_path();
    return [
        $root . '/uploads/customers',
        $root . '/uploads/legacy_customers',
        $root . '/assets/img/customers',
        $root . '/img/customer',
        $root . '/img/customers',
        $root . '/customer',
        $root . '/upload/customer',
        $root . '/uploads',
    ];
}

function ame_customer_photo_public_url(string $dir, string $base): string
{
    $root = ame_webroot_path();
    $realRoot = realpath($root);
    $realDir = realpath($dir);
    if ($realRoot === false || $realDir === false || !str_starts_with($realDir, $realRoot)) {
        return '';
    }
    $rel = ltrim(str_replace('\\', '/', substr($realDir, strlen($realRoot))), '/');
    return '/' . $rel . '/' . rawurlencode($base);
}

/** HTML আউটপুটের সময় ব্যবহার করুন — DB থেকে আনা যেকোনো ডাটা স্ক্রিনে দেখানোর আগে এটি দিয়ে wrap করুন */
function esc_html($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * পুরনো POS/Excel থেকে আসা নাম: &zwj; &rdquo; &#039; ও লুকানো ইউনিকোড সরানো।
 */
function ame_clean_display_text($value): string
{
    $text = trim((string)($value ?? ''));
    if ($text === '') {
        return '';
    }
    for ($i = 0; $i < 3; $i++) {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($decoded === $text) {
            break;
        }
        $text = $decoded;
    }
    $text = str_replace("\xC2\xAD", '', $text);
    $stripped = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{FEFF}]/u', '', $text);
    if (is_string($stripped)) {
        $text = $stripped;
    }
    $visarga = preg_replace('/মো[”"‟″〃]+/u', 'মোঃ', $text);
    if (is_string($visarga)) {
        $text = $visarga;
    }
    $collapsed = preg_replace('/\s+/u', ' ', $text);
    return trim(is_string($collapsed) ? $collapsed : $text);
}

/**
 * স্টেটমেন্টের ডেবিট/ক্রেডিট ঘর। শূন্য ঘর em dash।
 * প্লাস '+' এবং মাইনাস U+2212 — হাইফেন নয়। চলতি ব্যালেন্স বদলায় না।
 */
function ame_statement_signed_amount($amount, string $sign): string
{
    $n = round((float) $amount, 2);
    if (abs($n) < 0.009) {
        return '—';
    }
    $body = number_format(abs($n), 2);
    if ($sign === '-') {
        return "\u{2212}" . $body;
    }
    return '+' . $body;
}

/** হোম ব্যানার — DB খালি থাকলে assets/img-এর slide/banner ফাইল */
function ame_dashboard_banner_files(): array
{
    $dir = dirname(__DIR__) . '/assets/img';
    if (!is_dir($dir)) {
        return [];
    }
    $found = [];
    foreach (glob($dir . '/{banner_*,slide_*}.{jpg,jpeg,png,webp,JPG,PNG}', GLOB_BRACE) ?: [] as $path) {
        if (is_file($path)) {
            $found[] = basename($path);
        }
    }
    $found = array_values(array_unique($found));
    sort($found, SORT_NATURAL);
    return $found;
}

/** বাংলাদেশি মোবাইল → WhatsApp। খালি নম্বর হলে খালি স্ট্রিং। */
function ame_whatsapp_url($phone): string
{
    $digits = preg_replace('/\D+/', '', (string)$phone) ?? '';
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }
    if (str_starts_with($digits, '0') && strlen($digits) === 11) {
        $digits = '88' . $digits;
    } elseif (strlen($digits) === 11 && str_starts_with($digits, '1')) {
        $digits = '88' . $digits;
    } elseif (strlen($digits) === 10 && str_starts_with($digits, '1')) {
        $digits = '880' . $digits;
    }
    return 'https://wa.me/' . $digits;
}

/** JavaScript স্ট্রিং লিটারেল — onclick/ইনলাইন JS-এ নাম/নোট বসানোর আগে */
function esc_js($value): string
{
    return json_encode(
        (string)($value ?? ''),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
}

function format_bdt($amount) {
    $num = (float)$amount;
    $color = ($num < 0) ? "#C62828" : "inherit";
    return "<span style='color: $color; font-weight: bold;'>" . number_format($num, 2) . " ৳</span>";
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function admin_only() {
    if (!empty($_SESSION['is_super_admin'])) {
        return;
    }
    $role = strtolower((string) ($_SESSION['role'] ?? ''));
    if (in_array($role, ['admin', 'super_admin', 'superadmin'], true)) {
        return;
    }
    echo "<script>alert('অনুমতি নেই!'); window.location='/index.php';</script>";
    exit();
}

function ame_is_production(): bool
{
    $env = getenv('APP_ENV') ?: 'production';
    return strtolower(trim((string)$env)) !== 'development';
}

/** ডিফল্ট/অনিরাপদ পাসওয়ার্ড — লগইনের পর বাধ্যতামূলক পরিবর্তন */
function ame_is_default_password(string $plain, string $hash): bool
{
    if (!password_verify($plain, $hash)) {
        return false;
    }
    $defaults = ['password', 'admin', '123456', 'admin123'];
    return in_array(strtolower($plain), $defaults, true);
}

function log_activity($action, $module, $details, $extra = []) {
    if (!function_exists('ame_audit')) {
        $audit = __DIR__ . '/audit_log.php';
        if (is_file($audit)) {
            require_once $audit;
        }
    }
    if (function_exists('ame_audit')) {
        $event = is_array($extra) ? $extra : [];
        $event['action'] = (string)$action;
        $event['module'] = (string)$module;
        $event['details'] = (string)$details;
        ame_audit($event);
        return;
    }
    global $db;
    if (!($db instanceof mysqli)) {
        return;
    }
    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, module, details) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        error_log('log_activity skipped: ' . $db->error);
        return;
    }
    $action = (string)$action;
    $module = (string)$module;
    $details = (string)$details;
    $stmt->bind_param("isss", $user_id, $action, $module, $details);
    $stmt->execute();
    $stmt->close();
}

/* CSRF টোকেন তৈরি করে (প্রতি সেশনে একবার তৈরি হয়, বারবার একই থাকে) */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/* ফর্মে সরাসরি বসানোর জন্য হিডেন ইনপুট HTML তৈরি করে দেয় */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/* POST-এ আসা টোকেন যাচাই করে; না মিললে ফর্ম প্রসেসিং আটকে দেয় */
function csrf_verify() {
    $submitted = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($_SESSION['csrf_token']) || $submitted === '' || !hash_equals((string)$_SESSION['csrf_token'], $submitted)) {
        http_response_code(403);
        die('❌ নিরাপত্তা যাচাই ব্যর্থ হয়েছে (CSRF)। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।');
    }
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        die('❌ এই কাজ শুধু ফর্ম সাবমিট দিয়ে করা যায়।');
    }
}

function env_flag_enabled(string $name): bool
{
    $value = strtolower(trim((string)(getenv($name) ?: '')));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function require_env_flag(string $name): void
{
    if (!env_flag_enabled($name)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
        echo "Not Found\n";
        exit;
    }
}

function ame_clear_session_cookie(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool)$p['secure'], (bool)$p['httponly']);
    }
    session_destroy();
}

function ame_ensure_auth_session_schema(mysqli $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $result = $db->query("SHOW COLUMNS FROM users LIKE 'auth_version'");
        if ($result && $result->num_rows === 0) {
            $db->query(
                'ALTER TABLE users ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 0'
            );
        }
    } catch (Throwable $e) {
        error_log('Auth session schema check failed: ' . $e->getMessage());
    }
}

function ame_bump_auth_version(mysqli $db, int $user_id): void
{
    if ($user_id <= 0) {
        return;
    }
    $stmt = $db->prepare(
        'UPDATE users SET auth_version = auth_version + 1 WHERE id = ? LIMIT 1'
    );
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

function ame_enforce_account_session(mysqli $db): void
{
    $user_id = (int)($_SESSION['user_id'] ?? 0);
    if ($user_id <= 0) {
        return;
    }

    $stmt = $db->prepare(
        'SELECT status, auth_version, removed_at FROM users WHERE id = ? LIMIT 1'
    );
    if (!$stmt) {
        $stmt = $db->prepare(
            'SELECT status, auth_version FROM users WHERE id = ? LIMIT 1'
        );
    }
    if (!$stmt) {
        ame_clear_session_cookie();
        header('Location: /login.php?session=invalid');
        exit();
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $session_version = isset($_SESSION['auth_version'])
        ? (int)$_SESSION['auth_version']
        : -1;
    $removed = $account && trim((string) ($account['removed_at'] ?? '')) !== '';
    if (!$account
        || $removed
        || (int)($account['status'] ?? 0) !== 1
        || (int)($account['auth_version'] ?? 0) !== $session_version
    ) {
        ame_clear_session_cookie();
        header('Location: /login.php?session=revoked');
        exit();
    }
}

/* =============================================================
 * মাল্টি-টেন্যান্ট ব্র্যান্ডিং হেল্পার (White-Label Support)
 * -------------------------------------------------------------
 * পুরো সফটওয়্যারে কোথাও কোনো নির্দিষ্ট প্রতিষ্ঠানের নাম হার্ডকোড
 * করা থাকবে না। Settings টেবিলে যা সেভ থাকবে সেটাই সব জায়গায়
 * (হেডার টাইটেল, ইনভয়েস, PWA অ্যাপ-নাম/আইকন, ফুটার ইত্যাদি) দেখাবে।
 * এভাবেই একই কোডবেজ একাধিক দোকানদারের কাছে বিক্রি করা যাবে।
 * ============================================================= */

/* settings টেবিল থেকে একটি নির্দিষ্ট কী পড়ে; না পেলে $default রিটার্ন করে */
function shop_setting($key, $default = '') {
    global $shop;
    if (isset($shop[$key]) && $shop[$key] !== null && $shop[$key] !== '') {
        return $shop[$key];
    }
    return $default;
}

function ame_shop_identity_name(): string
{
    return 'মা ইঞ্জিনিয়ারিং ওয়ার্কশপ এন্ড মামন ট্রেডার্স';
}

function ame_shop_identity_tagline(): string
{
    return 'স্পেয়ার পার্টস ব্যবসা ও ইঞ্জিনিয়ারিং ওয়ার্কশপ';
}

function ame_shop_name_is_placeholder(string $name): bool
{
    $n = trim($name);
    if ($n === '') {
        return true;
    }
    $generic = [
        'আমার ব্যবসা প্রতিষ্ঠান',
        'Aponshop',
        'APONSHOP',
    ];
    return in_array($n, $generic, true);
}

function ame_ensure_shop_branding($db, array &$shop): void
{
    if (!($db instanceof mysqli)) {
        return;
    }
    $cols = [];
    $colRes = @$db->query('SHOW COLUMNS FROM settings');
    if ($colRes) {
        while ($c = $colRes->fetch_assoc()) {
            $cols[] = $c['Field'];
        }
    }
    if (!in_array('shop_tagline', $cols, true)) {
        @$db->query("ALTER TABLE settings ADD COLUMN shop_tagline VARCHAR(180) NULL DEFAULT NULL");
    }
    @$db->query("ALTER TABLE settings MODIFY shop_name VARCHAR(180) NOT NULL DEFAULT 'আমার ব্যবসা প্রতিষ্ঠান'");

    $id = (int)($shop['id'] ?? 0);
    if ($id <= 0) {
        return;
    }
    $name = trim((string)($shop['shop_name'] ?? ''));
    $tag = trim((string)($shop['shop_tagline'] ?? ''));
    $logo = trim((string)($shop['shop_logo'] ?? ''));
    $new_name = $name;
    $new_tag = $tag;
    $new_logo = $logo;
    if (ame_shop_name_is_placeholder($name)) {
        $new_name = ame_shop_identity_name();
    }
    if ($tag === '') {
        $new_tag = ame_shop_identity_tagline();
    }
    $ma = 'ma-logo.png';
    $imgDir = dirname(__DIR__) . '/assets/img';
    $legacyLogo = 'shop_logo_6f6e645e4b9b_1788379041.png';
    if (is_file($imgDir . '/' . $ma) && (
        $logo === '' ||
        $logo === $legacyLogo ||
        ($logo !== '' && !is_file($imgDir . '/' . basename($logo)))
    )) {
        $new_logo = $ma;
    }
    if ($new_name === $name && $new_tag === $tag && $new_logo === $logo) {
        $shop['shop_name'] = $name;
        $shop['shop_tagline'] = $tag;
        $shop['shop_logo'] = $logo;
        return;
    }
    $stmt = $db->prepare('UPDATE settings SET shop_name = ?, shop_tagline = ?, shop_logo = ? WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('sssi', $new_name, $new_tag, $new_logo, $id);
        $stmt->execute();
        $stmt->close();
    }
    $shop['shop_name'] = $new_name;
    $shop['shop_tagline'] = $new_tag;
    $shop['shop_logo'] = $new_logo;
}

/* প্রতিষ্ঠানের নাম — Settings থেকে আসে; খালি থাকলে এই দোকানের নাম বসে, এডমিন বদলাতে পারেন */
function shop_name() {
    return shop_setting('shop_name', ame_shop_identity_name());
}

function shop_tagline() {
    return shop_setting('shop_tagline', ame_shop_identity_tagline());
}

function shop_short_name() {
    $name = shop_name();
    return mb_substr($name, 0, 36, 'UTF-8');
}

/* লোগো/আইকন-এর পূর্ণাঙ্গ ওয়েব পাথ; সেট করা না থাকলে ডিফল্ট আইকন দেখায় */
function shop_logo_url($fallback = '/assets/img/ma-logo.png') {
    $logo = shop_setting('shop_logo', '');
    return $logo !== '' ? '/assets/img/' . rawurlencode($logo) : $fallback;
}

/** প্রিন্ট/শেয়ার মেমোর লোগোর উপর — মালিকের বানান হুবহু। */
function ame_logo_eid_text(): string
{
    return "সাইয়্যিদু সাইয়্যিদিল আ'ইয়াদ শরীফ, মোবারক হো";
}

/** শোরুম অফার — প্রতি আরবি মাসের ১২ তারিখের ১২% ডিসকাউন্ট (সব কাস্টমার মেমোতে একই)। */
function ame_memo_showroom_offer_text(): string
{
    if (function_exists('is_en') && is_en()) {
        return '12% special discount on every product and service for 24 hours from Maghrib on the 12th of each Arabic month';
    }
    return 'প্রতি আরবি মাসের ১২ তারিখ মাগরিব থেকে ২৪ ঘন্টা ১২% বিশেষ ডিসকাউন্ট সমস্ত পণ্য ও সেবার উপর';
}

function ame_memo_showroom_offer_html(): string
{
    return '<p class="ame-memo-side" role="note">'
        . htmlspecialchars(ame_memo_showroom_offer_text(), ENT_QUOTES, 'UTF-8')
        . '</p>';
}

function shop_print_logo_tag(int $height = 56): string
{
    $url = shop_logo_url('/assets/img/header-logo.png');
    $alt = function_exists('shop_name') ? shop_name() : 'Aponshop';
    $safeHeight = max(32, min(96, $height));
    $line = htmlspecialchars(ame_logo_eid_text(), ENT_QUOTES, 'UTF-8');
    $img = '<img class="ame-print-logo" src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
        . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8')
        . '" style="height:' . $safeHeight . 'px;width:auto;max-width:180px;object-fit:contain;display:block;margin:0 auto;">';
    static $cssOnce = false;
    $css = '';
    if (!$cssOnce) {
        $cssOnce = true;
        $css = '<style id="ame-logo-eid-css">'
            . '.ame-print-brand{display:block;width:100%;max-width:100%;text-align:center;margin:0 0 6px;}'
            . '.ame-logo-eid{display:block;width:100%;box-sizing:border-box;margin:0 0 8px;padding:10px 12px;'
            . 'font-size:18px;font-weight:900;line-height:1.5;letter-spacing:.1px;border-radius:0;border:0;'
            . 'text-align:center;white-space:normal;overflow:visible;word-break:break-word;'
            . 'background:#ffffff !important;background-color:#ffffff !important;background-image:none !important;'
            . 'color:#C9A227 !important;-webkit-text-fill-color:#C9A227 !important;-webkit-text-stroke:0;paint-order:normal;text-shadow:1px 1px 0 #1a1204,2px 2px 0 #1a1204;'
            . 'animation:none !important;'
            . '-webkit-print-color-adjust:exact;print-color-adjust:exact;}'
            . '@media (max-width:420px){.ame-logo-eid{font-size:15px;letter-spacing:0;padding:10px 8px;}}'
            . '.ame-memo-side{display:block;width:100%;box-sizing:border-box;margin:0 0 8px;padding:0 12px 2px;'
            . 'text-align:center;font-size:16px;font-weight:800;line-height:1.5;letter-spacing:0;white-space:normal;word-break:break-word;'
            . 'color:#0B3A82 !important;-webkit-text-fill-color:#0B3A82 !important;text-shadow:none !important;'
            . '-webkit-print-color-adjust:exact;print-color-adjust:exact;}'
            . '@media print{.ame-logo-eid{animation:none !important;background:#ffffff !important;background-color:#ffffff !important;color:#C9A227 !important;-webkit-text-fill-color:#C9A227 !important;-webkit-text-stroke:0;paint-order:normal;text-shadow:1px 1px 0 #1a1204,2px 2px 0 #1a1204;font-size:18px;font-weight:900;white-space:normal;}'
            . '.ame-memo-side{color:#0B3A82 !important;-webkit-text-fill-color:#0B3A82 !important;font-size:15px;font-weight:800;}}'
            . '</style>';
    }
    $offer = function_exists('ame_memo_showroom_offer_html') ? ame_memo_showroom_offer_html() : '';
    return $css . '<div class="ame-print-brand"><p class="ame-logo-eid">' . $line . '</p>' . $offer . $img . '</div>';
}

/* হোম-স্ক্রিন / PWA স্কয়ার আইকন — চওড়া shop_logo এখানে যাবে না */
function shop_app_icon_url($size = 192) {
    return ((int)$size >= 512) ? '/assets/img/icon-512.png' : '/assets/img/icon-192.png';
}

function shop_phone()   { return shop_setting('shop_phone', ''); }
function shop_address() { return shop_setting('shop_address', ''); }
function shop_banner_url() {
    $b = shop_setting('shop_banner', '');
    return $b !== '' ? '/assets/img/' . rawurlencode($b) : '';
}

function ame_request_script(): string
{
    return strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
}

function ame_is_sales_desk_page(): bool
{
    return str_contains(ame_request_script(), '/modules/sales/pos.php');
}

/** mysqli_result বা ব্যর্থ কুয়েরিকে নিরাপদ অ্যারে করে */
function ame_mysqli_rows($result): array
{
    if ($result instanceof mysqli_result) {
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        return is_array($rows) ? $rows : [];
    }
    return [];
}

/** একীভূত বকেয়া নোটিফিকেশন: সর্বশেষ বিক্রয়/পেমেন্টের ৮ দিন পর */
function ame_due_watch_interval_days(): int
{
    return ame_due8_days();
}

function ame_ensure_due_watch_columns($db): void
{
    static $done = false;
    if ($done || !($db instanceof mysqli)) {
        return;
    }
    $done = true;
    if ($db instanceof mysqli && !isset($GLOBALS['db'])) {
        $GLOBALS['db'] = $db;
    }
    if (function_exists('ame_schema_stamp') && ame_schema_stamp('due_watch_cols_v2')) {
        return;
    }
    $cols = [
        'due_watch' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'due_watch_on' => 'DATE DEFAULT NULL',
        'due_notify_suppressed' => 'TINYINT(1) NOT NULL DEFAULT 0',
    ];
    foreach ($cols as $col => $def) {
        $chk = @$db->query("SHOW COLUMNS FROM customers LIKE '" . $db->real_escape_string($col) . "'");
        if ($chk && $chk->num_rows === 0) {
            @$db->query("ALTER TABLE customers ADD COLUMN `$col` $def");
        }
    }
    if (function_exists('ame_schema_stamp')) {
        ame_schema_stamp('due_watch_cols_v2', true);
    }
}

function ame_due_watch_fire_date(?string $on): ?string
{
    $on = trim((string)$on);
    if ($on === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $on)) {
        return null;
    }
    $t = strtotime($on . ' +' . ame_due_watch_interval_days() . ' days');
    return $t ? date('Y-m-d', $t) : null;
}

function ame_due_watch_ready_sql(): string
{
    $days = ame_due_watch_interval_days();
    $empty = ame_due_date_empty_sql('next_due_date');
    return '(COALESCE(due_notify_suppressed, 0) = 0 AND due_watch = 1 AND due_watch_on IS NOT NULL AND DATE_ADD(due_watch_on, INTERVAL ' . $days . ' DAY) <= CURDATE() AND receivable_balance > 0 AND ' . $empty . ')';
}

/** খালি/অবৈধ next_due_date — ভবিষ্যতের সংশোধিত তারিখকে ৭ দিনের পুরোনো তারিখ দিয়ে চাপা দেয় না */
function ame_due_date_value($value): string
{
    $value = trim((string) $value);
    if ($value === '' || str_starts_with($value, '0000-00-00')) {
        return '';
    }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $m)) {
        return $m[1];
    }
    return '';
}

function ame_due_date_empty_sql(string $column = 'c.next_due_date'): string
{
    if (!preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*\.)?[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
        $column = 'c.next_due_date';
    }
    return '(' . $column . " IS NULL OR CAST(" . $column . " AS CHAR) IN ('', '0000-00-00', '0000-00-00 00:00:00'))";
}

/** শুধু কাস্টমার। is_customer=0 সাপ্লায়ার-ব্রিজ বকেয়া নোটিফিকেশনে আসে না। */
function ame_due_customer_role_sql(string $column = 'c.is_customer'): string
{
    if (!preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*\.)?[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
        $column = 'c.is_customer';
    }
    return '(' . $column . ' = 1 OR ' . $column . ' IS NULL)';
}

function ame_due_notification_where_sql(): string
{
    $empty = ame_due_date_empty_sql('next_due_date');
    $set = '(NOT ' . $empty . ')';
    return ame_due_customer_role_sql('is_customer') . ' AND COALESCE(due_notify_suppressed, 0) = 0 AND receivable_balance > 0.004 AND (('
        . $set . ' AND next_due_date <= CURDATE()) OR ('
        . $empty . ' AND due_watch = 1 AND due_watch_on IS NOT NULL AND DATE_ADD(due_watch_on, INTERVAL '
        . ame_due8_days() . ' DAY) <= CURDATE()))';
}

function ame_due_notification_count($db): int
{
    try {
        if (!($db instanceof mysqli) || !ame_due8_enabled($db)) {
            return 0;
        }
        $now = time();
        if (session_status() === PHP_SESSION_ACTIVE) {
            $hit = $_SESSION['ame_due_badge'] ?? null;
            if (is_array($hit) && (int)($hit['at'] ?? 0) + 60 > $now) {
                return (int)($hit['n'] ?? 0);
            }
        }
        ame_ensure_due_watch_columns($db);
        // Header badge এবং due-reminder page একই rule ব্যবহার করে: নির্ধারিত বকেয়া
        // অথবা টানা ৭ দিন payment না-পাওয়া positive-due customer।
        // Balance/ledger কখনো cache, write বা পুনর্গণনা করা হয় না।
        $q = ame_due8_last_act_sql($db);
        $res = @$db->query('SELECT COUNT(*) AS c FROM customers c' . $q['joins'] . ' WHERE ' . $q['where']);
        $n = $res ? (int)($res->fetch_assoc()['c'] ?? 0) : 0;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['ame_due_badge'] = ['at' => $now, 'n' => $n];
        }
        return $n;
    } catch (Throwable $e) {
        error_log('due notify count: ' . $e->getMessage());
        return 0;
    }
}

function ame_due_watch_from_post(bool $checked, $prev_watch = 0, $prev_on = null): array
{
    if (!$checked) {
        return [0, null];
    }
    $on = date('Y-m-d');
    if ((int)$prev_watch === 1) {
        $kept = trim((string)$prev_on);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $kept)) {
            $on = $kept;
        }
    }
    return [1, $on];
}

/** বকেয়া নোটিফিকেশন চালু আছে কি — নির্ধারিত তারিখ অথবা পুরোনো ওয়াচ */
function ame_customer_due_notify_on(array $c): bool
{
    if (!empty($c['due_notify_suppressed'])) {
        return false;
    }
    if (!empty($c['due_watch'])) {
        return true;
    }
    return trim((string)($c['next_due_date'] ?? '')) !== '';
}

/** ঘণ্টা ও বাকি-তারিখ তালিকা থেকে এই কাস্টমারকে নামান */
function ame_customer_notify_off($db, int $customer_id): bool
{
    if (!($db instanceof mysqli) || $customer_id <= 0) {
        return false;
    }
    ame_ensure_due_watch_columns($db);
    $sql = 'UPDATE customers SET due_watch = 0, due_watch_on = NULL, next_due_date = NULL, due_notify_suppressed = 1 WHERE id = ?';
    $chkAmt = @$db->query("SHOW COLUMNS FROM customers LIKE 'next_due_amount'");
    if ($chkAmt && $chkAmt->num_rows > 0) {
        $sql = 'UPDATE customers SET due_watch = 0, due_watch_on = NULL, next_due_date = NULL, next_due_amount = NULL, due_notify_suppressed = 1 WHERE id = ?';
    }
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $customer_id);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok && session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['ame_due_badge']);
    }
    return (bool)$ok;
}

/** ব্যবহারকারী নতুন reminder/watch দিলে স্থায়ী suppression তুলে দিন। */
function ame_customer_notify_allow($db, int $customer_id): bool
{
    if (!($db instanceof mysqli) || $customer_id <= 0) {
        return false;
    }
    ame_ensure_due_watch_columns($db);
    $stmt = $db->prepare('UPDATE customers SET due_notify_suppressed = 0 WHERE id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $customer_id);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok && session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['ame_due_badge']);
    }
    return (bool)$ok;
}

/**
 * customers.status: 1/active/খালি = চালু, 0/inactive = বন্ধ।
 * পুরনো (int) কাস্ট 'active'-কে ভুল করে বন্ধ দেখাত।
 */
function ame_customer_status_enabled($status): bool
{
    if ($status === null) {
        return true;
    }
    if (is_bool($status)) {
        return $status;
    }
    $raw = trim((string)$status);
    if ($raw === '') {
        return true;
    }
    $value = strtolower($raw);
    if (in_array($value, ['0', 'off', 'no', 'false', 'inactive', 'disabled', 'disable', 'closed', 'blocked'], true)) {
        return false;
    }
    if (is_numeric($value)) {
        return abs((float)$value - 1.0) < 0.00001;
    }
    return in_array($value, ['1', 'on', 'yes', 'true', 'active', 'enabled', 'enable', 'open'], true);
}

/**
 * পণ্য অপশন: সক্রিয়, খালি, বা পুরনো 1। নিষ্ক্রিয়, 0, খসড়া, archived বাদ।
 * CAST AS CHAR — TINYINT কলামে status='active' ভুল করে 0 (নিষ্ক্রিয়) মেলাত।
 */
function ame_product_option_sql(string $column = 'status'): string
{
    $column = preg_replace('/[^A-Za-z0-9_.]/', '', $column) ?? '';
    if (!preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*\.){0,2}status$/', $column)) {
        $column = 'status';
    }
    $text = "LOWER(TRIM(CAST($column AS CHAR)))";
    return "($column IS NULL OR TRIM(CAST($column AS CHAR)) = '' OR $text IN ('active','1','yes','on','enabled','সক্রিয়'))";
}

function ame_customer_status_pick(array $values, bool $on): string
{
    $needles = $on
        ? ['1', 'active', 'enabled', 'enable', 'yes', 'on', 'true', 'open']
        : ['0', 'inactive', 'disabled', 'disable', 'no', 'off', 'false', 'closed', 'blocked'];
    foreach ($needles as $needle) {
        foreach ($values as $value) {
            if (strtolower(trim((string)$value)) === $needle) {
                return (string)$value;
            }
        }
    }
    if ($on) {
        return (string)($values[0] ?? '1');
    }
    return (string)($values[1] ?? ($values[0] ?? '0'));
}

/** কলামের আসল টাইপ অনুযায়ী চালু/বন্ধ মান। সংখ্যা কলামে সবসময় 1/0, যাতে status = 1 কুয়েরি মিলে। */
function ame_customer_status_tokens(string $type, string $default = ''): array
{
    $type = strtolower(trim($type));
    if ($default !== '' && preg_match('/^(tinyint|smallint|mediumint|int|bigint|bit|bool|boolean|decimal|numeric|float|double)/', $type)) {
        return ['1', '0'];
    }
    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|bit|bool|boolean|decimal|numeric|float|double)/', $type)) {
        return ['1', '0'];
    }
    if (preg_match('/^enum\((.*)\)$/s', $type, $match)) {
        $vals = str_getcsv($match[1], ',', "'");
        $vals = array_values(array_filter(array_map(static function ($value) {
            return trim((string)$value);
        }, $vals), static function ($value) {
            return $value !== '';
        }));
        return [
            ame_customer_status_pick($vals, true),
            ame_customer_status_pick($vals, false),
        ];
    }
    return ['1', '0'];
}

function ame_customer_status_boot($db): array
{
    static $meta = null;
    if (is_array($meta)) {
        return $meta;
    }
    $meta = ['on' => '1', 'off' => '0', 'type' => 'tinyint(1)'];
    if (!($db instanceof mysqli)) {
        $meta = null;
        return ['on' => '1', 'off' => '0', 'type' => 'tinyint(1)'];
    }
    $res = @$db->query("SHOW COLUMNS FROM customers LIKE 'status'");
    $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
    if ($res instanceof mysqli_result) {
        $res->free();
    }
    if (!is_array($row)) {
        @$db->query('ALTER TABLE customers ADD COLUMN status TINYINT(1) NOT NULL DEFAULT 1');
        return $meta;
    }
    $type = strtolower((string)($row['Type'] ?? ''));
    $default = $row['Default'] === null ? '' : (string)$row['Default'];
    [$on, $off] = ame_customer_status_tokens($type, $default);
    $meta = ['on' => $on, 'off' => $off, 'type' => $type];
    $already = function_exists('ame_schema_stamp') && ame_schema_stamp('customer_status_norm_v1');
    if ($already || str_starts_with($type, 'enum(')) {
        return $meta;
    }
    $on_sql = $db->real_escape_string($on);
    $off_sql = $db->real_escape_string($off);
    @$db->query("UPDATE customers SET status = '{$on_sql}' WHERE LOWER(TRIM(CAST(status AS CHAR))) IN ('active','enabled','enable','yes','on','true','open')");
    @$db->query("UPDATE customers SET status = '{$off_sql}' WHERE LOWER(TRIM(CAST(status AS CHAR))) IN ('inactive','disabled','disable','no','off','false','closed','blocked')");
    if (function_exists('ame_schema_stamp')) {
        ame_schema_stamp('customer_status_norm_v1', true);
    }
    return $meta;
}

function ame_customer_status_store($db, bool $enabled): string
{
    $meta = ame_customer_status_boot($db);
    return $enabled ? (string)$meta['on'] : (string)$meta['off'];
}

/**
 * চালু কাস্টমার (খালি/1/active)। বন্ধ (0/inactive) শুধু কাস্টমার তালিকায় থাকে।
 */
function ame_customer_active_sql(string $alias = ''): string
{
    $p = '';
    if ($alias !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
        $p = $alias . '.';
    }
    $text = "LOWER(TRIM(CAST({$p}status AS CHAR)))";
    return "({$p}status IS NULL OR TRIM(CAST({$p}status AS CHAR)) = '' OR {$text} IN ('1','active','enabled','enable','yes','on','true','open'))";
}

/**
 * POS / পেমেন্ট / ভাড়া / সার্চ পিকার: মুছে-না-ফেলা এবং চালু কাস্টমার।
 */
function ame_customer_picker_sql($db = null, string $alias = ''): string
{
    if ($db instanceof mysqli && function_exists('ame_customer_status_boot')) {
        ame_customer_status_boot($db);
    }
    if (!function_exists('ame_customer_live_sql')) {
        $pp = __DIR__ . '/party_purge.php';
        if (is_file($pp)) {
            require_once $pp;
        }
    }
    $parts = [ame_customer_active_sql($alias)];
    if ($db instanceof mysqli && function_exists('ame_customer_live_sql')) {
        $parts[] = ame_customer_live_sql($db, $alias);
    }
    return '(' . implode(' AND ', $parts) . ')';
}

function ame_customer_row_operable(?array $row): bool
{
    if (!$row) {
        return false;
    }
    if (function_exists('ame_customer_is_purged') && ame_customer_is_purged($row)) {
        return false;
    }
    return ame_customer_status_enabled($row['status'] ?? 1);
}

/** বাকি-রিমাইন্ডার সারির ব্যাজ: নির্ধারিত তারিখ অথবা ৭ দিনের স্বয়ংক্রিয় নিয়ম */
function ame_due_row_badge(array $c, string $today): array
{
    $due = ame_due_date_value($c['next_due_date'] ?? '');
    $bal = (float)($c['receivable_balance'] ?? 0);
    $lastAct = trim((string)($c['last_act'] ?? ''));
    $fire = null;
    if ($lastAct !== '' && !str_starts_with($lastAct, '1000-')) {
        $t = strtotime($lastAct . ' +' . ame_due8_days() . ' days');
        $fire = $t ? date('Y-m-d', $t) : null;
    } elseif (!empty($c['due_watch'])) {
        $fire = ame_due_watch_fire_date($c['due_watch_on'] ?? null);
    }
    // সংশোধিত তারিখ থাকলে সেটাই দেখাবে — ৭ দিনের পুরোনো তারিখ আর চাপা দেয় না।
    $watch_ready = $due === ''
        && $fire !== null
        && $fire <= $today
        && $bal > 0;

    $display = $due !== '' ? $due : (string) $fire;

    $badge = 'badge-upcoming';
    $text = 'আসন্ন';
    if ($watch_ready) {
        $badge = 'badge-overdue';
        $text = '৭ দিন পেমেন্ট নেই';
    } elseif ($due !== '') {
        if ($due < $today) {
            $badge = 'badge-overdue';
            $text = 'মেয়াদ পার';
        } elseif ($due === $today) {
            $badge = 'badge-today';
            $text = 'আজ';
        }
    } elseif ($fire) {
        if ($fire < $today) {
            $badge = 'badge-overdue';
            $text = '৭ দিন পেমেন্ট নেই';
        } elseif ($fire === $today) {
            $badge = 'badge-today';
            $text = 'আজ';
        }
    }

    return [
        'date' => $display !== '' ? $display : '—',
        'badge' => $badge,
        'text' => $text,
        'watch_ready' => $watch_ready,
        'fire' => $fire,
    ];
}

/** বকেয়া কাস্টমার ৭ দিন পেমেন্ট না দিলে নোটিফিকেশনে আসবে */
function ame_due8_days(): int
{
    return 7;
}

function ame_due8_ensure($db): void
{
    static $done = false;
    if ($done || !($db instanceof mysqli)) {
        return;
    }
    $done = true;
    if ($db instanceof mysqli && !isset($GLOBALS['db'])) {
        $GLOBALS['db'] = $db;
    }
    if (function_exists('ame_schema_stamp') && ame_schema_stamp('due8_app_settings')) {
        return;
    }
    @$db->query("CREATE TABLE IF NOT EXISTS app_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (function_exists('ame_schema_stamp')) {
        ame_schema_stamp('due8_app_settings', true);
    }
}

function ame_due8_enabled($db): bool
{
    if (!($db instanceof mysqli)) {
        return false;
    }
    ame_due8_ensure($db);
    if (function_exists('get_app_setting')) {
        $v = get_app_setting($db, 'due8_notify_on', '1');
        return (string)$v !== '0';
    }
    $stmt = @$db->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
    if (!$stmt) {
        return true;
    }
    $key = 'due8_notify_on';
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return true;
    }
    return (string)$row['setting_value'] !== '0';
}

function ame_due8_set_enabled($db, bool $on): bool
{
    if (!($db instanceof mysqli)) {
        return false;
    }
    ame_due8_ensure($db);
    $val = $on ? '1' : '0';
    if (function_exists('set_app_setting')) {
        return (bool)set_app_setting($db, 'due8_notify_on', $val);
    }
    $stmt = @$db->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    if (!$stmt) {
        return false;
    }
    $key = 'due8_notify_on';
    $stmt->bind_param('ss', $key, $val);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

function ame_due8_table_ok($db, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    if (!($db instanceof mysqli)) {
        return $cache[$table] = false;
    }
    $t = $db->real_escape_string($table);
    $res = @$db->query("SHOW TABLES LIKE '$t'");
    return $cache[$table] = ($res && $res->num_rows > 0);
}

function ame_due8_last_act_sql($db): array
{
    $days = ame_due8_days();
    $joins = '';
    $parts = ["COALESCE(DATE(c.created_at), '1000-01-01')"];
    // শুধু payment activity দেখা হয়; নতুন sale সাত দিনের payment clock reset করে না।
    // প্রতি সারিতে আলাদা সাবকোয়েরি নয় — একবার গ্রুপ করে জোড়া। ফল আগের মতো।
    if (ame_due8_table_ok($db, 'customer_payments')) {
        $joins = ' LEFT JOIN (SELECT customer_id, DATE(MAX(payment_date)) AS last_pay FROM customer_payments GROUP BY customer_id) ame_lp ON ame_lp.customer_id = c.id';
        $parts[] = "COALESCE(ame_lp.last_pay, '1000-01-01')";
    }
    $act = count($parts) === 1 ? $parts[0] : ('GREATEST(' . implode(', ', $parts) . ')');
    $cutoff = 'DATE_SUB(CURDATE(), INTERVAL ' . $days . ' DAY)';
    $quiet = ["COALESCE(DATE(c.created_at), '1000-01-01') <= {$cutoff}"];
    if (ame_due8_table_ok($db, 'customer_payments')) {
        $quiet[] = "NOT EXISTS (SELECT 1 FROM customer_payments p WHERE p.customer_id = c.id AND p.payment_date > {$cutoff})";
    }
    $empty = ame_due_date_empty_sql('c.next_due_date');
    $set = '(NOT ' . $empty . ')';
    $customerOnly = ame_due_customer_role_sql('c.is_customer');
    $picker = function_exists('ame_customer_picker_sql') ? ame_customer_picker_sql($db, 'c') : '1=1';
    // ভবিষ্যতের সংশোধিত তারিখ = সেই দিন পর্যন্ত নোটিফিকেশন বন্ধ। ৭ দিনের নিয়ম শুধু তারিখ না-থাকলে।
    // সাপ্লায়ার-শুধু সারি (is_customer=0) এখানে আসে না; কাস্টমার+সাপ্লায়ার থাকলে আসে।
    // বন্ধ (inactive) কাস্টমার শুধু কাস্টমার তালিকায় — বকেয়া ঘণ্টা/রিমাইন্ডারে নয়।
    $where = "{$customerOnly} AND {$picker} AND COALESCE(c.due_notify_suppressed, 0) = 0 AND c.receivable_balance > 0.004 AND (
        ({$set} AND c.next_due_date <= CURDATE())
        OR ({$empty} AND " . implode(' AND ', $quiet) . ")
    )";
    return ['joins' => $joins, 'act' => $act, 'where' => $where];
}

function ame_due8_count($db): int
{
    try {
        if (!($db instanceof mysqli) || !ame_due8_enabled($db)) {
            return 0;
        }
        $q = ame_due8_last_act_sql($db);
        $sql = 'SELECT COUNT(*) AS c FROM customers c' . $q['joins'] . ' WHERE ' . $q['where'];
        $res = @$db->query($sql);
        if (!$res) {
            return 0;
        }
        return (int)($res->fetch_assoc()['c'] ?? 0);
    } catch (Throwable $e) {
        error_log('due8 count: ' . $e->getMessage());
        return 0;
    }
}

function ame_due8_list($db, string $search = ''): array
{
    if (!($db instanceof mysqli) || !ame_due8_enabled($db)) {
        return [];
    }
    $q = ame_due8_last_act_sql($db);
    $sql = 'SELECT c.id, c.customer_code, c.name, c.phone, c.receivable_balance, c.created_at, '
        . $q['act'] . ' AS last_act';
    if (ame_due8_table_ok($db, 'customer_payments')) {
        $sql .= ', (SELECT DATE(MAX(p.payment_date)) FROM customer_payments p WHERE p.customer_id = c.id) AS last_pay';
    } else {
        $sql .= ', NULL AS last_pay';
    }
    if (ame_due8_table_ok($db, 'sales')) {
        $sql .= ", (SELECT DATE(MAX(s.sale_date)) FROM sales s WHERE s.customer_id = c.id AND s.sale_date IS NOT NULL AND s.sale_date > '1000-01-01') AS last_sale";
    } else {
        $sql .= ', NULL AS last_sale';
    }
    $sql .= ' FROM customers c' . $q['joins'] . ' WHERE ' . $q['where'];
    $search = trim($search);
    $types = '';
    $params = [];
    if ($search !== '') {
        $sql .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR c.customer_code LIKE ?)';
        $like = '%' . $search . '%';
        $types = 'sss';
        $params = [$like, $like, $like];
    }
    $sql .= ' ORDER BY last_act ASC, c.receivable_balance DESC';
    if ($types !== '') {
        $stmt = @$db->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return is_array($rows) ? $rows : [];
    }
    $res = @$db->query($sql);
    if (!$res) {
        return [];
    }
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    return is_array($rows) ? $rows : [];
}

if (!function_exists('ame_chart_peak')) {
    function ame_chart_peak(array $values, int $floor = 1): int
    {
        if ($values === []) {
            return $floor;
        }
        return max($floor, (int) max($values));
    }
}
?>