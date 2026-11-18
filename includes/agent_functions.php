<?php
/**
 * Shop-domain agents — rule models, not a cloud LLM.
 */

function ame_agent_collection(mysqli $db, array $pulse): array
{
    $rows = [];
    foreach ($pulse['dues'] ?? [] as $c) {
        $days = 0;
        $due = (string)($c['next_due_date'] ?? '');
        if ($due !== '') {
            $days = (int)round((time() - strtotime($due)) / 86400);
        }
        $amt = (float)$c['receivable_balance'];
        $last = ame_pulse_scalar($db, "SELECT UNIX_TIMESTAMP(MAX(payment_date)) t FROM customer_payments WHERE customer_id=" . (int)$c['id'], 0);
        $quiet = $last > 0 ? (int)round((time() - (int)$last) / 86400) : 99;
        $score = min(100, max(0, ($days > 0 ? $days * 2 : 8) + min(40, $amt / 500) + min(25, $quiet / 2)));
        $why = [];
        if ($days > 0) {
            $why[] = $days . ' দিন তারিখ পার';
        } elseif ($due === date('Y-m-d')) {
            $why[] = 'আজকের তারিখ';
        }
        $why[] = number_format($amt, 0) . ' টাকা বকেয়া';
        $why[] = $quiet >= 99 ? 'কখনো জমা নেই' : ('শেষ জমা ' . $quiet . ' দিন আগে');
        $rows[] = [
            'id' => (int)$c['id'],
            'name' => (string)$c['name'],
            'phone' => (string)($c['phone'] ?? ''),
            'amount' => $amt,
            'score' => (int)round($score),
            'why' => implode(' · ', $why),
            'due' => $due,
        ];
    }
    usort($rows, static fn($a, $b) => $b['score'] <=> $a['score']);
    $top = $rows[0] ?? null;
    return [
        'id' => 'collection',
        'name' => 'কালেকশন এজেন্ট',
        'domain' => 'Domain model · dues',
        'verdict' => $top ? ($top['name'] . ' — স্কোর ' . $top['score']) : 'এখন তাড়া নেই',
        'next' => $top ? '/modules/customers/payment-receive.php?id=' . $top['id'] : '/modules/customers/due-reminder.php',
        'next_label' => $top ? 'এইজনের জমা নিন' : 'রিমাইন্ডার',
        'why' => $top['why'] ?? 'বকেয়া খাতা খালি',
        'items' => array_slice($rows, 0, 5),
    ];
}

function ame_agent_stock(array $pulse): array
{
    $items = [];
    foreach ($pulse['restock'] ?? [] as $r) {
        $cover = (float)$r['cover_days'];
        $score = min(100, (int)round($r['urgent'] ? 90 - min(40, $cover * 4) : 40 - min(30, $cover)));
        $items[] = [
            'name' => (string)$r['name'],
            'score' => max(1, $score),
            'why' => '১৪ দিনে ' . number_format((float)$r['sold_14'], 1) . ' · আর ~' . ($cover >= 90 ? '৯০+' : (string)round($cover)) . ' দিন · কিনুন ' . (int)$r['suggest'],
            'href' => '/modules/purchase/add.php',
        ];
    }
    $top = $items[0] ?? null;
    return [
        'id' => 'stock',
        'name' => 'স্টক এজেন্ট',
        'domain' => 'Domain model · velocity',
        'verdict' => $top ? $top['name'] : 'রিস্টক চাপ নেই',
        'next' => '#restock',
        'next_label' => 'রিস্টক দেখুন',
        'why' => $top['why'] ?? 'গত ১৪ দিনে জরুরি ঘাটতি নেই',
        'items' => array_slice($items, 0, 5),
    ];
}

function ame_agent_cash(array $pulse): array
{
    $sales = (float)$pulse['today_sales'];
    $got = (float)$pulse['today_collected'];
    $gap = max(0, $sales - $got);
    $rate = $sales > 0 ? ($got / $sales) * 100 : ($got > 0 ? 100 : 0);
    $score = (int)round(min(100, ($pulse['due_over'] * 8) + ($gap / 500) + (100 - min(100, $rate))));
    $why = 'আদায়/বিক্রি ' . number_format($rate, 0) . '% · ফাঁক ' . number_format($gap, 0) . ' টাকা';
    if ($pulse['due_over'] > 0) {
        $why .= ' · ' . (int)$pulse['due_over'] . ' জন পার';
    }
    return [
        'id' => 'cash',
        'name' => 'ক্যাশ এজেন্ট',
        'domain' => 'Domain model · treasury',
        'verdict' => $gap > 0 ? ('আজ ' . number_format($gap, 0) . ' টাকা এখনো খাতায়') : 'আজকের জমা বিক্রির সাথে মিলছে',
        'next' => '/modules/reports/day_closing.php',
        'next_label' => 'দিন শেষ',
        'why' => $why,
        'score' => min(100, $score),
        'items' => [],
    ];
}

function ame_shop_agents(mysqli $db, array $pulse): array
{
    return [
        ame_agent_collection($db, $pulse),
        ame_agent_stock($pulse),
        ame_agent_cash($pulse),
    ];
}

function ame_can_see_agents(mysqli $db): bool
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    if (strtolower((string)($_SESSION['role'] ?? '')) === 'admin') {
        return true;
    }
    return has_permission($db, 'm-agents');
}
