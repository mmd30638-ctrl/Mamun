<?php
/**
 * Replace customer and supplier master data from the 25 Sep 2026 old-POS export.
 * Sales, purchases, stock, and accounts stay. Linked ids are kept.
 */

function ame_fresh_contacts_path(): string
{
    return dirname(__DIR__) . '/data/fresh_contacts_20260925.json';
}

function ame_fresh_contacts_load(): array
{
    $path = ame_fresh_contacts_path();
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function ame_fresh_contacts_backup(mysqli $db): string
{
    $dir = dirname(ame_fresh_contacts_path());
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $customers = [];
    $res = @$db->query('SELECT id, customer_code, name, phone, address, receivable_balance, status FROM customers');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $customers[] = $row;
        }
    }
    $suppliers = [];
    $res = @$db->query('SELECT id, name, phone, address, total_due, status FROM suppliers');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $suppliers[] = $row;
        }
    }
    $file = $dir . '/contact_backup_' . date('Ymd_His') . '.json';
    file_put_contents($file, json_encode([
        'customers' => $customers,
        'suppliers' => $suppliers,
    ], JSON_UNESCAPED_UNICODE));
    return $file;
}

function ame_fresh_contacts_apply(mysqli $db): array
{
    $data = ame_fresh_contacts_load();
    if ($data === [] || empty($data['customers']) || empty($data['suppliers'])) {
        return ['ok' => false, 'message' => 'সোর্স ফাইল পাওয়া যায়নি।'];
    }
    require_once __DIR__ . '/ma_engineering_migrate.php';
    require_once dirname(__DIR__) . '/modules/customers/customer_schema.php';
    if (function_exists('ensure_customer_tables')) {
        ensure_customer_tables($db);
    }
    @set_time_limit(180);
    $backup = ame_fresh_contacts_backup($db);
    $stats = [
        'customers_updated' => 0,
        'customers_added' => 0,
        'customers_removed' => 0,
        'customers_kept_for_sales' => 0,
        'suppliers_updated' => 0,
        'suppliers_added' => 0,
        'suppliers_removed' => 0,
        'suppliers_kept_for_purchase' => 0,
        'groups' => 0,
        'areas' => 0,
        'backup' => basename($backup),
    ];

    $db->begin_transaction();
    try {
        foreach ((array) ($data['groups'] ?? []) as $group) {
            $name = trim((string) ($group['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $chk = $db->prepare('SELECT id FROM customer_groups WHERE name=? LIMIT 1');
            if (!$chk) {
                continue;
            }
            $chk->bind_param('s', $name);
            $chk->execute();
            $found = $chk->get_result()->fetch_assoc();
            $chk->close();
            if ($found) {
                continue;
            }
            $ins = $db->prepare('INSERT INTO customer_groups (name) VALUES (?)');
            if ($ins) {
                $ins->bind_param('s', $name);
                $ins->execute();
                $ins->close();
                $stats['groups']++;
            }
        }
        foreach ((array) ($data['areas'] ?? []) as $area) {
            $name = trim((string) ($area['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $chk = $db->prepare('SELECT id FROM customer_areas WHERE name=? LIMIT 1');
            if (!$chk) {
                continue;
            }
            $chk->bind_param('s', $name);
            $chk->execute();
            $found = $chk->get_result()->fetch_assoc();
            $chk->close();
            if ($found) {
                continue;
            }
            $ins = $db->prepare('INSERT INTO customer_areas (name) VALUES (?)');
            if ($ins) {
                $ins->bind_param('s', $name);
                $ins->execute();
                $ins->close();
                $stats['areas']++;
            }
        }

        $keepCustomers = [];
        foreach ((array) $data['customers'] as $row) {
            $legacy = (int) ($row['legacy_id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            $phone = trim((string) ($row['phone'] ?? ''));
            $addr = trim((string) ($row['address'] ?? ''));
            $note = trim((string) ($row['note'] ?? ''));
            $due = round((float) ($row['due'] ?? 0), 2);
            $status = (int) ($row['status'] ?? 1) === 1 ? 1 : 0;
            $code = trim((string) ($row['code'] ?? ''));
            if ($name === '' || $legacy <= 0) {
                continue;
            }
            $id = mae_customer_id_from_legacy($db, $legacy, $code);
            if ($id > 0) {
                mae_customer_apply_master($db, $id, $name, $phone, $addr, $due);
                $st = $db->prepare('UPDATE customers SET notes=?, status=? WHERE id=?');
                if ($st) {
                    $st->bind_param('sii', $note, $status, $id);
                    $st->execute();
                    $st->close();
                }
                $stats['customers_updated']++;
            } else {
                $id = mae_customer_insert_or_get($db, $code, $legacy, $name, $phone, $addr, '', $due, $note, $status);
                mae_customer_apply_master($db, $id, $name, $phone, $addr, $due);
                $stats['customers_added']++;
            }
            if ($id > 0) {
                $keepCustomers[$id] = true;
            }
        }

        $res = $db->query('SELECT id FROM customers');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $id = (int) $row['id'];
                if (isset($keepCustomers[$id])) {
                    continue;
                }
                $linked = 0;
                $st = @$db->prepare('SELECT COUNT(*) c FROM sales WHERE customer_id=?');
                if ($st) {
                    $st->bind_param('i', $id);
                    $st->execute();
                    $linked = (int) (($st->get_result()->fetch_assoc()['c'] ?? 0));
                    $st->close();
                }
                if ($linked > 0) {
                    $stats['customers_kept_for_sales']++;
                    continue;
                }
                @$db->query('DELETE FROM customer_ledger WHERE customer_id=' . $id);
                @$db->query('DELETE FROM customer_payments WHERE customer_id=' . $id);
                $del = $db->prepare('DELETE FROM customers WHERE id=?');
                if ($del) {
                    $del->bind_param('i', $id);
                    $del->execute();
                    if ($del->affected_rows > 0) {
                        $stats['customers_removed']++;
                    }
                    $del->close();
                }
            }
        }

        $keepSuppliers = [];
        foreach ((array) $data['suppliers'] as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $phone = trim((string) ($row['phone'] ?? ''));
            $addr = trim((string) ($row['address'] ?? ''));
            $due = round((float) ($row['due'] ?? 0), 2);
            $status = (string) ($row['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
            if ($name === '') {
                continue;
            }
            $id = mae_supplier_id_by_name($db, $name);
            if ($id > 0) {
                mae_supplier_apply_due($db, $id, $phone, $addr, $due);
                $st = $db->prepare('UPDATE suppliers SET status=? WHERE id=?');
                if ($st) {
                    $st->bind_param('si', $status, $id);
                    $st->execute();
                    $st->close();
                }
                $stats['suppliers_updated']++;
            } else {
                $ins = $db->prepare('INSERT INTO suppliers (name, phone, address, total_due, status) VALUES (?, ?, ?, ?, ?)');
                if ($ins) {
                    $ins->bind_param('sssds', $name, $phone, $addr, $due, $status);
                    $ins->execute();
                    $id = (int) $ins->insert_id;
                    $ins->close();
                    $stats['suppliers_added']++;
                }
            }
            if ($id > 0) {
                $keepSuppliers[$id] = true;
            }
        }

        $res = $db->query('SELECT id, name FROM suppliers');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $id = (int) $row['id'];
                if (isset($keepSuppliers[$id])) {
                    continue;
                }
                $linked = 0;
                $st = @$db->prepare('SELECT COUNT(*) c FROM purchases WHERE supplier_id=?');
                if ($st) {
                    $st->bind_param('i', $id);
                    $st->execute();
                    $linked = (int) (($st->get_result()->fetch_assoc()['c'] ?? 0));
                    $st->close();
                }
                if ($linked > 0) {
                    $stats['suppliers_kept_for_purchase']++;
                    continue;
                }
                $del = $db->prepare('DELETE FROM suppliers WHERE id=?');
                if ($del) {
                    $del->bind_param('i', $id);
                    $del->execute();
                    if ($del->affected_rows > 0) {
                        $stats['suppliers_removed']++;
                    }
                    $del->close();
                }
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        return ['ok' => false, 'message' => 'বদলানো যায়নি। আগের ডেটা আছে।', 'stats' => $stats];
    }

    $custN = 0;
    $custDue = 0.0;
    $r = $db->query('SELECT COUNT(*) n, COALESCE(SUM(receivable_balance),0) d FROM customers');
    if ($r && ($row = $r->fetch_assoc())) {
        $custN = (int) $row['n'];
        $custDue = (float) $row['d'];
    }
    $supN = 0;
    $supDue = 0.0;
    $r = $db->query('SELECT COUNT(*) n, COALESCE(SUM(total_due),0) d FROM suppliers');
    if ($r && ($row = $r->fetch_assoc())) {
        $supN = (int) $row['n'];
        $supDue = (float) $row['d'];
    }
    $stats['customers_now'] = $custN;
    $stats['customer_due_now'] = round($custDue, 2);
    $stats['suppliers_now'] = $supN;
    $stats['supplier_due_now'] = round($supDue, 2);
    $stats['source_customers'] = count($data['customers']);
    $stats['source_suppliers'] = count($data['suppliers']);
    $stats['source_customer_due'] = round(array_sum(array_map(static function ($row) {
        return (float) ($row['due'] ?? 0);
    }, $data['customers'])), 2);
    $stats['source_supplier_due'] = round(array_sum(array_map(static function ($row) {
        return (float) ($row['due'] ?? 0);
    }, $data['suppliers'])), 2);

    return ['ok' => true, 'message' => 'ক্রেতা ও সাপ্লায়ার আজকের পুরনো ডেটা দিয়ে বসানো হয়েছে।', 'stats' => $stats];
}
