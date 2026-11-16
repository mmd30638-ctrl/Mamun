<?php
/**
 * কাস্টমার পেমেন্ট ডেস্ক — শুধু পড়া।
 * গ্রহণকৃত পেমেন্ট ও নির্ধারিত পরবর্তী তারিখ খাতা থেকে দেখায়।
 * কোনো ব্যালেন্স, পেমেন্ট বা বকেয়া এখানে লেখা হয় না।
 */

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'payments' => [], 'expected' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$customerId = (int)($_GET['id'] ?? 0);
if ($customerId <= 0) {
    echo json_encode(['ok' => true, 'payments' => [], 'expected' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

function ame_cpd_cols(mysqli $db, string $table): array
{
    $cols = [];
    $res = @$db->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[$row['Field']] = true;
        }
        $res->free();
    }
    return $cols;
}

function ame_cpd_date(string $raw): string
{
    $raw = substr(trim($raw), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) || $raw === '0000-00-00') {
        return '';
    }
    $time = strtotime($raw);
    return $time ? date('d/m/Y', $time) : '';
}

function ame_cpd_money($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    return number_format((float)$value, 2, '.', ',');
}

$payments = [];
$payCols = ame_cpd_cols($db, 'customer_payments');
if ($payCols) {
    $discount = isset($payCols['discount']) ? 'p.discount' : '0 AS discount';
    $reversed = isset($payCols['reversed_at']) ? 'p.reversed_at' : 'NULL AS reversed_at';
    $note = isset($payCols['note']) ? 'p.note' : "'' AS note";
    $ref = isset($payCols['reference_no']) ? 'p.reference_no' : "'' AS reference_no";
    $sql = "SELECT p.id, p.amount, {$discount}, p.payment_date, {$note}, {$ref}, {$reversed}";
    $ledgerCols = ame_cpd_cols($db, 'customer_ledger');
    if (isset($ledgerCols['running_balance'], $ledgerCols['ref_no'], $ledgerCols['customer_id'])) {
        $sql .= ", l.running_balance
                 FROM customer_payments p
                 LEFT JOIN customer_ledger l
                   ON l.customer_id = p.customer_id
                  AND l.ref_no = CONCAT('PAY-', LPAD(p.id, 6, '0'))
                 WHERE p.customer_id = ?
                 ORDER BY p.payment_date DESC, p.id DESC
                 LIMIT 80";
    } else {
        $sql .= ", NULL AS running_balance
                 FROM customer_payments p
                 WHERE p.customer_id = ?
                 ORDER BY p.payment_date DESC, p.id DESC
                 LIMIT 80";
    }
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $id = (int)$row['id'];
            $invoice = trim((string)($row['reference_no'] ?? ''));
            if ($invoice === '') {
                $invoice = 'PAY-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
            }
            $rev = trim((string)($row['reversed_at'] ?? ''));
            $payments[] = [
                'id' => $id,
                'date' => ame_cpd_date((string)($row['payment_date'] ?? '')),
                'invoice' => $invoice,
                'note' => (string)($row['note'] ?? ''),
                'amount' => ame_cpd_money($row['amount']),
                'due' => $row['running_balance'] === null ? '—' : ame_cpd_money($row['running_balance']),
                'reversed' => $rev !== '' && $rev !== '0000-00-00 00:00:00',
            ];
        }
        $stmt->close();
    }
}

$expected = [];
$custCols = ame_cpd_cols($db, 'customers');
$custSql = 'SELECT receivable_balance';
if (isset($custCols['next_due_date'])) {
    $custSql .= ', next_due_date';
}
if (isset($custCols['next_due_amount'])) {
    $custSql .= ', next_due_amount';
}
if (isset($custCols['due_note'])) {
    $custSql .= ', due_note';
}
$custSql .= ' FROM customers WHERE id = ? LIMIT 1';
$cust = $db->prepare($custSql);
$book = null;
if ($cust) {
    $cust->bind_param('i', $customerId);
    $cust->execute();
    $book = $cust->get_result()->fetch_assoc() ?: null;
    $cust->close();
}
if ($book) {
    $dueDate = ame_cpd_date((string)($book['next_due_date'] ?? ''));
    if ($dueDate !== '') {
        $expected[] = [
            'date' => $dueDate,
            'note' => (string)($book['due_note'] ?? ''),
            'amount' => array_key_exists('next_due_amount', $book) && $book['next_due_amount'] !== null
                ? ame_cpd_money($book['next_due_amount'])
                : '—',
        ];
    }
}

echo json_encode([
    'ok' => true,
    'balance' => $book ? ame_cpd_money($book['receivable_balance'] ?? 0) : '—',
    'payments' => $payments,
    'expected' => $expected,
], JSON_UNESCAPED_UNICODE);
