<?php /** @var string $name @var string $id @var string $autocomplete @var bool $meter */ ?>
<div class="input-wrap">
  <input class="input" type="password" id="<?= e($id) ?>" name="<?= e($name) ?>" autocomplete="<?= e($autocomplete) ?>" required
    <?= !empty($meter) ? 'minlength="8" data-strength="' . e($id) . '-meter" aria-describedby="' . e($id) . '-help"' : '' ?>>
  <button type="button" class="btn-reveal" aria-label="Show password"><?= icon('eye', 'sm') ?></button>
</div>
<?php if (!empty($meter)): ?>
  <div class="strength" id="<?= e($id) ?>-meter" data-score="0"><span></span><span></span><span></span><span></span></div>
  <div class="help" id="<?= e($id) ?>-help">At least 8 characters.</div>
<?php endif ?>
