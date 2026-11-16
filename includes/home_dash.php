<?php
/**
 * কে কি করলো popup: the old live activity desk, plus today's memos and payments.
 * Read only. No edit, delete, or save.
 */

function ame_home_dash_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ame_home_dash_money($amount): string
{
    return number_format((float)$amount, 2);
}

function ame_home_dash_render(mysqli $db, array $in = []): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }
    if (!function_exists('ame_live_work_payload')) {
        require_once __DIR__ . '/live_work.php';
    }
    $pack = ame_live_work_payload($db, $in);
    $span = $pack['span'] ?? [];
    $kpis = $pack['kpis'] ?? [];
    $mode = (string)($span['mode'] ?? 'live');
    $modes = [
        'live' => 'লাইভ',
        'today' => 'আজ',
        'yesterday' => 'গতকাল',
        'week' => 'সপ্তাহ',
        'month' => 'মাস',
        'year' => 'বছর',
        'date' => 'তারিখ',
    ];
    $chartRows = $pack['hours'] ?? [];
    $chartTitle = 'ঘণ্টার ছন্দ';
    if (($span['chart'] ?? '') === 'days') {
        $chartRows = $pack['days'] ?? [];
        $chartTitle = 'দিনের ছন্দ';
    } elseif (($span['chart'] ?? '') === 'months') {
        $chartRows = $pack['months'] ?? [];
        $chartTitle = 'বছরের ছন্দ';
    }
    $today = date('Y-m-d');
    $sales = [];
    $payments = [];
    $saleCount = 0;
    $saleTotal = 0.0;
    $payCount = 0;
    $payTotal = 0.0;

    $saleSum = @$db->prepare(
        "SELECT COUNT(*) AS n, COALESCE(SUM(net_payable), 0) AS total
         FROM sales
         WHERE status = 'completed'
           AND DATE(COALESCE(completed_at, sale_date, created_at)) = ?"
    );
    if ($saleSum) {
        $saleSum->bind_param('s', $today);
        $saleSum->execute();
        $row = $saleSum->get_result()->fetch_assoc();
        $saleCount = (int)($row['n'] ?? 0);
        $saleTotal = (float)($row['total'] ?? 0);
        $saleSum->close();
    }
    $saleList = @$db->prepare(
        "SELECT s.id, s.invoice_number, s.net_payable, s.paid_amount,
                COALESCE(s.completed_at, s.sale_date, s.created_at) AS when_at,
                c.name AS customer_name
         FROM sales s
         LEFT JOIN customers c ON c.id = s.customer_id
         WHERE s.status = 'completed'
           AND DATE(COALESCE(s.completed_at, s.sale_date, s.created_at)) = ?
         ORDER BY s.id DESC
         LIMIT 400"
    );
    if ($saleList) {
        $saleList->bind_param('s', $today);
        $saleList->execute();
        $res = $saleList->get_result();
        while ($row = $res->fetch_assoc()) {
            $sales[] = $row;
        }
        $saleList->close();
    }

    $paySum = @$db->prepare(
        "SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS paid
         FROM customer_payments
         WHERE DATE(payment_date) = ?"
    );
    if ($paySum) {
        $paySum->bind_param('s', $today);
        $paySum->execute();
        $row = $paySum->get_result()->fetch_assoc();
        $payCount = (int)($row['n'] ?? 0);
        $payTotal = (float)($row['paid'] ?? 0);
        $paySum->close();
    }
    $payList = @$db->prepare(
        "SELECT p.amount, p.payment_date, c.name AS customer_name
         FROM customer_payments p
         LEFT JOIN customers c ON c.id = p.customer_id
         WHERE DATE(p.payment_date) = ?
         ORDER BY p.id DESC
         LIMIT 400"
    );
    if ($payList) {
        $payList->bind_param('s', $today);
        $payList->execute();
        $res = $payList->get_result();
        while ($row = $res->fetch_assoc()) {
            $payments[] = $row;
        }
        $payList->close();
    }
    $pickedUser = (int)($pack['user'] ?? 0);
    ?>
<div class="hd-split" data-hd-mode-now="<?php echo ame_home_dash_h($mode); ?>">
    <section class="hd-box hd-live">
        <h3>কে কী করলো</h3>
        <p class="hd-sum">লাইভ <b class="hd-green"><?php echo (int)($kpis['live'] ?? 0); ?></b>
            · কাজ করছেন <b class="hd-navy"><?php echo (int)($kpis['active'] ?? 0); ?></b>
            · মোট কাজ <b class="hd-teal"><?php echo (int)($kpis['actions'] ?? 0); ?></b>
            · বিক্রয় <b class="hd-green"><?php echo (int)($kpis['sales_n'] ?? 0); ?></b>
            · <b class="hd-green">৳<?php echo ame_home_dash_money($kpis['sales_amt'] ?? 0); ?></b></p>
        <p class="hd-note"><?php echo !empty($span['live']) ? 'লাইভ' : 'স্থির দৃশ্য'; ?>
            · <?php echo ame_home_dash_h(($span['from'] ?? '') === ($span['to'] ?? '') ? ($span['from'] ?? '') : (($span['from'] ?? '') . ' → ' . ($span['to'] ?? ''))); ?>
            · <?php echo ame_home_dash_h($pack['now_clock'] ?? ''); ?></p>
        <div class="hd-modes">
            <?php foreach ($modes as $key => $label): ?>
            <button type="button" class="hd-chip<?php echo $mode === $key ? ' on' : ''; ?>" data-hd-mode="<?php echo ame_home_dash_h($key); ?>"><?php echo ame_home_dash_h($label); ?></button>
            <?php endforeach; ?>
        </div>
        <div class="hd-filters">
            <label>তারিখ<input type="date" id="hdDate" value="<?php echo ame_home_dash_h($span['date'] ?? $today); ?>"></label>
            <label>মাস<input type="month" id="hdMonth" value="<?php echo ame_home_dash_h($span['month'] ?? substr($today, 0, 7)); ?>"></label>
            <label>বছর
                <select id="hdYear">
                    <?php
                    $yy = (int)date('Y');
                    for ($y = $yy + 1; $y >= $yy - 6; $y--) {
                        $sel = ((string)$y === (string)($span['year'] ?? $yy)) ? ' selected' : '';
                        echo '<option value="' . $y . '"' . $sel . '>' . $y . '</option>';
                    }
                    ?>
                </select>
            </label>
            <label>ব্যবহারকারী
                <select id="hdUser">
                    <option value="0">সবাই</option>
                    <?php foreach (($pack['directory'] ?? []) as $person): ?>
                    <option value="<?php echo (int)$person['id']; ?>"<?php echo $pickedUser === (int)$person['id'] ? ' selected' : ''; ?>><?php echo ame_home_dash_h($person['name'] . ' · ' . $person['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="button" class="hd-look" id="hdApply">তারিখ অনুযায়ী দেখুন</button>
        </div>
        <div class="hd-people">
            <?php if (empty($pack['people'])): ?>
            <p class="hd-note">কোনো ব্যবহারকারী নেই।</p>
            <?php else: foreach ($pack['people'] as $person): ?>
            <button type="button" class="hd-person<?php echo $pickedUser === (int)$person['id'] ? ' on' : ''; ?>" data-hd-user="<?php echo (int)$person['id']; ?>">
                <b class="hd-navy"><?php echo ame_home_dash_h($person['name']); ?></b>
                <span><?php echo ame_home_dash_h($person['label']); ?><?php echo $person['designation'] !== '' ? ' · ' . ame_home_dash_h($person['designation']) : ''; ?></span>
                <em class="<?php echo !empty($person['live']) ? 'hd-green' : 'hd-teal'; ?>"><?php echo (int)$person['n']; ?> কাজ<?php echo !empty($person['live']) ? ' · লাইভ' : ''; ?></em>
            </button>
            <?php endforeach; endif; ?>
        </div>
        <p class="hd-chart-title"><?php echo ame_home_dash_h($chartTitle); ?></p>
        <div class="hd-bars">
            <?php foreach ($chartRows as $bar): ?>
            <div class="hd-bar"><i style="height:<?php echo max(6, (int)($bar['pct'] ?? 0)); ?>%"></i><em><?php echo ame_home_dash_h($bar['label'] ?? ''); ?></em></div>
            <?php endforeach; ?>
        </div>
        <ol class="hd-feed">
            <?php if (empty($pack['events'])): ?>
            <li>এই ফিল্টারে কোনো কাজ নেই।</li>
            <?php else: foreach ($pack['events'] as $event): ?>
            <li>
                <time class="hd-teal"><?php echo ame_home_dash_h($event['clock'] ?? ''); ?></time>
                <b class="hd-navy"><?php echo ame_home_dash_h(($event['who'] ?? '') . ' — ' . ($event['title'] ?? '')); ?></b>
                <span><?php echo ame_home_dash_h(trim((string)($event['module'] ?? '') . ' ' . (string)($event['detail'] ?? ''))); ?><?php echo isset($event['amount']) && $event['amount'] !== null ? ' · ৳' . ame_home_dash_money($event['amount']) : ''; ?></span>
            </li>
            <?php endforeach; endif; ?>
        </ol>
    </section>
    <section class="hd-day">
        <article class="hd-box">
            <h3>আজকের বিক্রয় মেমো</h3>
            <p class="hd-sum">মেমো <b class="hd-navy"><?php echo (int)$saleCount; ?></b> · মোট <b class="hd-green">৳<?php echo ame_home_dash_money($saleTotal); ?></b></p>
            <p class="hd-note">নাম চাপলে মেমো দেখা যায়। এডিট, মুছা বা সেভ নেই।</p>
            <div class="hd-scroll">
                <table class="hd-table">
                    <thead><tr><th>মেমো</th><th>কাস্টমার</th><th>মোট</th></tr></thead>
                    <tbody>
                    <?php if (!$sales): ?>
                        <tr><td colspan="3">আজ কোনো বিক্রয় নেই।</td></tr>
                    <?php else: foreach ($sales as $sale): ?>
                        <tr>
                            <td><a class="hd-memo" href="/modules/sales/invoice.php?id=<?php echo (int)$sale['id']; ?>"><?php echo ame_home_dash_h($sale['invoice_number'] ?: ('#' . (int)$sale['id'])); ?></a></td>
                            <td class="hd-teal"><?php echo ame_home_dash_h($sale['customer_name'] ?: 'খুচরা'); ?></td>
                            <td class="hd-green">৳<?php echo ame_home_dash_money($sale['net_payable'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
        <article class="hd-box">
            <h3>আজকের কাস্টমার পেমেন্ট</h3>
            <p class="hd-sum">পেমেন্ট <b class="hd-navy"><?php echo (int)$payCount; ?></b> · মোট <b class="hd-green">৳<?php echo ame_home_dash_money($payTotal); ?></b></p>
            <div class="hd-scroll">
                <table class="hd-table">
                    <thead><tr><th>কাস্টমার</th><th>টাকা</th></tr></thead>
                    <tbody>
                    <?php if (!$payments): ?>
                        <tr><td colspan="2">আজ কোনো পেমেন্ট নেই।</td></tr>
                    <?php else: foreach ($payments as $pay): ?>
                        <tr>
                            <td class="hd-teal"><?php echo ame_home_dash_h($pay['customer_name'] ?: '—'); ?></td>
                            <td class="hd-green">৳<?php echo ame_home_dash_money($pay['amount'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </section>
</div>
    <?php
}
