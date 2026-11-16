<?php
/**
 * ফাইল খাতা — কোন ফোল্ডার/ফাইলে কী কাজ, সংক্ষেপে বাংলায়।
 * Hostinger-এ shop/ প্রিফিক্স নেই; লোকাল রিপোতে থাকলে দুইভাবে মিলে।
 */

function ame_atlas_norm(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#^./#', '', $path) ?? $path;
    return trim($path, '/');
}

function ame_atlas_keys(string $path): array
{
    $n = ame_atlas_norm($path);
    $keys = [$n, $n === '' ? '' : $n];
    if ($n !== '' && str_starts_with($n, 'shop/')) {
        $keys[] = substr($n, 5);
    } elseif ($n !== '') {
        $keys[] = 'shop/' . $n;
    }
    return array_values(array_unique($keys));
}

function ame_atlas_module_bn(): array
{
    return [
        'sales' => 'বিক্রয়', 'inventory' => 'স্টক ও পণ্য', 'customers' => 'ক্রেতা',
        'purchase' => 'কেনাকাটা', 'workshop' => 'ওয়ার্কশপ', 'hrm' => 'কর্মী ও HRM',
        'accounts' => 'হিসাব', 'finance' => 'ফিনান্স', 'settings' => 'সেটিংস',
        'family' => 'পারিবারিক খাতা', 'loans' => 'ঋণ', 'rental' => 'ভাড়া',
        'suppliers' => 'সাপ্লায়ার', 'expenses' => 'খরচ', 'returns' => 'ফেরত',
        'reports' => 'রিপোর্ট', 'contacts' => 'কন্টাক্ট', 'agents' => 'এজেন্ট',
        'partners' => 'পার্টনার', 'personal' => 'ব্যক্তিগত হিসাব', 'pulse' => 'পালস',
        'smart' => 'স্মার্ট সহায়ক', 'utilities' => 'ইউটিলিটি বিল', 'dashboard' => 'ড্যাশবোর্ড',
        'provenance' => 'মালিক/সোর্স তারিখ', 'sms' => 'এসএমএস', 'pos' => 'POS',
        'invoice' => 'রসিদ', 'theme' => 'থিম',
    ];
}

function ame_atlas_folder_raw(): array
{
    return [
        '' => 'APONSHOP PHP দোকান — বিক্রয়, স্টক, হিসাব, ওয়ার্কশপ। স্পিড লক: SPEED.txt।',
        'modules' => 'প্রতিটি ব্যবসায়িক মডিউলের পেজ ও API।',
        'modules/sales' => 'POS কাউন্টার, কার্ট, ইনভয়েস, ড্রাফট বিক্রয়।',
        'modules/inventory' => 'পণ্য যোগ, স্টক গণনা, বারকোড, ট্রান্সফার।',
        'modules/customers' => 'ক্রেতা খাতা, গ্রুপ, বাকি রিমাইন্ডার।',
        'modules/purchase' => 'কেনাকাটা মেমো, ড্রাফট, কেনাকাটা ফেরত।',
        'modules/workshop' => 'মেশিন, জব কার্ড, কোটেশন, পার্টস খোঁজা।',
        'modules/hrm' => 'হাজিরা, বেতন, আয়, ছুটি, বিভাগ।',
        'modules/accounts' => 'ক্যাশ বই, অ্যাকাউন্ট, লেনদেন।',
        'modules/finance' => 'ফিনান্স ড্যাশবোর্ড ও লেনদেন।',
        'modules/settings' => 'ইউজার, ব্যাকআপ, স্কিমা, SMS, থিম, ফাইল খাতা।',
        'modules/family' => 'পারিবারিক খাতা, লাইব্রেরি, ওষুধ।',
        'modules/loans' => 'ঋণ আবেদন, অনুমোদন, আদায়।',
        'modules/rental' => 'ভাড়া মেশিন, মালিক, বকেয়া।',
        'modules/suppliers' => 'সাপ্লায়ার খাতা ও পেমেন্ট।',
        'modules/expenses' => 'খরচ এন্ট্রি।',
        'modules/returns' => 'বিক্রয় ফেরত ফর্ম ও হিস্ট্রি।',
        'modules/reports' => 'দৈনিক, ক্যাশ, কর্মী ও আইটেম রিপোর্ট।',
        'modules/contacts' => 'কন্টাক্ট তালিকা ও গ্রুপ।',
        'modules/agents' => 'এজেন্ট তালিকা।',
        'modules/partners' => 'পার্টনার হিসাব, মূলধন, মুনাফা।',
        'modules/personal' => 'ব্যক্তিগত খরচ/এন্ট্রি।',
        'modules/pulse' => 'দোকানের লাইভ পালস স্ট্যাটাস।',
        'modules/smart' => 'স্মার্ট সহায়ক, ক্যালেন্ডার, ম্যাপ।',
        'modules/utilities' => 'ইউটিলিটি বিল।',
        'modules/dashboard' => 'অভ্যন্তরীণ ড্যাশবোর্ড পেজ।',
        'modules/provenance' => 'মালিক ও সোর্স তারিখ এন্ট্রি।',
        'includes' => 'শেয়ার করা কনফিগ, ডিবি, পারমিশন ও হেল্পার।',
        'includes/lang' => 'বাংলা/ইংরেজি ভাষার স্ট্রিং।',
        'includes/sales' => 'বিক্রয় হেল্পার ইনক্লুড।',
        'assets' => 'দোকানের CSS, JS ও ছবি।',
        'assets/css' => 'স্টাইলশিট — POS, শেল, থিম।',
        'assets/js' => 'জাভাস্ক্রিপ্ট — POS, সার্ভিস ওয়ার্কার বুট।',
        'assets/img' => 'আইকন ও ছবি।',
        'partials' => 'হেডার, সাইডবার, ফুটার — সব পেজে লাগে।',
        'sql' => 'MySQL স্কিমা — phpMyAdmin-এ ইমপোর্ট।',
        'scripts' => 'মোবাইল ওয়ান-ক্লিক জিপ স্ক্রিপ্ট।',
        'uploads' => 'ক্রেতা/পণ্য/কর্মী ছবি — প্যাচে মুছবেন না।',
        'logs' => 'PHP এরর লগ।',
    ];
}

function ame_atlas_file_raw(): array
{
    return [
        'index.php' => 'দোকানের হোম — গ্যালারি, লগইন গার্ড।',
        'login.php' => 'লগইন ফর্ম।',
        'process_login.php' => 'লগইন পোস্ট: পাসওয়ার্ড, লকআউট, সেশন।',
        'logout.php' => 'সেশন শেষ করে লগইনে পাঠায়।',
        'router.php' => 'অজানা URL — হোয়াইটলিস্টেড রুট বা ৪০৪।',
        'verify.php' => 'রসিদ/কোড যাচাই — লগইন ছাড়া।',
        'forgot_password.php' => 'পাসওয়ার্ড ভুলে গেলে OTP ফর্ম।',
        'reset_password.php' => 'নতুন পাসওয়ার্ড সেট করার পেজ।',
        'process_forgot_password.php' => 'ফরগট-পাসওয়ার্ড পোস্ট হ্যান্ডলার।',
        'process_password_reset.php' => 'রিসেট টোকেন যাচাই ও সেভ।',
        'process_new_password.php' => 'নতুন পাসওয়ার্ড লিখলে সেভ করে।',
        'sw.js' => 'PWA সার্ভিস ওয়ার্কার — অফলাইন ক্যাশ।',
        'manifest.php' => 'ডাইনামিক PWA ম্যানিফেস্ট।',
        'sms_sim.php' => 'সিম ফোনে SMS কিউ দেখার পেজ।',
        'sms_sim_api.php' => 'সিম SMS কিউ API।',
        'ame_if_boot.php' => 'প্রতি রিকোয়েস্টের আগে বুট/প্রিপেন্ড।',
        'SPEED.txt' => 'স্পিড সিস্টেমের স্থায়ী নোট — মুছবেন না। সেটিংস → স্পিড সিস্টেম।',
        'includes/config.php' => 'সেশন, ভার্সন, .env, লগইন গার্ড।',
        'includes/db.php' => 'MySQL mysqli কানেকশন।',
        'includes/helpers.php' => 'সাধারণ PHP হেল্পার (escape ইত্যাদি)।',
        'includes/file_atlas.php' => 'ফাইল খাতার বাংলা বর্ণনা ও ট্রি বানায়।',
        'includes/pos_skin.php' => 'বিক্রয় কাউন্টার থিম ও রং স্টুডিও।',
        'includes/pos_title.php' => 'POS শিরোনাম।',
        'includes/overlay_assets.php' => 'শেল CSS/JS ট্যাগ ইনজেক্ট করে।',
        'modules/settings/speed_system.php' => 'স্পিড সিস্টেমের স্থায়ী নোট — SPEED.txt দেখায়।',
        'includes/permission_functions.php' => 'পেজ পারমিশন এনফোর্স।',
        'includes/permission_catalog.php' => 'কোন মডিউলে কোন পারমিশন।',
        'includes/login_lockout.php' => 'ভুল পাসওয়ার্ডে লকআউট।',
        'includes/password_policy.php' => 'পাসওয়ার্ড নিয়ম।',
        'includes/schema_sync.php' => 'অনুপস্থিত টেবিল/কলাম যোগ।',
        'includes/schema_catalog.php' => 'স্কিমা ক্যাটালগ।',
        'includes/sms_functions.php' => 'SMS পাঠানোর ফাংশন।',
        'includes/sms_sim.php' => 'সিম গেটওয়ে হেল্পার।',
        'includes/theme_functions.php' => 'দোকানের রং টোকেন লোড/সেভ।',
        'partials/header.php' => 'উপরের মেনুবার, আইকন, ঘড়ি।',
        'partials/footer.php' => 'পেজ ফুটার।',
        'partials/sidebar.php' => 'সাইডবার ফ্রেম।',
        'partials/sidebar-menu.php' => 'সাইডবার লিংক — ফাইল খাতা ও রং পরিবর্তন আইকন।',
        'partials/sidebar-icons.php' => 'সাইডবার লাইন আইকন।',
        'partials/sidebar-shortcuts.php' => 'হেডার থেকে সরানো শর্টকাট।',
        'partials/sidebar-style.php' => 'সাইডবার CSS — ফাইল খাতা বাটনসহ।',
        'modules/sales/pos.php' => 'বিক্রয় কাউন্টার পেজ — সব POS পার্ট জোড়ে।',
        'modules/sales/pos_workspace.php' => 'সার্চ, কার্ট, পুরাতন ব্যাটারি এক্সচেঞ্জ, ইঞ্জিনিয়ার বিল, কাস্টম পণ্য।',
        'modules/sales/pos_style.php' => 'POS লেআউট ও অ্যাকর্ডিয়ন বক্সের CSS।',
        'modules/sales/pos_summary.php' => 'পেমেন্ট ক্যালকুলেটর: ট্যাক্স, ডিসকাউন্ট, বাকি।',
        'modules/sales/pos_payment_panel.php' => 'পরিশোধ প্যানেল।',
        'modules/sales/process_sale.php' => 'বিক্রয় সেভ — স্টক কাটে, ইনভয়েস বানায়।',
        'modules/sales/search_product.php' => 'পণ্য সার্চ API।',
        'modules/sales/invoice.php' => 'ইনভয়েস দেখা।',
        'modules/sales/invoice_digital.php' => 'ডিজিটাল রসিদ শেয়ার।',
        'modules/sales/history.php' => 'সাম্প্রতিক বিক্রয় তালিকা।',
        'modules/settings/file_atlas.php' => 'ফাইল খাতা পেজ — ফোল্ডার ও ফাইলের কাজ।',
        'modules/settings/theme.php' => 'দোকানের রং স্টুডিও — টোকেন সেভ।',
        'modules/settings/process_theme.php' => 'রং সেভ/রিসেট API।',
        'modules/settings/users.php' => 'সিস্টেম ইউজার তালিকা, এক্টিভ/ইনঅ্যাকটিভ।',
        'modules/settings/sms_config.php' => 'এসএমএস সেটআপ, সিম ফোন লিংক।',
        'assets/js/pos-skins.js' => 'থিম অ্যাপ্লাই, কালার এডিট, কনট্রাস্ট।',
        'assets/js/pos-exchange.js' => 'এক্সচেঞ্জ খাতের পণ্য কার্টে যোগ (আলাদা POS ফোল্ড নেই)।',
        'assets/js/sw-boot.js' => 'সার্ভিস ওয়ার্কার রেজিস্টার, ক্যাশ ভার্সন।',
        'assets/css/pos-skins.css' => 'কাউন্টার থিম ও কালার স্টুডিও স্টাইল।',
        'assets/css/pos-terminal.css' => 'POS কাউন্টারের মূল স্টাইল।',
        'assets/css/pos-box-type.css' => 'বক্সের মাঝখানে শিরোনাম, সাদা কার্ড।',
        'assets/css/hdr-icons.css' => 'হেডার ও সাইডবার আইকন — ফাইল খাতা বাটন দেখায়।',
    ];
}

function ame_atlas_lookup(array $map, string $path): ?string
{
    foreach (ame_atlas_keys($path) as $key) {
        if ($key !== '' && isset($map[$key])) {
            return $map[$key];
        }
        if ($key === '' && isset($map[''])) {
            return $map[''];
        }
    }
    return null;
}

function ame_atlas_pretty(string $raw): string
{
    $key = strtolower($raw);
    $mod = ame_atlas_module_bn();
    if (isset($mod[$key])) {
        return $mod[$key];
    }
    $stem = [
        'add' => 'যোগ ফর্ম', 'edit' => 'সম্পাদনা', 'list' => 'তালিকা', 'index' => 'মূল পেজ',
        'process' => 'সেভ হ্যান্ডলার', 'history' => 'ইতিহাস', 'invoice' => 'রসিদ',
        'draft' => 'ড্রাফট', 'pay' => 'পেমেন্ট', 'pos' => 'POS', 'login' => 'লগইন',
        'schema' => 'স্কিমা', 'backup' => 'ব্যাকআপ', 'theme' => 'থিম',
    ];
    return $stem[$key] ?? str_replace(['-', '_'], ' ', $raw);
}

function ame_atlas_describe_folder(string $dirPath): string
{
    $map = ame_atlas_folder_raw();
    $hit = ame_atlas_lookup($map, $dirPath);
    if ($hit) {
        return $hit;
    }
    $n = ame_atlas_norm($dirPath);
    if ($n === '') {
        return $map[''];
    }
    $parts = explode('/', $n);
    $last = $parts[count($parts) - 1];
    $mod = ame_atlas_module_bn();
    $label = $mod[$last] ?? ame_atlas_pretty($last);
    return $label . ' ফোল্ডার।';
}

function ame_atlas_describe_file(string $filePath): string
{
    $hit = ame_atlas_lookup(ame_atlas_file_raw(), $filePath);
    if ($hit) {
        return $hit;
    }
    $base = strtolower(basename($filePath));
    if (str_starts_with($base, 'offline-') && str_ends_with($base, '.html')) {
        return 'অফলাইন PWA পেজ — ' . ame_atlas_pretty(str_replace(['offline-', '.html'], '', $base)) . '।';
    }
    if (str_ends_with($base, '_functions.php')) {
        return ame_atlas_pretty(str_replace('_functions.php', '', $base)) . ' মডিউলের শেয়ার করা PHP ফাংশন।';
    }
    if (str_starts_with($base, 'process_')) {
        return ame_atlas_pretty(str_replace(['process_', '.php'], '', $base)) . ' সেভ/পোস্ট হ্যান্ডলার।';
    }
    if (str_starts_with($base, 'pos_') || str_starts_with($base, 'pos-')) {
        $stem = preg_replace('/^pos[_-]/', '', $base);
        $stem = preg_replace('/\.(php|css|js)$/', '', (string)$stem);
        return 'POS কাউন্টারের ' . ame_atlas_pretty((string)$stem) . ' অংশ।';
    }
    $folder = ame_atlas_describe_folder(dirname($filePath) === '.' ? '' : dirname($filePath));
    $stem = pathinfo($base, PATHINFO_FILENAME);
    $hint = implode(' · ', array_map('ame_atlas_pretty', array_slice(preg_split('/[-_]/', $stem) ?: [], 0, 4)));
    if (str_ends_with($base, '.css')) {
        return $hint . ' স্টাইল — ' . $folder;
    }
    if (str_ends_with($base, '.js')) {
        return $hint . ' স্ক্রিপ্ট — ' . $folder;
    }
    return $hint . ' — ' . $folder;
}

function ame_atlas_root(): string
{
    return realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
}

function ame_atlas_should_skip(string $rel): bool
{
    $rel = ame_atlas_norm($rel);
    $parts = explode('/', $rel);
    $skip = ['node_modules', '.git', 'vendor', 'uploads', 'logs', 'tmp', 'temp', '.next', 'data'];
    foreach ($parts as $p) {
        if (in_array($p, $skip, true)) {
            return true;
        }
    }
    $base = $parts[count($parts) - 1] ?? '';
    if ($base === '.gitkeep' || str_ends_with($base, '.log') || str_ends_with($base, '.zip')) {
        return true;
    }
    return false;
}

function ame_atlas_is_source(string $name): bool
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $ok = ['php', 'js', 'css', 'html', 'md', 'sql', 'json', 'txt', 'htaccess', 'ini', 'sh'];
    if (in_array($name, ['.htaccess', '.user.ini', '.gitignore'], true)) {
        return true;
    }
    return in_array($ext, $ok, true);
}

function ame_atlas_walk(string $root): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $rel = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
        if (ame_atlas_should_skip($rel)) {
            continue;
        }
        if (!ame_atlas_is_source($file->getFilename())) {
            continue;
        }
        $out[] = $rel;
    }
    sort($out);
    return $out;
}

function ame_atlas_build(?string $root = null): array
{
    $root = $root ?: ame_atlas_root();
    $files = ame_atlas_walk($root);
    $nodes = [];
    $nodes[''] = [
        'path' => '',
        'name' => basename($root) ?: 'APONSHOP',
        'purpose' => ame_atlas_describe_folder(''),
        'folderIds' => [],
        'files' => [],
    ];

    $touch = static function (string $dir) use (&$nodes): void {
        if (isset($nodes[$dir])) {
            return;
        }
        $parts = $dir === '' ? [] : explode('/', $dir);
        $acc = '';
        $parent = '';
        foreach ($parts as $part) {
            $acc = $acc === '' ? $part : $acc . '/' . $part;
            if (!isset($nodes[$acc])) {
                $nodes[$acc] = [
                    'path' => $acc,
                    'name' => $part,
                    'purpose' => ame_atlas_describe_folder($acc),
                    'folderIds' => [],
                    'files' => [],
                ];
                if (!in_array($acc, $nodes[$parent]['folderIds'], true)) {
                    $nodes[$parent]['folderIds'][] = $acc;
                }
            }
            $parent = $acc;
        }
    };

    foreach ($files as $rel) {
        $dir = str_replace('\\', '/', dirname($rel));
        if ($dir === '.') {
            $dir = '';
        }
        $touch($dir);
        $nodes[$dir]['files'][] = [
            'path' => $rel,
            'name' => basename($rel),
            'purpose' => ame_atlas_describe_file($rel),
        ];
    }

    $nest = static function (string $id) use (&$nodes, &$nest): array {
        $node = $nodes[$id];
        $folders = [];
        sort($node['folderIds']);
        foreach ($node['folderIds'] as $childId) {
            $folders[] = $nest($childId);
        }
        usort($node['files'], static fn($a, $b) => strcmp($a['name'], $b['name']));
        $fileCount = count($node['files']);
        foreach ($folders as $child) {
            $fileCount += $child['fileCount'];
        }
        return [
            'path' => $node['path'],
            'name' => $node['name'],
            'purpose' => $node['purpose'],
            'folders' => $folders,
            'files' => $node['files'],
            'fileCount' => $fileCount,
        ];
    };

    $tree = $nest('');
    $folderCount = max(0, count($nodes) - 1);
    return [
        'tree' => $tree,
        'fileCount' => $tree['fileCount'],
        'folderCount' => $folderCount,
    ];
}
