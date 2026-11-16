<?php
/* 10.98.715 · 10.98.714 — পার্টনারশিপ খরচের ফর্ম, তালিকা ও বাতিল-খরচ (শুধু দেখানো; হিসাবের কোড নয়) */

function pa_expense_form_html($ym, array $cats, $formId = 'paExpAdd')
{
    $h = static function ($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    };
    ob_start();
    ?>
    <form class="pa-form pa-exp-form no-print" method="POST" action="actions/partner_accounting.php" enctype="multipart/form-data" id="<?php echo $h($formId); ?>" data-pa-exp-form="1">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save_expense">
        <input type="hidden" name="month" value="<?php echo $h($ym); ?>">
        <input type="hidden" name="idempotency_token" value="<?php echo $h(bin2hex(random_bytes(16))); ?>">
        <label>তারিখ<input type="date" name="expense_date" value="<?php echo $h(date('Y-m-d')); ?>" max="<?php echo $h(date('Y-m-d')); ?>" required></label>
        <label>খাত
            <select name="category">
                <?php foreach ($cats as $k => $lab): ?>
                    <option value="<?php echo $h($k); ?>"><?php echo $h($lab); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>টাকা<input type="number" step="0.01" min="0.01" name="amount" placeholder="0.00" inputmode="decimal" required></label>
        <label>পেমেন্ট মাধ্যম
            <select name="payment_method">
                <?php foreach (pa_payment_methods() as $k => $lab): ?>
                    <option value="<?php echo $h($k); ?>"><?php echo $h($lab); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>প্রাপক / দোকান<input type="text" name="payee" maxlength="120" placeholder="কাকে দেওয়া হলো"></label>
        <label>রেফারেন্স / মেমো নং<input type="text" name="reference" maxlength="80" placeholder="ঐচ্ছিক"></label>
        <label class="wide">বিবরণ<input type="text" name="description" maxlength="255" placeholder="কীসের খরচ"></label>
        <label class="wide">নোট<input type="text" name="note" maxlength="255" placeholder="ঐচ্ছিক"></label>
        <label class="wide"><?php echo ame_l('রসিদ / ছবি (জেপিজি, পিএনজি, ওয়েবপি, পিডিএফ · সর্বোচ্চ ৫ এমবি)','Receipt / photo (JPG, PNG, WEBP, PDF · max 5 MB)'); ?><input type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf"></label>
        <button type="submit" class="pa-btn red wide">খরচ সাবমিট করুন</button>
    </form>
    <?php
    return (string)ob_get_clean();
}

/** খাতভিত্তিক মোট (সক্রিয় সারি থেকে) */
function pa_expense_category_totals(array $rows, array $cats)
{
    $out = [];
    $sum = 0.0;
    foreach ($rows as $r) {
        $k = (string)($r['category'] ?? 'other');
        $out[$k] = ($out[$k] ?? 0.0) + pa_money($r['amount'] ?? 0);
        $sum += pa_money($r['amount'] ?? 0);
    }
    arsort($out);
    $res = [];
    foreach ($out as $k => $v) {
        $res[] = ['label' => (string)($cats[$k] ?? $k), 'amount' => pa_money($v), 'pct' => $sum > 0.009 ? round($v / $sum * 100, 1) : 0];
    }
    return $res;
}

function pa_expense_table_html(array $rows, array $cats, $ym, $locked)
{
    $h = static function ($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    };
    $methods = pa_payment_methods();
    $sum = 0.0;
    ob_start();
    ?>
    <div class="pa-table-wrap">
        <table class="pa-table pa-exp-table">
            <thead>
                <tr><th>ভাউচার</th><th>তারিখ</th><th>খাত</th><th>প্রাপক</th><th>মাধ্যম</th><th>বিবরণ</th><th class="num">টাকা</th><th class="no-print"></th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $ex): $sum += pa_money($ex['amount']); ?>
                    <tr>
                        <td data-label="ভাউচার"><b><?php echo $h(pa_expense_voucher_label($ex)); ?></b></td>
                        <td data-label="তারিখ"><?php echo $h(date('d-m-Y', strtotime((string)$ex['expense_date']))); ?></td>
                        <td data-label="খাত"><?php echo $h($cats[$ex['category']] ?? $ex['category']); ?></td>
                        <td data-label="প্রাপক"><?php echo $h((string)($ex['payee'] ?? '')) ?: '—'; ?></td>
                        <td data-label="মাধ্যম"><?php echo $h($methods[$ex['payment_method'] ?? 'cash'] ?? ($ex['payment_method'] ?? '')); ?></td>
                        <td data-label="বিবরণ"><?php echo $h($ex['description']); ?><?php if (trim((string)($ex['reference'] ?? '')) !== ''): ?> <small>· রেফ <?php echo $h($ex['reference']); ?></small><?php endif; ?></td>
                        <td class="num" data-label="টাকা"><?php echo pa_taka($ex['amount']); ?></td>
                        <td class="no-print pa-exp-act" data-label="">
                            <a class="pa-btn soft" href="partner_expense_voucher.php?id=<?php echo (int)$ex['id']; ?>" target="_blank" rel="noopener">ভাউচার</a>
                            <?php if (!empty($ex['receipt_path'])): ?><a class="pa-btn soft" href="<?php echo $h($ex['receipt_path']); ?>" target="_blank" rel="noopener">রসিদ</a><?php endif; ?>
                            <?php if (!$locked): ?>
                            <form method="POST" action="actions/partner_accounting.php" data-pa-void="1">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="void_expense">
                                <input type="hidden" name="month" value="<?php echo $h($ym); ?>">
                                <input type="hidden" name="id" value="<?php echo (int)$ex['id']; ?>">
                                <input type="hidden" name="reason" value="">
                                <button type="submit" class="pa-btn red">বাতিল</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="6" data-label="মোট খরচ">মোট খরচ (<?php echo (int)count($rows); ?>টি)</td><td class="num" data-label="টাকা"><?php echo pa_taka($sum); ?></td><td class="no-print"></td></tr>
            </tfoot>
        </table>
    </div>
    <script>
    (function () {
        document.querySelectorAll('form[data-pa-void]').forEach(function (f) {
            if (f.dataset.bound) return;
            f.dataset.bound = '1';
            f.addEventListener('submit', function (e) {
                var r = window.prompt('কেন বাতিল করছেন? (কারণ লিখুন — হিসাব থেকে বাদ যাবে, রেকর্ড থেকে যাবে)');
                if (r === null || r.trim() === '') { e.preventDefault(); return; }
                f.querySelector('input[name=reason]').value = r.trim();
            });
        });
        document.querySelectorAll('form[data-pa-exp-form]').forEach(function (f) {
            if (f.dataset.bound) return;
            f.dataset.bound = '1';
            f.addEventListener('submit', function () {
                var b = f.querySelector('button[type=submit]');
                if (b) { setTimeout(function () { b.disabled = true; b.textContent = 'সেভ হচ্ছে…'; }, 0); }
            });
        });
    })();
    </script>
    <?php
    return (string)ob_get_clean();
}

function pa_voided_expense_html(array $rows, array $cats)
{
    if (!$rows) {
        return '';
    }
    $h = static function ($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    };
    ob_start();
    ?>
    <details class="pa-exp-void no-print">
        <summary>বাতিল খরচ (<?php echo (int)count($rows); ?>) — হিসাবে ধরা হয় না</summary>
        <div class="pa-table-wrap">
            <table class="pa-table">
                <thead><tr><th>ভাউচার</th><th>তারিখ</th><th>খাত</th><th>বিবরণ</th><th class="num">টাকা</th><th>বাতিলের কারণ</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $ex): ?>
                    <tr style="opacity:.75">
                        <td data-label="ভাউচার"><s><?php echo $h(pa_expense_voucher_label($ex)); ?></s></td>
                        <td data-label="তারিখ"><?php echo $h(date('d-m-Y', strtotime((string)$ex['expense_date']))); ?></td>
                        <td data-label="খাত"><?php echo $h($cats[$ex['category']] ?? $ex['category']); ?></td>
                        <td data-label="বিবরণ"><?php echo $h($ex['description']); ?></td>
                        <td class="num" data-label="টাকা"><s><?php echo pa_taka($ex['amount']); ?></s></td>
                        <td data-label="কারণ"><?php echo $h((string)($ex['void_reason'] ?? '')); ?><?php if (!empty($ex['voided_at'])): ?> <small>· <?php echo $h(date('d-m-Y H:i', strtotime((string)$ex['voided_at']))); ?></small><?php endif; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </details>
    <?php
    return (string)ob_get_clean();
}
