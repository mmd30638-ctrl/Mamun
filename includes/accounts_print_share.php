<?php
/**
 * Accounts print / share bar (partner statement and slips using $ame_slip).
 */
declare(strict_types=1);

function ame_slip_share_bar_render(): void
{
    if (!empty($GLOBALS['ame_slip_bar_done'])) {
        return;
    }
    if (!isset($GLOBALS['ame_slip']) || !is_array($GLOBALS['ame_slip'])) {
        return;
    }
    $GLOBALS['ame_slip_bar_done'] = true;
    $slip = $GLOBALS['ame_slip'];
    $target = (string)($slip['target'] ?? '#ame-slip');
    $file = (string)($slip['file'] ?? 'statement');
    $title = (string)($slip['title'] ?? 'Statement');
    $text = (string)($slip['text'] ?? $title);
    $ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1';
    ?>
    <div class="acc-share-wrap no-print" style="display:block;margin:12px 0;">
        <div class="acc-share-bar" data-slip-target="<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="acc-share-tile" data-pa-share-text="<?php echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); ?>" title="শেয়ার">
                <span class="acc-share-ico" aria-hidden="true">⎘</span>
                <span class="acc-share-lbl">শেয়ার</span>
            </button>
            <button type="button" class="acc-share-tile" data-pa-pdf-share="<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>" data-pa-pdf-file="<?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>" data-pa-pdf-title="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>" title="PDF শেয়ার">
                <span class="acc-share-ico" aria-hidden="true">📄</span>
                <span class="acc-share-lbl">PDF শেয়ার</span>
            </button>
            <button type="button" class="acc-share-tile" onclick="window.print();" title="ডাউনলোড / প্রিন্ট">
                <span class="acc-share-ico" aria-hidden="true">↓</span>
                <span class="acc-share-lbl">ডাউনলোড</span>
            </button>
            <button type="button" class="acc-share-tile" data-pa-slip-view="<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>" title="দেখুন">
                <span class="acc-share-ico" aria-hidden="true">👁</span>
                <span class="acc-share-lbl">দেখুন</span>
            </button>
        </div>
    </div>
    <style>
    .acc-share-bar{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;max-width:520px;margin:0 auto;padding:8px;}
    .acc-share-tile{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;min-height:72px;padding:8px 6px;border:1px solid #cbd5e1;border-radius:12px;background:#fff;color:#0f172a;font-weight:700;font-size:13px;cursor:pointer;}
    .acc-share-tile[data-pa-pdf-share]{border-color:#16a34a;background:#ecfdf5;color:#14532d;}
    .acc-share-ico{font-size:22px;line-height:1;}
    .acc-share-lbl{text-align:center;line-height:1.2;}
    </style>
    <script>
    document.addEventListener('click',function(e){
        var t=e.target.closest('[data-pa-slip-view]');
        if(t){var el=document.querySelector(t.getAttribute('data-pa-slip-view'));if(el)el.scrollIntoView({behavior:'smooth',block:'start'});}
        var s=e.target.closest('[data-pa-share-text]');
        if(s&&navigator.share){navigator.share({text:s.getAttribute('data-pa-share-text')||document.title}).catch(function(){});}
    });
    </script>
    <script src="/assets/js/partner-slip-pdf-share.js?v=<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <?php
}

function ame_accounts_print_share_render(): void
{
    ame_slip_share_bar_render();
}