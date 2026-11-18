<?php
$hire_here = $here ?? basename($_SERVER['SCRIPT_NAME'] ?? '');
$items = [
    'index.php' => ame_l('দৈনিক ভাড়া', 'Daily rent'),
    'create.php' => ame_l('নতুন চুক্তি', 'New contract'),
    'owners.php' => ame_l('মালিক হিসাব', 'Owner ledger'),
    'due-reminder.php' => ame_l('বকেয়া তারিখ', 'Due dates'),
    'now.php' => ame_l('সব চুক্তি', 'All contracts'),
    'settings.php' => ame_l('সেটিংস', 'Settings'),
];
?>
<nav class="hire-nav no-print">
<?php foreach ($items as $href => $label):
    $on = ($hire_here === $href || $hire_here === str_replace('.php', '', $href) || $hire_here === basename($href, '.php'));
    if ($hire_here === 'index' && $href === 'index.php') {
        $on = true;
    }
    if (in_array($href, ['show.php', 'now.php'], true) && in_array($hire_here, ['contracts.php', 'contracts', 'all.php', 'all', 'show', 'show.php', 'chuktis.php', 'chuktis', 'now.php', 'now'], true)) {
        $on = true;
    }
?>
  <a class="<?php echo $on ? 'is-on' : ''; ?>" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $label; ?></a>
<?php endforeach; ?>
</nav>
