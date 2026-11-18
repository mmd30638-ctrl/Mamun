<?php
declare(strict_types=1);

/**
 * CLI guard: ড্রাফটে আনুন (reopen_draft) কখনো void_lines বা customer দিয়ে ওভাররাইড হবে না।
 */
$root = dirname(__DIR__);
require_once $root . '/includes/sales/sale_admin_edit.php';

function ame_test_resolve_save_action(string $posted, string $intent, array $items): string
{
    $save_action = $posted !== '' ? $posted : 'customer';
    $intent_action = strtolower(trim($intent));
    if ($intent_action === 'reopen_draft') {
        $save_action = 'reopen_draft';
    }
    if (
        ame_sale_admin_post_has_voided_lines($items)
        && $save_action !== 'reopen_draft'
        && $save_action !== 'void_lines'
    ) {
        $save_action = 'void_lines';
    }

    return $save_action;
}

$voided = [['product_id' => 1, 'void' => '1']];

$cases = [
    ['posted' => 'reopen_draft', 'intent' => '', 'items' => $voided, 'want' => 'reopen_draft'],
    ['posted' => 'customer', 'intent' => 'reopen_draft', 'items' => $voided, 'want' => 'reopen_draft'],
    ['posted' => 'customer', 'intent' => '', 'items' => $voided, 'want' => 'void_lines'],
    ['posted' => 'void_lines', 'intent' => '', 'items' => $voided, 'want' => 'void_lines'],
    ['posted' => 'reopen_draft', 'intent' => '', 'items' => [], 'want' => 'reopen_draft'],
];

$failed = 0;
foreach ($cases as $i => $case) {
    $got = ame_test_resolve_save_action($case['posted'], $case['intent'], $case['items']);
    if ($got !== $case['want']) {
        echo 'FAIL case ' . $i . ": want {$case['want']}, got {$got}\n";
        $failed++;
    }
}

if ($failed > 0) {
    exit(1);
}

echo "OK invoice_edit reopen save_action tests (" . count($cases) . " cases)\n";