<?php
/**
 * One-shot live repair. Upload next to login.php inside public_html.
 * Open: /repair.php?k=apon743
 * Does not load config.php.
 */
declare(strict_types=1);

const AME_REPAIR_KEY = 'apon743';
const AME_REPAIR_VERSION = '10.98.743';

function ame_repair_is_cli(): bool
{
    return PHP_SAPI === 'cli';
}

function ame_repair_key_ok(): bool
{
    if (ame_repair_is_cli()) {
        global $argv;
        return (string) ($argv[1] ?? '') === AME_REPAIR_KEY;
    }
    return (string) ($_GET['k'] ?? '') === AME_REPAIR_KEY;
}

function ame_repair_page(string $title, string $body, bool $ok = false): void
{
    if (!headers_sent() && !ame_repair_is_cli()) {
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
        http_response_code($ok ? 200 : 400);
    }
    if (ame_repair_is_cli()) {
        fwrite($ok ? STDOUT : STDERR, strip_tags($title . "\n" . $body) . "\n");
        return;
    }
    $color = $ok ? '#0e8f52' : '#b91c1c';
    echo '<!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title></head>';
    echo '<body style="margin:0;background:#fff;color:#10233f;font-family:system-ui,sans-serif">';
    echo '<main style="min-height:100vh;display:grid;place-items:center;padding:20px">';
    echo '<section style="width:min(520px,100%);border:1px solid #e5e7eb;border-top:7px solid ' . $color . ';border-radius:18px;padding:24px;box-shadow:0 14px 36px rgba(15,23,42,.12)">';
    echo '<h1 style="margin:0 0 10px;color:' . $color . ';font-size:22px">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
    echo '<div style="line-height:1.65">' . $body . '</div>';
    echo '</section></main></body></html>';
}

function ame_repair_first_install_source(): string
{
    $bin = hex2bin(AME_REPAIR_FIRST_HEX);
    if ($bin === false) {
        return '';
    }
    $out = gzuncompress($bin);
    return is_string($out) ? $out : '';
}

function ame_repair_patch_config(string $src): string
{
    $src = str_replace("'10.98.741'", "'" . AME_REPAIR_VERSION . "'", $src);
    $src = str_replace("'10.98.742'", "'" . AME_REPAIR_VERSION . "'", $src);
    if (str_contains($src, 'is_file($ame_first_install)')) {
        return $src;
    }
    $guarded = <<<'GUARD'
    $ame_first_install = __DIR__ . '/first_install.php';
    if (is_file($ame_first_install)) {
        try {
            require_once $ame_first_install;
            if (function_exists('ame_install_needed') && ame_install_needed($db)) {
                ame_first_install($db);
            }
        } catch (Throwable $e) {
            error_log('First install boot skipped: ' . $e->getMessage());
        }
    }
GUARD;
    $count = 0;
    $patched = preg_replace(
        '/^[ \t]*require_once __DIR__ \. \'\/first_install\.php\';\s*^[ \t]*if \(function_exists\(\'ame_install_needed\'\) && ame_install_needed\(\$db\)\) \{\s*^[ \t]*ame_first_install\(\$db\);\s*^[ \t]*\}/m',
        $guarded,
        $src,
        1,
        $count
    );
    if (is_string($patched) && $count === 1) {
        return $patched;
    }
    $count = 0;
    $patched = preg_replace(
        '/^[ \t]*require_once __DIR__ \. \'\/first_install\.php\';/m',
        $guarded,
        $src,
        1,
        $count
    );
    return (is_string($patched) && $count === 1) ? $patched : $src;
}

function ame_repair_write(string $path, string $data): bool
{
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }
    return @file_put_contents($path, $data) !== false;
}

if (!defined('AME_REPAIR_FIRST_HEX')) {
    define('AME_REPAIR_FIRST_HEX', '78dabd57496fdb4614beeb574c5ca1241d6d7602c4b52abb5e98448997545292069642d0d4c8624d91cccc30b690f89043eb43d0539116e8250890c8451b34404e3d14f15fd14fe99b199222b538eea58261cdf296efbd799bbe5ef5bb7ea6383f9f41f36838f87d38783b3c7b010bd80c86672f878357c3c127d82fc3c12f62f971383887cddfc3b39fc4edf970f0175cc0d16ff1fd70f06138f86778760aa7afe16b38782f4e3fa2ab70f246acffe4a45cc66b38fa41ac3e08016f84c07309e23dd724107034efde729c9bb5dd7ba8881ab5fb3b1b6b0d1d969bfa960e0b4105ca4e8110363f0e07bf4a0cefc4f1ab6980f82597ff4ac03995969d0ace17e2e86582e4ec54422866327607a957dab863bbb8ad2a6bdbbab176ef9ef140afd5abbb3b8aa6a16719049facd9c30f30b9693b185550db262e1ca886b159ad1986860a4829863c0576cc94729209186c6a106cb6cd7d07ab09511a5a458cd83d55a5f0e51e68a803a7c6016686e5b90cbb8ca6c835b48c9450b8c43c093917abbd52a90035a8880e8079a154f86aa970e3fa35452b674e32994ee05accf65c042406883403871966bb67bb4640b1b4525b46125e46fa826016101729824cf99c18dfa4f4c823ed9962d67ccfbd71f8c5f5a5c505290cc218455cc6534cec4e5f4d51e550a150d05005ec6324c0fc1d2f00d0356977a6f2ec623fbb50cafaebb79c6bee23dca8779e2e3ddcd1d7f5a3de22f6e977d55a69d33a0ed8c1e29d7e63e9fbfdf6f19df58da5c5f56b85cead29a653dfb199419f38066526c33df1825231ca12f30860988498fd10053f82e80002080fdf312dacce3549d39dcba139f15ff09493b43ec10731b1527cdca4f3cd6273be30bfda844591827314256444abab62110af04dc2682442200501652ec02d8e5896d15e2b64f00206e4d1b6e341085b5da486824c8ab2606994218203f6a822435adc95e32b9e67f25a4465928b7f78b8db6e80470c27e352c72dcfe7f71e37dd1647df8bac1678b8d5b028cf8405de454dd674c1d5a5e67169bd3c371b287afe5cbc0fbc27186d1cd9ac1bca50f2f9517db8941de0cfbd160019a13b490623bf9f0c2912b822a07865507b7d58da28dbdecfa128acf8c5445c61df23e2f114ef504195155402c0f4d0f6e34dc7b41db1d96b850f2c4a61aa527564d1493cb014bc27b95bc21aa5feed16127de0132fd9a216ff21363f87fd437415d914e41efad3391423289b42c5c8599127a49aa48fc2f08f0ae5943a29c04a966fc041f9952701265039ea7a03ed4095aca38075967afbd795595437776b7af5d68e71577f646cdcd637eed62ba588388efe99692ef3674a5608bf26950982f1c8c9624212060a7238f248394d46bd0e0b6b06b7ddb45d500d84f0a49b01e0b20090a2a578f8270ce37116d3e1afdd47f8d8a68c5e9e6f1beaabed4333f421a94cd24787b8af6869a8329300eeb8a9a9481241d9ba7a35cd7b82b043f1457c8908e498c6d833170a8a854072a4549f2422ee32e1b110d93c1eb8138d01e376ba2f257219b277dff39c2879ad2eb60ec1ac2480b9faeddd87a8b1b6be0571bc55bdab2385f767aa44a54b0459c80a6f2657f91537e819c43ba2a29a95920f1122ee98e09e54a2c95098d00fa3d94603d96d74b3b6bb8d8472f4f0b65ed3513428f05a20670240b85d6da08524b848ee975f462a12e856a662e3cd3d058d6b022d17cd2a61ebe24d7f2aa59c06422aca7a2c3614fa0b3436acc62894ea4e5daf355075a7b11b1aac468a72f1849243c473302fc7260b680ef5020a55a96bba07d818d14059a5818f89449148b3076b5bf7e149d5d51c927f0bf24f53044dea7539dc5435e668746af114e016402a3b06a696e943c08932a20a92445e0ac77c86859368133559e521aa256322955273ff87b3920e539e45e69ff0eeff2cb24cec64188e9c3917cbd0d24dc57312619b888afccabeedb60113317baa42299fab84be9c74614ef24681e41d8ab62e18f131b602865341965fb11c8fc66771c33f9cac141d9b804fa0d432d371d24522d9e2b9076d0b5004aecbc7804a329345be853753f2ea524341ec45be15b2e184606875c4749938e5f9d94afb334633cadd2c855ad43367fd6c02fb8a260cf7b4ebf906eef9ac6f303e7dd002dc444f12cf33131391188542158964811012d7a12aa9491019b4ef5a05f8b99c1ede087e12d8041b9e6b617411577a528c9ece90254d5544b11fb170b0935362965f85d68c117383c6fae8a855721a0b5296e1b622074938d953c2a3d06f7003137034b54f1722060b9a94119f8cf19ea49e37942103a3155930d9db46464c0fd0cfb5cb30fc0d1744c3eff299ad92fcd736192abe02e9818412786eaf83a402987c29d8a089064ac69b2747f92f6edc8a67');
}

$root = __DIR__;
$includes = $root . '/includes';
$configPath = $includes . '/config.php';
$firstPath = $includes . '/first_install.php';
$versionPath = $root . '/VERSION.txt';

if (!ame_repair_key_ok()) {
    ame_repair_page('চাবি লাগবে', 'ঠিক লিংক খুলুন। File Manager-এ এই ফাইলটা <code>login.php</code>-এর পাশে থাকতে হবে।', false);
    exit(1);
}

if (!is_file($configPath)) {
    ame_repair_page('ফাইল পাওয়া যায়নি', '<code>includes/config.php</code> নেই। <code>repair.php</code> <b>public_html</b> ফোল্ডারে তুলুন — যেখানে <code>login.php</code> আছে।', false);
    exit(1);
}

$nestedFirst = $root . '/public_html/includes/first_install.php';
$firstSrc = is_file($nestedFirst) ? (string) file_get_contents($nestedFirst) : ame_repair_first_install_source();
if ($firstSrc === '' || !str_contains($firstSrc, 'function ame_install_needed')) {
    ame_repair_page('রিপেয়ার ফাইল খালি', 'first_install সোর্স পাওয়া যায়নি।', false);
    exit(1);
}
if (!ame_repair_write($firstPath, $firstSrc)) {
    ame_repair_page('লেখা যায়নি', '<code>includes/first_install.php</code> সেভ হয়নি। File Manager-এ লেখার অনুমতি দিন।', false);
    exit(1);
}

$cfg = (string) file_get_contents($configPath);
$patched = ame_repair_patch_config($cfg);
if (!ame_repair_write($configPath, $patched)) {
    ame_repair_page('কনফিগ সেভ হয়নি', '<code>includes/config.php</code> বদলানো যায়নি।', false);
    exit(1);
}

@file_put_contents($versionPath, AME_REPAIR_VERSION . "\n");

$gone = false;
if (!ame_repair_is_cli()) {
    $gone = @unlink(__FILE__);
}

$login = '/login.php';
ame_repair_page(
    'সাইট ঠিক হয়েছে',
    '<p>বুট ফাইল বসানো হয়েছে (' . htmlspecialchars(AME_REPAIR_VERSION, ENT_QUOTES, 'UTF-8') . ')। এখন লগইন খুলুন।</p>'
    . '<p><a href="' . $login . '" style="display:inline-block;margin-top:8px;padding:12px 18px;background:#0b1e4d;color:#fff;border-radius:10px;font-weight:800;text-decoration:none">লগইন খুলুন</a></p>'
    . ($gone ? '<p style="color:#64748b;font-size:13px">repair.php মুছে দেওয়া হয়েছে।</p>' : ''),
    true
);
