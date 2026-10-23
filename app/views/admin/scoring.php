<?php
use App\Controllers\Admin\ScoringController;
$mode = setting('first_blood_mode');
?>
<div class="page-head">
  <div><h1>Scoring and awards</h1><p>Give bonus points for unique solves, write-ups or bug reports, deduct points for misconduct, and review first-blood bonuses.</p></div>
</div>

<div class="grid grid-sidebar">
  <section class="panel">
    <div class="panel-head"><h2>Award or deduct points</h2></div>
    <form class="panel-body" method="post" action="/admin/awards">
      <?= csrf_field() ?>
      <div class="grid grid-2">
        <div class="field">
          <label class="label" for="type">Type</label>
          <select class="select" id="type" name="type">
            <?php foreach (ScoringController::TYPES as $k => $label): ?><option value="<?= $k ?>" <?= $prefill['type'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?>
          </select>
        </div>
        <div class="field">
          <label class="label" for="points">Points</label>
          <input class="input num" type="number" min="1" id="points" name="points" required placeholder="e.g. 50">
          <div class="help">Penalties are deducted automatically.</div>
        </div>
      </div>
      <div class="field">
        <label class="label" for="user_id">Player <span class="subtle">credits their team</span></label>
        <select class="select" id="user_id" name="user_id">
          <option value="">Whole team (choose below)</option>
          <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>" <?= $prefill['user_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['username']) ?> (<?= e($u['team']) ?>)</option><?php endforeach ?>
        </select>
      </div>
      <div class="field">
        <label class="label" for="team_ids">Teams <span class="subtle">hold Ctrl/Cmd to pick several</span></label>
        <select class="select" id="team_ids" name="team_ids[]" multiple size="6" style="height:auto;padding:6px;background-image:none">
          <?php foreach ($teams as $t): ?><option value="<?= (int) $t['id'] ?>" <?= $prefill['team_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach ?>
        </select>
      </div>
      <div class="field">
        <label class="label" for="challenge_id">Related challenge <span class="subtle">optional</span></label>
        <select class="select" id="challenge_id" name="challenge_id">
          <option value="">None</option>
          <?php foreach ($challenges as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $prefill['challenge_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach ?>
        </select>
      </div>
      <div class="field">
        <label class="label" for="reason">Reason <span class="subtle">shown to the team</span></label>
        <input class="input" id="reason" name="reason" maxlength="255" required placeholder="Solved the crypto challenge with an unintended lattice attack">
      </div>
      <div class="field"><label class="check"><input type="checkbox" name="is_public" value="1" checked> Show on the team page and live activity feed</label></div>
      <button class="btn btn-primary" type="submit" style="margin-top:var(--s-5)"><?= icon('award', 'sm') ?>Apply</button>
    </form>
  </section>

  <aside class="stack">
    <section class="panel">
      <div class="panel-head"><h2>First-blood bonus</h2><a class="small" href="/admin/settings#scoring">Change</a></div>
      <div class="panel-body small">
        <?php if ($mode === 'off'): ?>
          <p class="muted" style="margin:0">Off. Nobody earns extra points for solving first.</p>
        <?php else:
          $vals = array_map('trim', explode(',', setting('first_blood_values'))); ?>
          <dl class="kv">
            <?php foreach ($vals as $i => $v): ?><dt><?= ['1st', '2nd', '3rd', '4th', '5th'][$i] ?? ($i + 1) . 'th' ?> solver</dt><dd class="num">+<?= e($v) ?><?= $mode === 'percent' ? '% of value' : ' points' ?></dd><?php endforeach ?>
          </dl>
          <p class="help" style="margin:var(--s-3) 0 0">Applied automatically. Each challenge can override this in its scoring settings.</p>
        <?php endif ?>
      </div>
    </section>
    <section class="panel">
      <div class="panel-head"><h2>Recent bonuses</h2></div>
      <?php if (!$bloods): ?><div class="empty" style="padding:var(--s-6)"><p>None yet.</p></div><?php else: ?>
      <ul class="feed">
        <?php foreach ($bloods as $b): ?>
          <li><span class="feed-icon blood"><?= icon('droplet', 'sm') ?></span><div class="grow"><strong><?= e($b['team']) ?></strong>, <?= ['', '1st', '2nd', '3rd', '4th', '5th'][$b['solve_rank']] ?? '#' . $b['solve_rank'] ?> on <?= e($b['challenge']) ?><div><?= ts($b['created_at'], 'ago') ?></div></div><span class="num">+<?= (int) $b['bonus_points'] ?></span></li>
        <?php endforeach ?>
      </ul>
      <?php endif ?>
    </section>
  </aside>
</div>

<section class="panel" style="margin-top:var(--s-6)">
  <div class="panel-head"><h2>Award history</h2></div>
  <?php if (!$awards): ?>
    <div class="empty" style="padding:var(--s-8)"><?= icon('award') ?><p>Awards and penalties you give will be listed here.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>When</th><th>Team</th><th>Type</th><th>Reason</th><th class="r">Points</th><th>By</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($awards as $a): ?>
      <tr style="<?= $a['revoked_at'] ? 'opacity:.5' : '' ?>">
        <td class="small nowrap"><?= ts($a['created_at']) ?></td>
        <td><a class="rowlink" href="/admin/teams/<?= (int) $a['team_id'] ?>"><?= e($a['team']) ?></a><?= $a['username'] ? '<div class="subtle xs">' . e($a['username']) . '</div>' : '' ?></td>
        <td><span class="badge <?= $a['delta'] < 0 ? 'badge-danger' : 'badge-accent' ?>"><?= e(ScoringController::TYPES[$a['type']] ?? $a['type']) ?></span><?= $a['is_public'] ? '' : ' <span class="badge">Private</span>' ?></td>
        <td class="small"><?= e($a['reason']) ?><?= $a['challenge'] ? '<div class="subtle xs">' . e($a['challenge']) . '</div>' : '' ?></td>
        <td class="r num" style="color:<?= $a['delta'] < 0 ? 'var(--danger)' : 'var(--success)' ?>"><?= sprintf('%+d', $a['delta']) ?></td>
        <td class="small"><?= e($a['admin'] ?? '—') ?></td>
        <td class="r">
          <form method="post" action="/admin/awards/<?= (int) $a['id'] ?>/revoke" <?= $a['revoked_at'] ? '' : 'data-confirm="Revoke this award? The team loses these points."' ?>><?= csrf_field() ?>
            <button class="btn btn-ghost btn-sm" type="submit"><?= $a['revoked_at'] ? 'Restore' : 'Revoke' ?></button></form>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table></div>
  <?php endif ?>
</section>
