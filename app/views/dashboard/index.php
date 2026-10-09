<?php
$present  = $count['on_campus'] + $count['released'];
$pct      = $present > 0 ? round($count['released'] / $present * 100) : 0;
?>
<div class="page-head">
  <div>
    <h1><?= e(greeting()) ?>, <?= e($user['name']) ?></h1>
    <p class="sub"><?= date('l, F j, Y') ?></p>
  </div>
  <?php if (demo()): ?>
  <form method="post" action="<?= url('dashboard/resetDemo') ?>" data-confirm="Reset today's demo logs?">
    <?= csrf_field() ?><button class="btn btn-ghost btn-sm"><?= icon('refresh', 16) ?>Reset demo logs</button>
  </form>
  <?php endif; ?>
</div>

<section class="card glance">
  <div class="glance-grid">
    <div><b><?= $total ?></b><span>Registered students</span></div>
    <div class="n-blue"><b><?= $count['on_campus'] ?></b><span>On campus now</span></div>
    <div class="n-green"><b><?= $count['released'] ?></b><span>Released today</span></div>
    <div><b><?= $absent ?></b><span>No time-in today</span></div>
    <div class="n-red"><b><?= $denied ?></b><span>Releases denied</span></div>
    <div class="n-amber"><b><?= $override ?></b><span>Released by letter</span></div>
  </div>
  <div class="progress-row">
    <strong>Dismissal progress</strong>
    <div class="bar" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><i style="width:<?= $pct ?>%"></i></div>
    <span><?= $count['released'] ?> of <?= $present ?> present students released</span>
  </div>
</section>

<div class="two-col" style="margin-top:20px">
  <section class="card">
    <h3>Waiting for pickup</h3>
    <?php if (!$onCampus): ?><p class="empty">Nobody is waiting right now. Students appear here once they are timed in.</p><?php endif; ?>
    <ul class="people">
      <?php foreach ($onCampus as $s): ?>
        <li>
          <img src="<?= e(avatar($s['full_name'], $s['photo'])) ?>" alt="">
          <div><a href="<?= url('students/show/' . $s['id']) ?>"><strong><?= e($s['full_name']) ?></strong></a>
            <small><?= e($s['grade']) ?>, <?= e($s['section']) ?> · in at <?= fmt_time($s['since']) ?></small></div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="card">
    <h3>Recent activity</h3>
    <?php if (!$recent): ?><p class="empty">No activity yet today.</p><?php endif; ?>
    <ul class="feed">
      <?php foreach ($recent as $r): ?>
        <li>
          <span class="badge <?= action_class($r['action']) ?>"><?= e(action_label($r['action'])) ?></span>
          <div><strong><?= e(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: '(removed student)') ?></strong>
            <?php if ($r['guardian_name'] || $r['person_name']): ?> to <?= e($r['guardian_name'] ?: $r['person_name']) ?><?php endif; ?>
            <small><?= fmt_dt($r['logged_at']) ?></small></div>
        </li>
      <?php endforeach; ?>
    </ul>
    <a href="<?= url('logs') ?>">View all logs</a>
  </section>
</div>
