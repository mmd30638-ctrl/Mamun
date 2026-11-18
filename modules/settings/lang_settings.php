<?php
require_once '../../includes/config.php';
require_once '../../includes/lang_helper.php';
admin_only();
include '../../partials/header.php';

$current = current_lang();
?>
<style>
    .lang-wrap { padding: 15px; }
    .lang-card { background:#fff; border-radius:14px; max-width:480px; margin:0 auto; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.08); }
    .lang-topbar { display:flex; height:6px; }
    .lang-topbar span { flex:1; }
    .lang-c1{background:var(--brand-navy);} .lang-c2{background:var(--brand-accent);} .lang-c3{background:var(--brand-gold);}
    .lang-header { text-align:center; padding:20px 15px 15px; }
    .lang-header h3 { margin:0; font-size:19px; color:#1B5E20; }
    .lang-header small { color:#666; font-size:12px; }
    .lang-body { padding:0 20px 20px; }
    .lang-option { display:flex; align-items:center; justify-content:space-between; padding:16px; background:#fafafa; border-radius:10px; margin-bottom:12px; border:2px solid #eee; text-decoration:none; }
    .lang-option.active { border-color:var(--brand-accent); background:#E8F5E9; }
    .lang-option-left { display:flex; align-items:center; gap:12px; }
    .lang-flag { font-size:26px; }
    .lang-name { font-weight:700; color:#000; font-size:15px; }
    .lang-check { color:var(--brand-accent); font-size:18px; font-weight:900; }
    .lang-back { display:block; text-align:center; margin-top:14px; color:#666; text-decoration:none; font-size:13px; }
</style>

<div class="lang-wrap">
    <div class="lang-card">
        <div class="lang-topbar"><span class="lang-c1"></span><span class="lang-c2"></span><span class="lang-c3"></span></div>
        <div class="lang-header">
            <h3>🌐 <?php echo te('language_settings'); ?></h3>
            <small><?php echo te('select_language'); ?></small>
        </div>
        <div class="lang-body">
            <a href="/actions/switch_language.php?lang=bn" class="lang-option <?php echo ($current === 'bn') ? 'active' : ''; ?>">
                <div class="lang-option-left">
                    <span class="lang-flag">🇧🇩</span>
                    <span class="lang-name"><?php echo te('bengali'); ?> (Bengali)</span>
                </div>
                <?php if ($current === 'bn'): ?><span class="lang-check">✓</span><?php endif; ?>
            </a>
            <a href="/actions/switch_language.php?lang=en" class="lang-option <?php echo ($current === 'en') ? 'active' : ''; ?>">
                <div class="lang-option-left">
                    <span class="lang-flag">🇬🇧</span>
                    <span class="lang-name"><?php echo te('english'); ?></span>
                </div>
                <?php if ($current === 'en'): ?><span class="lang-check">✓</span><?php endif; ?>
            </a>
        </div>
        <a href="dashboard_settings.php" class="lang-back">⬅️ <?php echo te('back'); ?></a>
    </div>
</div>
