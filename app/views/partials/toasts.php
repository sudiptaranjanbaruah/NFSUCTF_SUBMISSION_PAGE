<div class="toasts" id="toasts" aria-live="polite">
<?php foreach (take_flashes() as $f): ?>
  <div class="toast <?= e($f['type']) ?>" data-autodismiss>
    <?= icon(match ($f['type']) { 'success' => 'check-circle', 'error' => 'alert', 'warning' => 'alert', default => 'info' }) ?>
    <div class="grow"><?= e($f['message']) ?></div>
  </div>
<?php endforeach ?>
</div>
