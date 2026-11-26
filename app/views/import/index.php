<div class="page-head">
  <div class="title-row">
    <a href="<?= url('students') ?>" class="btn btn-ghost btn-sm"><?= icon('back', 16) ?>Students</a>
    <h1>Import students</h1>
  </div>
</div>

<?php if ($preview === null): ?>
<section class="card import-steps">
  <div class="step-row">
    <span class="step-num">1</span>
    <div class="step-body">
      <h3>Download the Excel template</h3>
      <p class="muted">It has all the columns ready. Phone numbers and LRNs keep their leading zeros.</p>
    </div>
    <a class="btn btn-primary btn-lg" href="<?= url('import/template') ?>"><?= icon('download', 20) ?>Download Excel template</a>
  </div>
  <div class="step-row">
    <span class="step-num">2</span>
    <div class="step-body">
      <h3>Fill it in</h3>
      <p class="muted">One row per student, with up to two guardians. Required: first_name, last_name, grade (Kinder, Grade 1 to Grade 10) and section. The <b>How to fill in</b> sheet explains the rest.</p>
    </div>
  </div>
  <div class="step-row">
    <span class="step-num">3</span>
    <div class="step-body">
      <h3>Upload it</h3>
      <p class="muted">An Excel file (.xlsx) works. A CSV file also works. Nothing is saved until you check the preview and confirm.</p>
      <form method="post" enctype="multipart/form-data" action="<?= url('import/preview') ?>" class="row-gap wrap" style="margin-top:10px">
        <?= csrf_field() ?>
        <input type="file" name="file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required style="max-width:380px">
        <button class="btn btn-primary">Check file</button>
      </form>
    </div>
  </div>
  <p class="mini muted" style="margin:6px 0 0">Sections that do not exist yet are created. Students already registered (same LRN, or same name and birthdate) are skipped. A guardian with the same email is linked, not duplicated.</p>
</section>
<?php else: ?>
<section class="card glance" style="margin-bottom:18px">
  <div class="glance-grid" style="grid-template-columns:repeat(3,1fr)">
    <div class="n-green"><b><?= (int)$count['ready'] ?></b><span>Ready to import</span></div>
    <div class="n-amber"><b><?= (int)$count['skip'] ?></b><span>Skipped (already registered)</span></div>
    <div class="n-red"><b><?= (int)$count['error'] ?></b><span>Have errors (not imported)</span></div>
  </div>
</section>

<div class="row-gap wrap" style="margin-bottom:18px">
  <?php if ($count['ready']): ?>
  <form method="post" action="<?= url('import/confirm') ?>"><?= csrf_field() ?>
    <button class="btn btn-primary btn-lg">Import <?= (int)$count['ready'] ?> students</button></form>
  <?php endif; ?>
  <a class="btn btn-ghost btn-lg" href="<?= url('import') ?>">Upload a different file</a>
</div>
<?php if ($count['error']): ?><div class="alert alert-warning"><?= icon('alert', 20) ?><span>Rows with errors are left out. Fix them in your file and upload again, or import the ready rows now and add the rest by hand.</span></div><?php endif; ?>

<div class="card table-wrap">
  <table>
    <thead><tr><th>Line</th><th>Student</th><th>Grade and section</th><th>Guardians</th><th>Result</th></tr></thead>
    <tbody>
    <?php foreach (array_slice($preview, 0, 300) as $r): ?>
      <tr>
        <td><?= (int)$r['line'] ?></td>
        <td><strong><?= e($r['name'] ?: '—') ?></strong></td>
        <td><?= e($r['class']) ?></td>
        <td><?= (int)$r['guardians'] ?></td>
        <td><span class="badge <?= ['ready' => 'b-green', 'skip' => 'b-amber', 'error' => 'b-red'][$r['state']] ?>"><?= ['ready' => 'Ready', 'skip' => 'Skipped', 'error' => 'Error'][$r['state']] ?></span>
          <?php foreach ($r['messages'] as $m): ?><small class="block muted"><?= e($m) ?></small><?php endforeach; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if (count($preview) > 300): ?><p class="muted small">Showing the first 300 of <?= count($preview) ?> rows. All ready rows are imported.</p><?php endif; ?>
<?php endif; ?>
