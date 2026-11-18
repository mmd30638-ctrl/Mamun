<?php
require_once __DIR__ . '/_boot.php';

require_post();
csrf_verify();
$action = trim((string)($_POST['action'] ?? ''));
$user_id = (int)($_SESSION['user_id'] ?? 0);

try {
    if ($action === 'create_quote') {
        $name = trim((string)($_POST['customer_name'] ?? ''));
        $item = trim((string)($_POST['item_name'] ?? ''));
        $quote_no = ame_workshop_next_no($db, 'workshop_quotes', 'QT');
        $customer_id = (int)($_POST['customer_id'] ?? 0);
        $phone = trim((string)($_POST['phone'] ?? ''));
        $labor = max(0, (float)($_POST['labor_charge'] ?? 0));
        $discount = max(0, (float)($_POST['discount'] ?? 0));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $machine_id = (int)($_POST['machine_id'] ?? 0);
        $mid = $machine_id > 0 ? $machine_id : 0;
        if ($mid > 0 && $item === '') {
            $mach = ame_workshop_machine($db, $mid);
            if ($mach) {
                $item = ame_workshop_machine_label($mach);
            }
        }
        if ($name === '' || $item === '') {
            header('Location: quote_new.php?err=' . rawurlencode('নাম ও কাজের বিবরণ লাগবে।'));
            exit;
        }
        $db->begin_transaction();
        $stmt = $db->prepare(
            'INSERT INTO workshop_quotes
                (quote_no, customer_id, customer_name, phone, item_name, machine_id, notes, labor_charge, discount, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'sisssisddi',
            $quote_no,
            $customer_id,
            $name,
            $phone,
            $item,
            $mid,
            $notes,
            $labor,
            $discount,
            $user_id
        );
        $stmt->execute();
        $qid = (int)$stmt->insert_id;
        $stmt->close();

        $ids = $_POST['part_product'] ?? [];
        $names = $_POST['part_name'] ?? [];
        $qtys = $_POST['part_qty'] ?? [];
        $prices = $_POST['part_price'] ?? [];
        $parts_total = 0.0;
        if (is_array($names)) {
            $n = count($names);
            for ($i = 0; $i < $n && $i < 40; $i++) {
                $pname = trim((string)($names[$i] ?? ''));
                $qty = (float)($qtys[$i] ?? 0);
                $price = (float)($prices[$i] ?? 0);
                $pid = (int)($ids[$i] ?? 0);
                if ($pname === '' && $pid > 0) {
                    $pr = $db->prepare('SELECT name FROM products WHERE id = ?');
                    $pr->bind_param('i', $pid);
                    $pr->execute();
                    $prow = $pr->get_result()->fetch_assoc();
                    $pr->close();
                    $pname = (string)($prow['name'] ?? '');
                }
                if ($pname === '' || $qty <= 0) {
                    continue;
                }
                $line = round($qty * $price, 2);
                $parts_total += $line;
                $ins = $db->prepare(
                    'INSERT INTO workshop_quote_items (quote_id, product_id, product_name, qty, unit_price, line_total)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $ins->bind_param('iisddd', $qid, $pid, $pname, $qty, $price, $line);
                $ins->execute();
                $ins->close();
            }
        }
        $grand = max(0, round($labor + $parts_total - $discount, 2));
        $upd = $db->prepare('UPDATE workshop_quotes SET parts_total = ?, grand_total = ? WHERE id = ?');
        $upd->bind_param('ddi', $parts_total, $grand, $qid);
        $upd->execute();
        $upd->close();
        $db->commit();
        header('Location: quote_view.php?id=' . $qid);
        exit;
    }

    $quote_id = (int)($_POST['quote_id'] ?? 0);
    $qs = $db->prepare('SELECT * FROM workshop_quotes WHERE id = ?');
    $qs->bind_param('i', $quote_id);
    $qs->execute();
    $quote = $qs->get_result()->fetch_assoc();
    $qs->close();
    if (!$quote) {
        header('Location: quotes.php');
        exit;
    }

    if ($action === 'convert' && $quote['status'] === 'open') {
        header('Location: job_new.php?quote_id=' . $quote_id);
        exit;
    }

    if ($action === 'cancel_quote' && $quote['status'] === 'open') {
        $upd = $db->prepare("UPDATE workshop_quotes SET status = 'cancelled' WHERE id = ?");
        $upd->bind_param('i', $quote_id);
        $upd->execute();
        $upd->close();
        header('Location: quote_view.php?id=' . $quote_id);
        exit;
    }

    header('Location: quotes.php');
    exit;
} catch (Throwable $e) {
    try {
        $db->rollback();
    } catch (Throwable $ignored) {
    }
    error_log('quote_process: ' . $e->getMessage());
    header('Location: quote_new.php?err=' . rawurlencode('কোটেশন সেভ হয়নি।'));
    exit;
}
