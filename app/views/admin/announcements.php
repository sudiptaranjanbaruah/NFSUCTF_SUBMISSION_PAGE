<div class="page-head"><div><h1>Announcements</h1><p>Players see new announcements as a toast, a dot on the bell, and on their dashboard.</p></div></div>
<div class="grid grid-sidebar">
  <section class="panel">
    <div class="panel-head"><h2><?= $edit ? 'Edit announcement' : 'New announcement' ?></h2><?php if ($edit): ?><a class="small" href="/admin/announcements">Cancel</a><?php endif ?></div>
    <form class="panel-body" method="post" action="/admin/announcements">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <div class="field"><label class="label" for="a-title">Title</label><input class="input" id="a-title" name="title" value="<?= e($edit['title'] ?? '') ?>" maxlength="160" required></div>
      <div class="field"><label class="label" for="a-body">Message <span class="subtle">Markdown</span></label>
        <div class="md-split"><textarea class="textarea" id="a-body" name="body" rows="8" data-md-preview="a-preview" required><?= e($edit['body'] ?? '') ?></textarea><div class="preview prose small" id="a-preview"></div></div></div>
      <div class="row wrap" style="margin-top:var(--s-4);gap:var(--s-5)">
        <label class="check"><input type="checkbox" name="is_pinned" value="1" <?= !empty($edit['is_pinned']) ? 'checked' : '' ?>> Pin to the top</label>
        <label class="check"><input type="checkbox" name="email_all" value="1"> Also email all <?= (int) $recipients ?> verified players</label>
      </div>
      <button class="btn btn-primary" type="submit" style="margin-top:var(--s-5)"><?= icon('megaphone', 'sm') ?><?= $edit ? 'Save changes' : 'Publish' ?></button>
    </form>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>Published</h2></div>
    <?php if (!$rows): ?><div class="empty" style="padding:var(--s-6)"><p>Nothing published yet.</p></div><?php else: foreach ($rows as $a): ?>
      <article class="announcement">
        <h3><?php if ($a['is_pinned']): ?><span class="badge badge-accent">Pinned</span><?php endif ?><?= e($a['title']) ?></h3>
        <div class="subtle xs"><?= ts($a['created_at'], 'ago') ?><?= $a['username'] ? ' by ' . e($a['username']) : '' ?></div>
        <div class="row" style="margin-top:var(--s-2)">
          <a class="btn btn-ghost btn-sm" href="/admin/announcements?edit=<?= (int) $a['id'] ?>"><?= icon('pencil', 'sm') ?>Edit</a>
          <form method="post" action="/admin/announcements/<?= (int) $a['id'] ?>/delete" data-confirm="Delete this announcement?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('trash', 'sm') ?>Delete</button></form>
        </div>
      </article>
    <?php endforeach; endif ?>
  </section>
</div>
