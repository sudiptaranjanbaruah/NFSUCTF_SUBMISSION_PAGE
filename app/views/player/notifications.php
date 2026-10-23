<div class="page-head"><div><h1>Announcements</h1><p>Updates, clarifications and new challenge releases from the organisers.</p></div></div>
<section class="panel" style="max-width:820px">
  <?php if (!$items): ?>
    <div class="empty"><?= icon('megaphone') ?><h3>Nothing yet</h3><p>Announcements will appear here and as a dot on the bell.</p></div>
  <?php else: foreach ($items as $a): $new = !$seenAt || $a['created_at'] > $seenAt; ?>
    <article class="announcement">
      <h3>
        <?php if ($a['is_pinned']): ?><span class="badge badge-accent">Pinned</span><?php endif ?>
        <?php if ($new): ?><span class="badge badge-blood">New</span><?php endif ?>
        <?= e($a['title']) ?>
      </h3>
      <div class="prose"><?= md($a['body']) ?></div>
      <div class="subtle xs"><?= ts($a['created_at'], 'full') ?></div>
    </article>
  <?php endforeach; endif ?>
</section>
