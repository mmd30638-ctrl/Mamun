<?php
/**
 * পেমেন্ট ও বাতিলের একবারের নিরাপত্তা কোড।
 * একই ফর্ম দুবার চাপলে দ্বিতীয়বার কোড খরচ হয়ে যাওয়ায় আটকে যায়।
 */

function ame_pay_sec_t(string $bn, string $en): string
{
    return (function_exists('is_english') && is_english()) ? $en : $bn;
}

function ame_pay_sec_bag(): array
{
    $bag = $_SESSION['ame_pay_sec'] ?? [];
    return is_array($bag) ? $bag : [];
}

function ame_pay_sec_key(string $scope, int $partyId): string
{
    $scope = preg_replace('/[^a-z0-9_]+/i', '', $scope) ?: 'pay';
    return strtolower($scope) . ':' . max(0, $partyId);
}

function ame_pay_sec_locked(?array $bag = null): bool
{
    $bag = $bag ?? ame_pay_sec_bag();
    $fails = (int) ($bag['_fails'] ?? 0);
    $until = (int) ($bag['_lock_until'] ?? 0);
    return $fails >= 5 && $until > time();
}

function ame_pay_sec_note_fail(): void
{
    $bag = ame_pay_sec_bag();
    $fails = (int) ($bag['_fails'] ?? 0) + 1;
    $bag['_fails'] = $fails;
    if ($fails >= 5) {
        $bag['_lock_until'] = time() + 600;
    }
    $_SESSION['ame_pay_sec'] = $bag;
}

function ame_pay_sec_make(string $scope, int $partyId): string
{
    $bag = ame_pay_sec_bag();
    if (ame_pay_sec_locked($bag)) {
        throw new RuntimeException(ame_pay_sec_t(
            'পাঁচবার ভুল কোড। দশ মিনিট পর আবার চেষ্টা করুন।',
            'Five wrong codes. Try again in ten minutes.'
        ));
    }
    $key = ame_pay_sec_key($scope, $partyId);
    $had = $bag[$key] ?? null;
    if (is_array($had) && (int) ($had['exp'] ?? 0) > time() && (string) ($had['code'] ?? '') !== '') {
        return strtoupper((string) $had['code']);
    }
    $code = strtoupper(bin2hex(random_bytes(3)));
    $bag[$key] = [
        'code' => $code,
        'exp' => time() + 180,
        'scope' => $scope,
        'party' => $partyId,
    ];
    $_SESSION['ame_pay_sec'] = $bag;
    return $code;
}

function ame_pay_sec_verify(string $scope, int $partyId, string $typed): void
{
    $bag = ame_pay_sec_bag();
    if (ame_pay_sec_locked($bag)) {
        throw new RuntimeException(ame_pay_sec_t(
            'পাঁচবার ভুল কোড। দশ মিনিট পর আবার চেষ্টা করুন।',
            'Five wrong codes. Try again in ten minutes.'
        ));
    }
    $key = ame_pay_sec_key($scope, $partyId);
    $slot = is_array($bag[$key] ?? null) ? $bag[$key] : [];
    $want = strtoupper((string) ($slot['code'] ?? ''));
    $exp = (int) ($slot['exp'] ?? 0);
    if ($want === '' || $exp < time()) {
        throw new RuntimeException(ame_pay_sec_t(
            'নিরাপত্তা কোড মেয়াদোত্তীর্ণ। পেজ রিফ্রেশ করে নতুন কোড নিন।',
            'Security code expired. Refresh for a new code.'
        ));
    }
    $got = strtoupper(preg_replace('/\s+/', '', $typed) ?? '');
    if ($got === '' || !hash_equals($want, $got)) {
        ame_pay_sec_note_fail();
        throw new RuntimeException(ame_pay_sec_t(
            'নিরাপত্তা কোড মেলেনি। পর্দায় যে কোড আছে সেটাই লিখুন।',
            'Security code did not match. Type the code shown on screen.'
        ));
    }
    unset($bag[$key]);
    $bag['_fails'] = 0;
    $bag['_lock_until'] = 0;
    $_SESSION['ame_pay_sec'] = $bag;
}

function ame_pay_sec_posted(): string
{
    return trim((string) ($_POST['pay_security_code'] ?? ''));
}

function ame_pay_sec_box(string $scope, int $partyId, string $hintBn = '', string $hintEn = '', bool $required = true): string
{
    static $boxSeq = 0;
    $boxSeq++;
    try {
        $code = ame_pay_sec_make($scope, $partyId);
    } catch (Throwable $e) {
        return '<p class="ame-pay-sec-err">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    }
    $hint = ame_pay_sec_t(
        $hintBn !== '' ? $hintBn : 'বারবার পেমেন্ট আটকাতে এই কোড লিখে নিশ্চিত করুন।',
        $hintEn !== '' ? $hintEn : 'Type this code to confirm. It stops the same payment twice.'
    );
    $label = ame_pay_sec_t('নিরাপত্তা কোড', 'Security code');
    $ph = ame_pay_sec_t('এখানে কোড লিখুন', 'Type the code here');
    $escCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $escHint = htmlspecialchars($hint, ENT_QUOTES, 'UTF-8');
    $escLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $escPh = htmlspecialchars($ph, ENT_QUOTES, 'UTF-8');
    $inputId = 'amePaySecurityCode-' . preg_replace('/[^a-z0-9_-]+/i', '-', $scope) . '-' . max(0, $partyId) . '-' . $boxSeq;
    $clickHint = htmlspecialchars(ame_pay_sec_t('কোড বসাতে একবার ক্লিক করুন', 'Click once to fill the code'), ENT_QUOTES, 'UTF-8');
    // White box + navy type, inline !important so ame-front-colors cannot hide the code.
    return '<div class="ame-pay-sec" data-ame-pay-sec="1" data-security-code="' . $escCode . '" style="margin:12px 0;padding:14px;border:2px solid #c2410c !important;border-radius:12px;background:#ffffff !important;background-color:#ffffff !important;color:#111827 !important">'
        . '<p class="ame-pay-sec-kicker" style="margin:0 0 4px;font-size:12px;font-weight:800;letter-spacing:.04em;color:#c2410c !important;-webkit-text-fill-color:#c2410c !important">' . $escLabel . '</p>'
        . '<button type="button" class="ame-pay-sec-code" data-ame-pay-sec-fill="1" aria-label="' . $clickHint . '" aria-controls="' . $inputId . '" title="' . $clickHint . '" style="display:block;width:100%;margin:0 0 8px;padding:6px 8px;border:1.5px dashed #003f8f;border-radius:10px;background:#ffffff;cursor:pointer;text-align:left;font-size:32px;font-weight:900;letter-spacing:.16em;font-family:ui-monospace,Menlo,Consolas,monospace;color:#003f8f !important;-webkit-text-fill-color:#003f8f !important">' . $escCode . '</button>'
        . '<p class="ame-pay-sec-click-hint" style="margin:-2px 0 8px;font-size:12px;font-weight:800;color:#0f766e !important;-webkit-text-fill-color:#0f766e !important">' . $clickHint . '</p>'
        . '<p class="ame-pay-sec-hint" style="margin:0 0 8px;font-size:13px;font-weight:700;color:#111827 !important;-webkit-text-fill-color:#111827 !important">' . $escHint . '</p>'
        . '<label class="ame-pay-sec-lbl" for="' . $inputId . '" style="display:block;margin:0 0 6px;font-size:13px;font-weight:800;color:#111827 !important;-webkit-text-fill-color:#111827 !important">' . $escLabel . '</label>'
        . '<input class="ame-pay-sec-input" id="' . $inputId . '" name="pay_security_code" type="text"'
        . ($required ? ' required' : '')
        . ' maxlength="12" autocomplete="off" autocapitalize="characters" spellcheck="false"'
        . ' inputmode="text" placeholder="' . $escPh . '"'
        . ' style="width:100%;box-sizing:border-box;min-height:46px;padding:8px 12px;border-radius:10px;border:2px solid #003f8f !important;background:#ffffff !important;color:#111827 !important;-webkit-text-fill-color:#111827 !important;font-size:18px;font-weight:800;letter-spacing:.12em;text-transform:uppercase">'
        . '</div>';
}
