<?php
// Aponshop International - Accounting Engine
require_once __DIR__ . '/config.php';

// ⭐ অ্যাকাউন্টের আসল ব্যালেন্স শুধু list.php এবং Daily Audit Center
// (daily_audit.php)-এ দেখা যায়। বাকি জায়গায় সংখ্যা মাস্ক।
function ame_balance_visible_here(): bool
{
    $script = strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
    return (bool) preg_match('#/modules/accounts/(list|daily_audit)\.php$#', $script);
}

/** নাম্বার-হাইড করা ব্যালেন্স লেবেল — accounts/list.php ছাড়া সব জায়গায় "৳••••" দেখাবে। */
function ame_balance_label(float $balance): string
{
    if (ame_balance_visible_here()) {
        return '৳' . number_format($balance, 2);
    }
    return '৳••••';
}

/** ড্রপডাউন/JSON-এর মতো জায়গায় শুধু সংখ্যাটা (কোনো ৳ চিহ্ন ছাড়া) দরকার হলে। */
function ame_balance_number_masked($balance)
{
    return ame_balance_visible_here() ? (float)$balance : 0;
}

/**
 * Canonical source for every real funding-account selector.
 *
 * Do not filter by account_type: cash, bank, mobile-wallet and future account
 * types are all valid when active. Callers may safely render the returned
 * balance because choosing a funding source requires seeing its available
 * balance.
 */
/**
 * Status values that mean the shop account is closed.
 * Cash, Bank, Mobile, bkash, and Bengali types are not in this list.
 * Blank, active, 1, yes, enabled, and সক্রিয় stay open.
 */
function ame_account_closed_status_sql(string $column = 'status'): string
{
    return "LOWER(TRIM($column)) IN ('inactive','0','no','n','off','disabled','disable','false','closed','close','stopped','stop','deleted','archived','void','2','নিষ্ক্রিয়','বন্ধ')";
}

function ame_account_squash(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = strtr($text, [
        'বিকাশ' => 'bkash',
        'ডাচ-বাংলা' => 'dutchbangla',
        'ডাচ বাংলা' => 'dutchbangla',
        'ডাচবাংলা' => 'dutchbangla',
        'dutch-bangla' => 'dutchbangla',
        'dutch bangla' => 'dutchbangla',
        'dutchbangla' => 'dutchbangla',
        'dbbl' => 'dutchbangla',
        'bkash' => 'bkash',
    ]);
    $text = preg_replace('/[\s\-\.·•|,৳]+/u', '', $text) ?? $text;
    return $text;
}

/**
 * Brand token with bank/mobile/balance words removed.
 * "ডাচ বাংলা ব্যাংক" and "Dutch-Bangla" both become dutchbangla.
 * "বিকাশ" and "bKash" both become bkash.
 */
function ame_account_name_key(string $text): string
{
    $key = ame_account_squash($text);
    if ($key === '') {
        return '';
    }
    $key = str_replace(
        ['ব্যাংক', 'bank', 'মোবাইল', 'mobile', 'ওয়ালেট', 'wallet', 'অ্যাকাউন্ট', 'একাউন্ট', 'account', 'ব্যালেন্স', 'balance', 'mfs'],
        '',
        $key
    );
    return $key;
}

function ame_account_brand(string $text): string
{
    $key = ame_account_squash($text);
    if ($key === '') {
        return '';
    }
    if (str_contains($key, 'dutchbangla')) {
        return 'dutchbangla';
    }
    if (str_contains($key, 'bkash')) {
        return 'bkash';
    }
    return '';
}

/**
 * Figure the সাধারণ লেনদেন label prints.
 * `balance` wins when it is non-zero. A 0 balance with money in another
 * column (current_balance and the like) uses that column, because that is
 * the number on screen.
 */
function ame_account_spendable_amount(array $account): float
{
    $balance = round((float)($account['balance'] ?? 0), 2);
    if (abs($balance) >= 0.009) {
        return $balance;
    }
    foreach (['current_balance', 'available_balance', 'running_balance', 'ledger_balance', 'book_balance', 'closing_balance'] as $col) {
        if (!array_key_exists($col, $account)) {
            continue;
        }
        $alt = round((float)$account[$col], 2);
        if (abs($alt) >= 0.009) {
            return $alt;
        }
    }
    return $balance;
}

function ame_account_money_columns(mysqli $db): array
{
    $allowed = ['current_balance', 'available_balance', 'running_balance', 'ledger_balance', 'book_balance', 'closing_balance'];
    $found = [];
    $res = @$db->query('SHOW COLUMNS FROM accounts');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $field = (string)($row['Field'] ?? '');
            if (in_array($field, $allowed, true)) {
                $found[] = $field;
            }
        }
    }
    return $found;
}

/** @return array{amount: float, source: ?string}|null */
function ame_account_money_snapshot(mysqli $db, int $account_id): ?array
{
    $extra = ame_account_money_columns($db);
    $sql = 'SELECT balance';
    foreach ($extra as $col) {
        $sql .= ', `' . $col . '`';
    }
    $sql .= ' FROM accounts WHERE id = ? LIMIT 1';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $account_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    $balance = round((float)($row['balance'] ?? 0), 2);
    if (abs($balance) >= 0.009) {
        return ['amount' => $balance, 'source' => null];
    }
    foreach ($extra as $col) {
        $alt = round((float)($row[$col] ?? 0), 2);
        if (abs($alt) >= 0.009) {
            return ['amount' => $alt, 'source' => $col];
        }
    }
    return ['amount' => $balance, 'source' => null];
}

function ame_account_store_balance(mysqli $db, int $account_id, float $new_balance, ?string $source): void
{
    $upd = $db->prepare('UPDATE accounts SET balance = ? WHERE id = ?');
    if (!$upd) {
        throw new Exception('অ্যাকাউন্ট ব্যালেন্স আপডেট হয়নি।');
    }
    $upd->bind_param('di', $new_balance, $account_id);
    $upd->execute();
    $upd->close();
    $allowed = ['current_balance', 'available_balance', 'running_balance', 'ledger_balance', 'book_balance', 'closing_balance'];
    if ($source === null || !in_array($source, $allowed, true)) {
        return;
    }
    $sql = 'UPDATE accounts SET `' . $source . '` = ? WHERE id = ?';
    $alt = $db->prepare($sql);
    if (!$alt) {
        throw new Exception('অ্যাকাউন্ট ব্যালেন্স আপডেট হয়নি।');
    }
    $alt->bind_param('di', $new_balance, $account_id);
    $alt->execute();
    $alt->close();
}

function ame_active_accounts(mysqli $db): array
{
    $cols = [];
    $colRes = @$db->query('SHOW COLUMNS FROM accounts');
    if ($colRes) {
        while ($row = $colRes->fetch_assoc()) {
            $cols[$row['Field']] = true;
        }
    }
    $typeSql = isset($cols['account_type']) ? 'account_type' : "'' AS account_type";
    $statusSql = isset($cols['status']) ? 'status' : "'active' AS status";
    $moneySql = 'balance';
    foreach (['current_balance', 'available_balance', 'running_balance', 'ledger_balance', 'book_balance', 'closing_balance'] as $moneyCol) {
        if (isset($cols[$moneyCol])) {
            $moneySql .= ', ' . $moneyCol;
        }
    }
    /*
     * Sales and spending selectors share this list. Keep every open cash,
     * bank, and mobile row, including type Mobile / bkash and status yes,
     * enabled, সক্রিয়, 1, or blank. Do not require context='business':
     * live rows are stored as project, bank, mobile, empty, or NULL.
     * Personal and family ledgers stay out. Account type is never a filter.
     */
    $where = '1=1';
    if (isset($cols['status'])) {
        $where .= ' AND (status IS NULL OR TRIM(status) = \'\' OR NOT (' . ame_account_closed_status_sql('status') . '))';
    }
    if (isset($cols['context'])) {
        $where .= " AND (context IS NULL OR TRIM(context) = '' OR LOWER(TRIM(context)) NOT IN ('personal','family','private','ব্যক্তিগত','পারিবারিক'))";
    }
    if (isset($cols['is_personal'])) {
        $where .= " AND (is_personal IS NULL OR TRIM(CAST(is_personal AS CHAR)) = '' OR LOWER(TRIM(CAST(is_personal AS CHAR))) IN ('0','no','false'))";
    }
    $order = (isset($cols['is_default']) ? 'is_default DESC, ' : '') . 'account_name ASC, id ASC';
    $result = @$db->query(
        "SELECT id, account_name, {$typeSql}, {$moneySql}, {$statusSql}
         FROM accounts
         WHERE {$where}
         ORDER BY {$order}"
    );
    if (!$result) {
        $fallback = isset($cols['status'])
            ? "(status IS NULL OR TRIM(status) = '' OR NOT (" . ame_account_closed_status_sql('status') . '))'
            : '1=1';
        if (isset($cols['context'])) {
            $fallback .= " AND (context IS NULL OR TRIM(context) = '' OR LOWER(TRIM(context)) NOT IN ('personal','family','private','ব্যক্তিগত','পারিবারিক'))";
        }
        if (isset($cols['is_personal'])) {
            $fallback .= " AND (is_personal IS NULL OR TRIM(CAST(is_personal AS CHAR)) = '' OR LOWER(TRIM(CAST(is_personal AS CHAR))) IN ('0','no','false'))";
        }
        $result = @$db->query(
            "SELECT id, account_name, account_type, {$moneySql}, status
             FROM accounts
             WHERE {$fallback}
             ORDER BY account_name ASC, id ASC"
        );
    }
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function ame_require_active_account(mysqli $db, int $account_id): array
{
    if ($account_id < 1) {
        throw new InvalidArgumentException('প্রকৃত ব্যবসায়িক অ্যাকাউন্ট নির্বাচন করুন।');
    }
    /*
     * Same open-status set as ame_active_accounts(). Closed words stay out.
     * No context filter here — personal rows stay out of shop lists via
     * ame_active_accounts(), not via debit/credit.
     */
    $open = ame_account_closed_status_sql('status');
    $stmt = $db->prepare(
        "SELECT id, account_name, account_type, balance, status
         FROM accounts
         WHERE id=?
           AND (status IS NULL OR TRIM(status) = '' OR NOT ($open))
         LIMIT 1"
    );
    $stmt->bind_param('i', $account_id);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$account) {
        throw new InvalidArgumentException('নির্বাচিত ব্যবসায়িক অ্যাকাউন্টটি সক্রিয় নয়।');
    }
    return $account;
}

function ame_account_payment_method(array $account): string
{
    $text = strtolower((string)($account['account_type'] ?? '') . ' ' . (string)($account['account_name'] ?? ''));
    if (str_contains($text, 'bank') || str_contains($text, 'ব্যাংক')) {
        return 'bank';
    }
    if (str_contains($text, 'mobile') || str_contains($text, 'wallet')
        || str_contains($text, 'bkash') || str_contains($text, 'nagad')
        || str_contains($text, 'বিকাশ') || str_contains($text, 'রকেট')) {
        return 'mobile';
    }
    if (str_contains($text, 'cash') || str_contains($text, 'নগদ') || str_contains($text, 'ক্যাশ')) {
        return 'cash';
    }
    return 'other';
}

function ame_account_option_label(array $account): string
{
    $name = trim((string)($account['account_name'] ?? ''));
    $type = trim((string)($account['account_type'] ?? ''));
    $balance = number_format(ame_account_spendable_amount($account), 2);
    return $name . ($type !== '' ? ' · ' . $type : '') . ' · ব্যালেন্স ৳' . $balance;
}

function ame_render_account_options(array $accounts, int $selected = 0, string $placeholder = 'প্রকৃত অ্যাকাউন্ট নির্বাচন করুন'): string
{
    $html = '<option value="">' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '</option>';
    foreach ($accounts as $account) {
        $id = (int)($account['id'] ?? 0);
        if ($id < 1) {
            continue;
        }
        $html .= '<option value="' . $id . '"' . ($id === $selected ? ' selected' : '') . '>'
            . htmlspecialchars(ame_account_option_label($account), ENT_QUOTES, 'UTF-8')
            . '</option>';
    }
    return $html;
}

/**
 * One selector for every module that receives or pays money.
 * Options always come from ame_active_accounts(). Pass extras only for a
 * due/credit choice the module already had; they do not replace accounts.
 */
function ame_account_select(mysqli $db, array $opt = []): string
{
    $accounts = isset($opt['accounts']) && is_array($opt['accounts'])
        ? $opt['accounts']
        : ame_active_accounts($db);
    $name = (string)($opt['name'] ?? 'account_id');
    $idAttr = trim((string)($opt['id'] ?? ''));
    $direction = (($opt['direction'] ?? 'receive') === 'pay') ? 'pay' : 'receive';
    $required = !empty($opt['required']);
    $selected = (int)($opt['selected'] ?? 0);
    $class = trim('ame-account-select ' . (string)($opt['class'] ?? ''));
    $placeholder = array_key_exists('placeholder', $opt) ? $opt['placeholder'] : 'নির্বাচন';
    $attrs = trim((string)($opt['attrs'] ?? ''));
    $html = '<select class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"'
        . ' name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
        . ' data-ame-account-select="1"'
        . ' data-ame-direction="' . $direction . '"';
    if ($idAttr !== '') {
        $html .= ' id="' . htmlspecialchars($idAttr, ENT_QUOTES, 'UTF-8') . '"';
    }
    if ($required) {
        $html .= ' required';
    }
    if ($attrs !== '') {
        $html .= ' ' . $attrs;
    }
    $html .= '>';
    if ($placeholder !== false && $placeholder !== null) {
        $html .= '<option value="">' . htmlspecialchars((string)$placeholder, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    foreach ($opt['prepend'] ?? [] as $extra) {
        $html .= ame_account_extra_option($extra, $selected);
    }
    foreach ($accounts as $account) {
        $id = (int)($account['id'] ?? 0);
        if ($id < 1) {
            continue;
        }
        $type = strtolower(trim((string)($account['account_type'] ?? '')));
        $balance = ame_balance_number_masked($account['balance'] ?? 0);
        $html .= '<option value="' . $id . '"'
            . ' data-type="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-bal="' . htmlspecialchars((string)$balance, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-balance="' . htmlspecialchars((string)$balance, ENT_QUOTES, 'UTF-8') . '"'
            . ($id === $selected ? ' selected' : '')
            . '>'
            . htmlspecialchars(trim((string)($account['account_name'] ?? '')), ENT_QUOTES, 'UTF-8')
            . '</option>';
    }
    foreach ($opt['extras'] ?? [] as $extra) {
        $html .= ame_account_extra_option($extra, $selected);
    }
    $html .= '</select>';
    return $html;
}

function ame_account_extra_option(array $extra, int $selected): string
{
    $value = (string)($extra['value'] ?? '');
    $label = trim((string)($extra['label'] ?? ''));
    if ($label === '') {
        return '';
    }
    $picked = ($value !== '' && (string)$selected === $value) ? ' selected' : '';
    return '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . $picked . '>'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        . '</option>';
}

function ame_selectable_account(mysqli $db, int $account_id): ?array
{
    if ($account_id < 1) {
        return null;
    }
    foreach (ame_active_accounts($db) as $account) {
        if ((int)($account['id'] ?? 0) === $account_id) {
            return $account;
        }
    }
    return null;
}

function ame_require_selectable_account(mysqli $db, int $account_id): array
{
    $account = ame_selectable_account($db, $account_id);
    if (!$account) {
        throw new InvalidArgumentException('সক্রিয় ক্যাশ, ব্যাংক বা মোবাইল অ্যাকাউন্ট নির্বাচন করুন।');
    }
    return $account;
}

/**
 * The form posts the account id. If a control posts the visible label
 * instead, pick the shop row whose name, type, and shown balance match.
 * A selectable id is never moved onto a different same-name row.
 */
function ame_posted_shop_account_id(mysqli $db, string $raw): int
{
    $raw = trim($raw);
    if ($raw === '') {
        return 0;
    }
    if (preg_match('/^\d+$/', $raw)) {
        $id = (int)$raw;
        return ame_selectable_account($db, $id) ? $id : 0;
    }
    $accounts = ame_active_accounts($db);
    $bare = trim((string)preg_replace('/\s*[\(\[·].*$/u', '', $raw));
    $rawKey = ame_account_squash($raw);
    $rawName = ame_account_name_key($raw);
    $rawBrand = ame_account_brand($raw);
    $bestId = 0;
    $bestScore = 0;
    $tied = false;
    foreach ($accounts as $account) {
        $id = (int)($account['id'] ?? 0);
        if ($id < 1) {
            continue;
        }
        $label = ame_account_option_label($account);
        $accountName = trim((string)($account['account_name'] ?? ''));
        if ($raw === $label || $raw === $accountName) {
            return $id;
        }
        $nameKey = ame_account_name_key($accountName);
        $oldKey = ame_account_squash($accountName);
        $brand = ame_account_brand($accountName);
        $matched = false;
        if ($nameKey !== '' && $rawName !== '' && $rawName === $nameKey) {
            $matched = true;
        }
        if (!$matched && $brand !== '' && $brand === $rawBrand) {
            $matched = true;
        }
        if (!$matched && $oldKey !== '' && $rawKey !== '' && str_contains($rawKey, $oldKey)) {
            $matched = true;
        }
        $bareHit = $bare !== '' && $bare === $accountName;
        if (!$matched && !$bareHit) {
            continue;
        }
        $score = $bareHit ? 9 : (($nameKey !== '' && $rawName !== '' && $rawName === $nameKey) ? 8 : 6);
        $type = strtolower(trim((string)($account['account_type'] ?? '')));
        if ($type !== '' && str_contains(mb_strtolower($raw, 'UTF-8'), $type)) {
            $score += 2;
        }
        if (preg_match('/৳\s*([0-9][0-9,]*(?:\.[0-9]+)?)/u', $raw, $money)) {
            $shown = (float)str_replace(',', '', $money[1]);
            $book = ame_account_spendable_amount($account);
            if (abs($shown - $book) >= 0.01) {
                continue;
            }
            $score += 4;
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestId = $id;
            $tied = false;
        } elseif ($score === $bestScore) {
            $tied = true;
        }
    }
    if ($bestScore >= 5 && !$tied) {
        return $bestId;
    }
    return 0;
}

/**
 * খরচ may arrive as txn_type, type, or direction.
 * debit / খরচ / expense / pay / out debit the account.
 * credit / জমা / income / receive credit it.
 */
function ame_manual_txn_direction(array $post): string
{
    $map = static function ($raw): string {
        $text = mb_strtolower(trim((string)$raw), 'UTF-8');
        $text = str_replace([' ', '_', '-'], '', $text);
        if (in_array($text, ['debit', 'expense', 'pay', 'out', 'withdraw', 'withdrawal', 'খরচ'], true)) {
            return 'debit';
        }
        if (in_array($text, ['credit', 'income', 'receive', 'in', 'deposit', 'জমা'], true)) {
            return 'credit';
        }
        return '';
    };
    $primary = $map($post['posted_txn_type'] ?? '');
    if ($primary === '') {
        $primary = $map($post['txn_type'] ?? '');
    }
    if ($primary !== '') {
        return $primary;
    }
    foreach (['type', 'direction', 'txn', 'kind'] as $key) {
        $hit = $map($post[$key] ?? '');
        if ($hit !== '') {
            return $hit;
        }
    }
    return '';
}

/** Recent সাধারণ লেনদেন: manual_expense and debit kinds belong in the খরচ cell. */
function ame_manual_row_is_deposit(string $kind, string $ref): bool
{
    $kind = mb_strtolower(trim($kind), 'UTF-8');
    $ref = mb_strtolower(trim($ref), 'UTF-8');
    if (in_array($ref, ['manual_expense', 'expense'], true) || in_array($kind, ['debit', 'expense', 'খরচ', 'out', 'pay'], true)) {
        return false;
    }
    return in_array($kind, ['credit', 'in', 'income', 'deposit', 'জমা'], true)
        || in_array($ref, ['manual_income', 'income', 'deposit'], true);
}

/**
 * Single posting path. Receive credits the chosen account once.
 * Pay debits the chosen account once. A missing choice is rejected
 * when money is moving and the account is required.
 */
function ame_post_account(mysqli $db, string $direction, int $account_id, float $amount, string $ref_type, int $ref_id, string $note, bool $required = true): bool
{
    $amount = round($amount, 2);
    $direction = $direction === 'pay' ? 'pay' : 'receive';
    if ($amount <= 0) {
        return false;
    }
    if ($account_id < 1) {
        if ($required) {
            throw new InvalidArgumentException('অ্যাকাউন্ট নির্বাচন করুন।');
        }
        return false;
    }
    ame_require_selectable_account($db, $account_id);
    if ($direction === 'pay') {
        return (bool)account_debit($account_id, $amount, $ref_type, $ref_id, $note);
    }
    return (bool)account_credit($account_id, $amount, $ref_type, $ref_id, $note);
}

// ⭐ নতুন ফিচারের জন্য দরকারি কলাম/টেবিল — একবারই অটো-তৈরি হবে, phpMyAdmin-এ ম্যানুয়াল কাজ লাগবে না
function ensure_accounts_schema($db) {
    $ledgerCols = [];
    $res = @$db->query("SHOW COLUMNS FROM account_ledger");
    if ($res) {
        while ($row = $res->fetch_assoc()) { $ledgerCols[] = $row['Field']; }
    }
    if ($ledgerCols && !in_array('is_reconciled', $ledgerCols, true)) {
        $db->query("ALTER TABLE account_ledger ADD COLUMN is_reconciled TINYINT(1) NOT NULL DEFAULT 0");
    }
    if ($ledgerCols && !in_array('attachment', $ledgerCols, true)) {
        $db->query("ALTER TABLE account_ledger ADD COLUMN attachment VARCHAR(255) DEFAULT NULL");
    }

    $accCols = [];
    $res2 = @$db->query("SHOW COLUMNS FROM accounts");
    if ($res2) {
        while ($row = $res2->fetch_assoc()) { $accCols[] = $row['Field']; }
    }
    if ($accCols && !in_array('low_balance_threshold', $accCols, true)) {
        $db->query("ALTER TABLE accounts ADD COLUMN low_balance_threshold DECIMAL(12,2) DEFAULT NULL");
    }
    if ($accCols && !in_array('alert_phone', $accCols, true)) {
        $db->query("ALTER TABLE accounts ADD COLUMN alert_phone VARCHAR(20) DEFAULT NULL");
    }
    if ($accCols && !in_array('last_alert_sent_at', $accCols, true)) {
        $db->query("ALTER TABLE accounts ADD COLUMN last_alert_sent_at DATETIME DEFAULT NULL");
    }
    if ($accCols && !in_array('account_number', $accCols, true)) {
        $db->query("ALTER TABLE accounts ADD COLUMN account_number VARCHAR(50) DEFAULT NULL");
    }
    if ($accCols && !in_array('bank_branch', $accCols, true)) {
        $db->query("ALTER TABLE accounts ADD COLUMN bank_branch VARCHAR(150) DEFAULT NULL");
    }

    if ($ledgerCols && !in_array('category_id', $ledgerCols, true)) {
        $db->query("ALTER TABLE account_ledger ADD COLUMN category_id INT DEFAULT NULL");
    }
    if ($ledgerCols && !in_array('merchant_id', $ledgerCols, true)) {
        $db->query("ALTER TABLE account_ledger ADD COLUMN merchant_id INT DEFAULT NULL");
    }
    if ($ledgerCols && !in_array('member_id', $ledgerCols, true)) {
        $db->query("ALTER TABLE account_ledger ADD COLUMN member_id INT DEFAULT NULL");
    }

    $db->query("CREATE TABLE IF NOT EXISTS merchants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        category_id INT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS family_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        role VARCHAR(30) NOT NULL DEFAULT 'other',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!function_exists('ame_family_ensure') && is_file(__DIR__ . '/family_schema.php')) {
        require_once __DIR__ . '/family_schema.php';
    }
    if (function_exists('ame_family_ensure')) {
        ame_family_ensure($db);
    }

    $db->query("CREATE TABLE IF NOT EXISTS expense_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS cash_closing (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT NOT NULL,
        closing_date DATE NOT NULL,
        system_balance DECIMAL(12,2) NOT NULL,
        counted_balance DECIMAL(12,2) NOT NULL,
        difference DECIMAL(12,2) NOT NULL,
        note VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_account (account_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS app_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ⭐ সেটিংস পড়া/লেখার হেল্পার (হাই-ভ্যালু অনুমোদনের থ্রেশহোল্ড ও PIN রাখার জন্য)
function get_app_setting($db, $key, $default = null) {
    $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? $row['setting_value'] : $default;
}

function set_app_setting($db, $key, $value) {
    $stmt = $db->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->bind_param("ss", $key, $value);
    return $stmt->execute();
}

// ১. অ্যাকাউন্টে টাকা জমা করা (Credit)
// ⚠️ এই ফাংশন কল করার আগে $db->begin_transaction() শুরু করা থাকা উচিত (row lock ঠিকভাবে কাজ করার জন্য)
// ⭐ $category_id/$merchant_id/$member_id ঐচ্ছিক — MyFinance ড্যাশবোর্ডের জন্য, বাকি সব কল অপরিবর্তিত থাকবে
function account_credit($account_id, $amount, $ref_type, $ref_id, $note, $category_id = null, $merchant_id = null, $member_id = null) {
    global $db;

    $amount = round((float)$amount, 2);
    if ($amount <= 0) {
        throw new Exception('পরিমাণ শূন্য বা ঋণাত্মক হতে পারবে না।');
    }

    /*
     * ⭐ রেস-কন্ডিশন ফিক্স: "FOR UPDATE" এখানে কমেন্টে "রেস-কন্ডিশন ঠেকাতে"
     * লেখা থাকলেও, কোনো explicit transaction (begin_transaction/commit)
     * ছাড়া mysqli-র autocommit মোডে এই লক আসলে কিছুই আটকায় না — SELECT
     * শেষ হতেই লক ছেড়ে দেয়। তাই একই অ্যাকাউন্টে দুটো credit/debit ঠিক
     * একই মুহূর্তে চললে balance-এর একটা আপডেট হারিয়ে যেতে পারতো। এখন
     * customer_ledger-এর মতোই named lock (GET_LOCK) দিয়ে সিরিয়ালাইজ
     * করা হলো।
     */
    $lock_key = 'acct_bal_' . $account_id;
    $locked = function_exists('ame_lock') ? ame_lock($db, $lock_key) : false;
    try {
        $snap = ame_account_money_snapshot($db, (int)$account_id);
        if ($snap === null) {
            throw new Exception('অ্যাকাউন্ট খুঁজে পাওয়া যায়নি।');
        }

        $new_balance = round($snap['amount'] + $amount, 2);
        ame_account_store_balance($db, (int)$account_id, $new_balance, $snap['source']);

        // টাইপ স্ট্রিং: i(id) d(amt) d(bal) s(ref_t) i(ref_id) s(note) i(cat) i(merchant) i(member) = "iddsisiii" (৯টি)
        $stmt = $db->prepare("INSERT INTO account_ledger (account_id, type, amount, balance_after, ref_type, ref_id, note, category_id, merchant_id, member_id) VALUES (?, 'credit', ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iddsisiii", $account_id, $amount, $new_balance, $ref_type, $ref_id, $note, $category_id, $merchant_id, $member_id);
        return $stmt->execute();
    } finally {
        if ($locked && function_exists('ame_unlock')) {
            ame_unlock($db, $lock_key);
        }
    }
}

// ২. অ্যাকাউন্ট থেকে টাকা খরচ করা (Debit)
// $allow_negative = true দিলে ব্যালেন্স কম থাকলেও জোর করে ঋণাত্মক করা যাবে (শুধু রিভার্সালের মতো বিশেষ ক্ষেত্রে ব্যবহার করুন)
function account_debit($account_id, $amount, $ref_type, $ref_id, $note, $allow_negative = false, $category_id = null, $merchant_id = null, $member_id = null) {
    global $db;

    $account_id = (int)$account_id;
    $ref_type = (string)$ref_type;
    $is_reversal = in_array($ref_type, ['balance_correction', 'opening_balance'], true)
        || str_contains($ref_type, 'reversal')
        || str_contains($ref_type, 'reverse');
    if (!$is_reversal) {
        ame_require_active_account($db, $account_id);
    } elseif ($account_id < 1) {
        throw new InvalidArgumentException('রিভার্সালের মূল অ্যাকাউন্ট পাওয়া যায়নি।');
    }

    $amount = round((float)$amount, 2);
    if ($amount <= 0) {
        throw new Exception('পরিমাণ শূন্য বা ঋণাত্মক হতে পারবে না।');
    }

    // ⭐ রেস-কন্ডিশন ফিক্স — account_credit()-এর মতো একই কারণ, দেখুন সেখানকার কমেন্ট
    $lock_key = 'acct_bal_' . $account_id;
    $locked = function_exists('ame_lock') ? ame_lock($db, $lock_key) : false;
    try {
        /*
         * Mobile, bkash, and mfs rows debit the same way as cash.
         * The check uses the figure on the option label: accounts.balance,
         * or another money column when balance is 0 and that column holds
         * the amount the screen shows.
         */
        $snap = ame_account_money_snapshot($db, $account_id);
        if ($snap === null) {
            throw new Exception('অ্যাকাউন্ট খুঁজে পাওয়া যায়নি।');
        }

        $current_balance = round((float)$snap['amount'], 2);

        // ⭐ নেগেটিভ ব্যালেন্স সুরক্ষা
        if (!$allow_negative && $current_balance < $amount) {
            throw new Exception('অপর্যাপ্ত ব্যালেন্স — এই পরিমাণ (৳' . number_format($amount, 2) . ') লেনদেনের জন্য অ্যাকাউন্টে পর্যাপ্ত টাকা নেই।');
        }

        $new_balance = round($current_balance - $amount, 2);
        ame_account_store_balance($db, $account_id, $new_balance, $snap['source']);

        $stmt = $db->prepare("INSERT INTO account_ledger (account_id, type, amount, balance_after, ref_type, ref_id, note, category_id, merchant_id, member_id) VALUES (?, 'debit', ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iddsisiii", $account_id, $amount, $new_balance, $ref_type, $ref_id, $note, $category_id, $merchant_id, $member_id);
        return $stmt->execute();
    } finally {
        if ($locked && function_exists('ame_unlock')) {
            ame_unlock($db, $lock_key);
        }
    }
}

/** লেজারে বসা ওপেনিং/প্রাথমিক ব্যালেন্সের মোট (ক্রেডিট − ডেবিট) */
function ame_account_opening_sum($db, int $account_id): float
{
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE -amount END), 0) AS n
         FROM account_ledger
         WHERE account_id = ? AND ref_type = 'opening_balance'"
    );
    if (!$stmt) {
        return 0.0;
    }
    $stmt->bind_param('i', $account_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    return round((float) ($row['n'] ?? 0), 2);
}

// ৩. একটা লেজার এন্ট্রি রিভার্স/বাতিল করা (ভুল এন্ট্রি সংশোধনের জন্য)
// মূল এন্ট্রি ডিলিট হয় না — উল্টো একটা নতুন এন্ট্রি বসে, তাই অডিট ট্রেইল অক্ষত থাকে
function reverse_ledger_entry($ledger_id, $performed_note = '') {
    global $db;

    $stmt = $db->prepare("SELECT * FROM account_ledger WHERE id = ?");
    $stmt->bind_param("i", $ledger_id);
    $stmt->execute();
    $entry = $stmt->get_result()->fetch_assoc();

    if (!$entry) {
        throw new Exception('মূল লেজার এন্ট্রি খুঁজে পাওয়া যায়নি।');
    }

    // একই এন্ট্রি দুইবার রিভার্স হওয়া ঠেকানো
    $chk = $db->prepare("SELECT id FROM account_ledger WHERE ref_type = 'reversal' AND ref_id = ?");
    $chk->bind_param("i", $ledger_id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        throw new Exception('এই এন্ট্রিটি ইতিমধ্যে রিভার্স করা হয়েছে।');
    }

    $note = 'রিভার্সাল: ' . $entry['note'] . ($performed_note !== '' ? ' — ' . $performed_note : '');

    if ($entry['type'] === 'debit') {
        // আগে টাকা কাটা হয়েছিল → রিভার্সে টাকা ফেরত জমা হবে
        return account_credit($entry['account_id'], (float)$entry['amount'], 'reversal', $ledger_id, $note);
    } else {
        // আগে টাকা জমা হয়েছিল → রিভার্সে টাকা কেটে নেওয়া হবে (ব্যালেন্স কম থাকলেও রিভার্স যেন আটকে না যায়)
        return account_debit($entry['account_id'], (float)$entry['amount'], 'reversal', $ledger_id, $note, true);
    }
}
// ৪. কম ব্যালেন্স হলে SMS অ্যালার্ট (দিনে একবারের বেশি না, স্প্যাম ঠেকাতে)
// নোট: send_sms() ফাংশন এখনো একটা কাঠামো (গেটওয়ে বসানো বাকি)। গেটওয়ে কনফিগার হলে এই ফাংশন নিজে থেকেই কাজ করবে, এখানে কিছু বদলাতে হবে না।
function check_low_balance_alerts($db) {
    require_once 'sms_functions.php';

    $today = date('Y-m-d');
    $res = $db->query("SELECT id, account_name, balance, low_balance_threshold, alert_phone, last_alert_sent_at 
        FROM accounts 
        WHERE status = 'active' 
        AND low_balance_threshold IS NOT NULL 
        AND balance < low_balance_threshold 
        AND alert_phone IS NOT NULL AND alert_phone != ''");

    while ($acc = $res->fetch_assoc()) {
        $last_sent_date = $acc['last_alert_sent_at'] ? date('Y-m-d', strtotime($acc['last_alert_sent_at'])) : null;
        if ($last_sent_date === $today) {
            continue; // আজ ইতিমধ্যে এই অ্যাকাউন্টের অ্যালার্ট পাঠানো হয়েছে
        }

        $message = ame_sms_text('low_balance', ['account' => (string)($acc['account_name'] ?? ''), 'balance' => (float)($acc['balance'] ?? 0), 'limit' => (float)($acc['low_balance_threshold'] ?? 0)]);

        send_sms($acc['alert_phone'], $message);

        $upd = $db->prepare("UPDATE accounts SET last_alert_sent_at = NOW() WHERE id = ?");
        $upd->bind_param("i", $acc['id']);
        $upd->execute();
    }
}

/**
 * এক অ্যাকাউন্ট থেকে অন্য অ্যাকাউন্টে টাকা। Debit + Credit এক লেনদেনে।
 * একই উৎস/গন্তব্য/পরিমাণ ৬০ সেকেন্ডের মধ্যে আবার চলবে না।
 *
 * @param array{from_account:int,to_account:int,amount:float,note?:string,approval_pin?:string} $in
 * @return array{from_name:string,to_name:string,amount:float,from_prev:float,from_now:float,to_prev:float,to_now:float}
 */
function ame_post_account_transfer(mysqli $db, array $in): array
{
    $from = (int)($in['from_account'] ?? 0);
    $to = (int)($in['to_account'] ?? 0);
    $amount = round((float)($in['amount'] ?? 0), 2);
    $note = trim((string)($in['note'] ?? ''));
    if (function_exists('mb_substr')) {
        $note = mb_substr($note, 0, 120);
    } else {
        $note = substr($note, 0, 120);
    }
    $approval_pin = trim((string)($in['approval_pin'] ?? ''));
    $idempotency_token = substr(trim((string)($in['idempotency_token'] ?? '')), 0, 80);

    if ($from <= 0 || $to <= 0) {
        throw new Exception('উৎস ও গন্তব্য অ্যাকাউন্ট নির্বাচন করুন।');
    }
    if ($from === $to) {
        throw new Exception('উভয় অ্যাকাউন্ট একই হতে পারবে না।');
    }
    if ($amount < 0.01) {
        throw new Exception('পরিমাণ কমপক্ষে ৳0.01 হতে হবে।');
    }

    $threshold_raw = get_app_setting($db, 'transfer_approval_threshold', '');
    $pin_hash = get_app_setting($db, 'transfer_approval_pin_hash', '');
    if ($threshold_raw !== '' && $pin_hash !== '') {
        $threshold = (float)$threshold_raw;
        if ($amount > $threshold) {
            if ($approval_pin === '' || !password_verify($approval_pin, $pin_hash)) {
                throw new Exception('৳' . number_format($threshold, 2) . '-এর বেশি ট্রান্সফারের জন্য সঠিক অনুমোদন PIN আবশ্যক।');
            }
        }
    }

    $found = [
        $from => ame_require_selectable_account($db, $from),
        $to => ame_require_selectable_account($db, $to),
    ];

    $from_name = (string)$found[$from]['account_name'];
    $to_name = (string)$found[$to]['account_name'];
    $from_prev = round((float)$found[$from]['balance'], 2);
    $to_prev = round((float)$found[$to]['balance'], 2);
    $transfer_lock_key = 'account_transfer_' . min($from, $to) . '_' . max($from, $to);
    $transfer_locked = function_exists('ame_lock') ? ame_lock($db, $transfer_lock_key) : false;
    if (function_exists('ame_lock') && !$transfer_locked) {
        throw new Exception('এই অ্যাকাউন্টে আরেকটি ট্রান্সফার চলছে। কয়েক সেকেন্ড পরে চেষ্টা করুন।');
    }

    $dup = $db->prepare(
        "SELECT id FROM account_ledger
         WHERE account_id = ? AND type = 'debit' AND ref_type = 'transfer' AND ref_id = ?
           AND ROUND(amount, 2) = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
         LIMIT 1"
    );
    $dup->bind_param('iid', $from, $to, $amount);
    $dup->execute();
    if ($dup->get_result()->fetch_assoc()) {
        $dup->close();
        if ($transfer_locked && function_exists('ame_unlock')) {
            ame_unlock($db, $transfer_lock_key);
        }
        throw new Exception('একই ট্রান্সফার এক মিনিটের মধ্যে দুইবার হবে না। একটু অপেক্ষা করুন।');
    }
    $dup->close();

    $debit_note = 'ট্রান্সফার → ' . $to_name;
    $credit_note = 'ট্রান্সফার ← ' . $from_name;
    if ($note !== '') {
        $debit_note .= ' — ' . $note;
        $credit_note .= ' — ' . $note;
    }

    $own_tx = $db->begin_transaction();
    if (!$own_tx) {
        if ($transfer_locked && function_exists('ame_unlock')) {
            ame_unlock($db, $transfer_lock_key);
        }
        throw new Exception('ট্রান্সফার শুরু হয়নি। আবার চেষ্টা করুন।');
    }
    try {
        $existing_ref = 0;
        if ($idempotency_token !== ''
            && function_exists('claim_idempotency_token')
            && !claim_idempotency_token($db, 'account_transfer', $idempotency_token, $existing_ref)) {
            throw new Exception('এই ট্রান্সফার আগেই সম্পন্ন হয়েছে — ডাবল এন্ট্রি আটকানো হয়েছে।');
        }
        account_debit($from, $amount, 'transfer', $to, $debit_note);
        account_credit($to, $amount, 'transfer', $from, $credit_note);
        if ($idempotency_token !== '' && function_exists('finish_idempotency_token')) {
            finish_idempotency_token($db, 'account_transfer', $idempotency_token, $from);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        if ($transfer_locked && function_exists('ame_unlock')) {
            ame_unlock($db, $transfer_lock_key);
        }
        throw $e;
    }
    if ($transfer_locked && function_exists('ame_unlock')) {
        ame_unlock($db, $transfer_lock_key);
    }

    return [
        'from_name' => $from_name,
        'to_name' => $to_name,
        'amount' => $amount,
        'from_prev' => $from_prev,
        'from_now' => round($from_prev - $amount, 2),
        'to_prev' => $to_prev,
        'to_now' => round($to_prev + $amount, 2),
    ];
}
?>
