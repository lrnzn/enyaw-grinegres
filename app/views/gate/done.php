<?php
$a = $log['action'];
$name = $log['student_name'];
$cfg = [
  'TIME_IN'           => ['',       'check', 'Time-in recorded',   "$name is marked present."],
  'RELEASED'          => ['',       'check', 'Released',           "$name went home with " . ($log['released_to_name'] ?? '') . '.'],
  'RELEASED_OVERRIDE' => ['warn',   'letter','Released with letter', "$name was released to " . ($log['released_to_name'] ?? '') . ', approved by ' . ($log['approver_name'] ?: 'the administrator') . '.'],
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
    <?php if ($log['guardian_id']): ?><img src="<?= e(avatar($log['released_to_name'], $photo)) ?>" alt=""><?php endif; ?>
  </div>
  <p class="muted"><?= fmt_dt($log['logged_at'], 'l, M d, Y \a\t h:i A') ?>, recorded by <?= e($log['staff_name']) ?></p>
  <?php if ($log['note']): ?><p class="muted">Note: <?= e($log['note']) ?></p><?php endif; ?>
  <?php if (!empty($messages)): ?>
    <div class="msg-box">
      <strong>Parents notified</strong>
      <?php foreach ($messages as $m): ?>
        <div class="msg-row"><span><?= e($m['recipient_name']) ?></span>
          <span class="badge <?= ['sent' => 'b-green', 'simulated' => 'b-blue', 'failed' => 'b-red', 'queued' => 'b-amber'][$m['status']] ?>"><?= ['sent' => 'Sent', 'simulated' => 'Simulated', 'failed' => 'Not sent', 'queued' => 'Queued'][$m['status']] ?></span></div>
      <?php endforeach; ?>
      <p class="mini"><?= e($messages[0]['message']) ?></p>
    </div>
  <?php endif; ?>
  <div class="row-gap center-row">
    <a class="btn btn-primary btn-xl" href="<?= e($next) ?>">Next student<?= icon('forward', 22) ?></a>
    <a class="btn btn-ghost btn-lg" href="<?= url('logs') ?>">View logs</a>
  </div>
  <?php if ($a !== 'DENIED'): ?><p class="muted small" data-countdown style="margin-top:16px">Returning to the gate screen automatically.</p><?php endif; ?>
</div>
