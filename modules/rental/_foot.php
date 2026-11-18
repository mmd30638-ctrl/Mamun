<?php
echo '</div>';
$footer = __DIR__ . '/../../partials/footer.php';
if (is_file($footer)) {
    include $footer;
}
