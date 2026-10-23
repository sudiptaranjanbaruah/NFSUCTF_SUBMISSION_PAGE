<div class="page-head">
  <div>
    <h1>Challenges</h1>
    <p><span data-solved-count><?= (int) $solved ?></span> of <?= (int) $total ?> solved, <span class="num" data-team-score><?= number_format($score) ?></span> points</p>
  </div>
  <?php if ($state !== 'live'): ?>
    <span class="badge badge-warning"><?= icon($state === 'paused' ? 'pause' : 'lock') ?><?= $state === 'paused' ? 'Submissions paused' : ($state === 'ended' ? 'Event ended: read-only' : 'Admin preview') ?></span>
  <?php endif ?>
</div>

<?php if (!$groups): ?>
  <div class="panel"><div class="empty"><?= icon('target') ?><h3>No challenges released yet</h3><p>The organisers release challenges in waves. Check the announcements for the next drop.</p></div></div>
<?php else: ?>
<div class="board-toolbar" role="search">
  <label class="search">
    <?= icon('search', 'sm') ?>
    <span class="sr-only">Search challenges</span>
    <input class="input" type="search" placeholder="Search by name or tag" data-board-search>
  </label>
  <div class="segmented" role="group" aria-label="Filter by status">
    <button type="button" class="is-active" data-board-filter="all">All</button>
    <button type="button" data-board-filter="open">Unsolved</button>
    <button type="button" data-board-filter="solved">Solved</button>
  </div>
  <select class="select" style="width:auto;height:38px" data-board-diff aria-label="Filter by difficulty">
    <option value="">Any difficulty</option>
    <option value="easy">Easy</option><option value="medium">Medium</option><option value="hard">Hard</option><option value="insane">Insane</option>
  </select>
</div>

<?php foreach ($groups as $cat => $list):
  $done = count(array_filter($list, fn($c) => $c['solved_at'])); ?>
  <section class="cat-block" data-cat-block>
    <div class="cat-title">
      <h2><?= e($cat) ?></h2>
      <span class="progress-text"><?= $done ?>/<?= count($list) ?></span>
    </div>
    <div class="chal-grid">
      <?php foreach ($list as $c): ?>
        <button type="button"
          class="chal <?= $c['solved_at'] ? 'is-solved' : '' ?> <?= $c['locked'] ? 'is-locked' : '' ?>"
          data-chal="<?= (int) $c['id'] ?>"
          data-title="<?= e(strtolower($c['title'] . ' ' . $c['tags'] . ' ' . $c['author'])) ?>"
          data-diff="<?= e($c['difficulty']) ?>"
          data-solved="<?= $c['solved_at'] ? '1' : '0' ?>"
          <?= $c['locked'] ? 'aria-disabled="true" title="Solve ' . e($c['requires_title']) . ' first"' : '' ?>>
          <div class="chal-top">
            <span class="chal-id"><?= e($c['code']) ?></span>
            <span class="diff diff-<?= e($c['difficulty']) ?>"><?= e(ucfirst($c['difficulty'])) ?></span>
          </div>
          <div class="chal-title"><?php if ($c['locked']): ?><?= icon('lock', 'sm') ?> <?php endif ?><?= e($c['title']) ?></div>
          <?php if ($c['locked']): ?><div class="subtle xs">Solve <?= e($c['requires_title']) ?> first</div><?php endif ?>
          <div class="chal-meta">
            <span class="chal-points"><?= (int) $c['value'] ?></span>
            <span class="chal-solves"><?= icon('users', 'sm') ?><?= (int) $c['public_solves'] ?></span>
          </div>
          <?php if ($c['solved_at']): ?><span class="chal-status badge <?= $c['my_rank'] == 1 ? 'badge-blood' : 'badge-success' ?>"><?= icon($c['my_rank'] == 1 ? 'droplet' : 'check') ?><?= $c['my_rank'] == 1 ? 'First blood' : 'Solved' ?></span><?php endif ?>
        </button>
      <?php endforeach ?>
    </div>
  </section>
<?php endforeach ?>
<div class="panel hidden" data-board-empty><div class="empty"><?= icon('search') ?><h3>No challenges match</h3><p>Try a different search or clear the filters.</p></div></div>
<?php endif ?>

<dialog class="drawer" id="chal-drawer" aria-labelledby="chal-drawer-title">
  <div class="drawer-inner">
    <div class="drawer-head row-between">
      <div class="grow">
        <div class="row xs subtle" data-d="meta"></div>
        <h2 id="chal-drawer-title" style="margin-top:var(--s-1)" data-d="title">Loading…</h2>
      </div>
      <button class="btn btn-ghost btn-icon" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
    </div>
    <div class="drawer-body" data-d="body"><div class="skeleton" style="height:120px"></div></div>
    <div class="drawer-foot" data-d="foot"></div>
  </div>
</dialog>
<script src="<?= asset('js/challenges.js') ?>" defer></script>
<script src="<?= asset('js/live.js') ?>" defer></script>
