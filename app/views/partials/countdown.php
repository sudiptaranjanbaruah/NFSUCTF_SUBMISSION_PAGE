<?php
$state = event_state();
$target = match ($state) { 'upcoming' => setting('starts_at'), 'live' => setting('ends_at'), default => '' };
$label = match ($state) {
    'upcoming' => 'The event starts in',
    'live' => $target ? 'Time remaining' : 'Event status',
    'paused' => 'Event status',
    default => 'Event status',
};
?>
<div class="countdown-label"><?= e($label) ?></div>
<?php if (!$target): ?>
<div class="countdown-state"><?= e(['live' => 'Live now', 'paused' => 'Paused', 'ended' => 'Finished', 'upcoming' => 'Coming soon'][$state]) ?></div>
<?php if ($state === 'live'): ?><p class="countdown-note">Challenges are open. Register, form a team and start capturing flags.</p><?php endif ?>
<?php else: ?>
<div class="countdown-digits" data-countdown="<?= e(gmdate('c', parse_dt($target))) ?>" data-reload>
  <div><b>00</b><span>days</span></div><div><b>00</b><span>hours</span></div><div><b>00</b><span>min</span></div><div><b>00</b><span>sec</span></div>
</div>
<?php endif ?>
<?php if (setting('starts_at') || setting('ends_at')): ?>
<div class="countdown-when">
  <?php if (setting('starts_at')): ?><div>Starts <?= ts(gmdate('Y-m-d H:i:s', parse_dt(setting('starts_at'))), 'full') ?></div><?php endif ?>
  <?php if (setting('ends_at')): ?><div>Ends <?= ts(gmdate('Y-m-d H:i:s', parse_dt(setting('ends_at'))), 'full') ?></div><?php endif ?>
</div>
<?php endif ?>
