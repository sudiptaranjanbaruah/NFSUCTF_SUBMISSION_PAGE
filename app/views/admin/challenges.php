<?php $stateBadge = ['draft' => 'badge', 'hidden' => 'badge badge-warning', 'visible' => 'badge badge-success']; ?>
<div class="page-head">
  <div><h1>Challenges</h1><p><?= count($rows) ?> challenges, <?= count(array_filter($rows, fn($c) => $c['state'] === 'visible')) ?> visible to players.</p></div>
  <div class="row wrap">
    <details class="menu">
      <summary class="btn"><?= icon('upload', 'sm') ?>Import / export</summary>
      <div class="menu-list" style="min-width:300px">
        <a href="/admin/challenges/export"><?= icon('download', 'sm') ?>Export JSON (no files)</a>
        <a href="/admin/challenges/export?files=1"><?= icon('download', 'sm') ?>Export JSON with files</a>
        <div class="sep"></div>
        <form method="post" action="/admin/challenges/import" enctype="multipart/form-data" style="padding:var(--s-2) var(--s-3)">
          <?= csrf_field() ?>
          <label class="label" for="bundle" style="margin-bottom:6px">Import an export file</label>
          <input class="input" type="file" id="bundle" name="bundle" accept=".json,application/json" required style="height:auto;padding:6px">
          <button class="btn btn-sm btn-block" type="submit" style="margin-top:var(--s-2)">Import as hidden</button>
        </form>
      </div>
    </details>
    <a class="btn btn-primary" href="/admin/challenges/new"><?= icon('plus', 'sm') ?>New challenge</a>
  </div>
</div>

<section class="panel" style="margin-bottom:var(--s-6)">
  <?php if (!$rows): ?>
    <div class="empty"><?= icon('target') ?><h3>No challenges yet</h3><p>Create your first challenge or import a set from another event.</p><a class="btn btn-primary" href="/admin/challenges/new">New challenge</a></div>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Challenge</th><th>State</th><th>Scoring</th><th class="r">Value</th><th class="r">Solves</th><th class="r">Attempts</th><th>Setup</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $c):
      $scheduled = $c['release_at'] && strtotime($c['release_at'] . ' UTC') > time(); ?>
      <tr>
        <td>
          <a class="rowlink" href="/admin/challenges/<?= (int) $c['id'] ?>"><?= e($c['title']) ?></a>
          <div class="subtle xs"><span class="mono"><?= e($c['code']) ?></span>, <?= e($c['category']) ?>, <span class="diff diff-<?= e($c['difficulty']) ?>"><?= e($c['difficulty']) ?></span></div>
        </td>
        <td>
          <span class="<?= $stateBadge[$c['state']] ?>"><?= e(ucfirst($c['state'])) ?></span>
          <?php if ($scheduled): ?><div class="xs subtle" style="margin-top:4px"><?= icon('clock', 'sm') ?> <?= ts($c['release_at']) ?></div><?php endif ?>
        </td>
        <td class="small"><?= $c['scoring'] === 'dynamic' ? 'Dynamic ' . (int) $c['points'] . '→' . (int) $c['min_points'] : 'Static' ?></td>
        <td class="r num"><?= (int) $c['value'] ?></td>
        <td class="r num"><?= (int) $c['solves'] ?></td>
        <td class="r num"><?= (int) $c['attempts'] ?></td>
        <td class="small">
          <?php if (!$c['flag_count']): ?><span class="badge badge-danger">No flag</span><?php else: ?><span class="subtle"><?= (int) $c['flag_count'] ?> flag<?= $c['flag_count'] > 1 ? 's' : '' ?></span><?php endif ?>
          <?php if ($c['file_count']): ?><span class="subtle">, <?= (int) $c['file_count'] ?> file<?= $c['file_count'] > 1 ? 's' : '' ?></span><?php endif ?>
          <?php if ($c['hint_count']): ?><span class="subtle">, <?= (int) $c['hint_count'] ?> hint<?= $c['hint_count'] > 1 ? 's' : '' ?></span><?php endif ?>
        </td>
        <td class="r nowrap">
          <a class="btn btn-ghost btn-sm btn-icon" href="/admin/challenges/<?= (int) $c['id'] ?>/stats" aria-label="Stats" title="Stats"><?= icon('chart', 'sm') ?></a>
          <a class="btn btn-ghost btn-sm btn-icon" href="/admin/challenges/<?= (int) $c['id'] ?>" aria-label="Edit" title="Edit"><?= icon('pencil', 'sm') ?></a>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table></div>
  <?php endif ?>
</section>

<section class="panel" id="categories" style="max-width:720px">
  <div class="panel-head"><h2>Categories</h2><span class="subtle xs">Lower order shows first</span></div>
  <div class="panel-body flush">
    <table class="table">
      <thead><tr><th>Name</th><th style="width:110px">Order</th><th class="r">Challenges</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($categories as $cat): ?>
        <tr>
          <td colspan="2">
            <form method="post" action="/admin/categories" class="row">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
              <input class="input" name="name" value="<?= e($cat['name']) ?>" style="height:32px" aria-label="Category name">
              <input class="input num" name="sort_order" type="number" value="<?= (int) $cat['sort_order'] ?>" style="height:32px;width:90px" aria-label="Order">
              <button class="btn btn-sm" type="submit">Save</button>
            </form>
          </td>
          <td class="r num"><?= (int) $cat['n'] ?></td>
          <td class="r">
            <?php if (!$cat['n']): ?>
            <form method="post" action="/admin/categories" data-confirm="Delete the <?= e($cat['name']) ?> category?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $cat['id'] ?>"><input type="hidden" name="delete" value="1"><button class="btn btn-ghost btn-sm btn-icon" aria-label="Delete category"><?= icon('trash', 'sm') ?></button></form>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <form class="panel-foot row" method="post" action="/admin/categories">
    <?= csrf_field() ?>
    <input class="input" name="name" placeholder="New category name" required style="height:34px">
    <button class="btn btn-sm" type="submit"><?= icon('plus', 'sm') ?>Add category</button>
  </form>
</section>
