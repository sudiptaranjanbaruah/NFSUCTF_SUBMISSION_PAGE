<?php $names = ['rules' => 'Rules', 'about' => 'About', 'landing' => 'Home page extra']; ?>
<div class="page-head"><div><h1>Pages</h1><p>Edit the public pages. Markdown is supported.</p></div></div>
<div class="tabs" style="margin-bottom:var(--s-5)"><?php foreach ($names as $slug => $label): ?><a href="/admin/pages/<?= $slug ?>" class="<?= $page['slug'] === $slug ? 'is-active' : '' ?>"><?= $label ?></a><?php endforeach ?></div>
<section class="panel">
  <form class="panel-body" method="post" action="/admin/pages/<?= e($page['slug']) ?>">
    <?= csrf_field() ?>
    <div class="field"><label class="label" for="p-title">Title</label><input class="input" id="p-title" name="title" value="<?= e($page['title']) ?>"></div>
    <div class="field"><label class="label" for="p-body">Content <?= $page['slug'] === 'landing' ? '<span class="subtle">shown under "How it works" on the home page: prizes, sponsors, schedule</span>' : '' ?></label>
      <div class="md-split"><textarea class="textarea code" id="p-body" name="body" rows="22" data-md-preview="p-preview"><?= e($page['body']) ?></textarea><div class="preview prose" id="p-preview"></div></div></div>
    <div class="row" style="margin-top:var(--s-5)"><button class="btn btn-primary" type="submit">Save page</button><?php if ($page['slug'] !== 'landing'): ?><a class="btn btn-ghost" href="/<?= e($page['slug']) ?>" target="_blank"><?= icon('external', 'sm') ?>View</a><?php endif ?></div>
  </form>
</section>
