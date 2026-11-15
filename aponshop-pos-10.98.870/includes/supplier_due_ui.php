<?php
/**
 * সাপ্লায়ার মোট বকেয়া — আন্তর্জাতিক প্রেজেন্টেশন (কমলা = payable, সবুজ = vendor credit).
 * শুধু দেখানোর হেল্পার; কোনো ব্যালেন্স এখানে বদলায় না।
 */

if (!function_exists('ame_supplier_due_presentation')) {
    /**
     * @return array{class:string,label:string,kind:string,amount:float,text:string,title:string}
     */
    function ame_supplier_due_presentation(float $amount): array
    {
        $amount = round($amount, 2);
        if ($amount < -0.004) {
            return [
                'class' => 'sup-due sup-due--credit',
                'label' => 'অগ্রিম',
                'kind' => 'credit',
                'amount' => $amount,
                'text' => number_format($amount, 2) . ' ৳',
                'title' => 'দোকানের কাছে সাপ্লায়ারের জমা (Vendor credit)',
            ];
        }
        if ($amount > 0.004) {
            return [
                'class' => 'sup-due sup-due--payable',
                'label' => 'বকেয়া',
                'kind' => 'payable',
                'amount' => $amount,
                'text' => number_format($amount, 2) . ' ৳',
                'title' => 'দোকান সাপ্লায়ারকে দেবে (Payable)',
            ];
        }

        return [
            'class' => 'sup-due sup-due--zero',
            'label' => '—',
            'kind' => 'zero',
            'amount' => 0.0,
            'text' => '0.00 ৳',
            'title' => 'কোনো বকেয়া নেই',
        ];
    }
}

if (!function_exists('ame_supplier_due_render_cell')) {
    /** @return string HTML (caller escapes if needed — returns safe structure) */
    function ame_supplier_due_render_cell(float $amount): string
    {
        $p = ame_supplier_due_presentation($amount);
        $cls = htmlspecialchars($p['class'], ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($p['label'], ENT_QUOTES, 'UTF-8');
        $text = htmlspecialchars($p['text'], ENT_QUOTES, 'UTF-8');

        return '<div class="' . $cls . ' sup-due-cell" title="' . $title . '">'
            . '<span class="sup-due-badge">' . $label . '</span>'
            . '<span class="sup-due-amt">' . $text . '</span>'
            . '</div>';
    }
}