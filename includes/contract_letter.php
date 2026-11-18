<?php
/**
 * Editable contract text for hire and installment customers.
 * Empty text means the default letter. Rows are never deleted here.
 */

function ame_installment_letter_default(): string
{
    return implode("\n\n", [
        'কিস্তি চুক্তিনামা',
        'আমি/আমরা উপরে বর্ণিত পণ্য কিস্তিতে গ্রহণ করলাম। মোট মূল্য, ডাউন পেমেন্ট, কিস্তির সংখ্যা ও ধরন এই চুক্তিতে লেখা অনুযায়ী পরিশোধ করব।',
        'নির্ধারিত সময়ে কিস্তি পরিশোধ না হলে বকেয়া কাস্টমারের খাতায় থাকবে।',
        'এই চুক্তি মুছে ফেলা হবে না। লেখা বদলালে শুধু চুক্তির নামা আপডেট হয়, পুরোনো চুক্তি থাকে।',
        'স্বাক্ষরের জায়গা খালি থাকবে যতক্ষণ না স্বাক্ষর দেওয়া হয়।',
    ]);
}

function ame_hire_letter_desk(): string
{
    return implode("\n", [
        'ভাড়া চুক্তি।',
        'কাস্টমার নির্ধারিত দৈনিক হারে ভাড়া দেবেন। জামানত কাস্টমার বকেয়ায় যোগ হয় না — ওয়ালেটে থাকে।',
        'পণ্য ফেরতের সময় অবস্থা যাচাই হবে। বকেয়া খাতায় থাকবে।',
    ]);
}

function ame_contract_letter_text(?string $saved, string $kind = 'hire'): string
{
    $saved = trim((string)$saved);
    if ($saved !== '') {
        return $saved;
    }
    if ($kind === 'installment') {
        return ame_installment_letter_default();
    }
    return ame_hire_letter_desk();
}

function ame_contract_letter_boot(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $tables = [
        'rental_contracts' => [
            'monthly_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'contract_letter' => 'MEDIUMTEXT NULL',
        ],
        'installment_contracts' => [
            'contract_letter' => 'MEDIUMTEXT NULL',
        ],
    ];
    foreach ($tables as $table => $cols) {
        $have = [];
        $res = @$db->query('SHOW COLUMNS FROM `' . $table . '`');
        if (!$res) {
            continue;
        }
        while ($row = $res->fetch_assoc()) {
            $have[$row['Field']] = true;
        }
        $res->close();
        foreach ($cols as $name => $ddl) {
            if (isset($have[$name])) {
                continue;
            }
            @$db->query('ALTER TABLE `' . $table . '` ADD COLUMN `' . $name . '` ' . $ddl);
        }
    }
}
