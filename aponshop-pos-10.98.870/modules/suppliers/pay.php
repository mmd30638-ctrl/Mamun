<?php
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
require_once '../../includes/supplier_functions.php';
require_once '../../includes/supplier_settlement.php';
require_once '../../includes/supplier_due_ui.php';
ame_spay_ensure($db);

$dash = ame_spay_dashboard($db);
$limit = ame_spay_approval_limit($db);
$sup_id = (int)($_GET['id'] ?? 0);
$edit_id = (int)($_GET['edit'] ?? 0);
$error = (string)($_SESSION['spay_error'] ?? '');
unset($_SESSION['spay_error']);

$alt_col = @$db->query("SHOW COLUMNS FROM suppliers LIKE 'alt_phone'");
$alt_sql = ($alt_col && $alt_col->num_rows > 0) ? ', alt_phone' : '';
ame_supplier_schema_boot($db);
$live = ame_supplier_live_sql($db);
$sup_on = ame_supplier_status_active_sql();
$list_stmt = $db->query("SELECT id, name, phone{$alt_sql}, business_name, party_code, total_due, address
    FROM suppliers
    WHERE {$live} AND {$sup_on}
    ORDER BY name ASC LIMIT 800");
$all = $list_stmt ? $list_stmt->fetch_all(MYSQLI_ASSOC) : [];
$card = $sup_id > 0 ? ame_spay_supplier_card($db, $sup_id) : null;
$invoices = $card ? ame_spay_open_invoices($db, $sup_id, $edit_id) : [];
$edit = ($edit_id > 0) ? ame_spay_fetch($db, $edit_id) : null;
if ($edit && (int)$edit['supplier_id'] !== $sup_id) {
    $edit = null;
}

$ledger_rows = [];
$hist = ['rows' => [], 'legacy' => []];
if ($card) {
    require_once '../../includes/purchase_functions.php';
    $ledger_rows = ame_supplier_ledger_rows($db, $sup_id);
    $hist = ame_spay_history($db, ['supplier_id' => $sup_id]);
}

$account_rows = ame_active_accounts($db);

$ame_body_class = 'ame-spay-page';
include '../../partials/header.php';
ame_supplier_due_page_boot(true, false);

function spay_money(float $n): string
{
    if (function_exists('ame_balance_book_money')) {
        return ame_balance_book_money($n);
    }
    $sign = $n < -0.009 ? '−' : '';
    return $sign . '৳' . number_format(abs($n), 2);
}
?>
<link rel="stylesheet" href="/assets/css/supplier-settlement.css?v=<?php echo htmlspecialchars((string)AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">
<div class="spay">
    <h1><?php echo te('spay_title'); ?></h1>
    <?php if ($error !== ''): ?>
        <div class="spay-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (!$card): ?>
    <div class="spay-kpis" aria-label="<?php echo te('spay_dash'); ?>">
        <?php
        $kpis = [
            ['spay_payable', $dash['payable'], 'danger'],
            ['spay_paid', $dash['paid'], 'ok'],
            ['spay_remain', $dash['remaining'], 'danger'],
            ['spay_overdue', $dash['overdue'], 'danger'],
            ['spay_due_today', $dash['due_today'], ''],
            ['spay_upcoming', $dash['upcoming'], ''],
            ['spay_purchase', $dash['purchase'], ''],
            ['spay_return', $dash['returns'], ''],
            ['spay_charges', $dash['charges'], ''],
            ['spay_net', $dash['net'], ''],
        ];
        foreach ($kpis as $kpi):
        ?>
        <div class="spay-kpi <?php echo $kpi[2]; ?>">
            <span><?php echo te($kpi[0]); ?></span>
            <strong><?php echo spay_money((float)$kpi[1]); ?></strong>
        </div>
        <?php endforeach; ?>
    </div>
    <p class="spay-muted">
        <?php echo te('spay_last_pay'); ?>:
        <?php if ($dash['last_payment']): ?>
            <?php echo htmlspecialchars($dash['last_payment']['name'], ENT_QUOTES, 'UTF-8'); ?>
            · <?php echo spay_money((float)$dash['last_payment']['amount']); ?>
            · <?php echo htmlspecialchars(date('d-m-Y', strtotime($dash['last_payment']['at'])), ENT_QUOTES, 'UTF-8'); ?>
        <?php else: ?>—<?php endif; ?>
        · <?php echo te('spay_threshold'); ?>: <?php echo spay_money($limit); ?>
    </p>
    <?php endif; ?>

    <div class="spay-card">
        <label for="spSearch"><?php echo te('spay_search'); ?></label>
        <input type="search" id="spSearch" autocomplete="off" placeholder="<?php echo te('spay_search'); ?>">
        <div id="spHits" class="spay-no-print"></div>
    </div>

    <?php if (!$card): ?>
        <p class="spay-muted"><?php echo te('spay_pick'); ?></p>
    <?php else: ?>
        <header class="spay-hero">
            <div>
                <h2><?php echo htmlspecialchars((string)$card['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                <p><?php echo htmlspecialchars((string)($card['business_name'] ?: $card['name']), ENT_QUOTES, 'UTF-8'); ?></p>
                <p><?php echo te('spay_mobile'); ?>: <?php echo htmlspecialchars((string)($card['phone'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></p>
                <p><?php echo te('spay_address'); ?>: <?php echo htmlspecialchars((string)($card['address'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></p>
                <p><?php echo te('spay_code'); ?>: <?php echo htmlspecialchars((string)($card['party_code'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?>
                    · <?php echo te('spay_terms'); ?>: <?php echo htmlspecialchars(ame_supplier_payment_term_label((string)($card['payment_terms'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                    · <?php echo te('spay_limit'); ?>: <?php echo spay_money((float)($card['credit_limit'] ?? 0)); ?></p>
            </div>
            <div class="spay-hero-due">
                <?php echo ame_supplier_due_render_hero((float)$card['total_due'], 'spDueNow', te('spay_outstanding')); ?>
            </div>
        </header>

        <div class="spay-kpis">
            <?php
            $mine = [
                ['spay_purchase', (float)($card['purchase_total'] ?? 0), ''],
                ['spay_paid', (float)($card['paid_total'] ?? 0), 'ok'],
                ['spay_return', (float)($card['return_total'] ?? 0), ''],
                ['spay_outstanding', (float)$card['total_due'], ame_supplier_due_kpi_class((float)$card['total_due'])],
                ['spay_today', (float)($card['today_paid'] ?? 0), ''],
                ['spay_last_amt', (float)$card['last_amount'], ''],
                ['spay_overdue', (float)($card['overdue'] ?? 0), 'danger'],
            ];
            foreach ($mine as $kpi):
            ?>
            <div class="spay-kpi <?php echo htmlspecialchars((string)$kpi[2], ENT_QUOTES, 'UTF-8'); ?>">
                <span><?php echo te($kpi[0]); ?></span>
                <strong><?php echo spay_money($kpi[1]); ?></strong>
            </div>
            <?php endforeach; ?>
        </div>

        <p class="spay-live-row">
            <span><?php echo te('spay_prev'); ?> <?php echo ame_supplier_due_render_inline((float)$card['total_due'], false); ?></span>
            <span><?php echo te('spay_paid_live'); ?> <b id="spPaidNow">0.00</b></span>
            <span><?php echo te('spay_after'); ?> <span id="spRemain"><?php echo ame_supplier_due_render_inline((float)$card['total_due'], false); ?></span></span>
        </p>
        <a class="spay-btn primary spay-pay-go" href="#spForm"><?php echo te('spay_pay_btn'); ?></a>

        <form class="spay-card" method="post" action="process_pay.php" enctype="multipart/form-data" id="spForm" data-ame-once="1">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="idempotency_token" value="<?php echo htmlspecialchars(bin2hex(random_bytes(16)), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="supplier_id" value="<?php echo (int)$card['id']; ?>">
            <?php if ($edit): ?><input type="hidden" name="settlement_id" value="<?php echo (int)$edit['id']; ?>"><?php endif; ?>
            <div class="spay-grid">
                <div>
                    <label for="spDate"><?php echo te('spay_date'); ?></label>
                    <input type="date" id="spDate" name="payment_date" required value="<?php echo htmlspecialchars((string)($edit['payment_date'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div>
                    <label for="spAmount"><?php echo te('spay_amount'); ?></label>
                    <input type="number" id="spAmount" name="amount" min="0.01" step="0.01" required value="<?php echo $edit ? htmlspecialchars((string)$edit['amount'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
                <div>
                    <label for="spCur"><?php echo te('spay_currency'); ?></label>
                    <select id="spCur" name="currency">
                        <?php foreach (ame_spay_currencies() as $cur): ?>
                        <option value="<?php echo $cur; ?>" <?php echo (($edit['currency'] ?? 'BDT') === $cur) ? 'selected' : ''; ?>><?php echo $cur; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="spRateBox">
                    <label for="spRate"><?php echo te('spay_rate'); ?></label>
                    <input type="number" id="spRate" name="fx_rate" min="0.000001" step="0.000001" value="<?php echo htmlspecialchars((string)($edit['fx_rate'] ?? '1'), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div>
                    <label for="spMethod"><?php echo te('spay_method'); ?></label>
                    <select id="spMethod" name="method">
                        <?php foreach (ame_spay_methods() as $key => $label): ?>
                        <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($edit['method'] ?? '') === $key) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="spAccount"><?php echo te('spay_account'); ?></label>
                    <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="pay" id="spAccount" name="account_id" required>
                        <?php echo ame_render_account_options($account_rows, (int)($edit['account_id'] ?? 0)); ?>
                    </select>
                </div>
                <div>
                    <label for="spRef"><?php echo te('spay_ref'); ?></label>
                    <input type="text" id="spRef" name="reference_no" maxlength="80" value="<?php echo htmlspecialchars((string)($edit['reference_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div>
                    <label for="spCheque"><?php echo te('spay_cheque'); ?></label>
                    <input type="text" id="spCheque" name="cheque_no" maxlength="40" value="<?php echo htmlspecialchars((string)($edit['cheque_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div>
                    <label for="spFile"><?php echo te('spay_file'); ?></label>
                    <input type="file" id="spFile" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf">
                </div>
                <div style="grid-column: 1 / -1;">
                    <label for="spNote"><?php echo te('spay_note'); ?></label>
                    <textarea id="spNote" name="note" maxlength="500"><?php echo htmlspecialchars((string)($edit['note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>
            <?php if (function_exists('ame_pay_sec_box')): ?>
            <?php echo ame_pay_sec_box('supplier_pay', (int)$card['id'], 'এই কোড না দিলে পেমেন্ট হবে না। একই টাকা দুবার কাটবে না।', 'This code is required. The same amount will not be paid twice.'); ?>
            <?php endif; ?>
            <div class="spay-row spay-submit-near" style="margin: 8px 0 12px;">
                <button class="spay-btn primary" type="submit" name="intent" value="pay" id="spSave"><?php echo te('spay_save'); ?></button>
                <button class="spay-btn" type="submit" name="intent" value="draft"><?php echo te('spay_draft'); ?></button>
                <a class="spay-btn" href="payments-list.php"><?php echo te('spay_hist'); ?></a>
                <a class="spay-btn" href="ledger.php?id=<?php echo (int)$card['id']; ?>"><?php echo te('nav_sup_ledger'); ?></a>
            </div>

            <div class="spay-modes" style="margin: 10px 0;">
                <?php foreach (['oldest' => 'spay_mode_oldest', 'specific' => 'spay_mode_one', 'multiple' => 'spay_mode_many', 'manual' => 'spay_mode_manual'] as $mode => $key): ?>
                <label>
                    <span><?php echo te($key); ?></span>
                    <input type="radio" name="allocation_mode" value="<?php echo $mode; ?>" <?php echo (($edit['allocation_mode'] ?? 'oldest') === $mode) ? 'checked' : ''; ?>>
                </label>
                <?php endforeach; ?>
            </div>

            <div class="spay-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th></th>
                            <th><?php echo te('spay_invoice'); ?></th>
                            <th><?php echo te('spay_date'); ?></th>
                            <th><?php echo te('spay_open'); ?></th>
                            <th><?php echo te('spay_alloc'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$invoices): ?>
                        <tr><td colspan="5"><?php echo te('spay_no_invoice'); ?></td></tr>
                    <?php else: foreach ($invoices as $inv): ?>
                        <tr data-memo="<?php echo htmlspecialchars($inv['memo'], ENT_QUOTES, 'UTF-8'); ?>" data-open="<?php echo htmlspecialchars((string)$inv['open'], ENT_QUOTES, 'UTF-8'); ?>" data-date="<?php echo htmlspecialchars($inv['date'], ENT_QUOTES, 'UTF-8'); ?>">
                            <td><input type="checkbox" name="pick[]" value="<?php echo htmlspecialchars($inv['memo'], ENT_QUOTES, 'UTF-8'); ?>"></td>
                            <td><?php echo htmlspecialchars($inv['memo'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(date('d-m-Y', strtotime($inv['date'])), ENT_QUOTES, 'UTF-8'); ?><br><span class="spay-muted"><?php echo htmlspecialchars($inv['due_on'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><?php echo spay_money((float)$inv['open']); ?></td>
                            <td><input type="number" class="spay-alloc" name="alloc[<?php echo htmlspecialchars($inv['memo'], ENT_QUOTES, 'UTF-8'); ?>]" min="0" step="0.01" value="0"></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="spay-muted"><?php echo te('spay_keys'); ?></p>
        </form>

        <section class="spay-card">
            <h2><?php echo te('spay_memos'); ?></h2>
            <div class="spay-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?php echo te('spay_invoice'); ?></th>
                            <th><?php echo te('spay_date'); ?></th>
                            <th><?php echo te('spay_total'); ?></th>
                            <th><?php echo te('spay_paid_col'); ?></th>
                            <th><?php echo te('spay_due_col'); ?></th>
                            <th><?php echo te('spay_status'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($card['memos'])): ?>
                        <tr><td colspan="7"><?php echo te('spay_no_invoice'); ?></td></tr>
                    <?php else: foreach ($card['memos'] as $memo):
                        $st = (string)$memo['status'];
                        $stKey = $st === 'paid' ? 'spay_status_paid' : ($st === 'partial' ? 'spay_status_partial' : 'spay_status_due');
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($memo['memo'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(date('d-m-Y', strtotime($memo['date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="spay-amt"><?php echo spay_money((float)$memo['bill']); ?></td>
                            <td class="spay-amt"><?php echo spay_money((float)$memo['paid']); ?></td>
                            <td class="spay-amt"><?php echo spay_money((float)$memo['due']); ?></td>
                            <td><span class="spay-badge <?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>"><?php echo te($stKey); ?></span></td>
                            <td><?php if ($memo['due'] > 0.009): ?><button type="button" class="spay-btn" data-pay-memo="<?php echo htmlspecialchars($memo['memo'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo te('spay_pay_memo'); ?></button><?php endif; ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="spay-card">
            <h2><?php echo te('spay_hist'); ?></h2>
            <div class="spay-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?php echo te('spay_id'); ?></th>
                            <th><?php echo te('spay_date'); ?></th>
                            <th><?php echo te('spay_amount'); ?></th>
                            <th><?php echo te('spay_method'); ?></th>
                            <th><?php echo te('spay_ref'); ?></th>
                            <th><?php echo te('spay_invoice'); ?></th>
                            <th><?php echo te('spay_prev'); ?></th>
                            <th><?php echo te('spay_after'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($hist['rows'])): ?>
                        <tr><td colspan="9"><?php echo te('spay_empty'); ?></td></tr>
                    <?php else: foreach ($hist['rows'] as $pay): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)$pay['payment_no'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)$pay['payment_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="spay-amt"><?php echo spay_money((float)$pay['amount_bdt']); ?></td>
                            <td><?php echo htmlspecialchars(ame_spay_methods()[$pay['method']] ?? $pay['method'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)($pay['reference_no'] ?: $pay['cheque_no'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)($pay['invoices'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo spay_money((float)$pay['previous_due']); ?></td>
                            <td><?php echo spay_money((float)$pay['remaining_due']); ?></td>
                            <td class="spay-row">
                                <a class="spay-btn" href="receipt.php?id=<?php echo (int)$pay['id']; ?>"><?php echo te('spay_view'); ?></a>
                                <a class="spay-btn" href="receipt.php?id=<?php echo (int)$pay['id']; ?>&print=1"><?php echo te('spay_print'); ?></a>
                                <a class="spay-btn" href="receipt.php?id=<?php echo (int)$pay['id']; ?>&amp;share=1"><?php echo te('spay_share'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="spay-card">
            <h2><?php echo te('spay_ledger'); ?></h2>
            <div class="spay-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?php echo te('spay_col_date'); ?></th>
                            <th><?php echo te('spay_col_ref'); ?></th>
                            <th><?php echo te('spay_col_memo'); ?></th>
                            <th><?php echo te('spay_col_desc'); ?></th>
                            <th><?php echo te('spay_col_purchase'); ?></th>
                            <th><?php echo te('spay_col_payment'); ?></th>
                            <th><?php echo te('spay_col_return'); ?></th>
                            <th><?php echo te('spay_col_balance'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$ledger_rows): ?>
                        <tr><td colspan="8"><?php echo te('spay_empty'); ?></td></tr>
                    <?php else: foreach ($ledger_rows as $lrow):
                        $lk = (string)($lrow['kind'] ?? '');
                        $la = (float)$lrow['amount'];
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date('d-m-Y', strtotime((string)$lrow['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)($lrow['ref_no'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)($lrow['ref'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)($lrow['note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="spay-amt"><?php echo in_array($lk, ['purchase', 'opening'], true) ? spay_money($la) : '—'; ?></td>
                            <td class="spay-amt"><?php echo in_array($lk, ['payment', 'reversal'], true) ? spay_money($la) : '—'; ?></td>
                            <td class="spay-amt"><?php echo $lk === 'return' ? spay_money($la) : '—'; ?></td>
                            <td class="spay-amt"><?php echo spay_money((float)$lrow['_bal']); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>
<script>
(function () {
    const people = <?php echo json_encode(array_map(static function ($p) {
        return [
            'id' => (int)$p['id'],
            'name' => (string)$p['name'],
            'phone' => (string)($p['phone'] ?? ''),
            'biz' => (string)($p['business_name'] ?? ''),
            'code' => (string)($p['party_code'] ?? ''),
            'alt' => (string)($p['alt_phone'] ?? ''),
            'due' => (float)$p['total_due'],
        ];
    }, $all), JSON_UNESCAPED_UNICODE); ?>;
    const box = document.getElementById('spSearch');
    const hits = document.getElementById('spHits');
    let active = 0;
    function draw(list) {
        hits.innerHTML = '';
        list.slice(0, 12).forEach(function (p, i) {
            const row = document.createElement('div');
            row.className = 'spay-hit' + (i === active ? ' is-on' : '');
            row.tabIndex = -1;
            const meta = document.createElement('span');
            meta.textContent = p.name + (p.code ? ' · ' + p.code : '') + (p.phone ? ' · ' + p.phone : '') + ' · ';
            const dueEl = document.createElement('span');
            if (window.ameSupDuePresentation) {
                const pr = window.ameSupDuePresentation(p.due);
                dueEl.className = pr.pickClass;
                dueEl.textContent = pr.text;
            } else {
                dueEl.textContent = p.due.toFixed(2);
            }
            row.appendChild(meta);
            row.appendChild(dueEl);
            row.addEventListener('click', function () { location.href = 'pay.php?id=' + p.id; });
            hits.appendChild(row);
        });
    }
    function filtered() {
        const q = box.value.trim().toLowerCase();
        if (!q) return <?php echo $card ? '[]' : 'people.slice(0, 12)'; ?>;
        return people.filter(function (p) {
            return (p.name + ' ' + p.phone + ' ' + (p.alt || '') + ' ' + p.biz + ' ' + p.code).toLowerCase().indexOf(q) !== -1;
        });
    }
    box.addEventListener('input', function () { active = 0; draw(filtered()); });
    box.addEventListener('keydown', function (e) {
        const list = filtered();
        if (e.key === 'ArrowDown') { active = Math.min(active + 1, Math.max(list.length - 1, 0)); draw(list); e.preventDefault(); }
        if (e.key === 'ArrowUp') { active = Math.max(active - 1, 0); draw(list); e.preventDefault(); }
        if (e.key === 'Enter' && list[active]) { e.preventDefault(); location.href = 'pay.php?id=' + list[active].id; }
        if (e.key === 'Escape') { hits.innerHTML = ''; }
    });
    <?php if (!$card): ?>draw(people.slice(0, 8));<?php endif; ?>

    const due = <?php echo json_encode((float)($card['total_due'] ?? 0)); ?>;
    const amount = document.getElementById('spAmount');
    const rate = document.getElementById('spRate');
    const cur = document.getElementById('spCur');
    const remain = document.getElementById('spRemain');
    function bdt() {
        const n = parseFloat(amount && amount.value || '0') || 0;
        const r = cur && cur.value !== 'BDT' ? (parseFloat(rate && rate.value || '0') || 0) : 1;
        return Math.round(n * r * 100) / 100;
    }
    function paintAlloc() {
        if (!amount) return;
        const mode = (document.querySelector('input[name="allocation_mode"]:checked') || {}).value || 'oldest';
        const rows = Array.from(document.querySelectorAll('tr[data-memo]'));
        const pay = bdt();
        let left = pay;
        rows.forEach(function (row) {
            const input = row.querySelector('.spay-alloc');
            const box = row.querySelector('input[type="checkbox"]');
            const open = parseFloat(row.getAttribute('data-open') || '0') || 0;
            input.readOnly = mode !== 'manual';
            let take = 0;
            const chosen = box && box.checked;
            if (mode === 'oldest' || (mode === 'multiple' && chosen) || (mode === 'specific' && chosen)) {
                take = Math.min(open, Math.max(left, 0));
                if (mode === 'specific' && chosen) take = Math.min(open, pay);
            }
            if (mode !== 'manual') {
                input.value = take > 0 ? take.toFixed(2) : '0';
                if (mode !== 'specific') left = Math.round((left - take) * 100) / 100;
            }
        });
        const paidNow = document.getElementById('spPaidNow');
        const leftAfter = Math.round((due - pay) * 100) / 100;
        if (paidNow) paidNow.textContent = pay.toFixed(2);
        if (remain && window.ameSupDuePresentation) {
            const pr = window.ameSupDuePresentation(leftAfter);
            remain.innerHTML = '<span class="' + pr.className + ' sup-due-inline"><span class="sup-due-amt">' + pr.text + '</span></span>';
        } else if (remain) {
            remain.textContent = (leftAfter < -0.009 ? '−' : '') + '৳' + Math.abs(leftAfter).toFixed(2);
        }
        document.getElementById('spRateBox').style.display = cur && cur.value === 'BDT' ? 'none' : '';
    }
    if (amount) {
        ['input', 'change'].forEach(function (ev) {
            amount.addEventListener(ev, paintAlloc);
            rate.addEventListener(ev, paintAlloc);
            cur.addEventListener(ev, paintAlloc);
        });
        document.querySelectorAll('input[name="allocation_mode"], input[name="pick[]"]').forEach(function (el) {
            el.addEventListener('change', paintAlloc);
        });
        paintAlloc();
        document.querySelectorAll('[data-pay-memo]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const memo = btn.getAttribute('data-pay-memo');
                const specific = document.querySelector('input[name="allocation_mode"][value="specific"]');
                if (specific) specific.checked = true;
                document.querySelectorAll('input[name="pick[]"]').forEach(function (box) {
                    box.checked = box.value === memo;
                });
                document.querySelectorAll('tr[data-memo]').forEach(function (row) {
                    if (row.getAttribute('data-memo') === memo && amount) {
                        const open = parseFloat(row.getAttribute('data-open') || '0') || 0;
                        if (open > 0) amount.value = open.toFixed(2);
                    }
                });
                paintAlloc();
                if (amount) amount.focus();
                const form = document.getElementById('spForm');
                if (form) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }
})();
</script>
<?php include '../../partials/footer.php'; ?>
