<div class="page-head">
  <div><h1>Attendance</h1><p class="sub">Students are marked present automatically when they are timed in at the gate. Use this page for absences, late arrivals and excused absences.</p></div>
</div>

<form method="get" action="<?= e(base_path()) ?>/index.php" class="filters card">
  <input type="hidden" name="url" value="attendance">
  <label>Date <input type="date" name="date" value="<?= e($date) ?>"></label>
  <label>Section
    <select name="section">
      <option value="">All sections</option>
      <?php foreach ($sections as $sec): ?>
        <option value="<?= (int)$sec['id'] ?>" <?= (string)$section === (string)$sec['id'] ? 'selected' : '' ?>><?= e($sec['label']) ?> (<?= e($sec['school_year']) ?>)</option>
      <?php endforeach; ?>
    </select></label>
  <button class="btn btn-primary">Show class</button>
</form>

<form method="post" action="<?= url('attendance/save') ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="date" value="<?= e($date) ?>">
  <input type="hidden" name="section" value="<?= e($section) ?>">
  <div class="card table-wrap">
    <table>
      <thead><tr><th></th><th>Student</th><th>Section</th><th>Status</th><th>Remarks</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><img class="thumb" src="<?= e(avatar($r['full_name'], $r['photo'])) ?>" alt=""></td>
          <td><strong><?= e($r['full_name']) ?></strong></td>
          <td><?= $r['section'] ? e($r['grade']) . ', ' . e($r['section']) : '—' ?></td>
          <td>
            <select class="status-select" name="status[<?= (int)$r['id'] ?>]">
              <option value="">Not marked</option>
              <?php foreach (['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'excused' => 'Excused'] as $k => $v): ?>
                <option value="<?= $k ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input class="remarks-input" name="remarks[<?= (int)$r['id'] ?>]" value="<?= e($r['remarks'] ?? '') ?>" maxlength="255" placeholder="Optional"></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5" class="muted center">No students in this selection.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($rows): ?><div style="margin-top:18px"><button class="btn btn-primary btn-lg">Save attendance</button></div><?php endif; ?>
</form>
