<?php
$q = function (array $over = []) use ($type, $range, $from, $sectionId, $subjectId) {
    return http_build_query(array_filter(array_merge(['type' => $type, 'range' => $range, 'date' => $from, 'section' => $sectionId, 'subject' => $subjectId], $over), function ($v) { return $v !== '' && $v !== null && $v !== 0; }));
};
$isAdmin = is_admin();
$codes = ['present' => 'P', 'late' => 'L', 'absent' => 'A', 'excused' => 'E', 'no_class' => '–'];
$title = $range === 'month' ? date('F Y', strtotime($from)) : date('M j', strtotime($from)) . ' – ' . date('M j, Y', strtotime($to));
?>
<div class="page-head no-print">
  <div><h1>Attendance sheet</h1><p class="sub">Choose a class and a week<?= $isAdmin ? ' or month' : '' ?>, then print it.</p></div>
  <?php if ($sheet): ?><button class="btn btn-primary" onclick="window.print()"><?= icon('download', 18) ?>Print</button><?php endif; ?>
</div>

<div class="tabs no-print">
  <?php if ($canDaily): ?><a class="<?= $type === 'daily' ? 'on' : '' ?>" href="<?= url('reports/sheet') ?>&<?= e($q(['type' => 'daily', 'section' => '', 'subject' => ''])) ?>">Daily (gate)</a><?php endif; ?>
  <a class="<?= $type === 'period' ? 'on' : '' ?>" href="<?= url('reports/sheet') ?>&<?= e($q(['type' => 'period', 'section' => '', 'subject' => ''])) ?>">By subject</a>
</div>

<form method="get" action="<?= e(base_path()) ?>/index.php" class="filters card no-print">
  <input type="hidden" name="url" value="reports/sheet">
  <input type="hidden" name="type" value="<?= e($type) ?>">
  <label>Section
    <select name="section" data-autosubmit>
      <?php foreach ($sections as $sec): ?>
        <option value="<?= (int)$sec['id'] ?>" <?= $sectionId === (int)$sec['id'] ? 'selected' : '' ?>><?= e($sec['label']) ?> (<?= e($sec['school_year']) ?>)</option>
      <?php endforeach; ?>
    </select></label>
  <?php if ($type === 'period'): ?>
    <label>Subject
      <select name="subject" data-autosubmit>
        <?php foreach ($subjects as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $subjectId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
      </select></label>
  <?php endif; ?>
  <?php if ($isAdmin): ?>
    <label>Show
      <select name="range" data-autosubmit>
        <option value="week" <?= $range === 'week' ? 'selected' : '' ?>>One week</option>
        <option value="month" <?= $range === 'month' ? 'selected' : '' ?>>One month</option>
      </select></label>
    <label>Date in that week/month <input type="date" name="date" value="<?= e($from) ?>" data-autosubmit></label>
  <?php else: ?>
    <input type="hidden" name="range" value="week">
    <span class="muted">Showing this week (<?= e($title) ?>)</span>
  <?php endif; ?>
  <noscript><button class="btn btn-primary">Show</button></noscript>
</form>

<?php if (!$sheet): ?>
  <div class="card empty-note muted center">
    <?= $type === 'period' ? 'No classes on the timetable for this selection yet.' : 'Choose a section to see its sheet.' ?>
  </div>
<?php else: ?>
<article class="sheet">
  <header class="sheet-head">
    <span class="sheet-logo"><?= brand_mark(40) ?></span>
    <div>
      <div class="sheet-school"><?= e(config('school_name')) ?></div>
      <div class="sheet-title">Attendance sheet — <?= $range === 'month' ? 'monthly' : 'weekly' ?><?= $type === 'period' ? ' (by subject)' : '' ?></div>
    </div>
  </header>
  <dl class="sheet-meta">
    <div><dt>Grade &amp; section</dt><dd><?= e($section['grade_level'] . ', ' . $section['section_name']) ?></dd></div>
    <div><dt>School year</dt><dd><?= e($section['school_year']) ?></dd></div>
    <div><dt>Adviser</dt><dd><?= e($section['adviser_name'] ?: '—') ?></dd></div>
    <?php if ($type === 'period'): ?><div><dt>Subject</dt><dd><?= e($subject['name'] ?? '—') ?></dd></div><?php endif; ?>
    <div><dt>Period covered</dt><dd><?= e($title) ?></dd></div>
  </dl>

  <table class="sheet-grid">
    <thead>
      <tr><th class="n">#</th><th class="nm">Student</th>
        <?php foreach ($sheet['days'] as $d): ?><th class="d"><?= date('D', strtotime($d)) ?><br><?= date('j', strtotime($d)) ?></th><?php endforeach; ?>
        <th class="t">P</th><th class="t">L</th><th class="t">A</th><th class="t">E</th></tr>
    </thead>
    <tbody>
    <?php $i = 0; foreach ($sheet['students'] as $s): $i++; $cnt = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0]; ?>
      <tr><td class="n"><?= $i ?></td><td class="nm"><?= e($s['full_name']) ?></td>
        <?php foreach ($sheet['days'] as $d): $st = $sheet['marks'][$s['id']][$d] ?? ''; if (isset($cnt[$st])) $cnt[$st]++; ?>
          <td class="c st-<?= e($st) ?>"><?= e($codes[$st] ?? '') ?></td>
        <?php endforeach; ?>
        <td class="t"><?= $cnt['present'] ?></td><td class="t"><?= $cnt['late'] ?></td><td class="t"><?= $cnt['absent'] ?></td><td class="t"><?= $cnt['excused'] ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$sheet['students']): ?><tr><td colspan="<?= count($sheet['days']) + 6 ?>" class="center muted">No enrolled students in this section.</td></tr><?php endif; ?>
    </tbody>
  </table>

  <p class="sheet-legend"><strong>P</strong> Present &nbsp; <strong>L</strong> Late &nbsp; <strong>A</strong> Absent &nbsp; <strong>E</strong> Excused &nbsp;
    <?php if ($type === 'period'): ?><strong>–</strong> No class &nbsp; <?php endif; ?>Blank = not recorded. Totals at the right count the days shown.</p>

  <div class="sheet-sign">
    <div><span class="line"></span>Prepared by (<?= $type === 'period' ? 'subject teacher' : 'adviser' ?>)</div>
    <div><span class="line"></span>Noted by (Vice Principal)</div>
  </div>
  <p class="sheet-foot">Printed <?= date('M j, Y g:i A') ?> by <?= e(current_user()['name'] ?? '') ?></p>
</article>
<?php endif; ?>
