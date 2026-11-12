<div class="page-head"><div><h1>Edit section</h1></div></div>
<form method="post" action="<?= url('sections/update/' . $sec['id']) ?>" class="card form-grid">
  <?= csrf_field() ?>
  <label>Grade level
    <select name="grade_level"><?php foreach ($grades as $g): ?><option <?= $sec['grade_level'] === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?></select></label>
  <label>Section name <input name="section_name" value="<?= e($sec['section_name']) ?>" required></label>
  <label>School year <input name="school_year" value="<?= e($sec['school_year']) ?>" required pattern="\d{4}-\d{4}"></label>
  <label>Adviser
    <select name="adviser_id"><option value="">No adviser yet</option>
      <?php foreach ($teachers as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (string)$sec['adviser_id'] === (string)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></label>
  <div class="span2 row-gap">
    <button class="btn btn-primary btn-lg">Save changes</button>
    <a class="btn btn-ghost btn-lg" href="<?= url('sections') ?>">Cancel</a>
  </div>
</form>
