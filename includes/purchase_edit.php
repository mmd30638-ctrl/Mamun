<?php
/**
 * 10.98.346 — Editable Purchase Invoice: update/remove/header + revision history.
 * Line remove is UPDATE (qty=0), never SQL DELETE of product codes.
 */

if (!function_exists('ame_purchase_append_item')) {
    $lib = __DIR__ . '/purchase_append.php';
    if (is_file($lib)) {
        require_once $lib;
    }
}

if (!function_exists('ame_purchase_ensure_revisions')) {
    function ame_purchase_ensure_revisions(mysqli $db): void
    {
        @$db->query(
            'CREATE TABLE IF NOT EXISTS ame_purchase_revisions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                invoice_id INT NOT NULL,
                action VARCHAR(40) NOT NULL,
                summary TEXT NULL,
                snapshot LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY invoice_id (invoice_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }
}

if (!function_exists('ame_purchase_log_revision')) {
    function ame_purchase_log_revision(mysqli $db, int $invoiceId, string $action, string $summary, array $snapshot = []): void
    {
        ame_purchase_ensure_revisions($db);
        ame_purchase_insert($db, 'ame_purchase_revisions', [
            'invoice_id' => $invoiceId,
            'action' => $action,
            'summary' => $summary,
            'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

if (!function_exists('ame_purchase_list_revisions')) {
    function ame_purchase_list_revisions(mysqli $db, int $invoiceId): array
    {
        ame_purchase_ensure_revisions($db);
        $out = [];
        $stmt = $db->prepare('SELECT id, action, summary, snapshot, created_at FROM ame_purchase_revisions WHERE invoice_id = ? ORDER BY id DESC LIMIT 40');
        if (!$stmt) {
            return $out;
        }
        ame_purchase_bind($stmt, 'i', [$invoiceId]);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $snap = json_decode((string) ($row['snapshot'] ?? ''), true);
                $out[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'action' => (string) ($row['action'] ?? ''),
                    'summary' => (string) ($row['summary'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'snapshot' => is_array($snap) ? $snap : [],
                ];
            }
        }
        $stmt->close();
        return $out;
    }
}

if (!function_exists('ame_purchase_apply_money')) {
    function ame_purchase_apply_money(mysqli $db, array $invoice, float $delta, int $pid, float $qtyDelta): void
    {
        if (abs($delta) < 0.0001 && abs($qtyDelta) < 0.0001) {
            return;
        }
        $map = ame_purchase_map($db);
        $head = $map['head'];
        $headCols = $map['headCols'];
        $headIdCol = ame_col($headCols, ['id', 'purchase_id', 'invoice_id', 'pid']) ?? 'id';
        $headPk = (int) ($invoice[$headIdCol] ?? 0);
        $sets = [];
        $types = '';
        $vals = [];
        foreach ([['subtotal', 'sub_total', 'item_total'], ['grand_total', 'net_total', 'total_amount', 'total', 'amount', 'payable'], ['due', 'due_amount', 'balance', 'payable_due']] as $names) {
            $col = ame_col($headCols, $names);
            if ($col) {
                $sets[] = ame_purchase_ident($col) . ' = IFNULL(' . ame_purchase_ident($col) . ',0) + ?';
                $types .= 'd';
                $vals[] = $delta;
            }
        }
        $updCol = ame_col($headCols, ['updated_at', 'modified_at']);
        if ($updCol) {
            $sets[] = ame_purchase_ident($updCol) . ' = NOW()';
        }
        if ($sets && $headPk > 0) {
            $vals[] = $headPk;
            $types .= 'i';
            ame_purchase_exec(
                $db,
                'UPDATE ' . ame_purchase_ident($head) . ' SET ' . implode(', ', $sets)
                . ' WHERE ' . ame_purchase_ident($headIdCol) . ' = ?',
                $types,
                $vals
            );
        }
        if ($pid > 0 && abs($qtyDelta) >= 0.0001 && $map['product']) {
            $pCols = $map['productCols'];
            $stockCol = ame_col($pCols, ['stock', 'quantity', 'qty', 'current_stock']);
            $pIdCol = ame_col($pCols, ['id', 'product_id']);
            if ($stockCol && $pIdCol) {
                ame_purchase_exec(
                    $db,
                    'UPDATE ' . ame_purchase_ident($map['product']) . ' SET ' . ame_purchase_ident($stockCol)
                    . ' = IFNULL(' . ame_purchase_ident($stockCol) . ',0) + ? WHERE ' . ame_purchase_ident($pIdCol) . ' = ?',
                    'di',
                    [$qtyDelta, $pid]
                );
            }
        }
        $partyCol = ame_col($headCols, ['supplier_id', 'vendor_id', 'customer_id', 'party_id']);
        $partyId = $partyCol ? (int) ($invoice[$partyCol] ?? 0) : 0;
        if ($partyId > 0 && abs($delta) >= 0.0001) {
            foreach (['suppliers', 'customers', 'vendors'] as $pt) {
                if (!ame_db_table_exists($db, $pt)) {
                    continue;
                }
                $pc = ame_db_columns($db, $pt);
                $due = ame_col($pc, ['total_due', 'due', 'balance', 'payable']);
                $idc = ame_col($pc, ['id']);
                if ($due && $idc) {
                    ame_purchase_exec(
                        $db,
                        'UPDATE ' . ame_purchase_ident($pt) . ' SET ' . ame_purchase_ident($due)
                        . ' = IFNULL(' . ame_purchase_ident($due) . ',0) + ? WHERE ' . ame_purchase_ident($idc) . ' = ?',
                        'di',
                        [$delta, $partyId]
                    );
                    break;
                }
            }
        }
    }
}

if (!function_exists('ame_purchase_list_invoices')) {
    function ame_purchase_list_invoices(mysqli $db, int $limit = 40): array
    {
        $map = ame_purchase_map($db);
        $head = $map['head'];
        $cols = $map['headCols'];
        if ($head === null) {
            return [];
        }
        $idCol = ame_col($cols, ['id', 'purchase_id', 'invoice_id', 'pid']) ?? 'id';
        $noCol = ame_col($cols, ['invoice_no', 'invoice_number', 'memo_no', 'purchase_no', 'bill_no', 'number']);
        $dateCol = ame_col($cols, ['date', 'invoice_date', 'purchase_date', 'created_at']);
        $totalCol = ame_col($cols, ['grand_total', 'net_total', 'total_amount', 'total', 'amount']);
        $partyCol = ame_col($cols, ['supplier_id', 'vendor_id', 'customer_id', 'party_id']);
        $sel = [ame_purchase_ident($idCol) . ' AS id'];
        if ($noCol) {
            $sel[] = ame_purchase_ident($noCol) . ' AS number';
        }
        if ($dateCol) {
            $sel[] = ame_purchase_ident($dateCol) . ' AS date';
        }
        if ($totalCol) {
            $sel[] = ame_purchase_ident($totalCol) . ' AS total';
        }
        if ($partyCol) {
            $sel[] = ame_purchase_ident($partyCol) . ' AS supplier_id';
        }
        $limit = max(1, min(80, $limit));
        $sql = 'SELECT ' . implode(',', $sel) . ' FROM ' . ame_purchase_ident($head)
            . ' ORDER BY ' . ame_purchase_ident($idCol) . ' DESC LIMIT ' . $limit;
        $res = @$db->query($sql);
        $out = [];
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $out[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'number' => (string) ($row['number'] ?? $row['id'] ?? ''),
                    'date' => (string) ($row['date'] ?? ''),
                    'total' => (float) ($row['total'] ?? 0),
                    'supplier_id' => (int) ($row['supplier_id'] ?? 0),
                ];
            }
            $res->free();
        }
        return $out;
    }
}

if (!function_exists('ame_purchase_list_suppliers')) {
    function ame_purchase_list_suppliers(mysqli $db, string $q = '', int $limit = 20): array
    {
        $out = [];
        foreach (['suppliers', 'vendors'] as $pt) {
            if (!ame_db_table_exists($db, $pt)) {
                continue;
            }
            $cols = ame_db_columns($db, $pt);
            $idc = ame_col($cols, ['id']);
            $name = ame_col($cols, ['name', 'supplier_name', 'company', 'title']);
            $phone = ame_col($cols, ['phone', 'mobile', 'contact']);
            $due = ame_col($cols, ['total_due', 'due', 'balance', 'payable']);
            if (!$idc || !$name) {
                continue;
            }
            $sel = [ame_purchase_ident($idc) . ' AS id', ame_purchase_ident($name) . ' AS name'];
            if ($phone) {
                $sel[] = ame_purchase_ident($phone) . ' AS phone';
            }
            if ($due) {
                $sel[] = ame_purchase_ident($due) . ' AS due';
            }
            $sql = 'SELECT ' . implode(',', $sel) . ' FROM ' . ame_purchase_ident($pt);
            $vals = [];
            $types = '';
            $q = trim($q);
            if ($q !== '') {
                $sql .= ' WHERE (' . ame_purchase_ident($name) . ' LIKE ?';
                $types .= 's';
                $vals[] = '%' . $q . '%';
                if ($phone) {
                    $sql .= ' OR ' . ame_purchase_ident($phone) . ' LIKE ?';
                    $types .= 's';
                    $vals[] = '%' . $q . '%';
                }
                $sql .= ')';
            }
            $sql .= ' ORDER BY ' . ame_purchase_ident($name) . ' ASC LIMIT ' . max(1, min(40, $limit));
            if ($types !== '') {
                $stmt = $db->prepare($sql);
                if ($stmt) {
                    ame_purchase_bind($stmt, $types, $vals);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res instanceof mysqli_result) {
                        while ($row = $res->fetch_assoc()) {
                            $out[] = [
                                'id' => (int) ($row['id'] ?? 0),
                                'name' => (string) ($row['name'] ?? ''),
                                'phone' => (string) ($row['phone'] ?? ''),
                                'due' => (float) ($row['due'] ?? 0),
                            ];
                        }
                    }
                    $stmt->close();
                }
            } else {
                $res = @$db->query($sql);
                if ($res instanceof mysqli_result) {
                    while ($row = $res->fetch_assoc()) {
                        $out[] = [
                            'id' => (int) ($row['id'] ?? 0),
                            'name' => (string) ($row['name'] ?? ''),
                            'phone' => (string) ($row['phone'] ?? ''),
                            'due' => (float) ($row['due'] ?? 0),
                        ];
                    }
                    $res->free();
                }
            }
            if ($out) {
                break;
            }
        }
        return $out;
    }
}

if (!function_exists('ame_purchase_list_items')) {
    function ame_purchase_list_items(mysqli $db, int $invoiceId): array
    {
        $map = ame_purchase_map($db);
        $invoice = ame_purchase_find($db, $invoiceId);
        if (!$invoice || $map['item'] === null) {
            return [];
        }
        $item = $map['item'];
        $cols = $map['itemCols'];
        $headCols = $map['headCols'];
        $headIdCol = ame_col($headCols, ['id', 'purchase_id', 'invoice_id', 'pid']) ?? 'id';
        $headPk = (int) ($invoice[$headIdCol] ?? $invoiceId);
        $fk = ame_col($cols, ['purchase_id', 'invoice_id', 'restock_id', 'buy_id', 'pid', 'purchase_invoice_id']) ?? 'purchase_id';
        $idCol = ame_col($cols, ['id', 'item_id']) ?? 'id';
        $nameCol = ame_col($cols, ['product_name', 'name', 'item_name', 'title']);
        $qtyCol = ame_col($cols, ['quantity', 'qty', 'qty_purchased', 'pcs']);
        $rateCol = ame_col($cols, ['purchase_price', 'buy_price', 'price', 'rate', 'unit_price', 'cost']);
        $discCol = ame_col($cols, ['discount', 'disc', 'line_discount']);
        $totCol = ame_col($cols, ['total', 'subtotal', 'amount', 'line_total', 'net_total']);
        $pidCol = ame_col($cols, ['product_id', 'item_id', 'pid']);
        $codeCol = ame_col($cols, ['sku', 'code', 'product_code', 'barcode']);
        $sel = [ame_purchase_ident($idCol) . ' AS id'];
        if ($nameCol) {
            $sel[] = ame_purchase_ident($nameCol) . ' AS name';
        }
        if ($qtyCol) {
            $sel[] = ame_purchase_ident($qtyCol) . ' AS qty';
        }
        if ($rateCol) {
            $sel[] = ame_purchase_ident($rateCol) . ' AS rate';
        }
        if ($discCol) {
            $sel[] = ame_purchase_ident($discCol) . ' AS discount';
        }
        if ($totCol) {
            $sel[] = ame_purchase_ident($totCol) . ' AS amount';
        }
        if ($pidCol && $pidCol !== $idCol) {
            $sel[] = ame_purchase_ident($pidCol) . ' AS product_id';
        }
        if ($codeCol) {
            $sel[] = ame_purchase_ident($codeCol) . ' AS code';
        }
        $stmt = $db->prepare(
            'SELECT ' . implode(',', $sel) . ' FROM ' . ame_purchase_ident($item)
            . ' WHERE ' . ame_purchase_ident($fk) . ' = ? ORDER BY ' . ame_purchase_ident($idCol)
        );
        if (!$stmt) {
            return [];
        }
        ame_purchase_bind($stmt, 'i', [$headPk]);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $qty = (float) ($row['qty'] ?? 0);
                $rate = (float) ($row['rate'] ?? 0);
                $disc = (float) ($row['discount'] ?? 0);
                $out[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? ''),
                    'code' => (string) ($row['code'] ?? ''),
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'qty' => $qty,
                    'rate' => $rate,
                    'discount' => $disc,
                    'amount' => (float) ($row['amount'] ?? max(0, $qty * $rate - $disc)),
                    'removed' => $qty <= 0,
                ];
            }
        }
        $stmt->close();
        return $out;
    }
}

if (!function_exists('ame_purchase_load_item')) {
    function ame_purchase_load_item(mysqli $db, int $itemId): ?array
    {
        $map = ame_purchase_map($db);
        if ($map['item'] === null || $itemId <= 0) {
            return null;
        }
        $idCol = ame_col($map['itemCols'], ['id', 'item_id']) ?? 'id';
        $stmt = $db->prepare('SELECT * FROM ' . ame_purchase_ident($map['item']) . ' WHERE ' . ame_purchase_ident($idCol) . ' = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        ame_purchase_bind($stmt, 'i', [$itemId]);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('ame_purchase_update_item')) {
    function ame_purchase_update_item(mysqli $db, int $invoiceId, int $itemId, array $input): array
    {
        $invoice = ame_purchase_find($db, $invoiceId);
        $old = ame_purchase_load_item($db, $itemId);
        if (!$invoice || !$old) {
            throw new RuntimeException('সারি পাওয়া যায়নি।');
        }
        $map = ame_purchase_map($db);
        $cols = $map['itemCols'];
        $qtyCol = ame_col($cols, ['quantity', 'qty', 'qty_purchased', 'pcs']);
        $rateCol = ame_col($cols, ['purchase_price', 'buy_price', 'price', 'rate', 'unit_price', 'cost']);
        $discCol = ame_col($cols, ['discount', 'disc', 'line_discount']);
        $totCol = ame_col($cols, ['total', 'subtotal', 'amount', 'line_total', 'net_total']);
        $pidCol = ame_col($cols, ['product_id', 'item_id', 'pid']);
        $oldQty = $qtyCol ? (float) ($old[$qtyCol] ?? 0) : 0;
        $oldRate = $rateCol ? (float) ($old[$rateCol] ?? 0) : 0;
        $oldDisc = $discCol ? (float) ($old[$discCol] ?? 0) : 0;
        $oldAmt = $totCol ? (float) ($old[$totCol] ?? ($oldQty * $oldRate - $oldDisc)) : ($oldQty * $oldRate - $oldDisc);
        $qty = array_key_exists('qty', $input) ? (float) $input['qty'] : $oldQty;
        $rate = array_key_exists('price', $input) ? (float) $input['price'] : $oldRate;
        $disc = array_key_exists('discount', $input) ? (float) $input['discount'] : $oldDisc;
        if ($qty < 0) {
            $qty = 0;
        }
        $amount = round(max(0, $qty * $rate - $disc), 2);
        $sets = [];
        $types = '';
        $vals = [];
        if ($qtyCol) {
            $sets[] = ame_purchase_ident($qtyCol) . ' = ?';
            $types .= 'd';
            $vals[] = $qty;
        }
        if ($rateCol) {
            $sets[] = ame_purchase_ident($rateCol) . ' = ?';
            $types .= 'd';
            $vals[] = $rate;
        }
        if ($discCol) {
            $sets[] = ame_purchase_ident($discCol) . ' = ?';
            $types .= 'd';
            $vals[] = $disc;
        }
        if ($totCol) {
            $sets[] = ame_purchase_ident($totCol) . ' = ?';
            $types .= 'd';
            $vals[] = $amount;
        }
        if (!$sets) {
            throw new RuntimeException('সারি আপডেটের কলাম নেই।');
        }
        $idCol = ame_col($cols, ['id', 'item_id']) ?? 'id';
        $vals[] = $itemId;
        $types .= 'i';
        $db->begin_transaction();
        try {
            ame_purchase_exec(
                $db,
                'UPDATE ' . ame_purchase_ident($map['item']) . ' SET ' . implode(', ', $sets)
                . ' WHERE ' . ame_purchase_ident($idCol) . ' = ?',
                $types,
                $vals
            );
            $pid = $pidCol ? (int) ($old[$pidCol] ?? 0) : 0;
            ame_purchase_apply_money($db, $invoice, $amount - $oldAmt, $pid, $qty - $oldQty);
            ame_purchase_log_revision($db, $invoiceId, 'update', 'সারির পরিমাণ/দাম/ডিসকাউন্ট আপডেট', [
                'item_id' => $itemId,
                'qty' => $qty,
                'rate' => $rate,
                'discount' => $disc,
                'amount' => $amount,
            ]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
        return ['ok' => true, 'item_id' => $itemId, 'qty' => $qty, 'rate' => $rate, 'discount' => $disc, 'amount' => $amount, 'message' => 'সারি আপডেট হয়েছে।'];
    }
}

if (!function_exists('ame_purchase_remove_item')) {
    function ame_purchase_remove_item(mysqli $db, int $invoiceId, int $itemId): array
    {
        $invoice = ame_purchase_find($db, $invoiceId);
        $old = ame_purchase_load_item($db, $itemId);
        if (!$invoice || !$old) {
            throw new RuntimeException('সারি পাওয়া যায়নি।');
        }
        $map = ame_purchase_map($db);
        $cols = $map['itemCols'];
        $qtyCol = ame_col($cols, ['quantity', 'qty', 'qty_purchased', 'pcs']);
        $rateCol = ame_col($cols, ['purchase_price', 'buy_price', 'price', 'rate', 'unit_price', 'cost']);
        $discCol = ame_col($cols, ['discount', 'disc', 'line_discount']);
        $totCol = ame_col($cols, ['total', 'subtotal', 'amount', 'line_total']);
        $pidCol = ame_col($cols, ['product_id', 'item_id', 'pid']);
        $idCol = ame_col($cols, ['id', 'item_id']) ?? 'id';
        $oldQty = $qtyCol ? (float) ($old[$qtyCol] ?? 0) : 0;
        $oldRate = $rateCol ? (float) ($old[$rateCol] ?? 0) : 0;
        $oldDisc = $discCol ? (float) ($old[$discCol] ?? 0) : 0;
        $oldAmt = $totCol ? (float) ($old[$totCol] ?? ($oldQty * $oldRate - $oldDisc)) : ($oldQty * $oldRate - $oldDisc);
        $sets = [];
        $types = '';
        $vals = [];
        if ($qtyCol) {
            $sets[] = ame_purchase_ident($qtyCol) . ' = 0';
        }
        if ($totCol) {
            $sets[] = ame_purchase_ident($totCol) . ' = 0';
        }
        if (!$sets) {
            throw new RuntimeException('সারি বাদ দেওয়ার কলাম নেই।');
        }
        $vals[] = $itemId;
        $types .= 'i';
        $db->begin_transaction();
        try {
            ame_purchase_exec(
                $db,
                'UPDATE ' . ame_purchase_ident($map['item']) . ' SET ' . implode(', ', $sets)
                . ' WHERE ' . ame_purchase_ident($idCol) . ' = ?',
                $types,
                $vals
            );
            $pid = $pidCol ? (int) ($old[$pidCol] ?? 0) : 0;
            ame_purchase_apply_money($db, $invoice, -$oldAmt, $pid, -$oldQty);
            ame_purchase_log_revision($db, $invoiceId, 'remove', 'পণ্য বাদ — আগের সারি খাতায় আছে', [
                'item_id' => $itemId,
                'old_qty' => $oldQty,
                'old_amount' => $oldAmt,
            ]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
        return ['ok' => true, 'item_id' => $itemId, 'message' => 'পণ্য বাদ, স্টক ও খাতা সমন্বয়।'];
    }
}

if (!function_exists('ame_purchase_update_header')) {
    function ame_purchase_update_header(mysqli $db, int $invoiceId, array $input): array
    {
        $invoice = ame_purchase_find($db, $invoiceId);
        if (!$invoice) {
            throw new RuntimeException('মেমো পাওয়া যায়নি।');
        }
        $map = ame_purchase_map($db);
        $cols = $map['headCols'];
        $head = $map['head'];
        $idCol = ame_col($cols, ['id', 'purchase_id', 'invoice_id', 'pid']) ?? 'id';
        $sets = [];
        $types = '';
        $vals = [];
        $noCol = ame_col($cols, ['invoice_no', 'invoice_number', 'memo_no', 'purchase_no', 'bill_no', 'number']);
        $dateCol = ame_col($cols, ['date', 'invoice_date', 'purchase_date']);
        $partyCol = ame_col($cols, ['supplier_id', 'vendor_id', 'customer_id', 'party_id']);
        $noteCol = ame_col($cols, ['notes', 'remark', 'comments']);
        if ($noCol && isset($input['number']) && trim((string) $input['number']) !== '') {
            $sets[] = ame_purchase_ident($noCol) . ' = ?';
            $types .= 's';
            $vals[] = trim((string) $input['number']);
        }
        if ($dateCol && !empty($input['date'])) {
            $sets[] = ame_purchase_ident($dateCol) . ' = ?';
            $types .= 's';
            $vals[] = (string) $input['date'];
        }
        if ($partyCol && isset($input['supplier_id']) && (int) $input['supplier_id'] > 0) {
            $sets[] = ame_purchase_ident($partyCol) . ' = ?';
            $types .= 'i';
            $vals[] = (int) $input['supplier_id'];
        }
        if ($noteCol && array_key_exists('notes', $input)) {
            $sets[] = ame_purchase_ident($noteCol) . ' = ?';
            $types .= 's';
            $vals[] = (string) $input['notes'];
        }
        if (!$sets) {
            return ['ok' => true, 'message' => 'পরিবর্তন নেই।'];
        }
        $updCol = ame_col($cols, ['updated_at', 'modified_at']);
        if ($updCol) {
            $sets[] = ame_purchase_ident($updCol) . ' = NOW()';
        }
        $vals[] = $invoiceId;
        $types .= 'i';
        ame_purchase_exec(
            $db,
            'UPDATE ' . ame_purchase_ident($head) . ' SET ' . implode(', ', $sets)
            . ' WHERE ' . ame_purchase_ident($idCol) . ' = ?',
            $types,
            $vals
        );
        ame_purchase_log_revision($db, $invoiceId, 'header', 'সাপ্লায়ার / নম্বর / তারিখ আপডেট', $input);
        return ['ok' => true, 'message' => 'ইনভয়েসের মূল তথ্য আপডেট হয়েছে।'];
    }
}
