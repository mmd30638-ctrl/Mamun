<?php
/*
 * বিক্রয় Draft সংরক্ষণ ও মালিকানা Engine
 */

function ame_sale_draft_require_finance(): void
{
    if (!function_exists('ame_sale_replace_services')) {
        require_once __DIR__ . '/sale_finance.php';
    }
}

function ame_draft_find(
    $db,
    $draft_id,
    $user_id,
    $is_admin = false,
    $lock_row = false
) {
    /* Shared sales desk: every signed-in cashier can open, edit, and
     * complete any draft bill. $user_id / $is_admin stay in the signature
     * so existing callers do not break. */
    $sql = "
        SELECT *
        FROM sales
        WHERE id = ?
          AND status = 'draft'
    ";

    if ($lock_row) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $db->prepare($sql);

    $stmt->bind_param(
        'i',
        $draft_id
    );

    $stmt->execute();

    $draft = $stmt
        ->get_result()
        ->fetch_assoc();

    $stmt->close();

    return $draft ?: null;
}

function ame_draft_assert_editable(
    $db,
    $draft_id,
    $user_id,
    $is_admin,
    $is_english
) {
    $draft = ame_draft_find(
        $db,
        $draft_id,
        $user_id,
        $is_admin,
        true
    );

    if (!$draft) {
        ame_sale_fail(
            $is_english,
            'ড্রাফট পাওয়া যায়নি অথবা সম্পাদনার অনুমতি নেই।',
            'Draft not found or permission denied.'
        );
    }

    $payment_check = $db->prepare(
        "SELECT COUNT(*) AS total
         FROM sale_payments
         WHERE sale_id = ?
           AND payment_status = 'posted'"
    );

    $payment_check->bind_param(
        'i',
        $draft_id
    );

    $payment_check->execute();

    $posted_count = (int)(
        $payment_check
            ->get_result()
            ->fetch_assoc()['total']
        ?? 0
    );

    $payment_check->close();

    if ($posted_count > 0) {
        ame_sale_fail(
            $is_english,
            'পরিশোধ হয়ে যাওয়া ড্রাফট সম্পাদনা করা যাবে না।',
            'A draft with posted payments cannot be edited.'
        );
    }

    return $draft;
}

function ame_draft_store_items(
    $db,
    $draft_id,
    $items
) {
    $delete = $db->prepare(
        'DELETE FROM sale_items WHERE sale_id = ?'
    );

    $delete->bind_param(
        'i',
        $draft_id
    );

    $delete->execute();
    $delete->close();

    foreach ($items as $item) {
        ame_sale_write_item_row(
            $db,
            $draft_id,
            $item,
            false
        );
    }
}

function ame_draft_store_service(
    $db,
    $draft_id,
    $engineer_id,
    $service_type,
    $service_amount
) {
    ame_sale_draft_require_finance();
    $lines = [];

    if ($service_amount > 0) {
        $lines[] = [
            'engineer_id' => $engineer_id,
            'service_type' => $service_type,
            'amount' => $service_amount,
        ];
    }

    ame_sale_replace_services($db, $draft_id, $lines);
}

function ame_draft_store_services($db, $draft_id, $lines)
{
    ame_sale_draft_require_finance();
    ame_sale_replace_services($db, $draft_id, $lines);
}

/**
 * রি-ওপেন: কাজের বিল সারি সরাসরি sale_services + service_amount রাখে (resolve ব্যতিকা না)।
 *
 * @param list<array<string, mixed>> $lines
 */
function ame_draft_restore_service_lines(mysqli $db, int $draft_id, array $lines): void
{
    ame_sale_draft_require_finance();
    $draft_id = (int)$draft_id;
  if (!function_exists('ame_engineer_statement_resolve_id')) {
        $engPost = dirname(__DIR__) . '/engineer_statement_post.php';
        if (is_file($engPost)) {
            require_once $engPost;
        }
    }
    $del = $db->prepare('DELETE FROM sale_services WHERE sale_id = ?');
    if ($del) {
        $del->bind_param('i', $draft_id);
        $del->execute();
        $del->close();
    }
    $total = 0.0;
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        $amount = round((float)($line['amount'] ?? 0), 2);
        if ($amount <= 0.009) {
            continue;
        }
        $type = trim((string)($line['service_type'] ?? ''));
        if ($type === '') {
            $type = 'কাজের বিল';
        }
        $engineer_id = (int)($line['engineer_id'] ?? 0);
        $query = trim((string)($line['engineer_query'] ?? $line['engineer_name'] ?? ''));
        if ($engineer_id <= 0 && $query !== '' && function_exists('ame_engineer_statement_resolve_id')) {
            $engineer_id = ame_engineer_statement_resolve_id($db, 0, $query);
        }
        if (
            $engineer_id <= 0
            && function_exists('ame_engineer_statement_open_engineers')
        ) {
            $open = ame_engineer_statement_open_engineers($db);
            if (count($open) === 1) {
                $engineer_id = (int)$open[0]['id'];
            }
        }
        if (function_exists('ame_sale_store_service')) {
            ame_sale_store_service($db, $draft_id, $engineer_id, $type, $amount);
        }
        $total += $amount;
    }
    if (abs($total) < 0.01) {
        return;
    }
    $up = @$db->prepare('UPDATE sales SET service_amount = ? WHERE id = ?');
    if ($up) {
        $up->bind_param('di', $total, $draft_id);
        $up->execute();
        $up->close();
    }
}

function ame_draft_store_payments(
    $db,
    $draft_id,
    $payments,
    $user_id
) {
    $delete = $db->prepare(
        "DELETE FROM sale_payments
         WHERE sale_id = ?
           AND payment_status = 'planned'"
    );

    $delete->bind_param(
        'i',
        $draft_id
    );

    $delete->execute();
    $delete->close();

    foreach ($payments as $payment) {
        $account_id = (int)$payment['account_id'];
        $amount = (float)$payment['amount'];

        $reference_number = (string)(
            $payment['reference_number'] ?? ''
        );

        $note = 'খসড়া পরিকল্পিত পরিশোধ';

        $insert = $db->prepare(
            "INSERT INTO sale_payments (
                sale_id,
                account_id,
                amount,
                reference_number,
                payment_status,
                note,
                created_by
             ) VALUES (
                ?, ?, ?, ?, 'planned', ?, ?
             )"
        );

        $insert->bind_param(
            'iidssi',
            $draft_id,
            $account_id,
            $amount,
            $reference_number,
            $note,
            $user_id
        );

        $insert->execute();
        $insert->close();
    }
}

function ame_draft_cancel(
    $db,
    $draft_id,
    $user_id,
    $is_admin,
    $is_english
) {
    ame_draft_assert_editable(
        $db,
        $draft_id,
        $user_id,
        $is_admin,
        $is_english
    );

    $stmt = $db->prepare(
        "UPDATE sales
         SET status = 'cancelled'
         WHERE id = ?
           AND status = 'draft'"
    );

    $stmt->bind_param(
        'i',
        $draft_id
    );

    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        $stmt->close();

        ame_sale_fail(
            $is_english,
            'ড্রাফট বাতিল করা যায়নি।',
            'The draft could not be cancelled.'
        );
    }

    $stmt->close();
}