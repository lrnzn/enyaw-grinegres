<div class="page-head">
  <div><h1>Staff accounts</h1><p class="sub">People who can sign in. Disable an account when someone leaves; it stops working immediately.</p></div>
  <a class="btn btn-primary" href="<?= url('staff/create') ?>"><?= icon('plus', 18) ?>Add account</a>
</div>
<div class="card table-wrap">
  <table>
    <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Last sign-in</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><strong><?= e($u['name']) ?></strong><?php if ($u['email']): ?><small class="block muted"><?= e($u['email']) ?></small><?php endif; ?></td>
        <td><?= e($u['username']) ?></td>
        <td><?= e(role_label($u['role'])) ?></td>
        <td><span class="badge <?= $u['status'] === 'active' ? 'b-green' : 'b-gray' ?>"><?= $u['status'] === 'active' ? 'Active' : 'Disabled' ?></span></td>
        <td><?= $u['last_login'] ? fmt_dt($u['last_login']) : '<span class="muted">Never</span>' ?></td>
        <td class="right"><a class="btn btn-ghost btn-sm" href="<?= url('staff/edit/' . $u['id']) ?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
