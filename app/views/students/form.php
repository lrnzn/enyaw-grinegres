<?php $isEdit = $mode === 'edit'; ?>
<div class="page-head"><div>
  <h1><?= $isEdit ? 'Edit student' : 'Register a student' ?></h1>
  <p class="sub"><?= $isEdit ? 'Update the details below.' : 'A unique QR code is made automatically when you save. You add guardians on the next page.' ?></p>
</div></div>

<?php if ($errors): ?>
  <div class="alert alert-error"><?= icon('alert', 20) ?><div><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div></div>
<?php endif; ?>

<form class="card form-grid" method="post" enctype="multipart/form-data"
      action="<?= $isEdit ? url('students/update/' . $s['id']) : url('students/store') ?>">
  <?= csrf_field() ?>
  <label>First name * <input name="first_name" value="<?= e($s['first_name'] ?? '') ?>" required></label>
  <label>Last name * <input name="last_name" value="<?= e($s['last_name'] ?? '') ?>" required></label>
  <label>Grade level *
    <select name="grade" required>
      <?php for ($g = 1; $g <= 6; $g++): $v = "Grade $g"; ?>
        <option <?= ($s['grade'] ?? '') === $v ? 'selected' : '' ?>><?= $v ?></option>
      <?php endfor; ?>
    </select>
  </label>
  <label>Section * <input name="section" value="<?= e($s['section'] ?? '') ?>" required></label>
  <label>Date of birth <input type="date" name="birthdate" value="<?= e($s['birthdate'] ?? '') ?>"></label>
  <label>LRN (optional) <input name="lrn" value="<?= e($s['lrn'] ?? '') ?>" inputmode="numeric"></label>
  <label class="span2">Address <input name="address" value="<?= e($s['address'] ?? '') ?>"></label>
  <label class="span2">Student photo (JPG, PNG or WEBP, up to 5 MB)
    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-preview data-max-mb="5"
           <?php if ($isEdit && !empty($s['photo'])): ?>data-current="<?= e(avatar($s['full_name'] ?? '', $s['photo'])) ?>"<?php endif; ?>>
  </label>
  <div class="span2 row-gap">
    <button class="btn btn-primary btn-lg"><?= $isEdit ? 'Save changes' : 'Register student' ?></button>
    <a class="btn btn-ghost btn-lg" href="<?= url($isEdit ? 'students/show/' . $s['id'] : 'students') ?>">Cancel</a>
  </div>
</form>
