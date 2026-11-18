<div class="diag-page">
    <section class="diag-shell">
        <div class="diag-colors">
            <span></span><span></span><span></span><span></span>
        </div>

        <header class="diag-heading">
            <h2>🩺 <?php echo $escape($words['title']); ?></h2>
            <p><?php echo $escape($words['subtitle']); ?></p>
        </header>

        <div class="diag-content">
            <div class="diag-checks">
                <?php foreach ($checks as $check): ?>
                    <div class="diag-check <?php echo $check['success'] ? '' : 'failed'; ?>">
                        <strong>
                            <?php echo $check['success'] ? '✅' : '❌'; ?>
                            <?php echo $escape($check['name']); ?>
                        </strong>

                        <small>
                            <?php echo $escape($check['detail']); ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($clear_message)): ?>
                <div class="diag-empty" style="border-color:#22c55e;">
                    ✅ <?php echo $escape($clear_message); ?>
                </div>
            <?php endif; ?>

            <h3 class="diag-section-title" style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                <span>⚠️ <?php echo $escape($words['recent']); ?></span>
                <?php if (!empty($entries)): ?>
                    <form method="post" action="process_clear_error_log.php"
                          onsubmit="return confirm('<?php echo $escape($words['clear_confirm']); ?>');"
                          style="margin:0;">
                        <?php echo csrf_field(); ?>
                        <button type="submit"
                                style="background:#ef4444; color:#fff; border:none; border-radius:8px; padding:8px 14px; font-size:13px; cursor:pointer;">
                            🗑️ <?php echo $escape($words['clear']); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </h3>

            <?php if (empty($entries)): ?>
                <div class="diag-empty">
                    ✅ <?php echo $escape($words['empty']); ?>
                </div>
            <?php else: ?>
                <?php foreach ($entries as $entry): ?>
                    <article class="diag-error">
                        <div class="diag-error-top">
                            <span>
                                <?php echo $escape($entry['type'] ?? 'ERROR'); ?>
                            </span>
                            <span>
                                <?php echo $escape($entry['time'] ?? ''); ?>
                            </span>
                        </div>

                        <div class="diag-error-message">
                            <?php echo $escape($entry['message'] ?? ''); ?>
                        </div>

                        <div class="diag-error-meta">
                            <?php echo $escape($entry['file'] ?? ''); ?>
                            :<?php echo (int)($entry['line'] ?? 0); ?>
                            <br>
                            Page: <?php echo $escape($entry['page'] ?? ''); ?>
                            <br>
                            Error ID:
                            <?php echo $escape($entry['request_id'] ?? ''); ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>

            <a href="../../index.php" class="diag-back">
                ← <?php echo $escape($words['back']); ?>
            </a>
        </div>
    </section>
</div>