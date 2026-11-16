<?php
/**
 * পুরনো মা ইঞ্জিনিয়ারিং সাইটের শেষ তারিখ পর্যন্ত ডাটা নতুন প্রজেক্টে বসায়।
 * নতুন প্রজেক্টে যে বিল আগে থেকে আছে সেটি মুছে না।
 */

function ame_old_site_sku_key(string $sku): string
{
    $sku = str_replace(["\t", "\r", "\n"], '', $sku);
    $sku = trim($sku);
    if (class_exists('Normalizer')) {
        $norm = Normalizer::normalize($sku, Normalizer::FORM_C);
        if (is_string($norm) && $norm !== '') {
            $sku = $norm;
        }
    }
    return mb_strtolower($sku, 'UTF-8');
}

function ame_old_site_sql_string(mysqli $db, string $value): string
{
    return "'" . $db->real_escape_string($value) . "'";
}

/**
 * @return array{ok:bool,message:string,stats:array,sql:string}
 */
function ame_import_old_site_through_last_date(mysqli $new, mysqli $old): array
{
    $new->query("SET time_zone = '+00:00'");
    $old->query("SET time_zone = '+00:00'");

    $stats = [
        'last_old_sale' => '',
        'sales_imported' => 0,
        'sales_skipped' => 0,
        'items_imported' => 0,
        'items_unmapped' => 0,
        'stock_updated' => 0,
        'dues_updated' => 0,
    ];
    $sql = [];
    $sql[] = "SET time_zone = '+00:00';";

    $last = $old->query('SELECT MAX(SalMoment) AS m, MAX(SalDate) AS d FROM sale WHERE SalStatus=1');
    $lastRow = $last ? $last->fetch_assoc() : null;
    $stats['last_old_sale'] = (string) ($lastRow['m'] ?? $lastRow['d'] ?? '');

    $products = [];
    $names = [];
    $byId = [];
    $pres = $new->query('SELECT id, sku, name, stock FROM products');
    while ($pres && ($row = $pres->fetch_assoc())) {
        $info = [
            'id' => (int) $row['id'],
            'sku' => (string) $row['sku'],
            'stock' => (float) $row['stock'],
        ];
        $byId[$info['id']] = $info;
        $key = ame_old_site_sku_key((string) $row['sku']);
        if ($key !== '') {
            $products[$key] = $info;
        }
        $nameKey = ame_old_site_sku_key((string) $row['name']);
        if ($nameKey !== '' && !isset($names[$nameKey])) {
            $names[$nameKey] = $info['id'];
        }
    }

    $customers = [];
    $cres = $new->query('SELECT id, customer_code, receivable_balance FROM customers');
    while ($cres && ($row = $cres->fetch_assoc())) {
        $key = ame_old_site_sku_key((string) $row['customer_code']);
        if ($key !== '') {
            $customers[$key] = [
                'id' => (int) $row['id'],
                'code' => (string) $row['customer_code'],
                'due' => (float) $row['receivable_balance'],
            ];
        }
    }

    $oldProducts = [];
    $op = $old->query(
        'SELECT p.ProId, p.ProCode, p.ProName, p.ProCost, p.ProRetMRP, p.ProWhoMRP, COALESCE(SUM(pb.PbStock), 0) AS stk
         FROM product p
         LEFT JOIN pro_branch pb ON pb.ProId = p.ProId
         GROUP BY p.ProId, p.ProCode, p.ProName, p.ProCost, p.ProRetMRP, p.ProWhoMRP'
    );
    while ($op && ($row = $op->fetch_assoc())) {
        $key = ame_old_site_sku_key((string) $row['ProCode']);
        $id = $products[$key]['id'] ?? 0;
        if ($id <= 0) {
            $id = $names[ame_old_site_sku_key((string) $row['ProName'])] ?? 0;
        }
        $oldProducts[(int) $row['ProId']] = [
            'new_id' => $id,
            'sku' => (string) ($byId[$id]['sku'] ?? ''),
            'name' => trim((string) $row['ProName']),
            'stock' => (float) $row['stk'],
            'cost' => (float) $row['ProCost'],
            'retail' => (float) $row['ProRetMRP'],
            'wholesale' => (float) $row['ProWhoMRP'],
        ];
    }

    $keepQty = [];
    $exclusive = ame_old_site_exclusive_qty($new, $old);
    if ($exclusive) {
        while ($row = $exclusive->fetch_assoc()) {
            $keepQty[(int) $row['product_id']] = (float) $row['qty'];
        }
    }

    $sales = $old->query(
        "SELECT s.SalId, s.SalInvoice, s.SalDate, s.SalMoment, s.CusType, s.SalSubTotal, s.SalSpeDisc,
                s.SalNetPay, s.SalCash, s.SalDue
         FROM sale s
         WHERE s.SalStatus = 1
         ORDER BY s.SalMoment ASC, s.SalId ASC"
    );
    $existing = [];
    $have = $new->query('SELECT invoice_number FROM sales');
    while ($have && ($row = $have->fetch_assoc())) {
        $existing[(string) $row['invoice_number']] = true;
    }

    $retailNames = [];
    $rr = $old->query('SELECT SalId, SrCusName FROM sale_retail');
    while ($rr && ($row = $rr->fetch_assoc())) {
        $retailNames[(int) $row['SalId']] = trim((string) $row['SrCusName']);
    }
    $registered = [];
    $rg = $old->query('SELECT SalId, CusId FROM sale_registered');
    while ($rg && ($row = $rg->fetch_assoc())) {
        $registered[(int) $row['SalId']] = (int) $row['CusId'];
    }
    $oldCustomers = [];
    $oc = $old->query('SELECT CusId, CusIdCode, CusName, CusDue FROM customer');
    while ($oc && ($row = $oc->fetch_assoc())) {
        $oldCustomers[(int) $row['CusId']] = $row;
    }

    $new->begin_transaction();
    try {
        if ($sales) {
            while ($row = $sales->fetch_assoc()) {
                $invoice = trim((string) $row['SalInvoice']);
                if ($invoice === '' || isset($existing[$invoice])) {
                    $stats['sales_skipped']++;
                    continue;
                }
                $salId = (int) $row['SalId'];
                $isRegistered = (int) ($row['CusType'] ?? 1) === 2;
                $customerId = 0;
                $guest = $retailNames[$salId] ?? '';
                if ($isRegistered && isset($registered[$salId], $oldCustomers[$registered[$salId]])) {
                    $code = ame_old_site_sku_key((string) $oldCustomers[$registered[$salId]]['CusIdCode']);
                    $customerId = $customers[$code]['id'] ?? 0;
                    if ($guest === '') {
                        $guest = trim((string) $oldCustomers[$registered[$salId]]['CusName']);
                    }
                }
                $bill = (string) ($row['SalDate'] ?: $row['SalMoment']);
                $created = (string) ($row['SalMoment'] ?: $row['SalDate']);
                $ctype = $isRegistered ? 'Registered' : 'Retail';
                $custSql = $customerId > 0 ? (string) $customerId : 'NULL';
                $stmt = 'INSERT IGNORE INTO sales ('
                    . 'invoice_number, customer_id, customer_type, guest_customer_name, status, '
                    . 'total_bill, discount, net_payable, paid_amount, due_amount, cash_paid_amount, '
                    . 'sale_date, created_at, completed_at'
                    . ') VALUES ('
                    . ame_old_site_sql_string($new, $invoice) . ', '
                    . $custSql . ', '
                    . ame_old_site_sql_string($new, $ctype) . ', '
                    . ame_old_site_sql_string($new, $guest) . ", 'completed', "
                    . ame_old_site_num($row['SalSubTotal']) . ', '
                    . ame_old_site_num($row['SalSpeDisc']) . ', '
                    . ame_old_site_num($row['SalNetPay']) . ', '
                    . ame_old_site_num($row['SalCash']) . ', '
                    . ame_old_site_num($row['SalDue']) . ', 0, '
                    . ame_old_site_sql_string($new, $bill) . ', '
                    . ame_old_site_sql_string($new, $created) . ', '
                    . ame_old_site_sql_string($new, $bill)
                    . ')';
                if (!$new->query($stmt)) {
                    throw new RuntimeException($new->error . ' :: ' . $invoice);
                }
                if ($new->affected_rows < 1) {
                    $stats['sales_skipped']++;
                    continue;
                }
                $stats['sales_imported']++;
                $existing[$invoice] = true;
                $sql[] = $stmt . ';';

                $items = $old->query(
                    'SELECT SiId, ProId, SiCost, SiMRP, SiQty FROM sale_item WHERE SalId=' . $salId
                );
                while ($items && ($it = $items->fetch_assoc())) {
                    $mapped = $oldProducts[(int) $it['ProId']] ?? null;
                    $pid = (int) ($mapped['new_id'] ?? 0);
                    $itemName = (string) ($mapped['name'] ?? '');
                    if ($pid <= 0) {
                        $stats['items_unmapped']++;
                    }
                    $pidSql = $pid > 0 ? (string) $pid : 'NULL';
                    $legacy = 'mae-sql:' . (int) $it['SiId'];
                    $itemSql = 'INSERT IGNORE INTO sale_items (sale_id, product_id, qty, purchase_price, sale_price, item_name, legacy_key, base_qty) '
                        . 'SELECT id, ' . $pidSql . ', '
                        . ame_old_site_num($it['SiQty']) . ', '
                        . ame_old_site_num($it['SiCost']) . ', '
                        . ame_old_site_num($it['SiMRP']) . ', '
                        . ame_old_site_sql_string($new, $itemName) . ', '
                        . ame_old_site_sql_string($new, $legacy) . ', '
                        . ame_old_site_num($it['SiQty'])
                        . ' FROM sales WHERE invoice_number=' . ame_old_site_sql_string($new, $invoice) . ' LIMIT 1';
                    if (!$new->query($itemSql)) {
                        throw new RuntimeException($new->error . ' :: item ' . $legacy);
                    }
                    $stats['items_imported'] += max(0, $new->affected_rows);
                    $sql[] = $itemSql . ';';
                }
            }
        }

        foreach ($oldProducts as $info) {
            $id = (int) $info['new_id'];
            if ($id <= 0 || $info['sku'] === '') {
                continue;
            }
            $target = round($info['stock'] - ($keepQty[$id] ?? 0), 3);
            $cur = $byId[$id]['stock'] ?? null;
            if ($cur !== null && abs($cur - $target) < 0.001) {
                continue;
            }
            $skuSql = ame_old_site_sql_string($new, $info['sku']);
            $stockSql = 'UPDATE products SET stock=' . ame_old_site_num($target) . ' WHERE sku=' . $skuSql;
            $storeSql = 'UPDATE store_stock ss JOIN products p ON p.id = ss.product_id '
                . 'SET ss.qty=' . ame_old_site_num($target)
                . ' WHERE ss.store_id=1 AND p.sku=' . $skuSql;
            if (!$new->query($stockSql) || !$new->query($storeSql)) {
                throw new RuntimeException($new->error);
            }
            if ($new->affected_rows > 0 || $cur === null || abs((float) $cur - $target) >= 0.001) {
                $stats['stock_updated']++;
                $sql[] = $stockSql . ';';
                $sql[] = $storeSql . ';';
            }
        }

        $latestDue = [];
        foreach ($oldCustomers as $row) {
            $key = ame_old_site_sku_key((string) $row['CusIdCode']);
            if ($key === '' || !isset($customers[$key])) {
                continue;
            }
            if (!isset($latestDue[$key]) || (int) $row['CusId'] > (int) $latestDue[$key]['CusId']) {
                $latestDue[$key] = $row;
            }
        }
        foreach ($latestDue as $key => $row) {
            $due = round((float) $row['CusDue'], 2);
            if (abs($customers[$key]['due'] - $due) < 0.009) {
                continue;
            }
            $dueSql = 'UPDATE customers SET receivable_balance=' . ame_old_site_num($due)
                . ' WHERE customer_code=' . ame_old_site_sql_string($new, $customers[$key]['code']);
            if (!$new->query($dueSql)) {
                throw new RuntimeException($new->error);
            }
            $stats['dues_updated']++;
            $sql[] = $dueSql . ';';
        }

        $new->commit();
    } catch (Throwable $e) {
        $new->rollback();
        return [
            'ok' => false,
            'message' => $e->getMessage(),
            'stats' => $stats,
            'sql' => '',
        ];
    }

    $message = 'শেষ বিক্রয় ' . $stats['last_old_sale']
        . '। নতুন বিক্রয় ' . $stats['sales_imported']
        . ', আগের বিক্রয় রাখা ' . $stats['sales_skipped']
        . ', লাইন ' . $stats['items_imported']
        . ', স্টক ' . $stats['stock_updated']
        . ', বকেয়া ' . $stats['dues_updated'] . '।';

    return [
        'ok' => true,
        'message' => $message,
        'stats' => $stats,
        'sql' => implode("\n", $sql) . "\n",
    ];
}

function ame_old_site_num($value): string
{
    return number_format((float) $value, 2, '.', '');
}

function ame_old_site_exclusive_qty(mysqli $new, mysqli $old)
{
    $invoices = [];
    $res = $old->query('SELECT SalInvoice FROM sale');
    while ($res && ($row = $res->fetch_assoc())) {
        $invoices[(string) $row['SalInvoice']] = true;
    }
    $qty = [];
    $sales = $new->query("SELECT id, invoice_number FROM sales WHERE status='completed'");
    $ids = [];
    while ($sales && ($row = $sales->fetch_assoc())) {
        if (!isset($invoices[(string) $row['invoice_number']])) {
            $ids[] = (int) $row['id'];
        }
    }
    if ($ids === []) {
        return null;
    }
    $list = implode(',', $ids);
    $q = $new->query("SELECT product_id, SUM(qty) AS qty FROM sale_items WHERE sale_id IN ($list) GROUP BY product_id");
    return $q ?: null;
}
