<?php
/**
 * সাপ্লায়ার total_due ও কাস্টমার receivable_balance —
 * ZIP/পেজ লোডে যেন ইচ্ছাকৃত বদল না হয় (শুধু লেনদেন বা অ্যাডমিন ম্যানুয়াল)।
 */

if (!function_exists('ame_party_amount_shield_triggers_ok')) {
    function ame_party_amount_shield_triggers_ok(mysqli $db): bool
    {
        $r = @$db->query("SHOW TRIGGERS WHERE `Trigger` IN ('ame_party_sup_bu','ame_party_cus_bu')");
        return $r && $r->num_rows >= 2;
    }
}

if (!function_exists('ame_party_amount_shield_install')) {
    /** @return array{ok:bool,message:string} */
    function ame_party_amount_shield_install(mysqli $db): array
    {
        $sql = [
            'DROP TRIGGER IF EXISTS ame_party_sup_bu',
            'DROP TRIGGER IF EXISTS ame_party_cus_bu',
            "CREATE TRIGGER ame_party_sup_bu BEFORE UPDATE ON suppliers FOR EACH ROW
             BEGIN
                IF NOT (OLD.total_due <=> NEW.total_due) THEN
                    IF IFNULL(@ame_party_balance_write, 0) <> 1
                       AND IFNULL(@ame_party_admin_manual, 0) <> 1 THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'সাপ্লায়ার দেনা শুধু লেনদেন বা অ্যাডমিনের হাতে বদলানো যাবে';
                    END IF;
                END IF;
             END",
            "CREATE TRIGGER ame_party_cus_bu BEFORE UPDATE ON customers FOR EACH ROW
             BEGIN
                IF NOT (OLD.receivable_balance <=> NEW.receivable_balance) THEN
                    IF IFNULL(@ame_party_balance_write, 0) <> 1
                       AND IFNULL(@ame_party_admin_manual, 0) <> 1 THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'কাস্টমার ব্যালেন্স শুধু লেনদেন বা অ্যাডমিনের হাতে বদলানো যাবে';
                    END IF;
                END IF;
             END",
        ];
        try {
            foreach ($sql as $q) {
                if (!$db->query($q)) {
                    return ['ok' => false, 'message' => 'শিল্ড ট্রিগার বসানো যায়নি: ' . $db->error];
                }
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        return ['ok' => ame_party_amount_shield_triggers_ok($db), 'message' => ''];
    }
}

if (!function_exists('ame_party_amount_request_uri')) {
    function ame_party_amount_request_uri(): string
    {
        $parts = [];
        foreach (['SCRIPT_NAME', 'PHP_SELF', 'REQUEST_URI'] as $key) {
            $raw = (string)($_SERVER[$key] ?? '');
            if ($raw === '') {
                continue;
            }
            $path = strtolower(strtok($raw, '?'));
            $path = str_replace('\\', '/', $path);
            if ($path !== '' && $path !== '/') {
                $parts[] = $path;
            }
        }
        if ($parts === []) {
            return '';
        }
        usort($parts, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        return $parts[0];
    }
}

if (!function_exists('ame_party_amount_request_is_ledger_write')) {
    function ame_party_amount_request_is_ledger_write(): bool
    {
        if (PHP_SAPI === 'cli') {
            $cli = getenv('AME_PARTY_LEDGER_CLI');
            return $cli === '1' || strtolower((string)$cli) === 'true';
        }
        $uri = ame_party_amount_request_uri();
        if ($uri === '') {
            return false;
        }
        $needles = [
            '/process_sale.php',
            '/draft_api.php',
            '/process_purchase.php',
            '/process_purchase_edit.php',
            '/process_payment',
            '/payment-receive.php',
            '/payment-receive',
            '/payment_receive',
            '/payment_receive.php',
            '/receive_payment',
            '/collect_payment',
            '/customer_payment',
            '/save_payment',
            '/record_payment',
            '/take_payment',
            '/process_customer.php',
            '/process_withdraw.php',
            '/process_pay.php',
            '/process_return',
            '/save_purchase_return.php',
            '/process_transaction.php',
            '/process_transfer.php',
            '/process_salary.php',
            '/process_worker_pay.php',
            '/job_process.php',
            '/void_memo_payment.php',
            '/set_supplier_previous.php',
            '/resolve_customer_supplier.php',
            '/create_invoice_return.php',
        ];
        foreach ($needles as $n) {
            if (str_contains($uri, $n)) {
                return true;
            }
        }
        if (str_contains($uri, '/modules/customers/actions/') && str_contains($uri, 'process_')) {
            return true;
        }
        if (str_contains($uri, '/modules/suppliers/') && str_contains($uri, 'process_')) {
            return true;
        }
        if (str_contains($uri, '/modules/sales/') && str_contains($uri, 'process_')) {
            return true;
        }
        if (str_contains($uri, '/modules/purchase/') && (str_contains($uri, 'process_') || str_contains($uri, '/actions/'))) {
            return true;
        }
        if (str_contains($uri, '/modules/returns/') && (str_contains($uri, 'process_') || str_contains($uri, '/actions/'))) {
            return true;
        }
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? ''));
        if ($method === 'POST' && (int)($_SESSION['user_id'] ?? 0) > 0) {
            foreach (
                [
                    '/modules/sales/',
                    '/modules/customers/',
                    '/modules/suppliers/',
                    '/modules/purchase/',
                    '/modules/returns/',
                    '/modules/accounts/',
                ] as $prefix
            ) {
                if (str_contains($uri, $prefix)) {
                    return true;
                }
            }
        }
        return false;
    }
}

if (!function_exists('ame_party_balance_allow_write')) {
    /**
     * লেনদেনের আগে একই mysqli কানেকশনে শিল্ড ট্রিগারকে অনুমোদন দিন।
     */
    function ame_party_balance_allow_write(?mysqli $db = null): bool
    {
        if (!($db instanceof mysqli)) {
            return false;
        }
        if (!$db->query('SET @ame_party_balance_write = 1')) {
            error_log('party_amount_shield: SET @ame_party_balance_write failed: ' . $db->error);
            return false;
        }
        return true;
    }
}

if (!function_exists('ame_party_amount_request_is_admin_manual')) {
    function ame_party_amount_request_is_admin_manual(): bool
    {
        if (PHP_SAPI === 'cli' && getenv('AME_PARTY_SHIELD_SELFTEST') !== '1') {
            return false;
        }
        if (strtolower((string)($_SESSION['role'] ?? '')) !== 'admin') {
            return false;
        }
        $uri = ame_party_amount_request_uri();
        $needles = [
            '/balance_guard.php',
            '/process_correct_balance.php',
            '/process_edit_account.php',
            '/process_opening_balance.php',
            '/quick_add_supplier.php',
            '/balance_correction',
        ];
        foreach ($needles as $n) {
            if (str_contains($uri, $n)) {
                return true;
            }
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['admin_party_amount_edit'])) {
            return true;
        }
        return false;
    }
}

if (!function_exists('ame_party_amount_request_is_customer_payment_page')) {
    function ame_party_amount_request_is_customer_payment_page(): bool
    {
        $uri = ame_party_amount_request_uri();
        if ($uri === '') {
            return false;
        }
        $patterns = [
            '/modules/customers/payment-receive',
            '/modules/customers/payment_receive',
            '/modules/customers/receive_payment',
            '/modules/customers/collect_payment',
            '/modules/customers/collect.php',
            '/modules/customers/payment.php',
            '/modules/customers/pay.php',
        ];
        foreach ($patterns as $pattern) {
            if (str_contains($uri, $pattern)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('ame_party_amount_authorize_request')) {
    function ame_party_amount_authorize_request(?mysqli $db = null): void
    {
        if (!($db instanceof mysqli)) {
            return;
        }
        $loggedIn = (int)($_SESSION['user_id'] ?? 0) > 0
            || (int)($_SESSION['id'] ?? 0) > 0
            || trim((string)($_SESSION['username'] ?? '')) !== '';
        if ($loggedIn && ame_party_amount_request_is_customer_payment_page()) {
            ame_party_balance_allow_write($db);
        }
        if (ame_party_amount_request_is_ledger_write()) {
            ame_party_balance_allow_write($db);
        }
        if (ame_party_amount_request_is_admin_manual()) {
            @$db->query('SET @ame_party_admin_manual = 1');
            ame_party_balance_allow_write($db);
        }
    }
}

if (!function_exists('ame_party_amount_drift_log_throttled')) {
    function ame_party_amount_drift_log_throttled(array $check): void
    {
        $sig = sprintf(
            '%.2f|%.2f|%.2f|%.2f',
            (float)($check['supplier'] ?? 0),
            (float)($check['expected_supplier'] ?? 0),
            (float)($check['customer'] ?? 0),
            (float)($check['expected_customer'] ?? 0)
        );
        $logDir = defined('AME_PRIVATE_DATA_DIR')
            ? (AME_PRIVATE_DATA_DIR . '/logs')
            : (dirname(__DIR__, 2) . '/private_data/logs');
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0700, true);
        }
        $stateFile = $logDir . '/party_drift_throttle.json';
        $now = time();
        $state = ['sig' => '', 'ts' => 0];
        if (is_file($stateFile)) {
            $decoded = json_decode((string)@file_get_contents($stateFile), true);
            if (is_array($decoded)) {
                $state = array_merge($state, $decoded);
            }
        }
        if ($state['sig'] === $sig && ($now - (int)$state['ts']) < 3600) {
            return;
        }
        @file_put_contents($stateFile, json_encode(['sig' => $sig, 'ts' => $now], JSON_UNESCAPED_UNICODE));
        error_log(sprintf(
            'PARTY AMOUNT DRIFT: suppliers Σ=%.2f (expected %.2f), customers Σ=%.2f (expected %.2f) — মনিটরিং স্ন্যাপশট পুরোনো; অ্যাডমিন «party_seal_baseline» দিয়ে বেসলাইন মিলান (ব্যালেন্স অপরিবর্তিত)',
            $check['supplier'],
            (float)$check['expected_supplier'],
            $check['customer'],
            (float)$check['expected_customer']
        ));
    }
}

if (!function_exists('ame_party_amount_shield_boot')) {
    function ame_party_amount_shield_boot(?mysqli $db = null): void
    {
        if (!($db instanceof mysqli)) {
            return;
        }
        if (!ame_party_amount_shield_triggers_ok($db)) {
            ame_party_amount_shield_install($db);
        }
        ame_party_amount_authorize_request($db);
        $check = ame_ledger_seal_verify_totals($db);
        if (!$check['ok'] && $check['expected_supplier'] !== null) {
            ame_party_amount_drift_log_throttled($check);
            if (strtolower((string)($_SESSION['role'] ?? '')) === 'admin') {
                $_SESSION['ame_party_amount_drift'] = $check;
            }
        } elseif (isset($_SESSION['ame_party_amount_drift'])) {
            unset($_SESSION['ame_party_amount_drift']);
        }
    }
}

if (!function_exists('ame_ledger_seal_verify_totals')) {
    /** @return array{ok:bool,supplier:float,customer:float,expected_supplier:?float,expected_customer:?float} */
    function ame_ledger_seal_verify_totals(mysqli $db): array
    {
        $out = [
            'ok' => true,
            'supplier' => 0.0,
            'customer' => 0.0,
            'expected_supplier' => null,
            'expected_customer' => null,
        ];
        if (!@$db->query('SELECT 1 FROM ame_ledger_seal WHERE id = 1 LIMIT 1')) {
            return $out;
        }
        $seal = $db->query('SELECT supplier_due_sum, customer_recv_sum FROM ame_ledger_seal WHERE id = 1 AND sealed = 1 LIMIT 1');
        if (!$seal || !($row = $seal->fetch_assoc())) {
            return $out;
        }
        $out['expected_supplier'] = $row['supplier_due_sum'] !== null ? round((float)$row['supplier_due_sum'], 2) : null;
        $out['expected_customer'] = $row['customer_recv_sum'] !== null ? round((float)$row['customer_recv_sum'], 2) : null;
        $sr = @$db->query('SELECT COALESCE(SUM(total_due),0) AS s FROM suppliers');
        if ($sr && ($srow = $sr->fetch_assoc())) {
            $out['supplier'] = round((float)$srow['s'], 2);
        }
        $cr = @$db->query('SELECT COALESCE(SUM(receivable_balance),0) AS s FROM customers');
        if ($cr && ($crow = $cr->fetch_assoc())) {
            $out['customer'] = round((float)$crow['s'], 2);
        }
        if ($out['expected_supplier'] !== null && abs($out['supplier'] - $out['expected_supplier']) > 0.05) {
            $out['ok'] = false;
        }
        if ($out['expected_customer'] !== null && abs($out['customer'] - $out['expected_customer']) > 0.05) {
            $out['ok'] = false;
        }
        return $out;
    }
}