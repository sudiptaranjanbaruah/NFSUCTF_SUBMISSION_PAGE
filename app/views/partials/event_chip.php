<?php
$state = event_state();
$target = match ($state) { 'upcoming' => setting('starts_at'), 'live' => setting('ends_at'), default => '' };
$label = match ($state) { 'upcoming' => 'Starts in', 'live' => $target ? 'Ends in' : 'Live', 'paused' => 'Paused', default => 'Ended' };
?>
<span class="event-chip <?= e($state) ?>" title="Event status">
  <span class="pulse"></span>
  <?= e($label) ?>
  <?php if ($target): ?><span data-countdown="<?= e(gmdate('c', parse_dt($target))) ?>" data-compact>—</span><?php endif ?>
</span>
