<div class="alert alert-info" style="margin-bottom:var(--s-6)"><?= icon('eye', 'sm') ?><div>Player preview of a <strong><?= e($ch['state']) ?></strong> challenge. Players see it in a side drawer on the challenge board. <a href="/admin/challenges/<?= (int) $ch['id'] ?>">Back to editor</a></div></div>
<article class="panel chal-detail" style="max-width:720px">
  <div class="drawer-head">
    <div class="row xs subtle"><span class="mono"><?= e(\App\Services\Challenges::code($ch)) ?></span><span><?= e($ch['category']) ?></span><span class="diff diff-<?= e($ch['difficulty']) ?>"><?= e(ucfirst($ch['difficulty'])) ?></span></div>
    <h2 style="margin-top:var(--s-1)"><?= e($ch['title']) ?></h2>
  </div>
  <div class="drawer-body">
    <div class="chal-facts">
      <span class="badge badge-accent"><?= icon('flag') ?><?= (int) $value ?> points<?= $ch['scoring'] === 'dynamic' ? ' (dynamic)' : '' ?></span>
      <?php if ($ch['author']): ?><span class="badge"><?= icon('user') ?><?= e($ch['author']) ?></span><?php endif ?>
      <?php foreach (array_filter(array_map('trim', explode(',', (string) $ch['tags']))) as $t): ?><span class="badge">#<?= e($t) ?></span><?php endforeach ?>
    </div>
    <div class="prose chal-desc"><?= md($ch['description']) ?></div>
    <?php if ($ch['connection_info']): ?><div class="detail-section"><h3>Connect</h3><div class="conn"><?= icon('terminal', 'sm') ?><code><?= e($ch['connection_info']) ?></code></div></div><?php endif ?>
    <?php if ($files): ?><div class="detail-section"><h3>Files</h3><div class="file-list"><?php foreach ($files as $f): ?><a class="file" href="/files/<?= (int) $f['id'] ?>"><?= icon('file') ?><div class="grow"><?= e($f['original_name']) ?><div class="hash">sha256 <?= e(substr($f['sha256'], 0, 16)) ?>…</div></div><span class="subtle xs"><?= human_bytes((int) $f['size']) ?></span></a><?php endforeach ?></div></div><?php endif ?>
    <?php if ($hints): ?><div class="detail-section"><h3>Hints</h3><div class="stack-sm"><?php foreach ($hints as $i => $h): ?><div class="hint is-open"><div class="xs subtle">Hint <?= $i + 1 ?>, costs <?= (int) $h['cost'] ?></div><div class="prose small"><?= md($h['content']) ?></div></div><?php endforeach ?></div></div><?php endif ?>
  </div>
</article>
