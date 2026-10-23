<?php /** @var array $feed */ ?>
<?php if (!$feed): ?>
  <div class="empty" style="padding:var(--s-8) var(--s-4)"><?= icon('droplet') ?><p>First bloods and bonus awards will show up here.</p></div>
<?php else: ?>
<ul class="feed">
  <?php foreach ($feed as $f): ?>
    <li>
      <?php if ($f['kind'] === 'blood'): ?>
        <span class="feed-icon blood"><?= icon('droplet', 'sm') ?></span>
        <div class="grow">
          <a href="/teams/<?= (int) $f['team_id'] ?>"><strong><?= e($f['team']) ?></strong></a> drew first blood on <strong><?= e($f['subject']) ?></strong><?= $f['amount'] ? ' <span class="badge badge-blood">+' . (int) $f['amount'] . '</span>' : '' ?>
          <div><?= ts($f['created_at'], 'ago') ?></div>
        </div>
      <?php else: ?>
        <span class="feed-icon award"><?= icon('award', 'sm') ?></span>
        <div class="grow">
          <a href="/teams/<?= (int) $f['team_id'] ?>"><strong><?= e($f['team']) ?></strong></a> earned <span class="badge badge-accent">+<?= (int) $f['amount'] ?></span> <?= e($f['reason']) ?>
          <div><?= ts($f['created_at'], 'ago') ?></div>
        </div>
      <?php endif ?>
    </li>
  <?php endforeach ?>
</ul>
<?php endif ?>
