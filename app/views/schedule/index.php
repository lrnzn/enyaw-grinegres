<div class="page-head">
  <div><h1>Schedules</h1><p class="sub">Set up the school day once: the bell schedule, the subjects, and which teacher teaches which section. Teachers then mark attendance for their own classes.</p></div>
</div>

<section class="card sched-card">
  <h3>1. Bell schedule (periods)</h3>
  <?php if (!$periods): ?>
    <p class="muted">No periods yet. Add them below, or start from a sample and change the times to match the school.</p>
    <form method="post" action="<?= url('schedule/samples') ?>"><?= csrf_field() ?><input type="hidden" name="what" value="periods">
      <button class="btn btn-ghost">Use a sample bell schedule</button></form>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Name</th><th>Starts</th><th>Ends</th><th></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($periods as $p): ?>
      <tr>
        <form method="post" action="<?= url('schedule/updatePeriod/' . $p['id']) ?>"><?= csrf_field() ?>
        <td><input name="name" value="<?= e($p['name']) ?>" maxlength="40" required></td>
        <td><input type="time" name="start_time" value="<?= e($p['start_time']) ?>" required></td>
        <td><input type="time" name="end_time" value="<?= e($p['end_time']) ?>" required></td>
        <td><button class="btn btn-ghost btn-sm">Save</button></td>
        </form>
        <td>
          <form method="post" action="<?= url('schedule/deletePeriod/' . $p['id']) ?>" data-confirm="Remove <?= e($p['name']) ?>?"><?= csrf_field() ?>
            <button class="btn btn-danger-ghost btn-sm" <?= $p['used'] ? 'disabled title="Used by class schedules"' : '' ?>>Remove</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <form method="post" action="<?= url('schedule/addPeriod') ?>" class="inline-form" style="margin-top:14px"><?= csrf_field() ?>
    <label>New period <input name="name" maxlength="40" placeholder="e.g. Period 8" required></label>
    <label>Starts <input type="time" name="start_time" required></label>
    <label>Ends <input type="time" name="end_time" required></label>
    <button class="btn btn-primary">Add period</button>
  </form>
</section>

<section class="card sched-card">
  <h3>2. Subjects</h3>
  <?php if (!$subjects): ?>
    <p class="muted">No subjects yet. Add them below, or start from a sample list.</p>
    <form method="post" action="<?= url('schedule/samples') ?>"><?= csrf_field() ?><input type="hidden" name="what" value="subjects">
      <button class="btn btn-ghost">Use sample subjects</button></form>
  <?php else: ?>
  <div class="chips">
    <?php foreach ($subjects as $s): ?>
      <form method="post" action="<?= url('schedule/deleteSubject/' . $s['id']) ?>" data-confirm="Remove <?= e($s['name']) ?>?" class="chip"><?= csrf_field() ?>
        <span><?= e($s['name']) ?></span>
        <?php if (!$s['used']): ?><button aria-label="Remove <?= e($s['name']) ?>" title="Remove">&times;</button><?php endif; ?>
      </form>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <form method="post" action="<?= url('schedule/addSubject') ?>" class="inline-form" style="margin-top:14px"><?= csrf_field() ?>
    <label>New subject <input name="name" maxlength="80" placeholder="e.g. Music" required></label>
    <button class="btn btn-primary">Add subject</button>
  </form>
</section>

<section class="card sched-card">
  <h3>3. Class schedule</h3>
  <?php if (!$sections): ?>
    <p class="muted">Add a section first (Administration &gt; Sections).</p>
  <?php else: ?>
  <form method="get" action="<?= e(base_path()) ?>/index.php" class="inline-form">
    <input type="hidden" name="url" value="schedule">
    <label>Section
      <select name="section" data-autosubmit>
        <?php foreach ($sections as $sec): ?>
          <option value="<?= (int)$sec['id'] ?>" <?= (int)$section === (int)$sec['id'] ? 'selected' : '' ?>><?= e($sec['label']) ?> (<?= e($sec['school_year']) ?>)</option>
        <?php endforeach; ?>
      </select></label>
    <noscript><button class="btn btn-primary">Show</button></noscript>
  </form>

  <?php
    $grid = [];
    foreach ($classes as $c) $grid[(int)$c['period_id']][(int)$c['day_of_week']] = $c;
    $secLabel = '';
    foreach ($sections as $sec) if ((int)$sec['id'] === (int)$section) $secLabel = $sec['label'] . ' (' . $sec['school_year'] . ')';
  ?>
  <h4 style="margin:16px 0 0">Weekly timetable: <?= e($secLabel) ?></h4>
  <?php if (!$periods): ?>
    <p class="muted">Add the periods above first. They become the rows of the timetable.</p>
  <?php else: ?>
  <div class="tt-wrap"><table class="tt">
    <thead><tr><th class="time">Time</th><?php foreach (Schedule::DAYS as $d => $dn): ?><th><?= e($dn) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($periods as $p): ?>
      <tr>
        <th class="time"><?= e($p['name']) ?><small><?= e($p['start_time']) ?> – <?= e($p['end_time']) ?></small></th>
        <?php foreach (Schedule::DAYS as $d => $dn): $c = $grid[(int)$p['id']][$d] ?? null; ?>
          <td>
          <?php if ($c): ?>
            <div class="slot c<?= (int)$c['subject_id'] % 8 ?>">
              <b><?= e($c['subject']) ?></b><span><?= e($c['teacher']) ?></span>
              <form method="post" action="<?= url('schedule/deleteClass/' . $c['id']) ?>" data-confirm="Remove <?= e($c['subject']) ?> on <?= e($dn) ?>, <?= e($p['name']) ?>?"><?= csrf_field() ?>
                <button title="Remove this class" aria-label="Remove">&times;</button></form>
            </div>
          <?php else: ?>
            <a class="empty" href="<?= url('schedule') ?>&section=<?= (int)$section ?>&day=<?= (int)$d ?>&period=<?= (int)$p['id'] ?>#add-class" title="Add a class on <?= e($dn) ?>, <?= e($p['name']) ?>">+</a>
          <?php endif; ?>
          </td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="muted small" style="margin-top:8px">Click a <strong>+</strong> to add a class in that slot. Click &times; on a class to remove it.</p>
  <?php endif; ?>

  <?php if (!$periods || !$subjects || !$teachers): ?>
    <div class="alert alert-warning" style="margin-top:14px"><?= icon('alert', 20) ?><span>To add classes you need at least one period, one subject and one active teacher account (Administration &gt; Staff accounts).</span></div>
  <?php else: ?>
  <form method="post" action="<?= url('schedule/addClass') ?>" class="inline-form" id="add-class" style="margin-top:14px"><?= csrf_field() ?>
    <input type="hidden" name="section" value="<?= (int)$section ?>">
    <label>Day <select name="day" required><?php foreach (Schedule::DAYS as $k => $v): ?><option value="<?= $k ?>" <?= (int)($_GET['day'] ?? 0) === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></label>
    <label>Period <select name="period" required><?php foreach ($periods as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)($_GET['period'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?> (<?= e($p['start_time']) ?>-<?= e($p['end_time']) ?>)</option><?php endforeach; ?></select></label>
    <label>Subject <select name="subject" required><?php foreach ($subjects as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></label>
    <label>Teacher <select name="teacher" required><?php foreach ($teachers as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-primary">Add class</button>
  </form>
  <p class="muted small">Tip: to give the same class on several days, add it once for each day.</p>
  <?php endif; ?>
  <?php endif; ?>
</section>
