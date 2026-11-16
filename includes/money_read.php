<?php
/**
 * Read-only money checks. These functions never UPDATE or INSERT.
 */

function ame_money_sales_gap(mysqli $db, string $from, string $to): array
{
    $out = ['invoices' => 0, 'paid' => 0.0, 'payments' => 0.0, 'mismatch' => 0];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        return $out;
    }
    $sql = "SELECT COUNT(*) AS invoices,
                   COALESCE(SUM(s.paid_amount), 0) AS paid,
                   COALESCE(SUM(pay.pay_total), 0) AS payments,
                   COALESCE(SUM(CASE WHEN ABS(s.paid_amount - pay.pay_total) > 0.009 THEN 1 ELSE 0 END), 0) AS mismatch
            FROM sales s
            LEFT JOIN (
                SELECT sale_id, SUM(amount) AS pay_total
                FROM sale_payments
                WHERE payment_status = 'posted'
                GROUP BY sale_id
            ) pay ON pay.sale_id = s.id
            WHERE s.status IN ('completed','posted','final','done')
              AND DATE(COALESCE(s.sale_date, s.created_at, s.completed_at)) BETWEEN ? AND ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return $out;
    }
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    $out['invoices'] = (int) ($row['invoices'] ?? 0);
    $out['paid'] = round((float) ($row['paid'] ?? 0), 2);
    $out['payments'] = round((float) ($row['payments'] ?? 0), 2);
    $out['mismatch'] = (int) ($row['mismatch'] ?? 0);
    return $out;
}

function ame_money_customer_drift_count(mysqli $db): int
{
    $sql = "SELECT COUNT(*) AS c FROM (
                SELECT c.id
                FROM customers c
                LEFT JOIN customer_ledger l ON l.customer_id = c.id
                WHERE c.is_customer = 1
                GROUP BY c.id, c.receivable_balance
                HAVING ABS(c.receivable_balance - COALESCE(SUM(l.debit) - SUM(l.credit), 0)) > 0.009
            ) drift";
    $res = $db->query($sql);
    if (!$res) {
        return 0;
    }
    return (int) ($res->fetch_assoc()['c'] ?? 0);
}
