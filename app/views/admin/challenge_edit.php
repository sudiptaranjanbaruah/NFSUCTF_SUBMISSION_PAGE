<?php
$v = fn($k, $d = '') => old($k, (string) ($ch[$k] ?? $d));
$flags = $flags ?: [['type' => 'static', 'content' => '', 'case_insensitive' => 0]];
?>
<div class="page-head">
  <div>
    <a class="small" href="/admin/challenges"><?= icon('arrow-left', 'sm') ?> All challenges</a>
    <h1 style="margin-top:var(--s-2)"><?= $ch ? e($ch['title']) : 'New challenge' ?></h1>
    <?php if ($ch): ?><p><?= $solves ?> solves so far.<?= $solves ? ' Changing flags does not affect existing solves.' : '' ?></p><?php endif ?>
  </div>
  <?php if ($ch): ?>
  <div class="row wrap">
    <a class="btn" href="/admin/challenges/<?= (int) $ch['id'] ?>/preview" target="_blank"><?= icon('eye', 'sm') ?>Preview as player</a>
    <a class="btn" href="/admin/challenges/<?= (int) $ch['id'] ?>/stats"><?= icon('chart', 'sm') ?>Stats</a>
    <form method="post" action="/admin/challenges/<?= (int) $ch['id'] ?>/duplicate" class="inline"><?= csrf_field() ?><button class="btn" type="submit"><?= icon('copy', 'sm') ?>Duplicate</button></form>
  </div>
  <?php endif ?>
</div>

<form method="post" action="/admin/challenges/save" id="chal-form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) ($ch['id'] ?? 0) ?>">
  <input type="hidden" name="tz_offset" value="0">
  <div class="grid grid-sidebar">
    <div class="stack">
      <section class="panel">
        <div class="panel-head"><h2>Details</h2></div>
        <div class="panel-body">
          <div class="field"><label class="label" for="title">Title</label><input class="input" id="title" name="title" value="<?= $v('title') ?>" maxlength="120" required></div>
          <div class="grid grid-3" style="margin-top:var(--s-4)">
            <div class="field"><label class="label" for="category_id">Category</label>
              <select class="select" id="category_id" name="category_id">
                <?php foreach ($categories as $cat): ?><option value="<?= (int) $cat['id'] ?>" <?= (int) $v('category_id') === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option><?php endforeach ?>
              </select></div>
            <div class="field"><label class="label" for="difficulty">Difficulty</label>
              <select class="select" id="difficulty" name="difficulty">
                <?php foreach (['easy', 'medium', 'hard', 'insane'] as $d): ?><option value="<?= $d ?>" <?= $v('difficulty', 'easy') === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option><?php endforeach ?>
              </select></div>
            <div class="field"><label class="label" for="author">Author</label><input class="input" id="author" name="author" value="<?= $v('author') ?>" maxlength="64"></div>
          </div>
          <div class="field"><label class="label" for="description">Description <span class="subtle">Markdown</span></label>
            <div class="md-split">
              <textarea class="textarea code" id="description" name="description" rows="14" data-md-preview="desc-preview"><?= $v('description') ?></textarea>
              <div class="preview prose small" id="desc-preview" aria-live="polite"></div>
            </div>
          </div>
          <div class="grid grid-2" style="margin-top:var(--s-4)">
            <div class="field"><label class="label" for="connection_info">Connection info <span class="subtle">optional</span></label><input class="input code" id="connection_info" name="connection_info" value="<?= $v('connection_info') ?>" placeholder="nc chall.example.com 31337"></div>
            <div class="field"><label class="label" for="tags">Tags <span class="subtle">comma separated</span></label><input class="input" id="tags" name="tags" value="<?= $v('tags') ?>" placeholder="beginner, steganography"></div>
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head"><h2>Flags</h2><span class="subtle xs">Any one matching flag solves the challenge</span></div>
        <div class="panel-body">
          <div data-repeat="flags">
            <?php foreach ($flags as $fi => $f): ?>
            <div class="repeat-row flag-row" data-row>
              <select class="select" name="flag_type[]" aria-label="Flag type">
                <option value="static" <?= $f['type'] === 'static' ? 'selected' : '' ?>>Exact</option>
                <option value="regex" <?= $f['type'] === 'regex' ? 'selected' : '' ?>>Regex</option>
              </select>
              <input class="input code" name="flag_content[]" value="<?= e($f['content']) ?>" placeholder="<?= e(setting('flag_format')) ?>" aria-label="Flag">
              <label class="check nowrap"><input type="checkbox" name="flag_ci[]" value="<?= (int) $fi ?>" <?= $f['case_insensitive'] ? 'checked' : '' ?>> Ignore case</label>
              <button class="btn btn-ghost btn-sm btn-icon" type="button" data-remove-row aria-label="Remove flag"><?= icon('trash', 'sm') ?></button>
            </div>
            <?php endforeach ?>
          </div>
          <button class="btn btn-sm" type="button" data-add-row="flags" style="margin-top:var(--s-3)"><?= icon('plus', 'sm') ?>Add flag</button>
          <p class="help" style="margin-top:var(--s-3)">Regex flags must match the whole submission, e.g. <code>NFSUCTF\{[a-f0-9]{8}\}</code>.</p>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head"><h2>Hints</h2><span class="subtle xs">Teams pay the cost when they reveal a hint</span></div>
        <div class="panel-body">
          <div data-repeat="hints">
            <?php foreach ($hints as $h): ?>
            <div class="repeat-row hint-row" data-row>
              <textarea class="textarea" name="hint_content[]" rows="2" style="min-height:64px" aria-label="Hint"><?= e($h['content']) ?></textarea>
              <input class="input num" type="number" min="0" name="hint_cost[]" value="<?= (int) $h['cost'] ?>" aria-label="Cost in points">
              <button class="btn btn-ghost btn-sm btn-icon" type="button" data-remove-row aria-label="Remove hint"><?= icon('trash', 'sm') ?></button>
            </div>
            <?php endforeach ?>
          </div>
          <?php if (!$hints): ?><p class="subtle small" data-empty-hint>No hints yet.</p><?php endif ?>
          <button class="btn btn-sm" type="button" data-add-row="hints" style="margin-top:var(--s-3)"><?= icon('plus', 'sm') ?>Add hint</button>
        </div>
      </section>
    </div>

    <aside class="stack">
      <section class="panel">
        <div class="panel-head"><h2>Publishing</h2></div>
        <div class="panel-body">
          <div class="field"><label class="label" for="state">Visibility</label>
            <select class="select" id="state" name="state">
              <option value="draft" <?= $v('state', 'draft') === 'draft' ? 'selected' : '' ?>>Draft: work in progress</option>
              <option value="hidden" <?= $v('state') === 'hidden' ? 'selected' : '' ?>>Hidden: ready, not shown</option>
              <option value="visible" <?= $v('state') === 'visible' ? 'selected' : '' ?>>Visible to players</option>
            </select></div>
          <div class="field"><label class="label" for="release_at">Release at <span class="subtle" data-tz-name></span></label>
            <input class="input" type="datetime-local" id="release_at" name="release_at" data-utc="<?= e(dt_iso($ch['release_at'] ?? null)) ?>">
            <div class="help">Visible challenges stay hidden until this time. Leave empty to release now.</div></div>
          <div class="field"><label class="label" for="requires_id">Unlock after solving</label>
            <select class="select" id="requires_id" name="requires_id">
              <option value="">Nothing: always unlocked</option>
              <?php foreach ($others as $o): ?><option value="<?= (int) $o['id'] ?>" <?= (int) $v('requires_id') === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['title']) ?></option><?php endforeach ?>
            </select></div>
          <div class="field"><label class="label" for="sort_order">Order in category</label><input class="input num" type="number" id="sort_order" name="sort_order" value="<?= $v('sort_order', '0') ?>"></div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head"><h2>Scoring</h2></div>
        <div class="panel-body">
          <div class="segmented" role="radiogroup" aria-label="Scoring type" style="width:100%">
            <?php foreach (['static' => 'Static', 'dynamic' => 'Dynamic'] as $k => $label): ?>
              <label style="flex:1;display:flex"><input class="sr-only" type="radio" name="scoring" value="<?= $k ?>" <?= $v('scoring', 'static') === $k ? 'checked' : '' ?> data-scoring>
              <span class="<?= $v('scoring', 'static') === $k ? 'is-active' : '' ?>" style="flex:1;justify-content:center;height:30px;display:flex;align-items:center;border-radius:4px;cursor:pointer;font-size:var(--fs-sm)"><?= $label ?></span></label>
            <?php endforeach ?>
          </div>
          <div class="grid grid-2" style="margin-top:var(--s-4)">
            <div class="field"><label class="label" for="points" data-points-label>Points</label><input class="input num" type="number" min="0" id="points" name="points" value="<?= $v('points', '100') ?>"></div>
            <div class="field" data-dynamic><label class="label" for="min_points">Minimum</label><input class="input num" type="number" min="0" id="min_points" name="min_points" value="<?= $v('min_points', '50') ?>"></div>
          </div>
          <div class="field" data-dynamic><label class="label" for="decay">Solves to reach minimum</label><input class="input num" type="number" min="1" id="decay" name="decay" value="<?= $v('decay', '20') ?>"></div>
          <div class="field"><label class="label" for="max_attempts">Max wrong attempts <span class="subtle">per team</span></label><input class="input num" type="number" min="0" id="max_attempts" name="max_attempts" value="<?= $v('max_attempts') ?>" placeholder="Unlimited"></div>
          <div class="field"><label class="label" for="first_blood_bonus">First-blood bonus</label>
            <input class="input code" id="first_blood_bonus" name="first_blood_bonus" value="<?= $v('first_blood_bonus') ?>" placeholder="Default: <?= e(setting('first_blood_values')) ?><?= setting('first_blood_mode') === 'percent' ? ' %' : '' ?>">
            <div class="help">Bonus for the 1st, 2nd, 3rd solver, e.g. <code>100,50,25</code>. Type <code>off</code> to disable. Leave empty to use the event default.</div></div>
        </div>
      </section>

      <div class="row">
        <button class="btn btn-primary grow" type="submit"><?= icon('check', 'sm') ?>Save challenge</button>
      </div>
    </aside>
  </div>
</form>

<?php if ($ch): ?>
<div class="grid grid-sidebar" style="margin-top:var(--s-6)">
  <section class="panel" id="files">
    <div class="panel-head"><h2>Files</h2><span class="subtle xs">Up to <?= (int) env('UPLOAD_MAX_MB', 50) ?> MB each. Served only to signed-in players.</span></div>
    <div class="panel-body stack">
      <?php if ($files): ?>
      <div class="file-list">
        <?php foreach ($files as $f): ?>
          <div class="file">
            <?= icon('file') ?>
            <div class="grow"><div><?= e($f['original_name']) ?></div><div class="hash">sha256 <?= e($f['sha256']) ?></div></div>
            <span class="subtle xs"><?= human_bytes((int) $f['size']) ?></span>
            <a class="btn btn-ghost btn-sm btn-icon" href="/files/<?= (int) $f['id'] ?>" aria-label="Download"><?= icon('download', 'sm') ?></a>
            <form method="post" action="/admin/files/<?= (int) $f['id'] ?>/delete" data-confirm="Delete <?= e($f['original_name']) ?>?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm btn-icon" aria-label="Delete file"><?= icon('trash', 'sm') ?></button></form>
          </div>
        <?php endforeach ?>
      </div>
      <?php endif ?>
      <form method="post" action="/admin/challenges/<?= (int) $ch['id'] ?>/files" enctype="multipart/form-data" data-upload>
        <?= csrf_field() ?>
        <label class="dropzone" data-dropzone>
          <?= icon('upload', 'lg') ?>
          <span><strong>Drop files here</strong> or click to choose</span>
          <span class="xs subtle" data-dropzone-names></span>
          <input class="sr-only" type="file" name="files[]" multiple>
        </label>
        <button class="btn btn-sm" type="submit" style="margin-top:var(--s-3)"><?= icon('upload', 'sm') ?>Upload</button>
      </form>
    </div>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>Danger zone</h2></div>
    <div class="panel-body">
      <p class="small muted">Deleting removes the challenge, its files and every solve. Team scores drop accordingly. To take it offline without losing data, set it to Hidden.</p>
      <form method="post" action="/admin/challenges/<?= (int) $ch['id'] ?>/delete" data-confirm="Delete &quot;<?= e($ch['title']) ?>&quot; and all <?= (int) $solves ?> solves? This cannot be undone."><?= csrf_field() ?><button class="btn btn-danger" type="submit"><?= icon('trash', 'sm') ?>Delete challenge</button></form>
    </div>
  </section>
</div>
<?php endif ?>

<template id="tpl-flags">
  <div class="repeat-row flag-row" data-row>
    <select class="select" name="flag_type[]" aria-label="Flag type"><option value="static">Exact</option><option value="regex">Regex</option></select>
    <input class="input code" name="flag_content[]" placeholder="<?= e(setting('flag_format')) ?>" aria-label="Flag">
    <label class="check nowrap"><input type="checkbox" name="flag_ci[]" value="1"> Ignore case</label>
    <button class="btn btn-ghost btn-sm btn-icon" type="button" data-remove-row aria-label="Remove flag"><?= icon('trash', 'sm') ?></button>
  </div>
</template>
<template id="tpl-hints">
  <div class="repeat-row hint-row" data-row>
    <textarea class="textarea" name="hint_content[]" rows="2" style="min-height:64px" aria-label="Hint"></textarea>
    <input class="input num" type="number" min="0" name="hint_cost[]" value="0" aria-label="Cost in points">
    <button class="btn btn-ghost btn-sm btn-icon" type="button" data-remove-row aria-label="Remove hint"><?= icon('trash', 'sm') ?></button>
  </div>
</template>
