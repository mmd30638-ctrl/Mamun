<?php
/*
 * AME change_password.php
 * শুধু এই পথে রাখুন: public_html/modules/settings/change_password.php
 * header.php, index.php, config.php বা login.php-এ বসাবেন না।
 */

require_once __DIR__ . '/../../includes/config.php';
include __DIR__ . '/../../partials/header.php';

$ame_cpw_english = function_exists('current_lang')
    && current_lang() === 'en';

$ame_cpw = $ame_cpw_english
    ? [
        'title' => 'Change Password',
        'subtitle' => 'Hold the eye icon to preview the password',
        'new' => 'New password',
        'new_ph' => '8+ chars, letter and number',
        'confirm' => 'Confirm password',
        'confirm_ph' => 'Type the same password again',
        'update' => 'Update password',
        'back' => 'Back',
        'show' => 'Hold to show password'
    ]
    : [
        'title' => 'পাসওয়ার্ড পরিবর্তন করুন',
        'subtitle' => 'চোখের আইকনের উপর ধরলে পাসওয়ার্ড দেখাবে',
        'new' => 'নতুন পাসওয়ার্ড',
        'new_ph' => '৮+ অক্ষর, অক্ষর ও সংখ্যা',
        'confirm' => 'পাসওয়ার্ড আবার লিখুন',
        'confirm_ph' => 'একই পাসওয়ার্ড আবার দিন',
        'update' => 'পাসওয়ার্ড আপডেট করুন',
        'back' => 'ফিরে যান',
        'show' => 'ধরে রাখলে পাসওয়ার্ড দেখাবে'
    ];

$ame_cpw_h = static function ($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
};

$ame_cpw_t = static function ($key, $fallback) {
    if (!function_exists('t')) {
        return $fallback;
    }

    $value = (string)t($key);

    if ($value === '' || $value === $key) {
        return $fallback;
    }

    return $value;
};

$csrf_token = (string)($_SESSION['csrf_token'] ?? '');

if ($csrf_token === '') {
    $csrf_token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrf_token;
}

$success_message = (string)($_SESSION['success_message'] ?? '');
$error_message = (string)($_SESSION['error_message'] ?? '');
unset($_SESSION['success_message'], $_SESSION['error_message']);
$password_required = !empty($_SESSION['must_change_password']) || isset($_GET['required']);

$eye_icon = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 5c-5 0-9.3 3.1-11 7 1.7 3.9 6 7 11 7s9.3-3.1 11-7c-1.7-3.9-6-7-11-7zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-2.2a2.8 2.8 0 1 0 0-5.6 2.8 2.8 0 0 0 0 5.6z"/></svg>';
?>
<style>
    .cpw-wrap { padding: 15px; }
    .cpw-card {
        background: #fff;
        border-radius: 14px;
        max-width: 420px;
        margin: 0 auto;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(17, 24, 39, 0.08);
    }
    .cpw-topbar { display: flex; height: 6px; }
    .cpw-topbar span { flex: 1; }
    .cpw-c1 { background: var(--brand-navy); }
    .cpw-c2 { background: var(--brand-accent); }
    .cpw-c3 { background: var(--brand-gold); }
    .cpw-c4 { background: #C62828; }
    .cpw-header { text-align: center; padding: 20px 15px 15px; }
    .cpw-header h3 { margin: 0; font-size: 19px; color: #0D47A1; }
    .cpw-header small { color: #64748B; font-size: 12px; }
    .cpw-body { padding: 0 20px 20px; }
    .cpw-group { margin-bottom: 14px; }
    .cpw-group label {
        display: block;
        font-weight: 700;
        font-size: 13px;
        color: #111827;
        margin-bottom: 6px;
    }
    .cpw-field-wrap { position: relative; }
    .cpw-group input {
        width: 100%;
        padding: 11px 46px 11px 11px;
        border: 1px solid #D1D5DB;
        border-radius: 8px;
        box-sizing: border-box;
        font-size: 14px;
        background: #FAFAFA;
        color: #111827;
    }
    .cpw-group input:focus {
        outline: none;
        border-color: #0D47A1;
        background: #fff;
    }
    .cpw-eye {
        position: absolute;
        right: 4px;
        top: 50%;
        transform: translateY(-50%);
        width: 38px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: none;
        border: 0;
        border-radius: 8px;
        color: #0D47A1;
        cursor: pointer;
        padding: 0;
    }
    .cpw-eye svg { width: 22px; height: 22px; display: block; }
    .cpw-eye:hover,
    .cpw-eye.is-on { color: #087F3D; background: #E8F5E9; }
    .cpw-eye:focus { outline: 2px solid #0D47A1; outline-offset: 1px; }
    .cpw-submit {
        width: 100%;
        padding: 14px;
        border: 0;
        border-radius: 8px;
        background: #111827;
        color: #fff;
        font-weight: 700;
        font-size: 15px;
        cursor: pointer;
        margin-top: 8px;
    }
    .cpw-submit:active { background: #C62828; }
    .cpw-back {
        display: block;
        text-align: center;
        margin: 0 20px 18px;
        color: #0D47A1;
        text-decoration: none;
        font-size: 13px;
        font-weight: 700;
    }
    .cpw-alert {
        margin: 0 20px 14px;
        padding: 10px 12px;
        border-radius: 8px;
        font-size: 13px;
    }
    .cpw-success {
        background: #E8F5E9;
        color: #087F3D;
        border: 1px solid #C8E6C9;
    }
    .cpw-error {
        background: #FFEBEE;
        color: #C62828;
        border: 1px solid #FFCDD2;
    }
</style>

<div class="cpw-wrap">
    <div class="cpw-card">
        <div class="cpw-topbar">
            <span class="cpw-c1"></span>
            <span class="cpw-c2"></span>
            <span class="cpw-c3"></span>
            <span class="cpw-c4"></span>
        </div>

        <div class="cpw-header">
            <h3>
                🔐 <?php echo $ame_cpw_h($ame_cpw_t('change_password_title', $ame_cpw['title'])); ?>
            </h3>
            <small>
                <?php echo $ame_cpw_h($ame_cpw_t('change_password_subtitle', $ame_cpw['subtitle'])); ?>
            </small>
        </div>

        <?php if ($success_message !== ''): ?>
            <div class="cpw-alert cpw-success">
                <?php echo $ame_cpw_h($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message !== ''): ?>
            <div class="cpw-alert cpw-error">
                <?php echo $ame_cpw_h($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($password_required): ?>
            <div class="cpw-alert cpw-error" style="margin:0 20px 15px;">
                ⚠️ ডিফল্ট পাসওয়ার্ড ব্যবহার করা যাবে না। নিরাপত্তার জন্য এখনই শক্তিশালী পাসওয়ার্ড সেট করুন।
            </div>
        <?php endif; ?>

        <form
            action="process_password.php"
            method="POST"
            class="cpw-body"
            autocomplete="off"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo $ame_cpw_h($csrf_token); ?>"
            >
            <?php if ($password_required): ?>
            <input type="hidden" name="required" value="1">
            <?php endif; ?>

            <div class="cpw-group">
                <label for="newPass">
                    <?php echo $ame_cpw_h($ame_cpw_t('new_password_label', $ame_cpw['new'])); ?>
                </label>
                <div class="cpw-field-wrap">
                    <input
                        type="password"
                        name="new_pass"
                        id="newPass"
                        placeholder="<?php echo $ame_cpw_h($ame_cpw_t('new_password_placeholder', $ame_cpw['new_ph'])); ?>"
                        required
                        minlength="8"
                        maxlength="72"
                        autocomplete="new-password"
                        spellcheck="false"
                    >
                    <button
                        type="button"
                        class="cpw-eye"
                        data-target="newPass"
                        aria-label="<?php echo $ame_cpw_h($ame_cpw['show']); ?>"
                        title="<?php echo $ame_cpw_h($ame_cpw['show']); ?>"
                    >
                        <?php echo $eye_icon; ?>
                    </button>
                </div>
            </div>

            <div class="cpw-group">
                <label for="confirmPass">
                    <?php echo $ame_cpw_h($ame_cpw_t('confirm_password_label', $ame_cpw['confirm'])); ?>
                </label>
                <div class="cpw-field-wrap">
                    <input
                        type="password"
                        name="confirm_pass"
                        id="confirmPass"
                        placeholder="<?php echo $ame_cpw_h($ame_cpw_t('confirm_password_placeholder', $ame_cpw['confirm_ph'])); ?>"
                        required
                        minlength="8"
                        maxlength="72"
                        autocomplete="new-password"
                        spellcheck="false"
                    >
                    <button
                        type="button"
                        class="cpw-eye"
                        data-target="confirmPass"
                        aria-label="<?php echo $ame_cpw_h($ame_cpw['show']); ?>"
                        title="<?php echo $ame_cpw_h($ame_cpw['show']); ?>"
                    >
                        <?php echo $eye_icon; ?>
                    </button>
                </div>
            </div>

            <button type="submit" class="cpw-submit">
                ✅ <?php echo $ame_cpw_h($ame_cpw_t('update_password_button', $ame_cpw['update'])); ?>
            </button>
        </form>

        <a href="profile.php" class="cpw-back">
            ⬅️ <?php echo $ame_cpw_h($ame_cpw_t('back', $ame_cpw['back'])); ?>
        </a>
    </div>
</div>

<script>
(function () {
    function setVisible(input, button, visible) {
        if (!input || !button) {
            return;
        }

        input.type = visible ? 'text' : 'password';
        button.classList.toggle('is-on', visible);
        button.setAttribute('aria-pressed', visible ? 'true' : 'false');
    }

    document.querySelectorAll('.cpw-eye').forEach(function (button) {
        var input = document.getElementById(
            button.getAttribute('data-target')
        );

        function showPassword(event) {
            event.preventDefault();
            setVisible(input, button, true);
        }

        function hidePassword() {
            setVisible(input, button, false);
        }

        button.addEventListener('pointerenter', showPassword);
        button.addEventListener('pointerleave', hidePassword);
        button.addEventListener('pointerdown', function (event) {
            event.preventDefault();
            if (typeof button.setPointerCapture === 'function') {
                button.setPointerCapture(event.pointerId);
            }
            setVisible(input, button, true);
        });
        button.addEventListener('pointerup', hidePassword);
        button.addEventListener('pointercancel', hidePassword);
        button.addEventListener('blur', hidePassword);
        button.addEventListener('keydown', function (event) {
            if (event.key === ' ' || event.key === 'Enter') {
                event.preventDefault();
                setVisible(input, button, true);
            }
        });
        button.addEventListener('keyup', hidePassword);
    });
})();
</script>

<?php include __DIR__ . '/../../partials/footer.php'; ?>