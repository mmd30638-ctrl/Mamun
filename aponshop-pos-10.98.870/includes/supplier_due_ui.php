<?php
/**
 * সাপ্লায়ার মোট বকেয়া — আন্তর্জাতিক প্রেজেন্টেশন (কমলা = payable, সবুজ = vendor credit).
 *
 * মানদণ্ড (INVARIANT — অন্য ফাইলে আলাদা রঙ লিখবেন না):
 * ১) যেকোনো `total_due` / বকেয়া **দেখানো** শুধু এই ফাইলের ফাংশন দিয়ে।
 * ২) কমলা #ea580c = দোকান সাপ্লায়ারকে দেবে · সবুজ #16a34a = অগ্রিম (দোকান পাবে)।
 * ৩) এই হেল্পার ব্যালেন্স লেখে না — শুধু ক্রয় / পেমেন্ট / অ্যাডমিন সংশোধনে DB বদলায়।
 *
 * @version 1.0.0
 */
if (!defined('AME_SUPPLIER_DUE_UI_VERSION')) {
    define('AME_SUPPLIER_DUE_UI_VERSION', '1.0.0');
}

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

if (!function_exists('ame_supplier_due_format_taka')) {
    function ame_supplier_due_format_taka(float $amount): string
    {
        $amount = round($amount, 2);
        if ($amount < -0.004) {
            return '−৳' . number_format(abs($amount), 2);
        }

        return '৳' . number_format($amount, 2);
    }
}

if (!function_exists('ame_supplier_due_pick_class')) {
    function ame_supplier_due_pick_class(float $amount): string
    {
        $p = ame_supplier_due_presentation($amount);

        return match ($p['kind']) {
            'payable' => 'sup-due-pick sup-due-pick--payable',
            'credit' => 'sup-due-pick sup-due-pick--credit',
            default => 'sup-due-pick sup-due-pick--zero',
        };
    }
}

if (!function_exists('ame_supplier_due_hero_classes')) {
    /** @return list<string> */
    function ame_supplier_due_hero_classes(float $amount, string ...$base): array
    {
        $p = ame_supplier_due_presentation($amount);
        $extra = match ($p['kind']) {
            'payable' => 'sup-due-close--payable',
            'credit' => 'sup-due-close--credit',
            default => 'sup-due-close--zero',
        };

        return array_merge($base, ['sup-due-hero-wrap', $extra, $p['class']]);
    }
}

if (!function_exists('ame_supplier_due_render_cell')) {
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

if (!function_exists('ame_supplier_due_render_inline')) {
    function ame_supplier_due_render_inline(float $amount, bool $with_badge = true): string
    {
        $p = ame_supplier_due_presentation($amount);
        $cls = htmlspecialchars($p['class'], ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($p['label'], ENT_QUOTES, 'UTF-8');
        $text = htmlspecialchars(ame_supplier_due_format_taka($amount), ENT_QUOTES, 'UTF-8');
        $badge = $with_badge && $p['kind'] !== 'zero'
            ? '<span class="sup-due-badge">' . $label . '</span> '
            : '';

        return '<span class="' . $cls . ' sup-due-inline" title="' . $title . '">' . $badge
            . '<span class="sup-due-amt">' . $text . '</span></span>';
    }
}

if (!function_exists('ame_supplier_due_render_hero')) {
    function ame_supplier_due_render_hero(float $amount, string $amount_id = '', string $label = 'মোট বকেয়া'): string
    {
        $p = ame_supplier_due_presentation($amount);
        $cls = htmlspecialchars($p['class'], ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8');
        $lbl = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $kind_lbl = htmlspecialchars($p['label'], ENT_QUOTES, 'UTF-8');
        $text = htmlspecialchars(ame_supplier_due_format_taka($amount), ENT_QUOTES, 'UTF-8');
        $id_attr = $amount_id !== '' ? ' id="' . htmlspecialchars($amount_id, ENT_QUOTES, 'UTF-8') . '"' : '';

        return '<span class="sup-due-hero-lbl">' . $lbl . '</span>'
            . '<span class="sup-due-hero-kind">' . $kind_lbl . '</span>'
            . '<strong class="sup-due-hero-amt ' . $cls . '"' . $id_attr . ' title="' . $title . '" data-sup-due-amt="1">' . $text . '</strong>';
    }
}

if (!function_exists('ame_supplier_due_kpi_class')) {
    function ame_supplier_due_kpi_class(float $amount): string
    {
        $p = ame_supplier_due_presentation($amount);

        return match ($p['kind']) {
            'payable' => 'sup-due-kpi sup-due-kpi--payable',
            'credit' => 'sup-due-kpi sup-due-kpi--credit',
            default => 'sup-due-kpi sup-due-kpi--zero',
        };
    }
}

if (!function_exists('ame_supplier_due_print_assets')) {
    function ame_supplier_due_print_assets(bool $include_js = true): void
    {
        if (!empty($GLOBALS['ame_supplier_due_assets_done'])) {
            return;
        }
        $GLOBALS['ame_supplier_due_assets_done'] = true;
        $ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.929';
        $v = htmlspecialchars((string)$ver, ENT_QUOTES, 'UTF-8');
        echo '<link rel="stylesheet" href="/assets/css/supplier-due-core.css?v=' . $v . '">' . "\n";
        if ($include_js) {
            echo '<script defer src="/assets/js/supplier-due-ui.js?v=' . $v . '"></script>' . "\n";
        }
    }
}

if (!function_exists('ame_supplier_due_statement_meta')) {
    /**
     * @return array{word:string,class:string,hero_class:string}
     */
    function ame_supplier_due_statement_meta(float $amount): array
    {
        $p = ame_supplier_due_presentation($amount);

        return match ($p['kind']) {
            'payable' => [
                'word' => 'মোট বকেয়া',
                'class' => 'closing sup-due-close--payable',
                'hero_class' => 'sst-total-hero sup-due-hero-wrap sup-due-close--payable sup-due--payable',
            ],
            'credit' => [
                'word' => 'অগ্রিম জমা',
                'class' => 'closing ok sup-due-close--credit',
                'hero_class' => 'sst-total-hero sup-due-hero-wrap sup-due-close--credit sup-due--credit',
            ],
            default => [
                'word' => 'হিসাব সমান',
                'class' => 'closing zero sup-due-close--zero',
                'hero_class' => 'sst-total-hero sup-due-hero-wrap sup-due-close--zero sup-due--zero',
            ],
        };
    }
}

if (!function_exists('ame_supplier_due_render_legend')) {
    /** @param 'compact'|'full' $variant */
    function ame_supplier_due_render_legend(string $variant = 'compact'): string
    {
        $extra = $variant === 'full'
            ? '<span class="sup-due-legend-item"><span class="sup-due-swatch sup-due-swatch--zero" aria-hidden="true"></span><span>ধূসর · সমান</span></span>'
            : '';

        return '<div class="sup-due-legend" role="list" aria-label="বকেয়ার রঙ ব্যাখ্যা">'
            . '<span class="sup-due-legend-item" role="listitem">'
            . '<span class="sup-due-swatch sup-due-swatch--payable" aria-hidden="true"></span>'
            . '<span>কমলা · বকেয়া (দোকান দেবে)</span></span>'
            . '<span class="sup-due-legend-item" role="listitem">'
            . '<span class="sup-due-swatch sup-due-swatch--credit" aria-hidden="true"></span>'
            . '<span>সবুজ · অগ্রিম (দোকান পাবে)</span></span>'
            . $extra
            . '</div>';
    }
}

if (!function_exists('ame_supplier_due_integrity_note')) {
    function ame_supplier_due_integrity_note(): string
    {
        return '<p class="sup-list-shield" role="note">লক: Party Amount Shield — তালিকা/স্টেটমেন্ট খোলা বা ZIP আপডেটে `total_due` নিজে থেকে বদলায় না। শুধু ক্রয়, সাপ্লায়ার পেমেন্ট বা অ্যাডমিন সংশোধন।</p>';
    }
}

if (!function_exists('ame_supplier_due_page_boot')) {
    function ame_supplier_due_page_boot(bool $legend = false, bool $integrity = false, string $legend_variant = 'compact'): void
    {
        ame_supplier_due_print_assets();
        if ($legend) {
            echo ame_supplier_due_render_legend($legend_variant);
        }
        if ($integrity) {
            echo ame_supplier_due_integrity_note();
        }
    }
}