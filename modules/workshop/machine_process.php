<?php
require_once __DIR__ . '/_boot.php';

require_post();
csrf_verify();

$action = trim((string)($_POST['action'] ?? ''));
$machine_id = (int)($_POST['machine_id'] ?? 0);

function ws_back_machines(string $err = '', bool $ok = false): void
{
    $url = 'machines.php';
    if ($ok) {
        $url .= '?ok=1';
    }
    if ($err !== '') {
        $url .= ($ok ? '&' : '?') . 'err=' . rawurlencode($err);
    }
    header('Location: ' . $url);
    exit;
}

function ws_back_machine(int $id, string $err = '', bool $ok = false): void
{
    $url = 'machine_view.php?id=' . $id;
    if ($ok) {
        $url .= '&ok=1';
    }
    if ($err !== '') {
        $url .= '&err=' . rawurlencode($err);
    }
    header('Location: ' . $url);
    exit;
}

try {
    if ($action === 'save_machine') {
        $type = trim((string)($_POST['machine_type'] ?? 'other'));
        if (!isset(ame_workshop_machine_types()[$type])) {
            $type = 'other';
        }
        $brand = trim((string)($_POST['brand'] ?? ''));
        $model = trim((string)($_POST['model'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));
        if ($model === '') {
            if ($machine_id > 0) {
                ws_back_machine($machine_id, 'মডেলের নাম লাগবে।');
            }
            ws_back_machines('মডেলের নাম লাগবে।');
        }
        if ($machine_id > 0) {
            $stmt = $db->prepare(
                'UPDATE workshop_machines SET machine_type = ?, brand = ?, model = ?, note = ? WHERE id = ?'
            );
            $stmt->bind_param('ssssi', $type, $brand, $model, $note, $machine_id);
            $stmt->execute();
            $stmt->close();
            ws_back_machine($machine_id, '', true);
        }
        $stmt = $db->prepare(
            'INSERT INTO workshop_machines (machine_type, brand, model, note) VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('ssss', $type, $brand, $model, $note);
        $stmt->execute();
        $new_id = (int)$stmt->insert_id;
        $stmt->close();
        ws_back_machine($new_id, '', true);
    }

    if ($action === 'add_machine_part') {
        if ($machine_id <= 0 || !ame_workshop_machine($db, $machine_id)) {
            ws_back_machines('মেশিন পাওয়া যায়নি।');
        }
        $product_id = (int)($_POST['product_id'] ?? 0);
        $oem = trim((string)($_POST['oem_code'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));
        if ($product_id <= 0) {
            ws_back_machine($machine_id, 'পার্টস বাছুন।');
        }
        $oem_n = $oem !== '' ? $oem : null;
        $note_n = $note !== '' ? $note : null;
        $stmt = $db->prepare(
            'INSERT INTO workshop_machine_parts (machine_id, product_id, oem_code, note) VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('iiss', $machine_id, $product_id, $oem_n, $note_n);
        if (!$stmt->execute()) {
            $stmt->close();
            ws_back_machine($machine_id, 'এই যন্ত্রাংশ ইতিমধ্যে এই মডেলে আছে।');
        }
        $stmt->close();
        ws_back_machine($machine_id, '', true);
    }

    if ($action === 'remove_machine_part') {
        $part_id = (int)($_POST['part_id'] ?? 0);
        if ($machine_id <= 0) {
            ws_back_machines('মেশিন পাওয়া যায়নি।');
        }
        $del = $db->prepare('DELETE FROM workshop_machine_parts WHERE id = ? AND machine_id = ?');
        $del->bind_param('ii', $part_id, $machine_id);
        $del->execute();
        $del->close();
        ws_back_machine($machine_id, '', true);
    }

    if ($action === 'delete_machine') {
        if ($machine_id <= 0) {
            ws_back_machines('মেশিন পাওয়া যায়নি।');
        }
        $used = $db->prepare('SELECT id FROM workshop_jobs WHERE machine_id = ? LIMIT 1');
        $used->bind_param('i', $machine_id);
        $used->execute();
        $has_job = $used->get_result()->fetch_assoc();
        $used->close();
        if ($has_job) {
            ws_back_machine($machine_id, 'এই মেশিনে জব আছে, মুছা যায় না।');
        }
        $db->begin_transaction();
        $delp = $db->prepare('DELETE FROM workshop_machine_parts WHERE machine_id = ?');
        $delp->bind_param('i', $machine_id);
        $delp->execute();
        $delp->close();
        $delm = $db->prepare('DELETE FROM workshop_machines WHERE id = ?');
        $delm->bind_param('i', $machine_id);
        $delm->execute();
        $delm->close();
        $db->commit();
        ws_back_machines('', true);
    }

    ws_back_machines('অজানা কাজ।');
} catch (Throwable $e) {
    if ($db instanceof mysqli) {
        try {
            $db->rollback();
        } catch (Throwable $ignored) {
        }
    }
    error_log('workshop machine_process: ' . $e->getMessage());
    if ($machine_id > 0) {
        ws_back_machine($machine_id, 'কাজটি সম্পন্ন হয়নি।');
    }
    ws_back_machines('কাজটি সম্পন্ন হয়নি।');
}
