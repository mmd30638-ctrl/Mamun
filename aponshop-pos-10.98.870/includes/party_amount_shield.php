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
        return strtolower((string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
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
        $reqPath = strtolower((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ''));
        if ($uri === '' && $reqPath === '') {
            return false;
        }
        if ($reqPath !== '' && $uri === '') {
            $uri = $reqPath;
        }
        if ($reqPath !== '' && !str_contains($uri, 'process_') && str_contains($reqPath, 'process_')) {
            $uri = $reqPath;
        }
        $needles = [
            '/process_sale.php',
            '/process_purchase.php',
            '/process_purchase_edit.php',
            '/process_payment',
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
        return false;
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

if (!function_exists('ame_party_balance_write_enable')) {
    function ame_party_balance_write_enable(mysqli $db): void
    {
        @$db->query('SET @ame_party_balance_write = 1');
    }
}

if (!function_exists('ame_party_admin_manual_enable')) {
    function ame_party_admin_manual_enable(mysqli $db): void
    {
        @$db->query('SET @ame_party_admin_manual = 1');
        @$db->query('SET @ame_party_balance_write = 1');
    }
}

if (!function_exists('ame_party_amount_authorize_request')) {
    function ame_party_amount_authorize_request(?mysqli $db = null): void
    {
        if (!($db instanceof mysqli)) {
            return;
        }
        if (ame_party_amount_request_is_ledger_write()) {
            ame_party_balance_write_enable($db);
        }
        if (ame_party_amount_request_is_admin_manual()) {
            ame_party_admin_manual_enable($db);
        }
    }
}

if (!function_exists('ame_party_amount_drift_log_once')) {
    function ame_party_amount_drift_log_once(array $check): void
    {
        $sig = sprintf(
            's%.0f_e%.0f_c%.0f_ec%.0f',
            $check['supplier'],
            (float)$check['expected_supplier'],
            $check['customer'],
            (float)$check['expected_customer']
        );
        $dir = function_exists('ame_private_log_dir') ? ame_private_log_dir() : (dirname(__DIR__) . '/private_data/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $stamp = $dir . '/.party_drift_log_' . md5($sig);
        if (is_file($stamp) && (time() - (int)@filemtime($stamp)) < 21600) {
            return;
        }
        @touch($stamp);
        error_log(sprintf(
            'PARTY AMOUNT DRIFT: suppliers Σ=%.2f (expected %.2f), customers Σ=%.2f (expected %.2f)',
            $check['supplier'],
            (float)$check['expected_supplier'],
            $check['customer'],
            (float)$check['expected_customer']
        ));
    }
}

if (!function_exists('ame_party_amount_skip_drift_check')) {
    function ame_party_amount_skip_drift_check(): bool
    {
        if (PHP_SAPI === 'cli') {
            return true;
        }
        $meth = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($meth !== 'GET') {
            return false;
        }
        if (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
            return true;
        }
        $path = strtolower((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ''));
        foreach ([
            'pos_party_search.php',
            'draft_api.php',
            'append_api.php',
            'customer_card.php',
            'pulse',
            'api.php',
        ] as $frag) {
            if (str_contains($path, $frag)) {
                return true;
            }
        }
        return false;
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
        if (ame_party_amount_skip_drift_check()) {
            return;
        }
        $check = ame_ledger_seal_verify_totals($db);
        if (!$check['ok'] && $check['expected_supplier'] !== null) {
            $is_admin = (function_exists('ame_is_admin_like') && ame_is_admin_like())
                || !empty($_SESSION['is_super_admin'])
                || in_array(strtolower((string)($_SESSION['role'] ?? '')), ['admin', 'super_admin', 'superadmin', 'owner'], true);
            if ($is_admin) {
                $_SESSION['ame_party_amount_drift'] = $check;
            }
            ame_party_amount_drift_log_once($check);
        } elseif (isset($_SESSION['ame_party_amount_drift'])) {
            unset($_SESSION['ame_party_amount_drift']);
        }
    }
}

if (!function_exists('ame_ledger_seal_rebaseline_totals')) {
    /**
     * লক-টেবিলের প্রত্যাশিত যোগফল = বর্তমান DB যোগফল (টাকা বদলায় না, শুধু তুলনার বেসলাইন)।
     * @return array{ok:bool,message:string}
     */
    function ame_ledger_seal_rebaseline_totals(mysqli $db): array
    {
        if (!@$db->query('SELECT 1 FROM ame_ledger_seal WHERE id = 1 LIMIT 1')) {
            return ['ok' => false, 'message' => 'ame_ledger_seal টেবিল নেই।'];
        }
        $supSum = 0.0;
        $cusSum = 0.0;
        $sr = @$db->query('SELECT COALESCE(SUM(total_due),0) AS s FROM suppliers');
        if ($sr && ($row = $sr->fetch_assoc())) {
            $supSum = round((float)$row['s'], 2);
        }
        $cr = @$db->query('SELECT COALESCE(SUM(receivable_balance),0) AS s FROM customers');
        if ($cr && ($row = $cr->fetch_assoc())) {
            $cusSum = round((float)$row['s'], 2);
        }
        $stmt = @$db->prepare(
            'UPDATE ame_ledger_seal SET supplier_due_sum = ?, customer_recv_sum = ?, sealed_at = NOW() WHERE id = 1'
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'সিল আপডেট প্রস্তুত হয়নি।'];
        }
        $stmt->bind_param('dd', $supSum, $cusSum);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok
            ? ['ok' => true, 'message' => 'লেজার সিল বেসলাইন আপডেট হয়েছে (individual ব্যালেন্স অপরিবর্তিত)।']
            : ['ok' => false, 'message' => 'সিল আপডেট ব্যর্থ।'];
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