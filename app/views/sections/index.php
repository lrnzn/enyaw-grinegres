<div class="page-head">
  <div><h1>Sections</h1><p class="sub">Classes for each school year. Students belong to a section, and each section can have an adviser.</p></div>
</div>

<form method="post" action="<?= url('sections/store') ?>" class="card inline-form" style="margin-bottom:20px">
  <?= csrf_field() ?>
  <label>Grade level
    <select name="grade_level"><?php foreach ($grades as $g): ?><option><?= e($g) ?></option><?php endforeach; ?></select></label>
  <label>Section name <input name="section_name" required placeholder="Sampaguita"></label>
  <label>School year <input name="school_year" value="<?= e($year) ?>" required pattern="\d{4}-\d{4}"></label>
  <label>Adviser
    <select name="adviser_id"><option value="">No adviser yet</option>
      <?php foreach ($teachers as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></label>
  <button class="btn btn-primary"><?= icon('plus', 18) ?>Add section</button>
</form>

<div class="card table-wrap">
  <table>
    <thead><tr><th>Grade</th><th>Section</th><th>School year</th><th>Adviser</th><th>Students</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($sections as $sec): ?>
      <tr>
        <td><?= e($sec['grade_level']) ?></td>
        <td><strong><?= e($sec['section_name']) ?></strong></td>
        <td><?= e($sec['school_year']) ?></td>
        <td><?= e($sec['adviser_name'] ?: '—') ?></td>
        <td><?= (int)$sec['student_count'] ?></td>
        <td class="right"><div class="row-gap" style="justify-content:flex-end">
          <a class="btn btn-ghost btn-sm" href="<?= url('sections/edit/' . $sec['id']) ?>">Edit</a>
          <form method="post" action="<?= url('sections/delete/' . $sec['id']) ?>" data-confirm="Remove this section? Its students are kept.">
            <?= csrf_field() ?><button class="btn btn-danger-ghost btn-sm">Remove</button></form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$sections): ?><tr><td colspan="6" class="muted center">No sections yet. Add the first one above.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
