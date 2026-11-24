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

  <div class="table-wrap" style="margin-top:14px"><table>
    <thead><tr><th>Day</th><th>Period</th><th>Subject</th><th>Teacher</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($classes as $c): ?>
      <tr>
        <td><?= e(Schedule::DAYS[$c['day_of_week']] ?? '') ?></td>
        <td><?= e($c['period']) ?> <small class="muted">(<?= e($c['start_time']) ?>-<?= e($c['end_time']) ?>)</small></td>
        <td><?= e($c['subject']) ?></td>
        <td><?= e($c['teacher']) ?></td>
        <td>
          <form method="post" action="<?= url('schedule/deleteClass/' . $c['id']) ?>" data-confirm="Remove this class from the schedule?"><?= csrf_field() ?>
            <button class="btn btn-danger-ghost btn-sm">Remove</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$classes): ?><tr><td colspan="5" class="muted center">No classes scheduled for this section yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div>

  <?php if (!$periods || !$subjects || !$teachers): ?>
    <div class="alert alert-warning" style="margin-top:14px"><?= icon('alert', 20) ?><span>To add classes you need at least one period, one subject and one active teacher account (Administration &gt; Staff accounts).</span></div>
  <?php else: ?>
  <form method="post" action="<?= url('schedule/addClass') ?>" class="inline-form" style="margin-top:14px"><?= csrf_field() ?>
    <input type="hidden" name="section" value="<?= (int)$section ?>">
    <label>Day <select name="day" required><?php foreach (Schedule::DAYS as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></label>
    <label>Period <select name="period" required><?php foreach ($periods as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['start_time']) ?>-<?= e($p['end_time']) ?>)</option><?php endforeach; ?></select></label>
    <label>Subject <select name="subject" required><?php foreach ($subjects as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></label>
    <label>Teacher <select name="teacher" required><?php foreach ($teachers as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-primary">Add class</button>
  </form>
  <p class="muted small">Tip: to give the same class on several days, add it once for each day.</p>
  <?php endif; ?>
  <?php endif; ?>
</section>
