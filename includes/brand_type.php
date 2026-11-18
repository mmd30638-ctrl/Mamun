<?php
/**
 * APONSHOP Type OS 8.06 — every page layer: wordmark, heading, body, caption, button, KPI, link.
 * Settings live in settings.brand_type_json. Never ships config.php.
 */

if (!function_exists('ame_brand_fonts')) {
    function ame_brand_fonts(): array
    {
        return [
            'hind_siliguri' => [
                'id' => 'hind_siliguri',
                'label_bn' => 'হিন্দ শিলিগুড়ি',
                'label_en' => 'Hind Siliguri',
                'role' => 'বাংলা UI — পরিষ্কার, আধুনিক',
                'family' => '"Hind Siliguri", "Noto Sans Bengali", sans-serif',
                'href' => 'https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap',
            ],
            'anek_bangla' => [
                'id' => 'anek_bangla',
                'label_bn' => 'অনেক বাংলা',
                'label_en' => 'Anek Bangla',
                'role' => 'সমসাময়িক ভারতীয় / আন্তর্জাতিক',
                'family' => '"Anek Bangla", "Noto Sans Bengali", sans-serif',
                'href' => 'https://fonts.googleapis.com/css2?family=Anek+Bangla:wght@400;500;600;700;800&display=swap',
            ],
            'noto_sans_bn' => [
                'id' => 'noto_sans_bn',
                'label_bn' => 'নোটো সান্স বাংলা',
                'label_en' => 'Noto Sans Bengali',
                'role' => 'গুগল স্ট্যান্ডার্ড — সর্বত্র পাঠযোগ্য',
                'family' => '"Noto Sans Bengali", sans-serif',
                'href' => 'https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700;800&display=swap',
            ],
            'noto_serif_bn' => [
                'id' => 'noto_serif_bn',
                'label_bn' => 'নোটো সেরিফ বাংলা',
                'label_en' => 'Noto Serif Bengali',
                'role' => 'এডিটোরিয়াল, সার্টিফিকেট, প্রিন্ট',
                'family' => '"Noto Serif Bengali", serif',
                'href' => 'https://fonts.googleapis.com/css2?family=Noto+Serif+Bengali:wght@400;500;600;700;800&display=swap',
            ],
            'tiro_bangla' => [
                'id' => 'tiro_bangla',
                'label_bn' => 'তীরো বাংলা',
                'label_en' => 'Tiro Bangla',
                'role' => 'ক্লাসিক লিটারেচার / হেরিটেজ',
                'family' => '"Tiro Bangla", "Noto Serif Bengali", serif',
                'href' => 'https://fonts.googleapis.com/css2?family=Tiro+Bangla&display=swap',
            ],
            'outfit_noto' => [
                'id' => 'outfit_noto',
                'label_bn' => 'আউটফিট + নোটো',
                'label_en' => 'Outfit + Noto',
                'role' => 'ইউরোপীয় SaaS + বাংলা ফলব্যাক',
                'family' => '"Outfit", "Noto Sans Bengali", sans-serif',
                'href' => 'https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap',
            ],
            'manrope_noto' => [
                'id' => 'manrope_noto',
                'label_bn' => 'ম্যানরোপ + নোটো',
                'label_en' => 'Manrope + Noto',
                'role' => 'ফিনটেক / প্রিমিয়াম সান্স',
                'family' => '"Manrope", "Noto Sans Bengali", sans-serif',
                'href' => 'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Noto+Sans+Bengali:wght@400;500;600;700;800&display=swap',
            ],
            'plus_jakarta_noto' => [
                'id' => 'plus_jakarta_noto',
                'label_bn' => 'প্লাস জাকার্তা + নোটো',
                'label_en' => 'Plus Jakarta + Noto',
                'role' => 'স্টার্টআপ / ব্র্যান্ড বোর্ড',
                'family' => '"Plus Jakarta Sans", "Noto Sans Bengali", sans-serif',
                'href' => 'https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',
            ],
            'ibm_plex_noto' => [
                'id' => 'ibm_plex_noto',
                'label_bn' => 'আইবিএম প্লেক্স + নোটো',
                'label_en' => 'IBM Plex + Noto',
                'role' => 'কর্পোরেট / ইঞ্জিনিয়ারিং',
                'family' => '"IBM Plex Sans", "Noto Sans Bengali", sans-serif',
                'href' => 'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Noto+Sans+Bengali:wght@400;500;600;700;800&display=swap',
            ],
            'cormorant_nsb' => [
                'id' => 'cormorant_nsb',
                'label_bn' => 'করমোরেন্ট + সেরিফ বাংলা',
                'label_en' => 'Cormorant + Serif BN',
                'role' => 'লুক্সারি এডিটোরিয়াল',
                'family' => '"Cormorant Garamond", "Noto Serif Bengali", serif',
                'href' => 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Noto+Serif+Bengali:wght@400;500;600;700;800&display=swap',
            ],
            'literata_nsb' => [
                'id' => 'literata_nsb',
                'label_bn' => 'লিটেরাটা + সেরিফ বাংলা',
                'label_en' => 'Literata + Serif BN',
                'role' => 'প্রকাশনা / ট্রেড ক্যাটালগ',
                'family' => '"Literata", "Noto Serif Bengali", serif',
                'href' => 'https://fonts.googleapis.com/css2?family=Literata:opsz,wght@7..72,400;7..72,600;7..72,700&family=Noto+Serif+Bengali:wght@400;500;600;700;800&display=swap',
            ],
            'source_serif_nsb' => [
                'id' => 'source_serif_nsb',
                'label_bn' => 'সোর্স সেরিফ + বাংলা',
                'label_en' => 'Source Serif + BN',
                'role' => 'আন্তর্জাতিক নিউজরুম',
                'family' => '"Source Serif 4", "Noto Serif Bengali", serif',
                'href' => 'https://fonts.googleapis.com/css2?family=Noto+Serif+Bengali:wght@400;500;600;700;800&family=Source+Serif+4:opsz,wght@8..60,400;8..60,600;8..60,700&display=swap',
            ],
        ];
    }
}

if (!function_exists('ame_brand_color_presets')) {
    function ame_brand_color_presets(): array
    {
        return [
            '#123526' => 'Forest ink',
            '#0B3D2E' => 'Workshop pine',
            '#065F46' => 'Emerald',
            '#0F766E' => 'Teal',
            '#0F172A' => 'Ink',
            '#1E293B' => 'Slate',
            '#1E3A5F' => 'Navy',
            '#7C2D12' => 'Copper',
            '#9A3412' => 'Rust',
            '#B45309' => 'Brass',
            '#C9A227' => 'Gold',
            '#9F1239' => 'Burgundy',
            '#334155' => 'Charcoal',
            '#F8FAFC' => 'Ivory',
        ];
    }
}

if (!function_exists('ame_brand_layers')) {
    function ame_brand_layers(): array
    {
        return [
            'name' => ['bn' => 'ওয়ার্ডমার্ক', 'en' => 'Wordmark', 'sample' => 'মা ইঞ্জিনিয়ারিং ওয়ার্কশপ এন্ড মামুন ট্রেডার্স'],
            'tag' => ['bn' => 'ট্যাগলাইন', 'en' => 'Tagline', 'sample' => 'স্পেয়ার পার্টস ব্যবসা ও ইঞ্জিনিয়ারিং ওয়ার্কশপ'],
            'heading' => ['bn' => 'শিরোনাম', 'en' => 'Headings', 'sample' => 'লোন ব্যবস্থাপনা — ড্যাশবোর্ড'],
            'body' => ['bn' => 'বডি টেক্সট', 'en' => 'Body', 'sample' => 'প্রত্যেক পেজের মূল লেখা এই স্তরে বসে — কার্ড, টেবিল, ফর্ম।'],
            'caption' => ['bn' => 'ক্যাপশন', 'en' => 'Caption', 'sample' => 'ছোট হিন্ট, লেবেল ও ইংরেজি সাবলাইন'],
            'button' => ['bn' => 'বাটন', 'en' => 'Buttons', 'sample' => 'সব তথ্য আপডেট করুন'],
            'kpi' => ['bn' => 'সংখ্যা / KPI', 'en' => 'Numbers', 'sample' => '12,50,000 ৳'],
            'link' => ['bn' => 'লিংক', 'en' => 'Links', 'sample' => 'বিস্তারিত দেখুন'],
        ];
    }
}

if (!function_exists('ame_brand_layer_defaults')) {
    function ame_brand_layer_defaults(string $layer): array
    {
        $base = [
            'font' => 'outfit_noto',
            'color' => '#123526',
            'weight' => '600',
            'size' => '15',
            'tracking' => '0',
            'leading' => '1.50',
            'transform' => 'none',
            'italic' => '0',
            'decoration' => 'none',
            'shadow' => 'none',
        ];
        $over = [
            'name' => ['font' => 'hind_siliguri', 'color' => '#123526', 'weight' => '700', 'size' => '20', 'tracking' => '-0.02', 'leading' => '1.28'],
            'tag' => ['font' => 'outfit_noto', 'color' => '#5B6F64', 'weight' => '600', 'size' => '13', 'leading' => '1.40'],
            'heading' => ['font' => 'outfit_noto', 'color' => '#0C241C', 'weight' => '800', 'size' => '24', 'tracking' => '-0.03', 'leading' => '1.22'],
            'body' => ['font' => 'outfit_noto', 'color' => '#123526', 'weight' => '500', 'size' => '15', 'leading' => '1.55'],
            'caption' => ['font' => 'outfit_noto', 'color' => '#6B7F74', 'weight' => '600', 'size' => '12', 'leading' => '1.40'],
            'button' => ['font' => 'outfit_noto', 'color' => '#FFFFFF', 'weight' => '800', 'size' => '15', 'tracking' => '0.01', 'leading' => '1.20'],
            'kpi' => ['font' => 'ibm_plex_noto', 'color' => '#0C241C', 'weight' => '800', 'size' => '30', 'tracking' => '-0.04', 'leading' => '1.05'],
            'link' => ['font' => 'outfit_noto', 'color' => '#15803D', 'weight' => '700', 'size' => '14', 'decoration' => 'none', 'leading' => '1.35'],
        ];
        return array_merge($base, $over[$layer] ?? []);
    }
}

if (!function_exists('ame_brand_type_defaults')) {
    function ame_brand_type_defaults(): array
    {
        $out = [];
        foreach (array_keys(ame_brand_layers()) as $layer) {
            $out[$layer] = ame_brand_layer_defaults($layer);
        }
        return $out;
    }
}

if (!function_exists('ame_brand_type_sanitize_hex')) {
    function ame_brand_type_sanitize_hex($raw, string $fallback): string
    {
        $v = strtoupper(trim((string)$raw));
        if ($v !== '' && $v[0] !== '#') {
            $v = '#' . $v;
        }
        if (!preg_match('/^#[0-9A-F]{6}$/', $v)) {
            return strtoupper($fallback);
        }
        return $v;
    }
}

if (!function_exists('ame_brand_type_normalize')) {
    function ame_brand_type_normalize($raw): array
    {
        $in = is_array($raw) ? $raw : [];
        if (isset($in['name_font']) && !isset($in['name']['font'])) {
            $in['name'] = [
                'font' => $in['name_font'] ?? null,
                'color' => $in['name_color'] ?? null,
                'weight' => $in['name_weight'] ?? null,
                'size' => $in['name_size'] ?? null,
                'tracking' => $in['name_tracking'] ?? null,
                'transform' => $in['name_transform'] ?? null,
            ];
            $in['tag'] = [
                'font' => $in['tag_font'] ?? null,
                'color' => $in['tag_color'] ?? null,
                'weight' => $in['tag_weight'] ?? null,
                'size' => $in['tag_size'] ?? null,
            ];
        }
        $fonts = ame_brand_fonts();
        $out = [];
        foreach (array_keys(ame_brand_layers()) as $layer) {
            $d = ame_brand_layer_defaults($layer);
            $src = is_array($in[$layer] ?? null) ? $in[$layer] : [];
            $font = (string)($src['font'] ?? $d['font']);
            if (!isset($fonts[$font])) {
                $font = $d['font'];
            }
            $weight = (string)($src['weight'] ?? $d['weight']);
            if (!in_array($weight, ['400', '500', '600', '700', '800'], true)) {
                $weight = $d['weight'];
            }
            $size = (int)($src['size'] ?? $d['size']);
            $size = max(10, min(48, $size));
            $tr = (float)($src['tracking'] ?? $d['tracking']);
            $tr = max(-0.10, min(0.16, $tr));
            $ld = (float)($src['leading'] ?? $d['leading']);
            $ld = max(1.0, min(2.2, $ld));
            $tf = (string)($src['transform'] ?? $d['transform']);
            if (!in_array($tf, ['none', 'capitalize'], true)) {
                $tf = 'none';
            }
            $dec = (string)($src['decoration'] ?? $d['decoration']);
            if (!in_array($dec, ['none', 'underline'], true)) {
                $dec = 'none';
            }
            $sh = (string)($src['shadow'] ?? $d['shadow']);
            if (!in_array($sh, ['none', 'soft', 'glow'], true)) {
                $sh = 'none';
            }
            $out[$layer] = [
                'font' => $font,
                'color' => ame_brand_type_sanitize_hex($src['color'] ?? $d['color'], $d['color']),
                'weight' => $weight,
                'size' => (string)$size,
                'tracking' => number_format($tr, 3, '.', ''),
                'leading' => number_format($ld, 2, '.', ''),
                'transform' => $tf,
                'italic' => !empty($src['italic']) && $src['italic'] !== '0' ? '1' : '0',
                'decoration' => $dec,
                'shadow' => $sh,
            ];
        }
        return $out;
    }
}

if (!function_exists('ame_brand_type_db')) {
    function ame_brand_type_db()
    {
        global $db;
        if (isset($db) && $db instanceof mysqli) {
            return $db;
        }
        static $local = false;
        if ($local instanceof mysqli) {
            return $local;
        }
        if ($local === true) {
            return null;
        }
        $candidates = [
            dirname(__DIR__) . '/.env',
            ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/.env',
            dirname(__DIR__, 2) . '/.env',
        ];
        $env = [];
        foreach ($candidates as $envPath) {
            if ($envPath === '/.env' || $envPath === '.env') {
                continue;
            }
            if (is_file($envPath) && is_readable($envPath)) {
                foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                    $line = trim((string)$line);
                    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                        continue;
                    }
                    [$key, $value] = array_map('trim', explode('=', $line, 2));
                    $env[$key] = trim($value, "\"' ");
                }
                break;
            }
        }
        $host = $env['DB_HOST'] ?? (getenv('DB_HOST') ?: 'localhost');
        $name = $env['DB_NAME'] ?? (getenv('DB_NAME') ?: '');
        $user = $env['DB_USER'] ?? (getenv('DB_USER') ?: '');
        $pass = $env['DB_PASS'] ?? (getenv('DB_PASS') ?: '');
        if ($name === '' || $user === '') {
            $local = true;
            return null;
        }
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = @new mysqli($host, $user, $pass, $name);
        if ($conn->connect_error) {
            $local = true;
            return null;
        }
        $conn->set_charset('utf8mb4');
        $local = $conn;
        return $conn;
    }
}

if (!function_exists('ame_brand_type_ensure')) {
    function ame_brand_type_ensure($db = null): void
    {
        $db = $db instanceof mysqli ? $db : ame_brand_type_db();
        if (!$db instanceof mysqli) {
            return;
        }
        $ok = @$db->query("SHOW COLUMNS FROM settings LIKE 'brand_type_json'");
        if ($ok && $ok->num_rows === 0) {
            @$db->query("ALTER TABLE settings ADD COLUMN brand_type_json TEXT NULL");
        }
    }
}

if (!function_exists('ame_brand_type_get')) {
    function ame_brand_type_get($db = null): array
    {
        $d = ame_brand_type_defaults();
        $db = $db instanceof mysqli ? $db : ame_brand_type_db();
        if (!$db instanceof mysqli) {
            return $d;
        }
        ame_brand_type_ensure($db);
        $res = @$db->query("SELECT brand_type_json FROM settings LIMIT 1");
        if (!$res || $res->num_rows === 0) {
            return $d;
        }
        $row = $res->fetch_assoc();
        $json = json_decode((string)($row['brand_type_json'] ?? ''), true);
        return ame_brand_type_normalize(is_array($json) ? $json : []);
    }
}

if (!function_exists('ame_brand_type_from_post')) {
    function ame_brand_type_from_post(array $post): array
    {
        $layers = [];
        foreach (array_keys(ame_brand_layers()) as $layer) {
            $p = 'brand_' . $layer . '_';
            $layers[$layer] = [
                'font' => $post[$p . 'font'] ?? null,
                'color' => $post[$p . 'color'] ?? null,
                'weight' => $post[$p . 'weight'] ?? null,
                'size' => $post[$p . 'size'] ?? null,
                'tracking' => $post[$p . 'tracking'] ?? null,
                'leading' => $post[$p . 'leading'] ?? null,
                'transform' => $post[$p . 'transform'] ?? null,
                'italic' => $post[$p . 'italic'] ?? '0',
                'decoration' => $post[$p . 'decoration'] ?? null,
                'shadow' => $post[$p . 'shadow'] ?? null,
            ];
        }
        return ame_brand_type_normalize($layers);
    }
}

if (!function_exists('ame_brand_type_save')) {
    function ame_brand_type_save($db, array $post): bool
    {
        if (!$db instanceof mysqli) {
            return false;
        }
        ame_brand_type_ensure($db);
        $type = ame_brand_type_from_post($post);
        $json = json_encode($type, JSON_UNESCAPED_UNICODE);
        $check = @$db->query("SELECT id FROM settings LIMIT 1");
        if ($check && $check->num_rows > 0) {
            $id = (int)($check->fetch_assoc()['id'] ?? 0);
            $stmt = $db->prepare('UPDATE settings SET brand_type_json=? WHERE id=?');
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('si', $json, $id);
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        }
        $stmt = $db->prepare('INSERT INTO settings (shop_name, brand_type_json) VALUES (?, ?)');
        if (!$stmt) {
            return false;
        }
        $name = 'আমার ব্যবসা প্রতিষ্ঠান';
        $stmt->bind_param('ss', $name, $json);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}

if (!function_exists('ame_brand_type_font_hrefs')) {
    function ame_brand_type_font_hrefs(array $type): array
    {
        $fonts = ame_brand_fonts();
        $hrefs = [];
        foreach (array_keys(ame_brand_layers()) as $layer) {
            $id = $type[$layer]['font'] ?? '';
            if (isset($fonts[$id]['href'])) {
                $hrefs[$fonts[$id]['href']] = true;
            }
        }
        return array_keys($hrefs);
    }
}

if (!function_exists('ame_brand_shadow_css')) {
    function ame_brand_shadow_css(string $kind): string
    {
        if ($kind === 'soft') {
            return '0 1px 1px rgba(12,36,28,0.18)';
        }
        if ($kind === 'glow') {
            return '0 0 18px rgba(34,197,94,0.28)';
        }
        return 'none';
    }
}

if (!function_exists('ame_brand_layer_css_vars')) {
    function ame_brand_layer_css_vars(string $layer, array $s): string
    {
        $fonts = ame_brand_fonts();
        $fam = $fonts[$s['font']]['family'] ?? '"Outfit", "Noto Sans Bengali", sans-serif';
        $p = '--ame-t-' . $layer;
        $italic = $s['italic'] === '1' ? 'italic' : 'normal';
        $shadow = ame_brand_shadow_css($s['shadow']);
        return "{$p}-font: {$fam};\n  {$p}-color: {$s['color']};\n  {$p}-weight: {$s['weight']};\n  {$p}-size: {$s['size']}px;\n  {$p}-tracking: {$s['tracking']}em;\n  {$p}-leading: {$s['leading']};\n  {$p}-transform: {$s['transform']};\n  {$p}-style: {$italic};\n  {$p}-decoration: {$s['decoration']};\n  {$p}-shadow: {$shadow};";
    }
}

if (!function_exists('ame_brand_type_css')) {
    function ame_brand_type_css(?array $type = null): string
    {
        $type = ame_brand_type_normalize($type ?? ame_brand_type_get());
        $imports = '';
        foreach (ame_brand_type_font_hrefs($type) as $href) {
            $imports .= '@import url("' . $href . '");' . "\n";
        }
        $vars = '';
        foreach ($type as $layer => $s) {
            $vars .= '  ' . ame_brand_layer_css_vars($layer, $s) . "\n";
        }
        return $imports . <<<CSS
:root {
{$vars}}
.hdr-brand-box {
  white-space: normal !important;
  overflow: visible !important;
  text-overflow: unset !important;
  max-width: min(56%, 640px) !important;
}
.hdr-brand-box h1,
.ame-brand-name,
.login-card h2,
.sidebar-brand-header .brand-info h4 {
  font-family: var(--ame-t-name-font) !important;
  color: var(--ame-t-name-color) !important;
  font-weight: var(--ame-t-name-weight) !important;
  letter-spacing: var(--ame-t-name-tracking) !important;
  text-transform: var(--ame-t-name-transform) !important;
  font-style: var(--ame-t-name-style) !important;
  text-decoration: var(--ame-t-name-decoration) !important;
  text-shadow: var(--ame-t-name-shadow) !important;
  line-height: var(--ame-t-name-leading) !important;
}
.hdr-brand-box h1 {
  font-size: clamp(13px, 1.35vw, var(--ame-t-name-size)) !important;
  white-space: normal !important;
}
.login-card h2 { font-size: calc(var(--ame-t-name-size) * 1.05) !important; }
.sidebar-brand-header .brand-info h4 { font-size: 14px !important; white-space: normal !important; }
.login-card > p.text-gray-600,
.sidebar-brand-header .brand-info > small:first-of-type,
.ame-brand-tag {
  font-family: var(--ame-t-tag-font) !important;
  color: var(--ame-t-tag-color) !important;
  font-weight: var(--ame-t-tag-weight) !important;
  font-size: var(--ame-t-tag-size) !important;
  letter-spacing: var(--ame-t-tag-tracking) !important;
  line-height: var(--ame-t-tag-leading) !important;
  font-style: var(--ame-t-tag-style) !important;
  text-decoration: var(--ame-t-tag-decoration) !important;
  text-transform: var(--ame-t-tag-transform) !important;
  text-shadow: var(--ame-t-tag-shadow) !important;
}
body.ame-saas-os #main-content h1,
body.ame-saas-os #main-content h2,
body.ame-saas-os #main-content h3,
body.ame-saas-os .set-header h3,
body.ame-saas-os .card h1,
body.ame-saas-os .card h2,
body.ame-saas-os .card h3 {
  font-family: var(--ame-t-heading-font) !important;
  color: var(--ame-t-heading-color) !important;
  font-weight: var(--ame-t-heading-weight) !important;
  letter-spacing: var(--ame-t-heading-tracking) !important;
  line-height: var(--ame-t-heading-leading) !important;
  font-style: var(--ame-t-heading-style) !important;
  text-transform: var(--ame-t-heading-transform) !important;
  text-decoration: var(--ame-t-heading-decoration) !important;
  text-shadow: var(--ame-t-heading-shadow) !important;
}
body.ame-saas-os #main-content h1 { font-size: var(--ame-t-heading-size) !important; }
body.ame-saas-os #main-content h2 { font-size: calc(var(--ame-t-heading-size) * 0.86) !important; }
body.ame-saas-os #main-content h3 { font-size: calc(var(--ame-t-heading-size) * 0.72) !important; }
body.ame-saas-os,
body.ame-saas-os #main-content,
body.ame-saas-os p,
body.ame-saas-os td,
body.ame-saas-os li,
body.ame-saas-os label,
body.ame-saas-os .card,
body.ame-saas-os input:not([type="checkbox"]):not([type="radio"]):not([type="color"]):not([type="range"]),
body.ame-saas-os textarea,
body.ame-saas-os select {
  font-family: var(--ame-t-body-font) !important;
  color: var(--ame-t-body-color) !important;
  letter-spacing: var(--ame-t-body-tracking) !important;
  line-height: var(--ame-t-body-leading) !important;
}
body.ame-saas-os #main-content,
body.ame-saas-os p,
body.ame-saas-os td,
body.ame-saas-os li,
body.ame-saas-os label {
  font-weight: var(--ame-t-body-weight) !important;
  font-size: var(--ame-t-body-size);
}
body.ame-saas-os small,
body.ame-saas-os .ame-pair em,
body.ame-saas-os .set-group small,
body.ame-saas-os .ld-kpi em {
  font-family: var(--ame-t-caption-font) !important;
  color: var(--ame-t-caption-color) !important;
  font-weight: var(--ame-t-caption-weight) !important;
  font-size: var(--ame-t-caption-size) !important;
  letter-spacing: var(--ame-t-caption-tracking) !important;
  line-height: var(--ame-t-caption-leading) !important;
  font-style: var(--ame-t-caption-style) !important;
}
body.ame-saas-os .btn-success,
body.ame-saas-os .btn-primary,
body.ame-saas-os .set-submit,
body.ame-saas-os button[type="submit"]:not([class*="hdr-"]) {
  font-family: var(--ame-t-button-font) !important;
  color: var(--ame-t-button-color) !important;
  font-weight: var(--ame-t-button-weight) !important;
  font-size: var(--ame-t-button-size) !important;
  letter-spacing: var(--ame-t-button-tracking) !important;
  font-style: var(--ame-t-button-style) !important;
  text-transform: var(--ame-t-button-transform) !important;
  text-decoration: var(--ame-t-button-decoration) !important;
}
body.ame-saas-os .fam-kpi b,
body.ame-saas-os .pulse-kpi b,
body.ame-saas-os .ld-kpi b,
body.ame-saas-os .pd-kpi-box .val,
body.ame-saas-os .ld-folio b {
  font-family: var(--ame-t-kpi-font) !important;
  color: var(--ame-t-kpi-color) !important;
  font-weight: var(--ame-t-kpi-weight) !important;
  letter-spacing: var(--ame-t-kpi-tracking) !important;
  line-height: var(--ame-t-kpi-leading) !important;
  font-style: var(--ame-t-kpi-style) !important;
  text-shadow: var(--ame-t-kpi-shadow) !important;
  font-variant-numeric: tabular-nums;
}
body.ame-saas-os a:not(.btn-success):not(.direct-link):not(.menu-head):not(.hdr-icon-btn) {
  color: var(--ame-t-link-color);
  font-weight: var(--ame-t-link-weight);
  text-decoration: var(--ame-t-link-decoration);
}
body.ame-saas-os #main-content .hy-mast .hy-kicker { color: #b45309 !important; }
body.ame-saas-os #main-content .tv-vault .tv-kicker { color: #a16207 !important; }
body.ame-saas-os #main-content .tv-vault h1 em { color: #a16207 !important; }
body.ame-saas-os #main-content .ld-book .ld-kicker { color: #b45309 !important; }
body.ame-saas-os #main-content .ty-mast .ty-kicker { color: #a16207 !important; }
body.ame-saas-os #main-content .ty-star.on { color: #fff8ea !important; }
body.ame-saas-os .ud-kicker { color: #0369a1 !important; }
body.ame-saas-os .ud-charge,
body.ame-saas-os .ud-charge b { color: #9f1239 !important; }
body.ame-saas-os .ud-charge.credit,
body.ame-saas-os .ud-charge.credit b { color: #3f6212 !important; }
body.ame-saas-os .ud-charge span { color: #9a3412 !important; }
body.ame-saas-os .ud-charge.credit span { color: #3f6212 !important; }
CSS;
    }
}
