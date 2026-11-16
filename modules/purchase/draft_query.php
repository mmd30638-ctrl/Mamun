<?php
require_once '../../includes/config.php';
require_once '../../includes/purchase_functions.php';
require_once '../../includes/purchase_draft.php';

header('Content-Type: application/json; charset=UTF-8');

$respond = static function ($status, $message, $extra = []) {
    echo json_encode(
        array_merge(['status' => $status, 'message' => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit();
};

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    $respond('error', 'লগইন করুন।');
}

if (!ame_purchase_can_access($db)) {
    http_response_code(403);
    $respond('error', 'অনুমতি নেই।');
}

ensure_purchase_schema($db);
ame_purchase_draft_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    $respond('error', 'অনুমোদিত অনুরোধ নয়।');
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$is_admin = strtolower((string)($_SESSION['role'] ?? '')) === 'admin';
$action = (string)($_GET['action'] ?? 'list');
$store_id = function_exists('current_store_id') ? current_store_id() : 0;

try {
    if ($action === 'list') {
        $sql = "
            SELECT d.id, d.draft_number, d.grand_total, d.updated_at, d.created_at,
                   d.supplier_id, s.name AS supplier_name
            FROM purchase_drafts d
            LEFT JOIN suppliers s ON s.id = d.supplier_id
            WHERE d.status = 'draft'
        ";
        $types = '';
        $params = [];
        if (!$is_admin) {
            $sql .= ' AND d.created_by = ?';
            $types .= 'i';
            $params[] = $user_id;
        }
        if ($store_id > 0) {
            $sql .= ' AND (d.store_id = ? OR d.store_id IS NULL)';
            $types .= 'i';
            $params[] = $store_id;
        }
        $sql .= ' ORDER BY d.updated_at DESC, d.id DESC LIMIT 40';

        $stmt = $db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $drafts = [];
        while ($row = $res->fetch_assoc()) {
            $drafts[] = [
                'id' => (int)$row['id'],
                'draft_number' => (string)$row['draft_number'],
                'supplier_name' => trim((string)($row['supplier_name'] ?? '')) !== ''
                    ? (string)$row['supplier_name']
                    : '—',
                'grand_total' => (float)$row['grand_total'],
                'updated_at' => (string)($row['updated_at'] ?: $row['created_at']),
            ];
        }
        $stmt->close();
        $respond('success', 'ok', ['drafts' => $drafts]);
    }

    if ($action === 'memo_check') {
        $sid = (int)($_GET['supplier_id'] ?? 0);
        $memo = function_exists('ame_purchase_normalize_memo')
            ? ame_purchase_normalize_memo($_GET['memo'] ?? '')
            : trim((string)($_GET['memo'] ?? ''));
        $exists = $sid > 0 && $memo !== '' && purchase_memo_exists($db, $sid, $memo);
        $exclude = function_exists('ame_purchase_normalize_memo')
            ? ame_purchase_normalize_memo($_GET['exclude'] ?? '')
            : trim((string)($_GET['exclude'] ?? ''));
        if ($exclude !== '' && $memo !== '' && $memo === $exclude) {
            $exists = false;
        }
        $suggestion = function_exists('ame_purchase_next_memo_suggestion')
            ? ame_purchase_next_memo_suggestion($db, $sid)
            : '';
        $respond('success', 'ok', [
            'exists' => $exists,
            'suggestion' => $suggestion,
            'supplier_id' => $sid,
            'memo' => $memo,
        ]);
    }

    if ($action === 'load') {
        $draft_id = (int)($_GET['id'] ?? 0);
        $draft = ame_purchase_draft_find($db, $draft_id, $user_id, $is_admin, false);
        if (!$draft) {
            http_response_code(404);
            $respond('error', 'ড্রাফট পাওয়া যায়নি।');
        }

        $items = json_decode((string)$draft['items_json'], true);
        $expenses = json_decode((string)$draft['expenses_json'], true);
        $supplier_name = '';
        if (!empty($draft['supplier_id'])) {
            $sid = (int)$draft['supplier_id'];
            $sq = $db->prepare('SELECT name, phone, total_due FROM suppliers WHERE id = ? LIMIT 1');
            if ($sq) {
                $sq->bind_param('i', $sid);
                $sq->execute();
                $sup = $sq->get_result()->fetch_assoc();
                $sq->close();
                if ($sup) {
                    $supplier_name = (string)$sup['name'];
                    $draft['supplier_phone'] = (string)($sup['phone'] ?? '');
                    $draft['supplier_due'] = (float)($sup['total_due'] ?? 0);
                }
            }
        }

        $respond('success', 'ok', [
            'draft' => [
                'id' => (int)$draft['id'],
                'draft_number' => (string)$draft['draft_number'],
                'supplier_id' => $draft['supplier_id'] !== null ? (int)$draft['supplier_id'] : 0,
                'supplier_name' => $supplier_name,
                'supplier_phone' => (string)($draft['supplier_phone'] ?? ''),
                'supplier_due' => (float)($draft['supplier_due'] ?? 0),
                'memo' => (string)($draft['memo_no'] ?? ''),
                'notes' => (string)($draft['notes'] ?? ''),
                'date' => (string)($draft['purchase_date'] ?? ''),
                'account_id' => $draft['account_id'] !== null ? (int)$draft['account_id'] : 0,
                'paid_amount' => (float)$draft['paid_amount'],
                'payment_type' => (string)$draft['payment_type'],
                'cart' => is_array($items) ? $items : [],
                'expenses' => is_array($expenses) ? $expenses : [],
                'grand_total' => (float)$draft['grand_total'],
            ],
        ]);
    }

    http_response_code(400);
    $respond('error', 'অজানা অ্যাকশন।');
} catch (Throwable $e) {
    error_log('Purchase draft query failed: ' . $e->getMessage());
    http_response_code(500);
    $respond('error', 'ড্রাফট লোড হয়নি।');
}
