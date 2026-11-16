<?php
/**
 * টাকা-পয়সার মিল যাচাই — শুধু পড়ে, হিসাব বদলায় না।
 * নিয়ম: সাবেক + এই ইনভয়েস − পরিশোধ = বর্তমান
 */

function ame_money_round($n): float
{
    return round((float)$n, 2);
}

function ame_money_mismatch($a, $b): bool
{
    return abs(ame_money_round($a) - ame_money_round($b)) > 0.009;
}

/**
 * @return array{
 *   formula: string,
 *   customer_mismatches: array,
 *   account_mismatches: array,
 *   orphan_payments: array,
 *   phantom_purchase_rows: int,
 *   suppliers_with_split_credit: array,
 *   ok: bool,
 *   counts: array
 * }
 */
function ame_money_guard_report(mysqli $db): array
{
    $customers = [];
    $sql = "SELECT c.id, c.name, c.customer_code, c.receivable_balance,
                   COALESCE(SUM(l.debit) - SUM(l.credit), 0) AS ledger_net
            FROM customers c
            LEFT JOIN customer_ledger l ON l.customer_id = c.id
            WHERE c.is_customer = 1
            GROUP BY c.id, c.name, c.customer_code, c.receivable_balance
            HAVING ABS(c.receivable_balance - COALESCE(SUM(l.debit) - SUM(l.credit), 0)) > 0.009
            ORDER BY ABS(c.receivable_balance - COALESCE(SUM(l.debit) - SUM(l.credit), 0)) DESC
            LIMIT 80";
    $res = @$db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $last = 0.0;
            $ls = $db->prepare('SELECT running_balance FROM customer_ledger WHERE customer_id = ? ORDER BY id DESC LIMIT 1');
            if ($ls) {
                $cid = (int)$row['id'];
                $ls->bind_param('i', $cid);
                $ls->execute();
                $lr = $ls->get_result()->fetch_assoc();
                $ls->close();
                if ($lr) {
                    $last = (float)$lr['running_balance'];
                }
            }
            $customers[] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'code' => (string)($row['customer_code'] ?? ''),
                'receivable' => ame_money_round($row['receivable_balance']),
                'ledger_net' => ame_money_round($row['ledger_net']),
                'last_running' => ame_money_round($last),
            ];
        }
    }

    $accounts = [];
    $ares = @$db->query('SELECT id, account_name, balance FROM accounts ORDER BY account_name ASC');
    if ($ares) {
        while ($a = $ares->fetch_assoc()) {
            $aid = (int)$a['id'];
            $last_after = null;
            $ls = $db->prepare('SELECT balance_after FROM account_ledger WHERE account_id = ? ORDER BY id DESC LIMIT 1');
            if ($ls) {
                $ls->bind_param('i', $aid);
                $ls->execute();
                $lr = $ls->get_result()->fetch_assoc();
                $ls->close();
                if ($lr) {
                    $last_after = ame_money_round($lr['balance_after']);
                }
            }
            $bal = ame_money_round($a['balance']);
            if ($last_after === null) {
                if (abs($bal) > 0.009) {
                    $accounts[] = [
                        'id' => $aid,
                        'name' => (string)$a['account_name'],
                        'balance' => $bal,
                        'ledger_after' => null,
                    ];
                }
                continue;
            }
            if (ame_money_mismatch($bal, $last_after)) {
                $accounts[] = [
                    'id' => $aid,
                    'name' => (string)$a['account_name'],
                    'balance' => $bal,
                    'ledger_after' => $last_after,
                ];
            }
        }
    }

    $orphans = [];
    $ores = @$db->query(
        "SELECT p.id, p.customer_id, p.amount, p.payment_date, p.method, c.name
         FROM customer_payments p
         LEFT JOIN account_ledger al
           ON al.ref_type = 'customer_payment' AND al.ref_id = p.id
         LEFT JOIN customers c ON c.id = p.customer_id
         WHERE al.id IS NULL
         ORDER BY p.id DESC
         LIMIT 80"
    );
    if ($ores) {
        while ($row = $ores->fetch_assoc()) {
            $orphans[] = [
                'id' => (int)$row['id'],
                'customer' => (string)($row['name'] ?? ('#' . $row['customer_id'])),
                'amount' => ame_money_round($row['amount']),
                'date' => (string)$row['payment_date'],
                'method' => (string)$row['method'],
            ];
        }
    }

    $phantom = 0;
    $pres = @$db->query(
        "SELECT COUNT(*) AS n FROM account_ledger
         WHERE ref_type = 'purchase' AND type = 'debit'
           AND ABS(balance_after) < 0.009 AND amount > 0.009"
    );
    if ($pres && ($prow = $pres->fetch_assoc())) {
        $phantom = (int)$prow['n'];
    }

    $split = [];
    $sres = @$db->query(
        "SELECT id, name, total_due, credit_balance
         FROM suppliers
         WHERE ABS(credit_balance) > 0.009
         ORDER BY credit_balance DESC
         LIMIT 80"
    );
    if ($sres) {
        while ($row = $sres->fetch_assoc()) {
            $due = ame_money_round($row['total_due']);
            $credit = ame_money_round($row['credit_balance']);
            $split[] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'total_due' => $due,
                'credit_balance' => $credit,
                'effective' => ame_money_round($due - $credit),
            ];
        }
    }

    $ok = $customers === [] && $accounts === [] && $orphans === [] && $phantom === 0;

    return [
        'formula' => 'সাবেক + এই ইনভয়েস − পরিশোধ = বর্তমান',
        'customer_mismatches' => $customers,
        'account_mismatches' => $accounts,
        'orphan_payments' => $orphans,
        'phantom_purchase_rows' => $phantom,
        'suppliers_with_split_credit' => $split,
        'ok' => $ok,
        'counts' => [
            'customers' => count($customers),
            'accounts' => count($accounts),
            'orphans' => count($orphans),
            'phantom' => $phantom,
            'split_credit' => count($split),
        ],
    ];
}
