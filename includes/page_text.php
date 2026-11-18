<?php
/**
 * Per-page text size and color. Lives in Settings as 🔤 — separate from 📐 layout.
 */

function ame_page_text_store_path(): string
{
    $dir = dirname(__DIR__) . '/storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir . '/page_text.json';
}

function ame_page_text_defaults(): array
{
    return ['pages' => []];
}

function ame_page_text_load(): array
{
    $path = ame_page_text_store_path();
    $base = ame_page_text_defaults();
    if (!is_file($path)) {
        return $base;
    }
    $raw = json_decode((string) file_get_contents($path), true);
    if (!is_array($raw) || !isset($raw['pages']) || !is_array($raw['pages'])) {
        return $base;
    }
    $base['pages'] = $raw['pages'];
    return $base;
}

function ame_page_text_save(array $data): bool
{
    $out = ['pages' => is_array($data['pages'] ?? null) ? $data['pages'] : []];
    $json = json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return $json !== false && file_put_contents(ame_page_text_store_path(), $json) !== false;
}

function ame_page_text_clamp_scale($scale): float
{
    $n = (float) $scale;
    if ($n < 0.7) {
        $n = 0.7;
    }
    if ($n > 1.8) {
        $n = 1.8;
    }
    return round($n, 2);
}

function ame_page_text_css(array $data): string
{
    $css = '';
    foreach (($data['pages'] ?? []) as $pageKey => $page) {
        if (!is_array($page) || !preg_match('/^[a-z0-9_\/-]{1,80}$/', (string) $pageKey)) {
            continue;
        }
        $scale = ame_page_text_clamp_scale($page['scale'] ?? 1);
        $color = (string) ($page['color'] ?? '');
        $scope = 'html body[data-ame-layout-page="' . htmlspecialchars((string) $pageKey, ENT_QUOTES, 'UTF-8') . '"]';
        if (abs($scale - 1.0) >= 0.01) {
            $css .= $scope . ' #main-content{zoom:' . $scale . ';}';
        }
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
            $css .= $scope . ' #main-content,'
                . $scope . ' #main-content :is(p,span,label,td,th,a,li,h1,h2,h3,h4,small,b,strong,input,select,textarea){color:'
                . $color . ' !important;}';
        }
    }
    return $css;
}

function ame_page_text_catalog(): array
{
    $list = function_exists('ame_page_layout_catalog') ? ame_page_layout_catalog() : [];
    $extra = [
        ['key' => 'customers/hire/index', 'url' => '/modules/customers/hire/index.php', 'label' => 'ভাড়া'],
        ['key' => 'customers/hire/due-reminder', 'url' => '/modules/customers/hire/due-reminder.php', 'label' => 'ভাড়া বকেয়া'],
        ['key' => 'workshop/index', 'url' => '/modules/workshop/index.php', 'label' => 'ওয়ার্কশপ'],
    ];
    $seen = [];
    foreach ($list as $item) {
        $seen[$item['key']] = true;
    }
    foreach ($extra as $item) {
        if (empty($seen[$item['key']])) {
            $list[] = $item;
        }
    }
    return $list;
}

function ame_page_text_boot(): void
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if (str_contains($script, 'text_style.php') || str_contains($script, 'layout_designer.php')) {
        return;
    }
    $data = ame_page_text_load();
    $css = ame_page_text_css($data);
    if ($css === '') {
        return;
    }
    echo '<style id="amePageTextCss">' . $css . '</style>' . "\n";
}
