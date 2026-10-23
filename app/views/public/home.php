<section class="hero">
  <div class="hero-inner">
    <div>
      <div class="org"><img src="<?= e(setting('logo_url') ?: asset('img/logo.svg')) ?>" alt=""><span><?= e(setting('org_name')) ?></span></div>
      <h1><?= e(setting('event_name')) ?></h1>
      <p class="tagline"><?= e(setting('event_tagline')) ?> A jeopardy-style competition across web, crypto, forensics, reversing, pwn and OSINT.</p>
      <div class="row wrap">
        <?php if (current_user()): ?>
          <a class="btn btn-primary btn-lg" href="/dashboard">Go to your dashboard</a>
        <?php elseif (setting('registration_open') === '1' && $state !== 'ended'): ?>
          <a class="btn btn-primary btn-lg" href="/register">Register to play</a>
          <a class="btn btn-outline btn-lg" href="/login">Sign in</a>
        <?php else: ?>
          <a class="btn btn-primary btn-lg" href="/login">Sign in</a>
        <?php endif ?>
        <?php if (setting('scoreboard_visibility') === 'public'): ?><a class="btn btn-outline btn-lg" href="/scoreboard">View scoreboard</a><?php endif ?>
      </div>
    </div>
    <div class="countdown"><?= partial('countdown') ?></div>
  </div>
</section>

<section class="section">
  <div class="grid grid-sidebar" style="gap:var(--s-10)">
    <div>
      <div class="section-head">
        <h2>How it works</h2>
        <p>Register, verify your email, and form a team of up to <?= (int) setting('team_size_max') ?>. Solve challenges to find flags shaped like <code><?= e(setting('flag_format')) ?></code> and submit them for points. Be first to a solve and you earn a first-blood bonus.</p>
      </div>
      <?php if ($cats): ?>
        <div class="cat-strip">
          <?php foreach ($cats as $c): ?>
            <div class="panel"><div class="cat-name"><?= e($c['name']) ?></div><div class="cat-count"><?= (int) $c['n'] ?> <?= $c['n'] == 1 ? 'challenge' : 'challenges' ?></div></div>
          <?php endforeach ?>
        </div>
      <?php endif ?>
      <?php if ($landing): ?><div class="prose" style="margin-top:var(--s-8)"><?= md($landing['body']) ?></div><?php endif ?>
    </div>
    <aside class="stack">
      <div class="grid grid-3" style="gap:var(--s-2)">
        <div class="panel stat"><div class="stat-value"><?= (int) $stats['players'] ?></div><div class="stat-label">Players</div></div>
        <div class="panel stat"><div class="stat-value"><?= (int) $stats['teams'] ?></div><div class="stat-label">Teams</div></div>
        <div class="panel stat"><div class="stat-value"><?= (int) $stats['challenges'] ?></div><div class="stat-label">Challenges</div></div>
      </div>
      <?php if ($top): ?>
      <section class="panel">
        <div class="panel-head"><h3>Leading teams</h3><a class="small" href="/scoreboard">Full scoreboard</a></div>
        <table class="table"><tbody>
          <?php foreach ($top as $t): ?>
            <tr><td style="width:48px"><span class="rank <?= $t['rank'] <= 3 ? 'rank-' . $t['rank'] : '' ?>"><?= (int) $t['rank'] ?></span></td><td><a class="rowlink" href="/teams/<?= (int) $t['id'] ?>"><?= e($t['name']) ?></a></td><td class="r score"><?= number_format($t['score']) ?></td></tr>
          <?php endforeach ?>
        </tbody></table>
      </section>
      <?php endif ?>
      <section class="panel"><div class="panel-body small">
        <strong>Organised by</strong>
        <p class="muted" style="margin:var(--s-1) 0 var(--s-3)"><?= e(setting('org_name')) ?></p>
        <a href="mailto:<?= e(setting('contact_email')) ?>"><?= icon('mail', 'sm') ?> <?= e(setting('contact_email')) ?></a>
      </div></section>
    </aside>
  </div>
</section>
