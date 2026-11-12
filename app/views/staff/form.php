<?php $isEdit = $mode === 'edit'; ?>
<div class="page-head"><div><h1><?= $isEdit ? 'Edit account' : 'Add staff account' ?></h1>
  <p class="sub"><?= $isEdit ? e($u['username']) : 'Gate staff scan and release students. Teachers also take attendance. Administrators manage everything.' ?></p></div></div>

<?php if ($errors): ?>
  <div class="alert alert-error"><?= icon('alert', 20) ?><div><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div></div>
<?php endif; ?>

<form method="post" class="card form-grid" action="<?= $isEdit ? url('staff/update/' . $u['id']) : url('staff/store') ?>">
  <?= csrf_field() ?>
  <label>First name * <input name="first_name" value="<?= e($u['first_name'] ?? '') ?>" required></label>
  <label>Middle name <input name="middle_name" value="<?= e($u['middle_name'] ?? '') ?>"></label>
  <label>Last name * <input name="last_name" value="<?= e($u['last_name'] ?? '') ?>" required></label>
  <label>Suffix <input name="name_suffix" value="<?= e($u['name_suffix'] ?? '') ?>" maxlength="10"></label>
  <label>Username * <input name="username" value="<?= e($u['username'] ?? '') ?>" required autocomplete="off"></label>
  <label>Email (optional) <input type="email" name="email" value="<?= e($u['email'] ?? '') ?>"></label>
  <label>Role
    <select name="role">
      <?php foreach (['gate_staff' => 'Gate staff', 'teacher' => 'Teacher', 'admin' => 'Administrator'] as $k => $v): ?>
        <option value="<?= $k ?>" <?= ($u['role'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select></label>
  <label>Contact number (teachers) <input name="contact_number" value="<?= e($u['contact_number'] ?? '') ?>"></label>
  <?php if ($isEdit): ?>
    <label>Account status
      <select name="status">
        <option value="active" <?= ($u['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="disabled" <?= ($u['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled (cannot sign in)</option>
      </select></label>
  <?php else: ?>
    <label>Starting password * (at least 10 characters) <input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
  <?php endif; ?>
  <div class="span2 row-gap">
    <button class="btn btn-primary btn-lg"><?= $isEdit ? 'Save changes' : 'Create account' ?></button>
    <a class="btn btn-ghost btn-lg" href="<?= url('staff') ?>">Cancel</a>
  </div>
</form>

<?php if ($isEdit): ?>
<form method="post" class="card inline-form" style="margin-top:20px" action="<?= url('staff/password/' . $u['id']) ?>"
      data-confirm="Change the password for this account?">
  <?= csrf_field() ?>
  <label>Set a new password (at least 10 characters) <input type="password" name="new_password" required minlength="10" autocomplete="new-password"></label>
  <button class="btn btn-ghost">Change password</button>
</form>
<?php endif; ?>
