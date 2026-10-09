<div class="page-head">
  <div><h1>Students</h1><p class="sub"><?= count($students) ?> registered</p></div>
  <?php if (is_admin()): ?>
  <div class="row-gap wrap">
    <a class="btn btn-ghost" href="<?= url('students/cards') ?>"><?= icon('printer', 18) ?>Print all ID cards</a>
    <a class="btn btn-primary" href="<?= url('students/create') ?>"><?= icon('plus', 18) ?>Register student</a>
  </div>
  <?php endif; ?>
</div>

<form method="get" action="<?= e(base_path()) ?>/index.php" class="filters card">
  <input type="hidden" name="url" value="students">
  <label class="grow">Search by name or LRN <input name="q" value="<?= e($q) ?>" placeholder="For example: Reyes"></label>
  <button class="btn btn-primary"><?= icon('search', 18) ?>Search</button>
</form>

<div class="card table-wrap">
  <table>
    <thead><tr><th></th><th>Name</th><th>Grade and section</th><th>LRN</th><th>Today</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($students as $s): $st = $status[$s['id']]['state'] ?? 'absent'; ?>
      <tr>
        <td><img class="thumb" src="<?= e(avatar($s['full_name'], $s['photo'])) ?>" alt=""></td>
        <td><a href="<?= url('students/show/' . $s['id']) ?>"><strong><?= e($s['full_name']) ?></strong></a></td>
        <td><?= e($s['grade']) ?>, <?= e($s['section']) ?></td>
        <td><?= e($s['lrn'] ?: '—') ?></td>
        <td><?= state_badge($st) ?></td>
        <td class="right"><a class="btn btn-ghost btn-sm" href="<?= url('students/show/' . $s['id']) ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$students): ?><tr><td colspan="6" class="muted center">No students match your search.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
