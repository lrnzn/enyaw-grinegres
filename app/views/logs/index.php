<div class="page-head">
  <div><h1>Activity logs</h1><p class="sub">Every time-in, release and denial is recorded. Records cannot be edited.</p></div>
  <a class="btn btn-ghost" href="<?= url('logs/export') ?>&date=<?= e($date) ?>&action=<?= e($action) ?>"><?= icon('download', 18) ?>Export CSV</a>
</div>

<form method="get" action="<?= e(base_path()) ?>/index.php" class="filters card">
  <input type="hidden" name="url" value="logs">
  <label>Date <input type="date" name="date" value="<?= e($date) ?>"></label>
  <label>Action
    <select name="action">
      <option value="">All actions</option>
      <?php foreach (['TIME_IN', 'RELEASED', 'RELEASED_OVERRIDE', 'DENIED'] as $a): ?>
        <option value="<?= $a ?>" <?= $action === $a ? 'selected' : '' ?>><?= e(action_label($a)) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn btn-primary">Show records</button>
</form>

<div class="card table-wrap">
  <table>
    <thead><tr><th>Time</th><th>Student</th><th>Action</th><th>Picked up by</th><th>Note</th><th>Recorded by</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= fmt_time($r['logged_at']) ?></td>
        <td><strong><?= e(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: '(removed student)') ?></strong>
            <small class="block muted"><?= e($r['grade'] ?? '') ?> <?= e($r['section'] ?? '') ?></small></td>
        <td><span class="badge <?= action_class($r['action']) ?>"><?= e(action_label($r['action'])) ?></span></td>
        <td><?= e($r['guardian_name'] ?: ($r['person_name'] ?: '—')) ?></td>
        <td><?= e($r['note'] ?: '—') ?></td>
        <td><?= e($r['staff_name'] ?: '—') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="muted center">No records for this date and action.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
