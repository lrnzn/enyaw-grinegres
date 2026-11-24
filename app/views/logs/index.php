<?php
$q = function (array $over = []) use ($range, $from, $to, $action, $grade, $section, $source) {
    return http_build_query(array_filter(array_merge(['range' => $range, 'date' => $from, 'from' => $from, 'to' => $to,
        'action' => $action, 'grade' => $grade, 'section' => $section ?: '', 'source' => $source], $over), function ($v) { return $v !== '' && $v !== null; }));
};
$period = $from === $to ? date('M j, Y', strtotime($from)) : date('M j', strtotime($from)) . ' – ' . date('M j, Y', strtotime($to));
?>
<div class="page-head">
  <div><h1>Activity logs</h1><p class="sub">Every time-in, release and denial is recorded. Records cannot be edited.</p></div>
  <a class="btn btn-ghost" href="<?= url('logs/export') ?>&<?= e($q()) ?>"><?= icon('download', 18) ?>Export CSV</a>
</div>

<div class="tabs">
  <a class="<?= $range === 'today' ? 'on' : '' ?>" href="<?= url('logs') ?>&<?= e($q(['range' => 'today', 'date' => '', 'from' => '', 'to' => ''])) ?>">Today</a>
  <a class="<?= $range === 'week' ? 'on' : '' ?>" href="<?= url('logs') ?>&<?= e($q(['range' => 'week', 'date' => $from, 'from' => '', 'to' => ''])) ?>">This week</a>
  <?php if ($isAdmin): ?>
    <a class="<?= $range === 'month' ? 'on' : '' ?>" href="<?= url('logs') ?>&<?= e($q(['range' => 'month', 'date' => $from, 'from' => '', 'to' => ''])) ?>">Month</a>
    <a class="<?= $range === 'custom' ? 'on' : '' ?>" href="<?= url('logs') ?>&<?= e($q(['range' => 'custom'])) ?>">Custom range</a>
  <?php endif; ?>
</div>

<form method="get" action="<?= e(base_path()) ?>/index.php" class="filters card">
  <input type="hidden" name="url" value="logs">
  <input type="hidden" name="range" value="<?= e($isAdmin && $range === 'today' ? 'custom' : $range) ?>">
  <?php if ($isAdmin && $range !== 'week' && $range !== 'month'): ?>
    <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
    <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
  <?php elseif ($isAdmin): ?>
    <label><?= $range === 'month' ? 'Month of' : 'Week of' ?> <input type="date" name="date" value="<?= e($from) ?>"></label>
  <?php endif; ?>
  <label>Grade level
    <select name="grade">
      <option value="">All grades</option>
      <?php foreach ($grades as $g): ?><option value="<?= e($g) ?>" <?= $grade === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Section
    <select name="section">
      <option value="">All sections</option>
      <?php foreach ($sections as $sec): ?>
        <option value="<?= (int)$sec['id'] ?>" <?= $section === (int)$sec['id'] ? 'selected' : '' ?>><?= e($sec['label']) ?> (<?= e($sec['school_year']) ?>)</option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Action
    <select name="action">
      <option value="">All actions</option>
      <?php foreach (['TIME_IN', 'RELEASED', 'RELEASED_OVERRIDE', 'DENIED'] as $a): ?>
        <option value="<?= $a ?>" <?= $action === $a ? 'selected' : '' ?>><?= e(action_label($a)) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Attendance source
    <select name="source">
      <option value="daily" <?= $source === 'daily' ? 'selected' : '' ?>>Daily (gate)</option>
      <option value="period" <?= $source === 'period' ? 'selected' : '' ?>>Class periods</option>
    </select>
  </label>
  <button class="btn btn-primary">Show records</button>
</form>

<?php if ($canReport): ?>
<section class="card table-wrap summary-card">
  <div class="summary-head">
    <h3>Attendance summary by <?= ($grade || $section) ? 'section' : 'grade level' ?> <small class="muted"><?= e($period) ?></small></h3>
    <?php if (!$isAdmin): ?><small class="muted">Teachers see the current week.</small><?php endif; ?>
  </div>
  <table>
    <thead><tr><th><?= ($grade || $section) ? 'Section' : 'Grade level' ?></th><th class="num">Present</th><th class="num">Late</th><th class="num">Absent</th><th class="num">Excused</th><th class="num">Records</th><th class="num">Attendance rate</th></tr></thead>
    <tbody>
    <?php $t = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0, 'total' => 0]; foreach ($summary as $r): foreach ($t as $k => $_) $t[$k] += $r[$k]; ?>
      <tr><td><strong><?= e($r['label']) ?></strong></td><td class="num"><?= (int)$r['present'] ?></td><td class="num"><?= (int)$r['late'] ?></td>
          <td class="num"><?= (int)$r['absent'] ?></td><td class="num"><?= (int)$r['excused'] ?></td><td class="num"><?= (int)$r['total'] ?></td><td class="num"><?= (int)$r['rate'] ?>%</td></tr>
    <?php endforeach; ?>
    <?php if ($summary): ?>
      <tr class="total-row"><td>Total</td><td class="num"><?= $t['present'] ?></td><td class="num"><?= $t['late'] ?></td><td class="num"><?= $t['absent'] ?></td><td class="num"><?= $t['excused'] ?></td><td class="num"><?= $t['total'] ?></td>
          <td class="num"><?= $t['total'] ? round(($t['present'] + $t['late']) / $t['total'] * 100) : 0 ?>%</td></tr>
    <?php else: ?><tr><td colspan="7" class="muted center">No attendance records in this range.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <p class="mini muted" style="margin:12px 4px 0">Attendance rate counts present and late. Source: <?= $source === 'period' ? 'class-period records' : 'daily (gate) attendance' ?>.
    <a href="<?= url('reports/sheet') ?>&<?= e($q(['source' => ''])) ?>">Open printable attendance sheet →</a></p>
</section>
<?php endif; ?>

<h3 class="section-title">Gate activity <small class="muted"><?= e($period) ?></small></h3>
<div class="card table-wrap">
  <table>
    <thead><tr><th>Date &amp; time</th><th>Student</th><th>Action</th><th>Picked up by</th><th>Details</th><th>Recorded by</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= $from === $to ? fmt_time($r['logged_at']) : e(date('M j', strtotime($r['logged_at']))) . ' ' . fmt_time($r['logged_at']) ?></td>
        <td><strong><?= e($r['student_name']) ?></strong>
            <small class="block muted"><?= e(trim(($r['grade'] ?? '') . ' ' . ($r['section'] ?? ''))) ?></small></td>
        <td><span class="badge <?= action_class($r['action']) ?>"><?= e(action_label($r['action'])) ?></span></td>
        <td><?= e($r['released_to_name'] ?: '—') ?></td>
        <td><?= e($r['note'] ?: '—') ?>
          <?php if ($r['approver_name']): ?><small class="block muted">Approved by <?= e($r['approver_name']) ?></small><?php endif; ?>
          <?php if ($r['letter_path'] && is_admin()): ?><a class="letter-link" href="<?= url('photo/letter/' . $r['letter_path']) ?>" target="_blank" rel="noopener"><?= icon('letter', 16) ?>View letter</a><?php endif; ?></td>
        <td><?= e($r['staff_name'] ?: '—') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="muted center">No records for this selection.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
