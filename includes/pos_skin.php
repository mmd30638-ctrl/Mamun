<?php
/**
 * বিক্রয় পেজের ডিজাইন থিম — মেনুবারের আইকন থেকে খোলে, কুকিতে সেভ হয়।
 */

function ame_pos_skins(): array
{
    return [
        'workshop' => [
            'id' => 'workshop',
            'name' => 'স্টিল কাউন্টার',
            'name_en' => 'Steel counter',
            'hint' => 'ক্লিন ওয়ার্কশপ — ছবি ছাড়া হালকা কাউন্টার',
            'hint_en' => 'Clean steel workshop, no photo',
            'kind' => 'solid',
            'photo' => '',
            'preview' => 'linear-gradient(135deg,#E8EDF2 0%,#c5d0dc 55%,#087F3D 100%)',
            'swatches' => ['#E8EDF2', '#087F3D', '#0D47A1'],
        ],
        'navy' => [
            'id' => 'navy',
            'name' => 'নেভি অ্যাটেলিয়ে',
            'name_en' => 'Navy atelier',
            'hint' => 'গাঢ় নীল ও সোনালি — লাক্সারি কাউন্টার',
            'hint_en' => 'Deep navy and gold luxury counter',
            'kind' => 'solid',
            'photo' => '',
            'preview' => 'linear-gradient(135deg,#0B1E4D 0%,#1e3a7a 52%,#C9A227 100%)',
            'swatches' => ['#0B1E4D', '#C9A227', '#EEF2FB'],
        ],
        'rose' => [
            'id' => 'rose',
            'name' => 'গোলাপ বাগান',
            'name_en' => 'Rose garden',
            'hint' => 'গোলাপ ও পিওনির প্যাটার্ন সহ গোলাপি কাউন্টার',
            'hint_en' => 'Rose and peony pattern on a blush counter',
            'kind' => 'flower',
            'photo' => '',
            'preview' => 'radial-gradient(circle at 18% 30%, #fff7f5 0 7%, transparent 8%),radial-gradient(circle at 18% 30%, #f4c6d0 8% 16%, transparent 17%),radial-gradient(circle at 72% 38%, #fff 0 6%, transparent 7%),radial-gradient(circle at 72% 38%, #c94a6a 7% 15%, transparent 16%),radial-gradient(circle at 48% 78%, #8b2e4a 0 10%, transparent 11%),linear-gradient(135deg,#fff4f6 0%,#f8d7e0 48%,#e7a0b4 100%)',
            'swatches' => ['#F4C6D0', '#8B2E4A', '#FFF7F5'],
        ],
        'lotus' => [
            'id' => 'lotus',
            'name' => 'পদ্ম সরোবর',
            'name_en' => 'Lotus pond',
            'hint' => 'পদ্মফুলের প্যাটার্ন সহ ক্রিম-টিল কাউন্টার',
            'hint_en' => 'Lotus pattern on a cream-teal counter',
            'kind' => 'flower',
            'photo' => '',
            'preview' => 'radial-gradient(circle at 22% 32%, #f7f3e8 0 8%, transparent 9%),radial-gradient(circle at 22% 32%, #7ec8bf 9% 17%, transparent 18%),radial-gradient(circle at 78% 28%, #d7efea 0 8%, transparent 9%),radial-gradient(circle at 50% 74%, #1f6f6a 0 12%, transparent 13%),linear-gradient(160deg,#f7f3e8 0%,#d7efea 52%,#9fd3cb 100%)',
            'swatches' => ['#D7EFEA', '#1F6F6A', '#F7F3E8'],
        ],
        'teak' => [
            'id' => 'teak',
            'name' => 'সেগুন বাগান',
            'name_en' => 'Teak grove',
            'hint' => 'সেগুন কাঠের দাগ ও গাছের ছায়া সহ উষ্ণ কাউন্টার',
            'hint_en' => 'Teak grain and tree shade on warm wood',
            'kind' => 'tree',
            'photo' => '',
            'preview' => 'repeating-linear-gradient(90deg,rgba(63,42,20,.14) 0 2px,transparent 2px 18px),radial-gradient(ellipse at 28% 18%, #c4a574 0 22%, transparent 52%),radial-gradient(ellipse at 82% 86%, #6b4423 0 26%, transparent 58%),linear-gradient(180deg,#f4ede0 0%,#c4a574 58%,#3f2a14 100%)',
            'swatches' => ['#C4A574', '#3F2A14', '#F4EDE0'],
        ],
        'forest' => [
            'id' => 'forest',
            'name' => 'বনছায়া',
            'name_en' => 'Forest shade',
            'hint' => 'গাছের ছাউনি সহ গাঢ় সবুজ রাতের কাউন্টার',
            'hint_en' => 'Tree-canopy shade on a deep forest counter',
            'kind' => 'tree',
            'photo' => '',
            'preview' => 'radial-gradient(ellipse at 18% 92%, #14532d 0 32%, transparent 33%),radial-gradient(ellipse at 52% 100%, #052e16 0 42%, transparent 43%),radial-gradient(ellipse at 84% 86%, #166534 0 28%, transparent 29%),linear-gradient(180deg,#0b1a12 0%,#143325 42%,#052e16 100%)',
            'swatches' => ['#143325', '#86EFAC', '#0B1A12'],
        ],
    ];
}

function ame_pos_skin_ids(): array
{
    return array_keys(ame_pos_skins());
}

function ame_pos_skin_token_labels(bool $english = false): array
{
    $rows = [
        'page' => ['পেজ', 'কাউন্টারের পটভূমি', 'Page', 'Counter background'],
        'card' => ['কার্ড', 'ফর্ম ও ক্যালকুলেটর বক্স', 'Card', 'Forms and calculator boxes'],
        'border' => ['বর্ডার', 'কার্ডের লাইন', 'Border', 'Card outline'],
        'titleBg' => ['শিরোনাম বক্স', 'সেকশন হেডিং ব্যাকগ্রাউন্ড', 'Title box', 'Section heading background'],
        'titleFg' => ['শিরোনাম লেখা', 'সেকশন হেডিং টেক্সট', 'Title text', 'Section heading text'],
        'btn' => ['বাটন', 'যোগ / অ্যাকশন বাটন', 'Button', 'Add / action buttons'],
        'btnFg' => ['বাটন লেখা', 'বাটনের উপরের টেক্সট', 'Button text', 'Text on buttons'],
        'th' => ['টেবিল হেড', 'কার্ট টেবিলের হেডার', 'Table head', 'Cart table header'],
        'thFg' => ['টেবিল হেড লেখা', 'হেডারের টেক্সট', 'Table head text', 'Header text'],
        'input' => ['ইনপুট', 'সার্চ ও ফিল্ড ব্যাকগ্রাউন্ড', 'Input', 'Search and field background'],
        'inputFg' => ['ইনপুট লেখা', 'ফিল্ডের টেক্সট', 'Input text', 'Field text'],
        'inputBd' => ['ইনপুট বর্ডার', 'ফিল্ডের লাইন', 'Input border', 'Field outline'],
        'text' => ['মূল লেখা', 'কাউন্টার বডি টেক্সট', 'Body text', 'Counter body copy'],
        'muted' => ['হালকা লেখা', 'প্লেসহোল্ডার ও সাহায্য', 'Muted', 'Placeholders and hints'],
        'chip' => ['চিপ', 'ছোট স্ট্যাটাস ব্যাজ', 'Chip', 'Small status badge'],
        'chipFg' => ['চিপ লেখা', 'ব্যাজের টেক্সট', 'Chip text', 'Badge text'],
        'net' => ['নেট টাকা', 'মোট পরিশোধের সংখ্যা', 'Net amount', 'Payable total'],
    ];
    $out = [];
    foreach ($rows as $key => $meta) {
        $out[$key] = [
            'name' => $english ? $meta[2] : $meta[0],
            'hint' => $english ? $meta[3] : $meta[1],
        ];
    }
    return $out;
}

function ame_pos_skin_current(): string
{
    $ids = ame_pos_skin_ids();
    $raw = strtolower(trim((string)($_COOKIE['ame_pos_skin'] ?? 'workshop')));
    $alias = [
        'emerald' => 'teak',
        'night' => 'forest',
        'green' => 'teak',
    ];
    if (isset($alias[$raw])) {
        $raw = $alias[$raw];
    }
    return in_array($raw, $ids, true) ? $raw : 'workshop';
}

function ame_pos_skin_tokens(): array
{
    return [
        'workshop' => [
            'page' => '#ffffff',
            'card' => '#ffffff',
            'border' => '#d9dee5',
            'titleBg' => '#e8edf2',
            'titleFg' => '#1f2937',
            'btn' => '#0d47a1',
            'btnFg' => '#ffffff',
            'radius' => '4px',
            'th' => '#e8edf2',
            'thFg' => '#1f2937',
            'input' => '#ffffff',
            'inputFg' => '#000000',
            'inputBd' => '#cfd6de',
            'text' => '#000000',
            'muted' => '#64748b',
            'chip' => '#e3f2fd',
            'chipFg' => '#0d47a1',
            'net' => '#0d47a1',
            'photo' => 'none',
        ],
        'navy' => [
            'page' => '#e8eef8',
            'card' => '#ffffff',
            'border' => '#c5d8ee',
            'titleBg' => '#0b1e4d',
            'titleFg' => '#f5d76e',
            'btn' => '#0b1e4d',
            'btnFg' => '#f5d76e',
            'radius' => '12px',
            'th' => '#0b1e4d',
            'thFg' => '#ffffff',
            'input' => '#f8fbff',
            'inputFg' => '#0b1e4d',
            'inputBd' => '#9eb6d9',
            'text' => '#0b1e4d',
            'muted' => '#5b6b82',
            'chip' => '#0b1e4d',
            'chipFg' => '#f5d76e',
            'net' => '#0b1e4d',
            'photo' => 'none',
        ],
        'rose' => [
            'page' => '#fff4f6',
            'card' => 'rgba(255, 251, 250, 0.94)',
            'border' => '#f0cfd6',
            'titleBg' => '#8b2e4a',
            'titleFg' => '#fff6f4',
            'btn' => '#8b2e4a',
            'btnFg' => '#fff7f5',
            'radius' => '18px',
            'th' => '#6f243b',
            'thFg' => '#fff7f5',
            'input' => '#fffaf9',
            'inputFg' => '#4a1c2b',
            'inputBd' => '#e7b7c2',
            'text' => '#4a1c2b',
            'muted' => '#8a5a68',
            'chip' => '#f8d7e0',
            'chipFg' => '#8b2e4a',
            'net' => '#8b2e4a',
            'photo' => (string)(ame_pos_skins()['rose']['preview'] ?? 'none'),
        ],
        'lotus' => [
            'page' => '#ffffff',
            'card' => '#ffffff',
            'border' => '#c9ddd6',
            'titleBg' => '#1f6f6a',
            'titleFg' => '#f7f3e8',
            'btn' => '#1f6f6a',
            'btnFg' => '#f7f3e8',
            'radius' => '20px',
            'th' => '#165753',
            'thFg' => '#f7f3e8',
            'input' => '#ffffff',
            'inputFg' => '#000000',
            'inputBd' => '#b7d4cc',
            'text' => '#000000',
            'muted' => '#5a756f',
            'chip' => '#d7efea',
            'chipFg' => '#1f6f6a',
            'net' => '#1f6f6a',
            'photo' => (string)(ame_pos_skins()['lotus']['preview'] ?? 'none'),
        ],
        'teak' => [
            'page' => '#f3ebe0',
            'card' => 'rgba(255, 250, 242, 0.94)',
            'border' => '#d7c2a4',
            'titleBg' => '#3f2a14',
            'titleFg' => '#f4ede0',
            'btn' => '#6b4423',
            'btnFg' => '#fff8ee',
            'radius' => '14px',
            'th' => '#3f2a14',
            'thFg' => '#f4ede0',
            'input' => '#fffaf3',
            'inputFg' => '#3f2a14',
            'inputBd' => '#cbb48f',
            'text' => '#3f2a14',
            'muted' => '#7a6548',
            'chip' => '#ead9bc',
            'chipFg' => '#3f2a14',
            'net' => '#6b4423',
            'photo' => (string)(ame_pos_skins()['teak']['preview'] ?? 'none'),
        ],
        'forest' => [
            'page' => '#0b1a12',
            'card' => 'rgba(16, 36, 24, 0.92)',
            'border' => '#2d5a40',
            'titleBg' => '#143325',
            'titleFg' => '#bbf7d0',
            'btn' => '#22c55e',
            'btnFg' => '#052e16',
            'radius' => '16px',
            'th' => '#14532d',
            'thFg' => '#ecfdf5',
            'input' => '#102418',
            'inputFg' => '#e8f5ec',
            'inputBd' => '#3f6b52',
            'text' => '#e8f5ec',
            'muted' => '#9cb8a8',
            'chip' => '#143325',
            'chipFg' => '#86efac',
            'net' => '#86efac',
            'photo' => (string)(ame_pos_skins()['forest']['preview'] ?? 'none'),
        ],
    ];
}

function ame_pos_theme_sheet_html(string $current = '', bool $english = false): string
{
    $current = $current !== '' ? $current : ame_pos_skin_current();
    $paletteId = function_exists('ame_site_palette_id') ? ame_site_palette_id() : 'custom';
    $palettes = function_exists('ame_theme_palettes') ? ame_theme_palettes() : [];
    $dbTheme = $GLOBALS['theme'] ?? (function_exists('ame_theme_defaults') ? ame_theme_defaults() : []);
    if (function_exists('ame_load_theme') && isset($GLOBALS['db']) && $GLOBALS['db'] instanceof mysqli) {
        try {
            $dbTheme = ame_load_theme($GLOBALS['db']);
        } catch (Throwable $e) {
        }
    }

    $title = $english ? 'Appearance' : 'রং ও ডিজাইন';
    $scriptName = strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
    $onPos = str_contains($scriptName, '/modules/sales/pos.php')
        || str_contains((string)($GLOBALS['ame_body_class'] ?? ''), 'ame-pos-terminal-page');
    $lead = $english
        ? 'Workspace palettes restyle the header and menus. To change the sales page, open the Sales counter tab — the parts counter updates immediately.'
        : '«সব পেজ» হেডার ও মেনু বদলায়। বিক্রয় পেজ বদলাতে «বিক্রয় কাউন্টার» ট্যাব থেকে বেছে নিন — সাথে সাথে কাউন্টার রং বদলাবে।';
    $tabWork = $english ? 'Workspace' : 'সব পেজ';
    $tabClock = $english ? 'Clock' : 'ঘড়ি';
    $tabPos = $english ? 'Sales counter' : 'বিক্রয় কাউন্টার';
    $customName = $english ? 'Atelier custom' : 'আপনার অ্যাটেলিয়ে';
    $customCity = $english ? 'Saved tokens' : 'সেভ করা টোকেন';
    $fine = $english ? 'Open full atelier' : 'পূর্ণ স্টুডিও';
    $editTitle = $english ? 'Edit every colour' : 'প্রতিটি রং ম্যানুয়ালি এডিট';
    $editLead = $english
        ? 'Pick a palette, then change any swatch with the picker or hex. The whole shop updates live.'
        : 'একটা প্যালেট বেছে নিন, তারপর নিচের প্রতিটি রং পিকার বা হেক্স দিয়ে বদলান — সাথে সাথে পুরো দোকান বদলাবে।';
    $saveLbl = $english ? 'Save identity' : 'পরিচয় সেভ';
    $resetLbl = $english ? 'Restore palette' : 'প্যালেটে ফিরুন';
    $posEditTitle = $english ? 'Counter colours' : 'কাউন্টারের প্রতিটি রং';
    $posEditLead = $english
        ? 'Choose a counter look, then edit each colour. Sales page updates immediately.'
        : 'কাউন্টার বেছে নিয়ে নিচের প্রতিটি রং এডিট করুন — বিক্রয় পেজ সাথে সাথে বদলাবে।';
    $kind_labels = [
        'solid' => $english ? 'Studio' : 'স্টুডিও',
        'flower' => $english ? 'Flowers' : 'ফুল',
        'tree' => $english ? 'Trees' : 'গাছ',
    ];

    $payload = [
        'palettes' => [],
        'custom' => $dbTheme,
        'varMap' => function_exists('ame_theme_css_var_map') ? ame_theme_css_var_map() : [],
        'active' => $paletteId,
        'clockColor' => function_exists('ame_resolved_clock_color') ? ame_resolved_clock_color($dbTheme) : '#2DD4BF',
        'clockPresets' => function_exists('ame_clock_presets') ? ame_clock_presets() : [],
        'skins' => [],
        'skin' => $current,
        'onPos' => $onPos,
        'posUrl' => '/modules/sales/pos.php',
        'english' => $english,
        'fields' => function_exists('ame_theme_field_cards') ? ame_theme_field_cards($english) : [],
        'posFields' => ame_pos_skin_token_labels($english),
        'csrf' => function_exists('csrf_token') ? csrf_token() : '',
        'saveUrl' => '/modules/settings/process_theme.php',
        'shopName' => (string)($GLOBALS['shop']['shop_name'] ?? 'APONSHOP'),
    ];
    foreach (ame_pos_skins() as $skinRow) {
        $sid = (string)$skinRow['id'];
        $tokens = ame_pos_skin_tokens()[$sid] ?? [];
        $payload['skins'][] = [
            'id' => $sid,
            'name' => $english ? $skinRow['name_en'] : $skinRow['name'],
            'kind' => $skinRow['kind'],
            'preview' => (string)($skinRow['preview'] ?? ''),
            'tokens' => $tokens,
        ];
    }
    foreach ($palettes as $p) {
        $payload['palettes'][] = [
            'id' => $p['id'],
            'name' => $p['name'],
            'city' => $p['city'],
            'colors' => $p['colors'],
        ];
    }

    $html = '<div id="ameThemeSheet" class="ame-theme-sheet" hidden>';
    $html .= '<div class="ame-theme-sheet-box" role="dialog" aria-modal="true" aria-labelledby="ameThemeSheetTitle">';
    $html .= '<div class="ame-theme-sheet-head">';
    $html .= '<div><p class="ame-theme-kicker">APONSHOP · Design system</p>';
    $html .= '<p id="ameThemeSheetTitle" class="ame-theme-sheet-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>';
    $html .= '<p class="ame-theme-sheet-lead">' . htmlspecialchars($lead, ENT_QUOTES, 'UTF-8') . '</p></div>';
    $html .= '<button type="button" class="ame-theme-sheet-close" data-ame-theme-close aria-label="'
        . htmlspecialchars($english ? 'Close' : 'বন্ধ', ENT_QUOTES, 'UTF-8') . '">';
    $html .= '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M6.4 6.4 17.6 17.6M17.6 6.4 6.4 17.6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
    $html .= '</button></div>';

    $html .= '<div class="ame-ds-tabs" role="tablist">';
    $html .= '<button type="button" class="ame-ds-tab' . ($onPos ? '' : ' is-active') . '" role="tab" aria-selected="'
        . ($onPos ? 'false' : 'true') . '" data-ame-ds-tab="workspace">'
        . htmlspecialchars($tabWork, ENT_QUOTES, 'UTF-8') . '</button>';
    $html .= '<button type="button" class="ame-ds-tab" role="tab" aria-selected="false" data-ame-ds-tab="clock">'
        . htmlspecialchars($tabClock, ENT_QUOTES, 'UTF-8') . '</button>';
    $html .= '<button type="button" class="ame-ds-tab' . ($onPos ? ' is-active' : '') . '" role="tab" aria-selected="'
        . ($onPos ? 'true' : 'false') . '" data-ame-ds-tab="counter">'
        . htmlspecialchars($tabPos, ENT_QUOTES, 'UTF-8') . '</button>';
    $html .= '</div>';

    $html .= '<div class="ame-ds-panel' . ($onPos ? '' : ' is-active') . '" data-ame-ds-panel="workspace"'
        . ($onPos ? ' hidden' : '') . '>';
    $html .= '<div class="ame-ds-grid">';

    $customActive = $paletteId === 'custom' ? ' is-active' : '';
    $html .= '<button type="button" class="ame-ds-palette' . $customActive . '" data-site-palette="custom" aria-pressed="'
        . ($paletteId === 'custom' ? 'true' : 'false') . '">';
    $html .= '<span class="ame-ds-swatches" aria-hidden="true">';
    foreach (['header_color', 'accent_color', 'gold_color', 'bg_color', 'text_color'] as $key) {
        $hex = htmlspecialchars((string)($dbTheme[$key] ?? '#0B1E4D'), ENT_QUOTES, 'UTF-8');
        $html .= '<i style="background:' . $hex . '"></i>';
    }
    $html .= '</span><span class="ame-ds-palette-name">' . htmlspecialchars($customName, ENT_QUOTES, 'UTF-8') . '</span>';
    $html .= '<span class="ame-ds-palette-city">' . htmlspecialchars($customCity, ENT_QUOTES, 'UTF-8') . '</span></button>';

    foreach ($palettes as $p) {
        $id = (string)$p['id'];
        $on = $id === $paletteId;
        $sw = $p['colors'];
        $html .= '<button type="button" class="ame-ds-palette' . ($on ? ' is-active' : '') . '" data-site-palette="'
            . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" aria-pressed="' . ($on ? 'true' : 'false') . '">';
        $html .= '<span class="ame-ds-swatches" aria-hidden="true">';
        foreach (['header_color', 'accent_color', 'gold_color', 'bg_color', 'text_color'] as $key) {
            $html .= '<i style="background:' . htmlspecialchars((string)($sw[$key] ?? '#000'), ENT_QUOTES, 'UTF-8') . '"></i>';
        }
        $html .= '</span><span class="ame-ds-palette-name">' . htmlspecialchars((string)$p['name'], ENT_QUOTES, 'UTF-8') . '</span>';
        $html .= '<span class="ame-ds-palette-city">' . htmlspecialchars((string)$p['city'], ENT_QUOTES, 'UTF-8') . '</span></button>';
    }

    $html .= '</div>';
    $html .= '<section class="ame-ds-studio" aria-label="' . htmlspecialchars($editTitle, ENT_QUOTES, 'UTF-8') . '">';
    $html .= '<p class="ame-ds-studio-kicker">Manual colour atelier</p>';
    $html .= '<p class="ame-ds-studio-title">' . htmlspecialchars($editTitle, ENT_QUOTES, 'UTF-8') . '</p>';
    $html .= '<p class="ame-ds-studio-lead">' . htmlspecialchars($editLead, ENT_QUOTES, 'UTF-8') . '</p>';
    $html .= '<div class="ame-ds-preview" id="ameDsPreview">';
    $html .= '<div class="ame-ds-preview-top"><b id="ameDsPreviewBrand">APONSHOP</b><span id="ameDsPreviewClock">12:45</span></div>';
    $html .= '<div class="ame-ds-preview-body">';
    $html .= '<span class="ame-ds-preview-btn" id="ameDsPreviewBtn">' . htmlspecialchars($english ? 'New sale' : 'নতুন সেল', ENT_QUOTES, 'UTF-8') . '</span>';
    $html .= '<span class="ame-ds-preview-gold" id="ameDsPreviewGold">' . htmlspecialchars($english ? 'Payment' : 'পেমেন্ট', ENT_QUOTES, 'UTF-8') . '</span>';
    $html .= '<span class="ame-ds-preview-danger" id="ameDsPreviewDanger">' . htmlspecialchars($english ? 'Due' : 'বকেয়া', ENT_QUOTES, 'UTF-8') . '</span>';
    $html .= '</div></div>';
    $html .= '<p class="ame-ds-contrast" id="ameDsContrast"></p>';
    $html .= '<div class="ame-ds-tokens" id="ameDsTokens"></div>';
    $html .= '<div class="ame-ds-studio-actions">';
    $html .= '<button type="button" class="ame-ds-save" id="ameDsSave">' . htmlspecialchars($saveLbl, ENT_QUOTES, 'UTF-8') . '</button>';
    $html .= '<button type="button" class="ame-ds-reset" id="ameDsReset">' . htmlspecialchars($resetLbl, ENT_QUOTES, 'UTF-8') . '</button>';
    $html .= '<a class="ame-ds-fine" href="/modules/settings/theme.php">' . htmlspecialchars($fine, ENT_QUOTES, 'UTF-8') . ' →</a>';
    $html .= '</div></section></div>';

    $clockNow = function_exists('ame_resolved_clock_color') ? ame_resolved_clock_color($dbTheme) : '#2DD4BF';
    $clockLead = $english
        ? 'Tap the header clock, or pick a colour here. The time still follows this phone or computer.'
        : 'উপরের ঘড়িতে ট্যাপ করুন, অথবা এখান থেকে রং বাছুন। সময় এই ফোন/কম্পিউটারের ঘড়ি অনুসারে চলে।';
    $clockCustom = $english ? 'Custom colour' : 'নিজের রং';
    $html .= '<div class="ame-ds-panel" data-ame-ds-panel="clock" hidden>';
    $html .= '<div class="ame-clock-studio">';
    $html .= '<div class="ame-clock-live" aria-hidden="true"><small>' . htmlspecialchars($english ? 'Header clock' : 'হেডার ঘড়ি', ENT_QUOTES, 'UTF-8')
        . '</small><b id="ameClockStudioPreview">12:00:00</b></div>';
    $html .= '<p class="ame-clock-studio-lead">' . htmlspecialchars($clockLead, ENT_QUOTES, 'UTF-8') . '</p>';
    $html .= '<div class="ame-clock-studio-swatches">';
    $presets = function_exists('ame_clock_presets') ? ame_clock_presets() : [];
    foreach ($presets as $sw) {
        $hex = (string)$sw['hex'];
        $lab = $english ? (string)($sw['en'] ?? $hex) : (string)($sw['bn'] ?? $hex);
        $on = strtoupper($hex) === strtoupper($clockNow);
        $html .= '<button type="button" class="ame-clock-chip' . ($on ? ' is-active' : '') . '" data-clock-color="'
            . htmlspecialchars($hex, ENT_QUOTES, 'UTF-8') . '" aria-pressed="' . ($on ? 'true' : 'false') . '">';
        $html .= '<i style="background:' . htmlspecialchars($hex, ENT_QUOTES, 'UTF-8') . '"></i>';
        $html .= '<span>' . htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') . '</span></button>';
    }
    $html .= '</div>';
    $html .= '<label class="ame-clock-custom">' . htmlspecialchars($clockCustom, ENT_QUOTES, 'UTF-8')
        . ' <input type="color" id="ameClockStudioColor" value="'
        . htmlspecialchars($clockNow, ENT_QUOTES, 'UTF-8') . '"></label>';
    $html .= '</div></div>';

    $html .= '<div class="ame-ds-panel' . ($onPos ? ' is-active' : '') . '" data-ame-ds-panel="counter"'
        . ($onPos ? '' : ' hidden') . '>';
    $html .= '<div class="ame-theme-grid">';

    foreach (ame_pos_skins() as $skin) {
        $id = $skin['id'];
        $active = $id === $current ? ' is-active' : '';
        $pressed = $id === $current ? 'true' : 'false';
        $name = $english ? $skin['name_en'] : $skin['name'];
        $hint = $english ? $skin['hint_en'] : $skin['hint'];
        $kind = $kind_labels[$skin['kind']] ?? '';
        $preview = (string)($skin['preview'] ?? '');
        $style = 'background:' . htmlspecialchars($preview, ENT_QUOTES, 'UTF-8');

        $html .= '<button type="button" class="ame-theme-card' . $active . '" data-pos-skin="'
            . htmlspecialchars($id, ENT_QUOTES, 'UTF-8')
            . '" aria-pressed="' . $pressed . '" title="'
            . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<span class="ame-theme-card-photo" style="' . $style . '"></span>';
        $html .= '<span class="ame-theme-card-body">';
        $html .= '<span class="ame-theme-card-kind">' . htmlspecialchars($kind, ENT_QUOTES, 'UTF-8') . '</span>';
        $html .= '<span class="ame-theme-card-name">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span>';
        $html .= '<span class="ame-theme-card-hint">' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '</span>';
        $html .= '<span class="ame-theme-card-swatches" aria-hidden="true">';
        foreach ($skin['swatches'] as $hex) {
            $html .= '<i style="background:' . htmlspecialchars($hex, ENT_QUOTES, 'UTF-8') . '"></i>';
        }
        $html .= '</span></span></button>';
    }

    $html .= '</div>';
    $html .= '<section class="ame-ds-studio ame-ds-studio-pos" aria-label="' . htmlspecialchars($posEditTitle, ENT_QUOTES, 'UTF-8') . '">';
    $html .= '<p class="ame-ds-studio-kicker">Sales counter atelier</p>';
    $html .= '<p class="ame-ds-studio-title">' . htmlspecialchars($posEditTitle, ENT_QUOTES, 'UTF-8') . '</p>';
    $html .= '<p class="ame-ds-studio-lead">' . htmlspecialchars($posEditLead, ENT_QUOTES, 'UTF-8') . '</p>';
    $html .= '<div class="ame-ds-tokens" id="amePosTokens"></div>';
    $html .= '<div class="ame-ds-studio-actions">';
    $html .= '<button type="button" class="ame-ds-reset" id="amePosReset">' . htmlspecialchars($english ? 'Restore counter' : 'কাউন্টারে ফিরুন', ENT_QUOTES, 'UTF-8') . '</button>';
    $html .= '</div></section></div>';
    $html .= '<script type="application/json" id="ameSitePalettesJson">'
        . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP)
        . '</script>';
    $html .= '</div></div>';
    return $html;
}
