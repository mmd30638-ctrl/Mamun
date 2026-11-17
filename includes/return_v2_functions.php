<?php
/**
 * ================================================================
 * Sales Return & Product Exchange Module — উন্নত সংস্করণ (v2)
 * ================================================================
 * পুরনো, সাধারণ sale_returns টেবিলের পাশাপাশি এই সম্পূর্ণ নতুন,
 * উন্নত সিস্টেম — Approval Workflow, Customer Ledger সংযুক্তি,
 * Wholesale/Retail ভাগ, ও সম্পূর্ণ অডিট ট্রেইলসহ।
 */

function ensure_return_v2_schema($db) {
    $db->query("CREATE TABLE IF NOT EXISTS sale_returns_v2 (
        id INT AUTO_INCREMENT PRIMARY KEY,
        return_no VARCHAR(30) NOT NULL UNIQUE,
        sale_id INT NULL,
        customer_id INT NULL,
        customer_name VARCHAR(100) NULL,
        sales_type VARCHAR(20) NOT NULL DEFAULT 'retail',
        return_type VARCHAR(30) NOT NULL DEFAULT 'invoice_based',
        return_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        previous_due DECIMAL(14,2) NULL DEFAULT NULL,
        balance_after DECIMAL(14,2) NULL DEFAULT NULL,
        exchange_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        customer_adjustment DECIMAL(14,2) NOT NULL DEFAULT 0,
        payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
        account_id INT NULL,
        reason VARCHAR(255) NULL,
        refund_method VARCHAR(20) NOT NULL DEFAULT 'customer_balance',
        discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        notes VARCHAR(500) NULL,
        return_date DATE NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        created_by INT NULL,
        approved_by INT NULL,
        approved_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        INDEX idx_status (status),
        INDEX idx_customer (customer_id),
        INDEX idx_sale (sale_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ================= আগে থেকে টেবিল তৈরি থাকলেও নতুন কলাম যোগ করা (নিরাপদ প্যাটার্ন) =================
    $sr_cols = [];
    $sr_res = $db->query("SHOW COLUMNS FROM sale_returns_v2");
    if ($sr_res) {
        while ($row = $sr_res->fetch_assoc()) { $sr_cols[] = $row['Field']; }
    }
    if (!in_array('refund_method', $sr_cols, true)) {
        @$db->query("ALTER TABLE sale_returns_v2 ADD COLUMN refund_method VARCHAR(20) NOT NULL DEFAULT 'customer_balance' AFTER reason");
    }
    if (!in_array('discount_amount', $sr_cols, true)) {
        @$db->query("ALTER TABLE sale_returns_v2 ADD COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER refund_method");
    }
    if (!in_array('notes', $sr_cols, true)) {
        @$db->query("ALTER TABLE sale_returns_v2 ADD COLUMN notes VARCHAR(500) NULL AFTER discount_amount");
    }
    if (!in_array('return_date', $sr_cols, true)) {
        @$db->query("ALTER TABLE sale_returns_v2 ADD COLUMN return_date DATE NULL AFTER notes");
        // পুরনো এন্ট্রিগুলোর জন্য created_at থেকে ডিফল্ট return_date বসিয়ে দেওয়া
        @$db->query("UPDATE sale_returns_v2 SET return_date = DATE(created_at) WHERE return_date IS NULL");
    }
    if (!in_array('previous_due', $sr_cols, true)) {
        @$db->query('ALTER TABLE sale_returns_v2 ADD COLUMN previous_due DECIMAL(14,2) NULL DEFAULT NULL AFTER return_amount');
    }
    if (!in_array('balance_after', $sr_cols, true)) {
        @$db->query('ALTER TABLE sale_returns_v2 ADD COLUMN balance_after DECIMAL(14,2) NULL DEFAULT NULL AFTER previous_due');
    }

    $db->query("CREATE TABLE IF NOT EXISTS sale_return_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        return_id INT NOT NULL,
        product_id INT NOT NULL,
        original_qty DECIMAL(10,2) NULL,
        returned_qty DECIMAL(10,2) NOT NULL,
        unit_price DECIMAL(12,2) NOT NULL,
        discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
        line_reason VARCHAR(100) NULL,
        line_total DECIMAL(14,2) NOT NULL,
        is_replacement TINYINT(1) NOT NULL DEFAULT 0,
        INDEX idx_return (return_id),
        INDEX idx_product (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $sri_cols = [];
    $sri_res = $db->query("SHOW COLUMNS FROM sale_return_items");
    if ($sri_res) {
        while ($row = $sri_res->fetch_assoc()) { $sri_cols[] = $row['Field']; }
    }
    if (!in_array('discount_pct', $sri_cols, true)) {
        @$db->query("ALTER TABLE sale_return_items ADD COLUMN discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER unit_price");
    }
    if (!in_array('line_reason', $sri_cols, true)) {
        @$db->query("ALTER TABLE sale_return_items ADD COLUMN line_reason VARCHAR(100) NULL AFTER discount_pct");
    }
}

/* স্বয়ংক্রিয় রিটার্ন নম্বর — RET-20260904-0001 প্যাটার্নে */
function ame_return_statement_desc($db, int $returnId, string $ref, float $amount, bool $isEnglish = false): string
{
    $bits = [];
    $stmt = @$db->prepare(
        'SELECT p.name, sri.returned_qty, sri.line_total
         FROM sale_return_items sri
         JOIN products p ON p.id = sri.product_id
         WHERE sri.return_id = ? AND sri.is_replacement = 0
         ORDER BY sri.id ASC'
    );
    if ($stmt) {
        $stmt->bind_param('i', $returnId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $bits[] = trim((string)$row['name']) . ' ×' . rtrim(rtrim(number_format((float)$row['returned_qty'], 2, '.', ''), '0'), '.');
        }
        $stmt->close();
    }
    $head = $isEnglish ? 'Product return ' : 'পণ্য ফেরত ';
    $desc = $head . $ref;
    if ($bits) {
        $desc .= ' · ' . implode(', ', $bits);
    }
    $desc .= $isEnglish
        ? ' · credit −৳' . number_format($amount, 2)
        : ' · ক্রেডিট −৳' . number_format($amount, 2);
    return function_exists('mb_substr') ? mb_substr($desc, 0, 240) : substr($desc, 0, 240);
}

function generate_return_no($db) {
    $today = date('Ymd');
    $prefix = 'RET-' . $today . '-';
    $stmt = $db->prepare("SELECT COUNT(*) c FROM sale_returns_v2 WHERE return_no LIKE ?");
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    return $prefix . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
}

/*
 * ইতিমধ্যে কোনো ইনভয়েসের কোন পণ্য কত পরিমাণ ফেরত হয়ে গেছে (approved/completed
 * স্ট্যাটাসের রিটার্ন থেকে) — যাতে বিক্রিত পরিমাণের বেশি ফেরত নেওয়া না যায়।
 */
function get_already_returned_qty($db, $sale_id, $product_id) {
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(sri.returned_qty), 0) AS total
         FROM sale_return_items sri
         JOIN sale_returns_v2 sr ON sri.return_id = sr.id
         WHERE sr.sale_id = ? AND sri.product_id = ? AND sri.is_replacement = 0
           AND sr.status IN ('approved', 'completed')"
    );
    $stmt->bind_param('ii', $sale_id, $product_id);
    $stmt->execute();
    return (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
}

/**
 * ফেরত UI-তে ব্যবহারকারী যা লেখে (মেমো #, বিক্রয় আইডি, পূর্ণ INV-...) সেটি পরিষ্কার করা।
 */
function ame_return_v2_normalize_sale_reference(string $raw): string
{
    $ref = trim($raw);
    $ref = preg_replace('/^\s*#+\s*/', '', $ref) ?? $ref;
  return trim($ref);
}

/**
 * বিক্রয় মেমো / ইনভয়েস রেফারেন্স থেকে সম্পন্ন বিক্রয় খুঁজে আনা (রিটার্ন v2 সার্চ)।
 */
function ame_return_v2_resolve_sale_by_reference(mysqli $db, string $raw): ?array
{
    $ref = ame_return_v2_normalize_sale_reference($raw);
    if ($ref === '') {
        return null;
    }

    $select = "SELECT s.id, s.invoice_number, s.sale_date, s.customer_id, s.customer_type,
            c.name AS customer_name, c.phone AS customer_phone, c.customer_code, c.receivable_balance
     FROM sales s
     LEFT JOIN customers c ON s.customer_id = c.id
     WHERE s.status = 'completed'";

    $fetchById = static function (int $saleId) use ($db, $select): ?array {
        if ($saleId <= 0) {
            return null;
        }
        $stmt = $db->prepare($select . ' AND s.id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $saleId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    };

    $fetchByInvoice = static function (string $invoice) use ($db, $select): ?array {
        $invoice = trim($invoice);
        if ($invoice === '') {
            return null;
        }
        $stmt = $db->prepare($select . ' AND UPPER(TRIM(s.invoice_number)) = UPPER(?) LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $invoice);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    };

    if (ctype_digit($ref)) {
        $row = $fetchById((int)$ref);
        if ($row) {
            return $row;
        }
    }

    $row = $fetchByInvoice($ref);
    if ($row) {
        return $row;
    }

    if (preg_match('/^INV[-\s]?(\d+)\s*$/i', $ref, $m)) {
        $row = $fetchById((int)$m[1]);
        if ($row) {
            return $row;
        }
    }

    if (preg_match('/INV/i', $ref) && preg_match('/(\d+)\s*$/', $ref, $m)) {
        $suffixId = (int)ltrim($m[1], '0');
        if ($suffixId <= 0 && $m[1] !== '') {
            $suffixId = (int)$m[1];
        }
        if ($suffixId > 0) {
            $row = $fetchById($suffixId);
            if ($row) {
                return $row;
            }
        }
    }

    $compact = strtoupper(preg_replace('/\s+/', '', $ref) ?? $ref);
    if (strlen($compact) >= 6) {
        $stmt = $db->prepare(
            $select . " AND REPLACE(UPPER(TRIM(s.invoice_number)), ' ', '') = ? LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param('s', $compact);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return $row;
            }
        }
    }

    return null;
}

/*
 * একটা রিটার্ন "সম্পন্ন" (completed) করা — এখানেই আসল ইন্টিগ্রেশন ঘটে:
 *  ১. স্টক আপডেট (ফেরত পণ্য +, বদলি পণ্য -)
 *  ২. কাস্টমার লেজার আপডেট (বকেয়া কমা/বাড়া)
 *  ৩. স্ট্যাটাস 'completed'-এ পরিবর্তন
 * এই ফাংশন একটা DB ট্রানজেকশনের ভেতরে কল করতে হবে (কলিং কোডে)।
 */
function ame_return_is_registered_invoice(bool $isManual, int $customerId): bool
{
    return !$isManual && $customerId > 0;
}

function ame_return_due_after(float $previousDue, float $returnAmount): float
{
    return round(round($previousDue, 2) - round(max(0, $returnAmount), 2), 2);
}

function ame_return_customer_due($db, int $customerId): float
{
    if ($customerId <= 0) {
        return 0.0;
    }
    if (!function_exists('ame_ledger_net')) {
        $ledgerPath = dirname(__DIR__) . '/modules/customers/customer_ledger_functions.php';
        if (is_file($ledgerPath)) {
            require_once $ledgerPath;
        }
    }
    if (function_exists('ame_ledger_net')) {
        return round((float)ame_ledger_net($db, $customerId), 2);
    }
    $stmt = @$db->prepare('SELECT receivable_balance FROM customers WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return 0.0;
    }
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return round((float) ($row['receivable_balance'] ?? 0), 2);
}

/**
 * রেজিস্টার ক্রেতার সম্পন্ন মেমো — ফেরতযোগ্য পণ্য সহ (রিটার্ন মডিউল সার্চ)।
 *
 * @return array{customer:array,balance:float,memos:array<int,array<string,mixed>>}
 */
function ame_return_v2_list_customer_memos(mysqli $db, int $customerId, int $limit = 50): array
{
    ensure_return_v2_schema($db);
    $empty = ['customer' => [], 'balance' => 0.0, 'memos' => []];
    if ($customerId <= 0) {
        return $empty;
    }

    $stmt = $db->prepare(
        'SELECT id, name, phone, customer_code, receivable_balance FROM customers WHERE id = ? AND is_customer = 1 LIMIT 1'
    );
    if (!$stmt) {
        return $empty;
    }
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $cust = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$cust) {
        return $empty;
    }

    $balance = ame_return_customer_due($db, $customerId);
    $limit = max(1, min(80, $limit));

    $salesStmt = $db->prepare(
        "SELECT id, invoice_number, sale_date, completed_at, net_payable, paid_amount, due_amount, customer_type
         FROM sales
         WHERE status = 'completed' AND customer_id = ?
         ORDER BY COALESCE(completed_at, sale_date, created_at) DESC
         LIMIT ?"
    );
    if (!$salesStmt) {
        return [
            'customer' => $cust,
            'balance' => $balance,
            'memos' => [],
        ];
    }
    $salesStmt->bind_param('ii', $customerId, $limit);
    $salesStmt->execute();
    $sales = $salesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $salesStmt->close();

    $memos = [];
    foreach ($sales as $sale) {
        $saleId = (int)$sale['id'];
        $itemsStmt = $db->prepare(
            'SELECT product_id, qty FROM sale_items WHERE sale_id = ? AND product_id IS NOT NULL AND product_id > 0'
        );
        if (!$itemsStmt) {
            continue;
        }
        $itemsStmt->bind_param('i', $saleId);
        $itemsStmt->execute();
        $itemRows = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $itemsStmt->close();

        $returnableQty = 0.0;
        $returnableLines = 0;
        foreach ($itemRows as $it) {
            $pid = (int)($it['product_id'] ?? 0);
            $sold = (float)($it['qty'] ?? 0);
            if ($pid <= 0 || $sold <= 0) {
                continue;
            }
            $already = get_already_returned_qty($db, $saleId, $pid);
            $left = round($sold - $already, 4);
            if ($left > 0.0009) {
                $returnableLines++;
                $returnableQty += $left;
            }
        }

        $inv = trim((string)($sale['invoice_number'] ?? ''));
        if ($inv === '') {
            $inv = 'INV-' . $saleId;
        }
        $when = $sale['completed_at'] ?? $sale['sale_date'] ?? '';
        $memos[] = [
            'sale_id' => $saleId,
            'invoice_number' => $inv,
            'search_ref' => $inv,
            'sale_date' => $when ? date('d-m-Y', strtotime((string)$when)) : '',
            'net_payable' => round((float)($sale['net_payable'] ?? 0), 2),
            'due_amount' => round((float)($sale['due_amount'] ?? 0), 2),
            'returnable_lines' => $returnableLines,
            'returnable_qty' => round($returnableQty, 2),
            'can_return' => $returnableLines > 0,
            'sales_type' => (strtolower((string)($sale['customer_type'] ?? '')) === 'wholesale') ? 'wholesale' : 'retail',
        ];
    }

    return [
        'customer' => [
            'id' => (int)$cust['id'],
            'name' => (string)$cust['name'],
            'phone' => (string)($cust['phone'] ?? ''),
            'code' => (string)($cust['customer_code'] ?? ''),
        ],
        'balance' => $balance,
        'memos' => $memos,
    ];
}

/**
 * রেজিস্টার ক্রেতার সব মেমো থেকে ফেরতযোগ্য পণ্য লাইন (নাম দিয়ে ফিল্টার)।
 *
 * @return list<array<string,mixed>>
 */
function ame_return_v2_customer_product_lines(mysqli $db, int $customerId, string $productQ = '', int $limit = 60): array
{
    ensure_return_v2_schema($db);
    if ($customerId <= 0) {
        return [];
    }
    $limit = max(1, min(120, $limit));
    $needle = mb_strtolower(trim($productQ));

    $stmt = $db->prepare(
        "SELECT s.id AS sale_id, s.invoice_number, s.completed_at, s.sale_date,
                si.product_id,
                COALESCE(NULLIF(TRIM(si.item_name), ''), p.name, CONCAT('পণ্য #', si.product_id)) AS product_name,
                si.qty AS sold_qty, si.sale_price AS unit_price,
                p.sale_price AS retail_price, p.wholesale_price, p.dealer_price,
                p.barcode, p.sku
         FROM sales s
         INNER JOIN sale_items si ON si.sale_id = s.id
         LEFT JOIN products p ON p.id = si.product_id
         WHERE s.status = 'completed'
           AND s.customer_id = ?
           AND si.product_id IS NOT NULL AND si.product_id > 0
         ORDER BY COALESCE(s.completed_at, s.sale_date, s.created_at) DESC, si.id DESC
         LIMIT 500"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $lines = [];
    foreach ($rows as $row) {
        $saleId = (int)($row['sale_id'] ?? 0);
        $productId = (int)($row['product_id'] ?? 0);
        if ($saleId <= 0 || $productId <= 0) {
            continue;
        }
        $sold = (float)($row['sold_qty'] ?? 0);
        $already = get_already_returned_qty($db, $saleId, $productId);
        $remaining = round($sold - $already, 4);
        if ($remaining <= 0.0009) {
            continue;
        }
        $name = trim((string)($row['product_name'] ?? ''));
        $barcode = trim((string)($row['barcode'] ?? ''));
        $sku = trim((string)($row['sku'] ?? ''));
        if ($needle !== '') {
            $hay = mb_strtolower($name . ' ' . $barcode . ' ' . $sku . ' ' . $productId);
            if (!str_contains($hay, $needle)) {
                continue;
            }
        }
        $inv = trim((string)($row['invoice_number'] ?? ''));
        if ($inv === '') {
            $inv = 'INV-' . $saleId;
        }
        $when = $row['completed_at'] ?? $row['sale_date'] ?? '';
        $lines[] = [
            'sale_id' => $saleId,
            'invoice_number' => $inv,
            'search_ref' => $inv,
            'sale_date' => $when ? date('d-m-Y', strtotime((string)$when)) : '',
            'product_id' => $productId,
            'product_name' => $name,
            'sold_qty' => $sold,
            'already_returned' => $already,
            'remaining' => $remaining,
            'unit_price' => round((float)($row['unit_price'] ?? 0), 2),
            'retail_price' => round((float)($row['retail_price'] ?? $row['unit_price'] ?? 0), 2),
            'wholesale_price' => round((float)($row['wholesale_price'] ?? 0), 2),
            'dealer_price' => round((float)($row['dealer_price'] ?? 0), 2),
        ];
        if (count($lines) >= $limit) {
            break;
        }
    }

    return $lines;
}

function ame_return_store_due_snapshot($db, int $returnId, float $previous, float $after): void
{
    if ($returnId <= 0) {
        return;
    }
    $stmt = @$db->prepare('UPDATE sale_returns_v2 SET previous_due = ?, balance_after = ? WHERE id = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ddi', $previous, $after, $returnId);
    $stmt->execute();
    $stmt->close();
}

function complete_return_transaction($db, $return_id, $performed_by, $is_english = false) {
    ensure_return_v2_schema($db);
    if (!function_exists('account_debit')) {
        require_once __DIR__ . '/account_functions.php';
    }
    $stmt = $db->prepare("SELECT * FROM sale_returns_v2 WHERE id = ? FOR UPDATE");
    $stmt->bind_param('i', $return_id);
    $stmt->execute();
    $ret = $stmt->get_result()->fetch_assoc();

    if (!$ret) {
        throw new RuntimeException($is_english ? 'Return not found.' : 'রিটার্ন খুঁজে পাওয়া যায়নি।');
    }
    if ($ret['status'] !== 'approved') {
        throw new RuntimeException($is_english ? 'Only approved returns can be completed.' : 'শুধু অনুমোদিত রিটার্নই সম্পন্ন করা যাবে।');
    }

    $items_stmt = $db->prepare("SELECT * FROM sale_return_items WHERE return_id = ?");
    $items_stmt->bind_param('i', $return_id);
    $items_stmt->execute();
    $items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($items as $item) {
            $qty = (float)$item['returned_qty'];
            $unit_id = (int)($item['unit_id'] ?? 0);
            if ($unit_id <= 0 && !empty($ret['sale_id']) && is_file(__DIR__ . '/unit_engine.php')) {
                require_once __DIR__ . '/unit_engine.php';
                $find = $db->prepare('SELECT unit_id FROM sale_items WHERE sale_id=? AND product_id=? AND unit_id IS NOT NULL ORDER BY id DESC LIMIT 1');
                $sale_id = (int)$ret['sale_id'];
                $product_id = (int)$item['product_id'];
                $find->bind_param('ii', $sale_id, $product_id);
                $find->execute();
                $found = $find->get_result()->fetch_assoc();
                $find->close();
                $unit_id = (int)($found['unit_id'] ?? 0);
            }
            if ($unit_id > 0 && is_file(__DIR__ . '/unit_engine.php')) {
                require_once __DIR__ . '/unit_engine.php';
                $qty = (float)ame_unit_to_base($db, (int)$item['product_id'], (string)$item['returned_qty'], $unit_id);
            }
            if ($item['is_replacement']) {
            $upd = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $upd->bind_param('di', $qty, $item['product_id']);
            $upd->execute();
        } else {
            $upd = $db->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            $upd->bind_param('di', $qty, $item['product_id']);
            $upd->execute();
        }
            if ($unit_id > 0 && function_exists('ame_unit_move')) {
                $signed = $item['is_replacement'] ? ame_unit_num((string)(-1 * $qty)) : ame_unit_num((string)$qty);
                ame_unit_move($db, (int)$item['product_id'], $signed, [
                    'kind' => 'sale_return',
                    'entered_qty' => $item['returned_qty'],
                    'unit_id' => $unit_id,
                    'ref_type' => 'sale_return',
                    'ref_id' => (int)$return_id,
                ], false, false);
            }
    }

    // ================= রিফান্ড/কাস্টমার লেজার ইন্টিগ্রেশন =================
    $ref_no = $ret['return_no'];
    $refund_method = $ret['refund_method'] ?? 'customer_balance';

    if ($ret['return_type'] === 'exchange') {
        if ($ret['customer_id']) {
            // এক্সচেঞ্জ: customer_adjustment ধনাত্মক হলে গ্রাহক বাড়তি পাওনা (debit),
            // ঋণাত্মক হলে গ্রাহকের বকেয়া কমবে (credit)
            $adj = (float)$ret['customer_adjustment'];
            if ($adj > 0) {
                add_ledger_entry(
                    $db, $ret['customer_id'],
                    $is_english ? 'Exchange — Extra Payable' : 'এক্সচেঞ্জ — বাড়তি প্রদেয়',
                    $ref_no,
                    $is_english ? 'Exchange adjustment' : 'এক্সচেঞ্জ সমন্বয়',
                    $adj, 0
                );
            } elseif ($adj < 0) {
                add_ledger_entry(
                    $db, $ret['customer_id'],
                    $is_english ? 'Exchange — Customer Credit' : 'এক্সচেঞ্জ — গ্রাহক ক্রেডিট',
                    $ref_no,
                    $is_english ? 'Exchange adjustment' : 'এক্সচেঞ্জ সমন্বয়',
                    0, abs($adj)
                );
            }
        }
    } elseif (in_array($refund_method, ['cash_refund', 'bank_transfer', 'mobile_banking'], true) && $ret['account_id']) {
        ame_require_active_account($db, (int)$ret['account_id']);
        // নগদ/ব্যাংক ফেরত — কাস্টমার নিবন্ধিত থাকুক বা Retail/walk-in হোক, টাকা
        // সত্যিই অ্যাকাউন্ট থেকে বেরিয়ে যাচ্ছে
        account_debit(
            $ret['account_id'], (float)$ret['return_amount'], 'sales_return', $return_id,
            $is_english ? 'Sales return cash refund (' . $ref_no . ')' : 'বিক্রয় ফেরত — নগদ পরিশোধ (' . $ref_no . ')',
            true
        );
    } elseif ($ret['customer_id']) {
        // গ্রাহক ব্যালেন্স সমন্বয় — কাস্টমারের বকেয়া থেকে ফেরতের পরিমাণ বাদ (credit)
        if (!function_exists('add_ledger_entry')) {
            $ledgerFile = __DIR__ . '/../modules/customers/customer_ledger_functions.php';
            if (is_file($ledgerFile)) {
                require_once $ledgerFile;
            }
        }
        $amount = (float)$ret['return_amount'];
        $prevDue = ame_return_customer_due($db, (int) $ret['customer_id']);
        add_ledger_entry(
            $db, $ret['customer_id'],
            $is_english ? 'Sales Return' : 'বিক্রয় ফেরত',
            $ref_no,
            ame_return_statement_desc($db, (int)$return_id, (string)$ref_no, $amount, $is_english),
            0, $amount
        );
        $afterDue = ame_return_customer_due($db, (int) $ret['customer_id']);
        ame_return_store_due_snapshot($db, (int) $return_id, $prevDue, $afterDue);
    }

    $upd_status = $db->prepare(
        "UPDATE sale_returns_v2 SET status = 'completed', updated_at = NOW() WHERE id = ?"
    );
    $upd_status->bind_param('i', $return_id);
    $upd_status->execute();

    log_activity('Return Completed', 'Returns', $ret['return_no']);

    return true;
}

/**
 * হাতে একটি পণ্য ফেরত — স্টক বাড়ে complete_return_transaction দিয়ে।
 * একই টোকেন দ্বিতীয়বার স্টক বাড়ায় না। ব্যর্থ হলে লেনদেন ফিরে যায়।
 */
function ame_post_hand_product_return(mysqli $db, array $in): array
{
    $productId = (int)($in['product_id'] ?? 0);
    $qty = round((float)($in['qty'] ?? 0), 2);
    $note = trim((string)($in['note'] ?? ''));
    $note = function_exists('mb_substr') ? mb_substr($note, 0, 255) : substr($note, 0, 255);
    $customerId = (int)($in['customer_id'] ?? 0);
    $token = substr(trim((string)($in['idempotency_token'] ?? '')), 0, 80);
    if ($productId <= 0 || $qty <= 0) {
        throw new RuntimeException('পণ্য ও পরিমাণ দিন।');
    }
    if ($token === '') {
        throw new RuntimeException('ফেরতের টোকেন পাওয়া যায়নি। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।');
    }

    if (function_exists('ensure_integrity_schema')) {
        ensure_integrity_schema($db);
    }
    ensure_return_v2_schema($db);
    if ($customerId > 0 && !function_exists('add_ledger_entry')) {
        $ledger = __DIR__ . '/../modules/customers/customer_ledger_functions.php';
        if (is_file($ledger)) {
            require_once $ledger;
        }
    }

    if (!$db->begin_transaction()) {
        throw new RuntimeException('পণ্য ফেরত রাখা যায়নি। স্টক আগের মতোই আছে।');
    }
    try {
        $existing = 0;
        if (!claim_idempotency_token($db, 'hand_product_return', $token, $existing)) {
            $db->rollback();
            return [
                'ok' => true,
                'replay' => true,
                'id' => $existing,
                'message' => 'এই ফেরত আগেই রাখা হয়েছে।',
            ];
        }

        $prodStmt = $db->prepare('SELECT id, name, sale_price, stock FROM products WHERE id = ? FOR UPDATE');
        if (!$prodStmt) {
            throw new RuntimeException('পণ্য ফেরত রাখা যায়নি। স্টক আগের মতোই আছে।');
        }
        $prodStmt->bind_param('i', $productId);
        $prodStmt->execute();
        $prod = $prodStmt->get_result()->fetch_assoc();
        $prodStmt->close();
        if (!$prod) {
            throw new RuntimeException('পণ্য খুঁজে পাওয়া যায়নি।');
        }
        $before = (float)$prod['stock'];
        $unit = round(max(0, (float)$prod['sale_price']), 2);
        $total = round($qty * $unit, 2);

        $customerName = '';
        if ($customerId > 0) {
            $cStmt = $db->prepare('SELECT id, name FROM customers WHERE id = ? LIMIT 1');
            if (!$cStmt) {
                throw new RuntimeException('পণ্য ফেরত রাখা যায়নি। স্টক আগের মতোই আছে।');
            }
            $cStmt->bind_param('i', $customerId);
            $cStmt->execute();
            $cust = $cStmt->get_result()->fetch_assoc();
            $cStmt->close();
            if (!$cust) {
                throw new RuntimeException('কাস্টমার খুঁজে পাওয়া যায়নি।');
            }
            $customerName = (string)$cust['name'];
        }

        $returnNo = generate_return_no($db);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $reason = $note !== '' ? $note : 'হাতে পণ্য ফেরত';
        $customerIdBind = $customerId > 0 ? $customerId : null;
        $stmt = $db->prepare(
            "INSERT INTO sale_returns_v2
                (return_no, customer_id, customer_name, sales_type, return_type, return_amount, reason, refund_method, notes, return_date, status, created_by)
             VALUES (?, ?, ?, 'retail', 'manual', ?, ?, 'customer_balance', ?, CURDATE(), 'approved', ?)"
        );
        if (!$stmt) {
            throw new RuntimeException('পণ্য ফেরত রাখা যায়নি। স্টক আগের মতোই আছে।');
        }
        $stmt->bind_param('sisdssi', $returnNo, $customerIdBind, $customerName, $total, $reason, $note, $userId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('পণ্য ফেরত রাখা যায়নি। স্টক আগের মতোই আছে।');
        }
        $returnId = (int)$stmt->insert_id;
        $stmt->close();

        $item = $db->prepare(
            'INSERT INTO sale_return_items (return_id, product_id, returned_qty, unit_price, line_total, is_replacement)
             VALUES (?, ?, ?, ?, ?, 0)'
        );
        if (!$item) {
            throw new RuntimeException('পণ্য ফেরত রাখা যায়নি। স্টক আগের মতোই আছে।');
        }
        $item->bind_param('iiddd', $returnId, $productId, $qty, $unit, $total);
        if (!$item->execute()) {
            $item->close();
            throw new RuntimeException('পণ্য ফেরত রাখা যায়নি। স্টক আগের মতোই আছে।');
        }
        $item->close();

        $isEnglish = (($_SESSION['app_lang'] ?? 'bn') === 'en');
        complete_return_transaction($db, $returnId, $userId, $isEnglish);

        $afterStmt = $db->prepare('SELECT stock FROM products WHERE id = ?');
        if (!$afterStmt) {
            throw new RuntimeException('পণ্য ফেরত রাখা যায়নি। স্টক আগের মতোই আছে।');
        }
        $afterStmt->bind_param('i', $productId);
        $afterStmt->execute();
        $after = (float)($afterStmt->get_result()->fetch_assoc()['stock'] ?? $before);
        $afterStmt->close();
        if (abs($after - ($before + $qty)) > 0.009) {
            throw new RuntimeException('পণ্য ফেরত রাখা যায়নি। স্টক আগের মতোই আছে।');
        }

        if (function_exists('finish_idempotency_token')) {
            finish_idempotency_token($db, 'hand_product_return', $token, $returnId);
        }
        if (!$db->commit()) {
            throw new RuntimeException('পণ্য ফেরত রাখা যায়নি। স্টক আগের মতোই আছে।');
        }
        return [
            'ok' => true,
            'id' => $returnId,
            'return_no' => $returnNo,
            'message' => 'পণ্য ফেরত নেওয়া হয়েছে। স্টক বেড়েছে।',
        ];
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
