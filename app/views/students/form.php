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
  <label>Middle name <input name="middle_name" value="<?= e($s['middle_name'] ?? '') ?>"></label>
  <label>Last name * <input name="last_name" value="<?= e($s['last_name'] ?? '') ?>" required></label>
  <label>Suffix (Jr., III…) <input name="name_suffix" value="<?= e($s['name_suffix'] ?? '') ?>" maxlength="10"></label>
  <label>Section
    <select name="section_id">
      <option value="">No section yet</option>
      <?php foreach ($sections as $sec): ?>
        <option value="<?= (int)$sec['id'] ?>" <?= (string)($s['section_id'] ?? '') === (string)$sec['id'] ? 'selected' : '' ?>><?= e($sec['label']) ?> (<?= e($sec['school_year']) ?>)</option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Gender
    <select name="gender">
      <option value="">Not stated</option>
      <option value="female" <?= ($s['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
      <option value="male"   <?= ($s['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
    </select>
  </label>
  <label>Date of birth <input type="date" name="date_of_birth" value="<?= e($s['date_of_birth'] ?? '') ?>"></label>
  <label>LRN (optional) <input name="lrn" value="<?= e($s['lrn'] ?? '') ?>" inputmode="numeric"></label>
  <?php if ($isEdit): ?>
  <label>Status
    <select name="status">
      <?php foreach (['enrolled' => 'Enrolled', 'withdrawn' => 'Withdrawn', 'graduated' => 'Graduated', 'archived' => 'Archived'] as $k => $v): ?>
        <option value="<?= $k ?>" <?= ($s['status'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <?php endif; ?>
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
