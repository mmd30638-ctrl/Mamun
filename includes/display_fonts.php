<?php
/**
 * ফন্ট ও রঙ সেটিংস — POS শিরোনাম ও কাউন্টডাউন ঘড়ির সময়ের জন্য।
 */

function ame_style_hex_color(?string $value, string $fallback): string
{
    $value = trim((string)$value);
    if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
        return strtoupper($value);
    }
    return $fallback;
}

function ame_font_family_options(): array
{
    return [
        '' => 'ডিফল্ট (পেজ ফন্ট)',
        'Segoe UI, "Noto Sans Bengali", "Kalpurush", sans-serif' => 'সেগো + বাংলা',
        '"Noto Sans Bengali", "Hind Siliguri", sans-serif' => 'নোটো সান্স বাংলা',
        '"Kalpurush", "Noto Sans Bengali", sans-serif' => 'কালপুরুষ',
        '"Hind Siliguri", sans-serif' => 'হিন্দ শিলিগুড়ি',
        'Arial, Helvetica, sans-serif' => 'এরিয়াল',
        'Georgia, "Times New Roman", serif' => 'জর্জিয়া (সেরিফ)',
        '"Courier New", Courier, monospace' => 'মোনোস্পেস',
    ];
}

function ame_sanitize_font_family(?string $value, string $fallback = ''): string
{
    $value = trim((string)$value);
    $opts = ame_font_family_options();
    if (array_key_exists($value, $opts)) {
        return $value;
    }
    return $fallback;
}

function ame_sanitize_font_weight(?string $value, string $fallback = '700'): string
{
    $weight = (string)($value ?? $fallback);
    if (!in_array($weight, ['400', '600', '700', '800'], true)) {
        return $fallback;
    }
    return $weight;
}

function ame_sanitize_font_size($value, int $default = 13, int $min = 10, int $max = 28): int
{
    $size = (int)$value;
    if ($size <= 0) {
        $size = $default;
    }
    return max($min, min($max, $size));
}

function ame_font_weight_options(): array
{
    return [
        '400' => 'সাধারণ (৪০০)',
        '600' => 'মাঝারি (৬০০)',
        '700' => 'বোল্ড (৭০০)',
        '800' => 'অতি বোল্ড (৮০০)',
    ];
}

function ame_font_family_select_html(string $name, string $selected, string $id = ''): string
{
    $opts = ame_font_family_options();
    $selected = ame_sanitize_font_family($selected, '');
    $idAttr = $id !== '' ? ' id="' . esc_html($id) . '"' : '';
    $html = '<select name="' . esc_html($name) . '"' . $idAttr . '>';
    foreach ($opts as $css => $label) {
        $html .= '<option value="' . esc_html($css) . '"' . ($css === $selected ? ' selected' : '') . '>' . esc_html($label) . '</option>';
    }
    $html .= '</select>';
    return $html;
}

function ame_font_weight_select_html(string $name, string $selected, string $id = ''): string
{
    $opts = ame_font_weight_options();
    $selected = ame_sanitize_font_weight($selected, '700');
    $idAttr = $id !== '' ? ' id="' . esc_html($id) . '"' : '';
    $html = '<select name="' . esc_html($name) . '"' . $idAttr . '>';
    foreach ($opts as $val => $label) {
        $html .= '<option value="' . esc_html($val) . '"' . ($val === $selected ? ' selected' : '') . '>' . esc_html($label) . '</option>';
    }
    $html .= '</select>';
    return $html;
}
