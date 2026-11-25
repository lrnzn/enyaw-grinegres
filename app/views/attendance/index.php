<div class="page-head">
  <div><h1>Attendance</h1><p class="sub">Mark each class period for your own classes. Statuses from the gate and the daily record are preselected to save time. Check them, then save.</p></div>
</div>

<div class="tabs"><a class="on" href="<?= url('attendance') ?>">By period</a><?php if (Section::hasDaily()): ?><a href="<?= url('attendance/daily') ?>">Daily (gate)</a><?php endif; ?></div>

<?php if (!$hasSchedule): ?>
  <div class="alert alert-warning"><?= icon('alert', 20) ?><span>No class schedule has been set up yet.
    <?php if (is_admin()): ?><a href="<?= url('schedule') ?>">Set up schedules</a> first.<?php else: ?>Ask the administrator to set up the schedules.<?php endif; ?></span></div>
<?php endif; ?>

<section class="card pick">
  <div class="pick-row">
    <span class="pick-label">1. Date</span>
    <div class="date-nav">
      <?php if ($prev): ?><a class="btn btn-ghost btn-sm" href="<?= url('attendance') ?>&date=<?= e($prev) ?>">‹ Previous day</a><?php endif; ?>
      <form method="get" action="<?= e(base_path()) ?>/index.php">
        <input type="hidden" name="url" value="attendance">
        <input type="date" name="date" value="<?= e($date) ?>" <?= $min ? 'min="' . e($min) . '"' : '' ?> <?= $max ? 'max="' . e($max) . '"' : '' ?> data-autosubmit>
      </form>
      <?php if ($date !== date('Y-m-d')): ?><a class="btn btn-ghost btn-sm" href="<?= url('attendance') ?>">Today</a><?php endif; ?>
      <?php if ($next): ?><a class="btn btn-ghost btn-sm" href="<?= url('attendance') ?>&date=<?= e($next) ?>">Next day ›</a><?php endif; ?>
      <span class="muted"><?= e(date('l, F j', strtotime($date))) ?></span>
    </div>
  </div>
  <div class="pick-row">
    <span class="pick-label">2. Section</span>
    <div class="chips">
      <?php foreach ($secList as $sid => $lbl): ?>
        <a class="chip <?= (int)$sid === (int)$sectionId ? 'on' : '' ?>" href="<?= url('attendance') ?>&date=<?= e($date) ?>&section=<?= (int)$sid ?>"><?= e($lbl) ?></a>
      <?php endforeach; ?>
      <?php if (!$secList): ?><span class="muted">No classes on this day</span><?php endif; ?>
    </div>
  </div>
  <?php if ($classes): ?>
  <div class="pick-row">
    <span class="pick-label">3. Subject / period</span>
    <div class="chips">
      <?php foreach ($classes as $c): ?>
        <a class="chip <?= $current && (int)$current['id'] === (int)$c['id'] ? 'on' : '' ?>" href="<?= url('attendance') ?>&date=<?= e($date) ?>&section=<?= (int)$sectionId ?>&class=<?= (int)$c['id'] ?>"><?= e($c['subject']) ?> <small><?= e($c['period']) ?> · <?= e($c['start_time']) ?></small></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</section>
<?php if (is_teacher()): ?><p class="muted small" style="margin:0 0 14px">Teachers can record today and earlier days of this week.</p><?php endif; ?>

<?php if ($current): ?>
<form method="post" action="<?= url('attendance/save') ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="date" value="<?= e($date) ?>">
  <input type="hidden" name="class" value="<?= (int)$current['id'] ?>">
  <div class="card table-wrap">
    <table>
      <thead><tr><th></th><th>Student</th><th>Gate / daily</th><th>This period</th><th>Remarks</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $sel = $r['status'] ?? $r['daily_status']; ?>
        <tr>
          <td><img class="thumb" src="<?= e(avatar($r['full_name'], $r['photo'])) ?>" alt=""></td>
          <td><strong><?= e($r['full_name']) ?></strong></td>
          <td><?= $r['daily_status'] ? e(ucfirst($r['daily_status'])) : '<span class="muted">—</span>' ?></td>
          <td>
            <div class="tap">
              <?php foreach (['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'excused' => 'Excused'] as $k => $v): ?>
                <label class="tap-<?= $k ?>"><input type="radio" name="status[<?= (int)$r['id'] ?>]" value="<?= $k ?>" <?= $sel === $k ? 'checked' : '' ?>><span><?= $v ?></span></label>
              <?php endforeach; ?>
            </div>
            <?php if ($r['status'] === null && $r['daily_status']): ?><small class="muted block">preselected from the gate</small><?php endif; ?>
          </td>
          <td><input class="remarks-input" name="remarks[<?= (int)$r['id'] ?>]" value="<?= e($r['remarks'] ?? '') ?>" maxlength="255" placeholder="Optional"></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5" class="muted center">No enrolled students in this section.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($rows): ?>
  <div class="row-gap wrap" style="margin-top:18px">
    <button class="btn btn-primary btn-lg">Save attendance</button>
    <button type="button" class="btn btn-ghost btn-lg" data-mark-all="present">Mark all present</button>
  </div>
  <?php endif; ?>
</form>
<?php elseif ($hasSchedule): ?>
  <div class="card"><p class="muted center" style="margin:0">No classes are scheduled for you on this day. Pick another date.</p></div>
<?php endif; ?>

<section class="card export-card">
  <h3>Download as Excel</h3>
  <p class="mini"><a href="<?= url('reports/sheet') ?>&type=period&section=<?= e($current['section_id'] ?? '') ?>&subject=<?= (int)($current['subject_id'] ?? 0) ?>">Open printable attendance sheet →</a></p>
  <p class="muted small" style="margin:0 0 12px">A spreadsheet (.xlsx) of saved attendance, with a summary sheet of totals per student.</p>
  <form method="get" action="<?= e(base_path()) ?>/index.php" class="inline-form">
    <input type="hidden" name="url" value="attendance/export">
    <label>Records
      <select name="type"><option value="period">By period</option><option value="daily">Daily (gate)</option></select></label>
    <label>From <input type="date" name="from" value="<?= e($weekFrom) ?>" <?= $min ? 'min="' . e($min) . '"' : '' ?> <?= $max ? 'max="' . e($max) . '"' : '' ?>></label>
    <label>To <input type="date" name="to" value="<?= e($weekTo) ?>" <?= $min ? 'min="' . e($min) . '"' : '' ?> <?= $max ? 'max="' . e($max) . '"' : '' ?>></label>
    <?php if (is_admin()): ?>
    <label>Section
      <select name="section"><option value="">All sections</option>
        <?php foreach ($sections as $sec): ?><option value="<?= (int)$sec['id'] ?>"><?= e($sec['label']) ?> (<?= e($sec['school_year']) ?>)</option><?php endforeach; ?>
      </select></label>
    <?php endif; ?>
    <button class="btn btn-primary"><?= icon('download', 18) ?>Download Excel</button>
  </form>
</section>
