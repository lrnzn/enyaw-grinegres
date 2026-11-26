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
    <label>New period <input name="name" maxlength="40" placeholder="e.g. Period 1 (Grade 7)" required></label>
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
    $showAll = !empty($_GET['all']) || !$grid;
    $rows = $showAll ? $periods : array_values(array_filter($periods, function ($p) use ($grid) { return isset($grid[(int)$p['id']]); }));
    $secLabel = '';
    foreach ($sections as $sec) if ((int)$sec['id'] === (int)$section) $secLabel = $sec['label'] . ' (' . $sec['school_year'] . ')';
  ?>
  <h4 style="margin:16px 0 0">Weekly timetable: <?= e($secLabel) ?></h4>
  <?php if (!$periods): ?>
    <p class="muted">Add the periods above first. They become the rows of the timetable.</p>
  <?php else: ?>
  <?php
    $mins = function ($t) { return (int)substr($t, 0, 2) * 60 + (int)substr($t, 3, 2); };
    $starts = $ends = [];
    foreach ($rows as $p) { $starts[] = $mins($p['start_time']); $ends[] = $mins($p['end_time']); }
    foreach ($classes as $c) { $starts[] = $mins($c['start_time']); $ends[] = $mins($c['end_time']); }
    $t0 = (int)(floor(min($starts) / 30) * 30); $t1 = (int)(ceil(max($ends) / 30) * 30);
    $PX = 1.5; $H = ($t1 - $t0) * $PX;
    $fmt = function ($m) { return sprintf('%02d:%02d', intdiv($m, 60), $m % 60); };
    $byDay = [];
    foreach ($classes as $c) $byDay[(int)$c['day_of_week']][] = $c;
  ?>
  <div class="tg-wrap"><div class="tg">
    <div class="tg-head"><div class="tg-time">Time</div><?php foreach (Schedule::DAYS as $d => $dn): ?><div><?= e($dn) ?></div><?php endforeach; ?></div>
    <div class="tg-body" style="height:<?= $H ?>px">
      <div class="tg-time tg-axis">
        <?php for ($m = $t0; $m < $t1; $m += 30): ?><span style="top:<?= ($m - $t0) * $PX ?>px"><?= $fmt($m) ?> - <?= $fmt($m + 30) ?></span><?php endfor; ?>
      </div>
      <?php foreach (Schedule::DAYS as $d => $dn):
        $busy = $byDay[$d] ?? [];
        $free = [];
        foreach ($rows as $p) {                                        // empty periods that do not overlap a class that day
          $ps = $mins($p['start_time']); $pe = $mins($p['end_time']); $hit = false;
          foreach ($busy as $c) if ($mins($c['start_time']) < $pe && $mins($c['end_time']) > $ps) { $hit = true; break; }
          if (!$hit) $free[] = ['p' => $p, 's' => $ps, 'e' => $pe];
        }
        usort($free, function ($x, $y) { return $x['s'] <=> $y['s'] ?: $x['e'] <=> $y['e']; });
        $groups = [];                                                   // overlapping empty periods become one + box
        foreach ($free as $fr) {
          $g = count($groups) - 1;
          if ($g >= 0 && $fr['s'] < $groups[$g]['e']) { $groups[$g]['e'] = max($groups[$g]['e'], $fr['e']); $groups[$g]['n']++; }
          else $groups[] = ['s' => $fr['s'], 'e' => $fr['e'], 'p' => $fr['p'], 'n' => 1];
        }
      ?>
      <div class="tg-day" style="height:<?= $H ?>px">
        <?php foreach ($busy as $c): $cs = $mins($c['start_time']); $ce = $mins($c['end_time']); ?>
          <div class="slot c<?= (int)$c['subject_id'] % 8 ?>" style="top:<?= ($cs - $t0) * $PX ?>px;height:<?= ($ce - $cs) * $PX ?>px">
            <b><?= e($c['subject']) ?></b><span><?= e($c['teacher']) ?></span><small><?= e($c['start_time']) ?> - <?= e($c['end_time']) ?></small>
            <form method="post" action="<?= url('schedule/deleteClass/' . $c['id']) ?>" data-confirm="Remove <?= e($c['subject']) ?> on <?= e($dn) ?>, <?= e($c['period']) ?>?"><?= csrf_field() ?>
              <button title="Remove this class" aria-label="Remove">&times;</button></form>
          </div>
        <?php endforeach; ?>
        <?php foreach ($groups as $gr): $p = $gr['p']; ?>
          <a class="empty" style="top:<?= ($gr['s'] - $t0) * $PX ?>px;height:<?= ($gr['e'] - $gr['s']) * $PX ?>px;left:0;width:100%"
             href="<?= url('schedule') ?>&section=<?= (int)$section ?>&day=<?= (int)$d ?>&period=<?= (int)$p['id'] ?>#add-class" title="Add a class on <?= e($dn) ?>"><span class="plus">+</span><small><?= $gr['n'] > 1 ? 'Add class' : e($p['name']) ?></small></a>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div></div>
  <p class="muted small" style="margin-top:8px">Click a <strong>+</strong> to add a class in that slot. Click &times; on a class to remove it.
    <?php if (!$showAll): ?>Showing only the periods this section uses. <a href="<?= url('schedule') ?>&section=<?= (int)$section ?>&all=1">Show all periods</a>
    <?php elseif ($grid && count($rows) > count($grid)): ?><a href="<?= url('schedule') ?>&section=<?= (int)$section ?>">Show only this section's periods</a><?php endif; ?></p>
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
