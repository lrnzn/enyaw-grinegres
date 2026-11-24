<div class="page-head">
  <div><h1>Messages to parents</h1>
    <p class="sub"><?= in_array('email', $channels, true) ? 'An email' : 'A text message' ?> goes to a child's authorized guardians when the child arrives and when the child is picked up.</p></div>
  <?php if ($retryable): ?>
  <form method="post" action="<?= url('notifications/retry') ?>"><?= csrf_field() ?>
    <button class="btn btn-ghost"><?= icon('refresh', 18) ?>Send <?= (int)$retryable ?> unsent again</button></form>
  <?php endif; ?>
</div>

<?php if (!$enabled): ?>
  <div class="alert alert-warning"><?= icon('alert', 20) ?><span>Messages are switched off in the configuration, so none are created.</span></div>
<?php elseif (!$live): ?>
  <div class="alert alert-info"><?= icon('alert', 20) ?><span><b>Messages are simulated.</b> They are listed here as they would be sent, but nothing leaves the system. To send real emails, set the Gmail details in <code>config/config.local.php</code>.</span></div>
<?php endif; ?>

<section class="card glance" style="margin-bottom:18px">
  <div class="glance-grid" style="grid-template-columns:repeat(3,1fr)">
    <div class="n-green"><b><?= $counts['sent'] + $counts['simulated'] ?></b><span>Delivered<?= !$live ? ' (simulated)' : '' ?> on this date</span></div>
    <div class="n-amber"><b><?= $counts['queued'] ?></b><span>Waiting to be sent</span></div>
    <div class="n-red"><b><?= $counts['failed'] ?></b><span>Not sent</span></div>
  </div>
</section>

<form method="get" action="<?= e(base_path()) ?>/index.php" class="filters card">
  <input type="hidden" name="url" value="notifications">
  <label>Date <input type="date" name="date" value="<?= e($date) ?>"></label>
  <label>Status
    <select name="status"><option value="">All</option>
      <?php foreach (['sent' => 'Sent', 'simulated' => 'Simulated', 'queued' => 'Waiting', 'failed' => 'Not sent'] as $k => $v): ?>
        <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select></label>
  <button class="btn btn-primary">Show messages</button>
</form>

<div class="card table-wrap">
  <table>
    <thead><tr><th>Time</th><th>To</th><th>Message</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= fmt_time($r['created_at']) ?></td>
        <td><strong><?= e($r['recipient_name']) ?></strong><small class="block muted"><?= e($r['recipient']) ?></small></td>
        <td><div class="sms-bubble"><?= e($r['message']) ?></div></td>
        <td><span class="badge <?= ['sent' => 'b-green', 'simulated' => 'b-blue', 'failed' => 'b-red', 'queued' => 'b-amber'][$r['status']] ?>"><?= ['sent' => 'Sent', 'simulated' => 'Simulated', 'failed' => 'Not sent', 'queued' => 'Waiting'][$r['status']] ?></span>
          <?php if ($r['provider_note']): ?><small class="block muted"><?= e($r['provider_note']) ?></small><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4" class="muted center">No messages for this date.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
