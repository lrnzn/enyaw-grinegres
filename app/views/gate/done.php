<?php
$a = $log['action'];
$name = trim($log['first_name'] . ' ' . $log['last_name']);
$cfg = [
  'TIME_IN'           => ['',       'check', 'Time-in recorded',   "$name is marked present."],
  'RELEASED'          => ['',       'check', 'Released',           "$name went home with " . ($log['guardian_name'] ?? '') . '.'],
  'RELEASED_OVERRIDE' => ['warn',   'letter','Released with letter', "$name was released to " . ($log['person_name'] ?? '') . ', approved by the supervisor.'],
  'DENIED'            => ['denied', 'ban',   'Release denied',     "Do not release $name to this person."],
][$a];
$next = url('gate') . '&mode=' . ($a === 'TIME_IN' ? 'in' : 'out');
?>
<div class="result-card <?= $cfg[0] ?>" <?= $a !== 'DENIED' ? 'data-redirect-after="12" data-redirect-to="' . e($next) . '"' : '' ?>>
  <div class="result-icon"><?= icon($cfg[1], 46) ?></div>
  <h1><?= e($cfg[2]) ?></h1>
  <p class="lead"><?= e($cfg[3]) ?></p>
  <div class="result-photos">
    <img src="<?= e(avatar($name, null)) ?>" alt="">
    <?php if ($log['guardian_name']): ?><img src="<?= e(avatar($log['guardian_name'], $photo)) ?>" alt=""><?php endif; ?>
  </div>
  <p class="muted"><?= fmt_dt($log['logged_at'], 'l, M d, Y \a\t h:i A') ?>, recorded by <?= e($log['staff_name']) ?></p>
  <?php if ($log['note']): ?><p class="muted">Note: <?= e($log['note']) ?></p><?php endif; ?>
  <div class="row-gap center-row">
    <a class="btn btn-primary btn-xl" href="<?= e($next) ?>">Next student<?= icon('forward', 22) ?></a>
    <a class="btn btn-ghost btn-lg" href="<?= url('logs') ?>">View logs</a>
  </div>
  <?php if ($a !== 'DENIED'): ?><p class="muted small" data-countdown style="margin-top:16px">Returning to the gate screen automatically.</p><?php endif; ?>
</div>
