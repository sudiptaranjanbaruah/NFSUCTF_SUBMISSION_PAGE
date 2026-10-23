<div class="page-head"><div><h1><?= e($page['title']) ?></h1><p>Last updated <?= ts($page['updated_at'], 'full') ?></p></div></div>
<article class="panel"><div class="panel-body prose" style="max-width:none"><div class="prose"><?= md($page['body']) ?></div></div></article>
