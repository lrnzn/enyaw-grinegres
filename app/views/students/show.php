<div class="page-head">
  <div class="title-row">
    <a href="<?= url('students') ?>" class="btn btn-ghost btn-sm"><?= icon('back', 16) ?>Students</a>
    <h1><?= e($s['full_name']) ?></h1>
  </div>
  <div class="row-gap wrap">
    <?php if (is_admin()): ?>
      <a class="btn btn-primary" href="<?= url('students/edit/' . $s['id']) ?>"><?= icon('edit', 18) ?>Edit student</a>
    <?php endif; ?>
    <a class="btn btn-ghost" href="<?= url('students/card/' . $s['id']) ?>"><?= icon('printer', 18) ?>ID card and QR</a>
    <?php if (is_admin()): ?>
      <?php if ($s['status'] === 'archived'): ?>
      <form method="post" action="<?= url('students/restore/' . $s['id']) ?>">
        <?= csrf_field() ?><button class="btn btn-primary"><?= icon('refresh', 18) ?>Restore student</button>
      </form>
      <?php else: ?>
      <form method="post" action="<?= url('students/archive/' . $s['id']) ?>" data-confirm="Archive this student? All records are kept, and you can restore the student later.">
        <?= csrf_field() ?><button class="btn btn-danger-ghost"><?= icon('trash', 18) ?>Archive</button>
      </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<div class="two-col wide-left">
  <section class="idpanel">
    <div class="idpanel-top"><?= icon('shield', 18) ?><?= e(config('school_name')) ?></div>
    <div class="idpanel-body">
      <img class="photo-lg" src="<?= e(avatar($s['full_name'], $s['photo'])) ?>" alt="">
      <h2><?= e($s['full_name']) ?></h2>
      <p class="lead"><?= $s['section'] ? e($s['grade']) . ', ' . e($s['section']) : 'No section yet' ?></p>
      <p><?= $s['status'] === 'enrolled' ? state_badge($state['state']) : '<span class="badge b-gray">' . e(ucfirst($s['status'])) . '</span>' ?></p>
      <dl>
        <dt>LRN</dt><dd><?= e($s['lrn'] ?: '—') ?></dd>
        <?php if (is_admin()): ?>
          <dt>Full name</dt><dd><?= e(trim($s['first_name'] . ' ' . ($s['middle_name'] ?? '') . ' ' . $s['last_name'] . ' ' . ($s['name_suffix'] ?? ''))) ?></dd>
          <dt>Date of birth</dt><dd><?= e($s['birthdate'] ? date('F j, Y', strtotime($s['birthdate'])) : '—') ?></dd>
          <dt>Address</dt><dd><?= e($s['address'] ?: '—') ?></dd>
        <?php endif; ?>
        <dt>QR code</dt><dd><code><?= e($s['qr_token']) ?></code></dd>
      </dl>
    </div>
  </section>

  <div class="stack">
    <section class="card">
      <h3>Registered guardians</h3>
      <p class="muted small">Only guardians marked <b>Authorized</b> can pick this student up.</p>
      <?php if (!$guardians): ?><div class="alert alert-warning"><?= icon('alert', 20) ?><span>No guardians registered yet, so nobody can be verified for pickup.</span></div><?php endif; ?>
      <div class="guardian-list">
      <?php foreach ($guardians as $g): ?>
        <div class="guardian <?= $g['is_authorized'] ? '' : 'revoked' ?>">
          <div class="g-photo <?= $g['is_authorized'] ? 'ok' : 'no' ?>"><img src="<?= e(avatar($g['full_name'], $g['photo'])) ?>" alt=""><span class="g-flag"><?= icon($g['is_authorized'] ? 'check' : 'x', 14) ?></span></div>
          <div class="g-info">
            <strong><?= e($g['full_name']) ?></strong>
            <small><?= e($g['relationship']) ?><?= $g['occupation'] ? ', ' . e($g['occupation']) : '' ?></small>
            <?php if (is_admin()): ?><small><?= icon('phone', 14) ?> <?= e($g['contact'] ?: '—') ?></small><small><?= icon('letter', 14) ?> <?= e($g['email'] ?: '—') ?></small><?php endif; ?>
            <span class="badge <?= $g['is_authorized'] ? 'b-green' : 'b-red' ?>"><?= $g['is_authorized'] ? 'Authorized' : 'Not authorized' ?></span>
            <?php if (is_admin() && $g['is_authorized']): ?>
              <small class="muted"><?php if (!$g['receive_notifications']): ?>Messages are off<?php elseif (!Notifier::reachable($g)): ?><?= e(Notifier::missingText()) ?>, so no messages can be sent<?php else: ?>Gets a message at drop-off and pickup<?php endif; ?></small>
            <?php endif; ?>
          </div>
          <?php if (is_admin()): ?>
          <div class="g-actions">
            <form method="post" action="<?= url('students/toggleGuardian/' . $g['link_id']) ?>"><?= csrf_field() ?>
              <button class="btn btn-ghost btn-sm"><?= $g['is_authorized'] ? 'Revoke pickup' : 'Restore pickup' ?></button></form>
            <?php if ($g['is_authorized']): ?>
            <form method="post" action="<?= url('students/toggleNotify/' . $g['link_id']) ?>"><?= csrf_field() ?>
              <button class="btn btn-ghost btn-sm"><?= $g['receive_notifications'] ? 'Turn messages off' : 'Turn messages on' ?></button></form>
            <?php endif; ?>
            <form method="post" action="<?= url('students/removeGuardian/' . $g['link_id']) ?>" data-confirm="Unlink this guardian?"><?= csrf_field() ?>
              <button class="btn btn-danger-ghost btn-sm">Unlink</button></form>
            <details class="g-edit"><summary class="btn btn-ghost btn-sm">Edit contact</summary>
              <form method="post" action="<?= url('students/updateContact/' . $g['link_id']) ?>"><?= csrf_field() ?>
                <label>Email <input type="email" name="email" value="<?= e($g['email'] ?? '') ?>" placeholder="parent@example.com"></label>
                <label>Contact number <input name="contact" value="<?= e($g['contact'] ?? '') ?>" placeholder="09xx xxx xxxx"></label>
                <button class="btn btn-primary btn-sm">Save</button>
              </form>
            </details>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
    </section>

    <?php if (is_admin()): ?>
    <section class="card">
      <h3>Add a new guardian</h3>
      <form class="form-grid" method="post" enctype="multipart/form-data" action="<?= url('students/addGuardian/' . $s['id']) ?>">
        <?= csrf_field() ?>
        <label>First name * <input name="first_name" required></label>
        <label>Middle name <input name="middle_name"></label>
        <label>Last name * <input name="last_name" required></label>
        <label>Suffix <input name="name_suffix" maxlength="10"></label>
        <label>Relationship * <input name="relationship" placeholder="Mother, father, aunt…" required></label>
        <label>Email (for arrival and pickup messages) <input type="email" name="email" placeholder="parent@example.com"></label>
        <label>Contact number <input name="contact" placeholder="09xx xxx xxxx"></label>
        <label class="span2">Occupation <input name="occupation"></label>
        <label class="span2">Guardian photo (a clear face, up to 5 MB)
          <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-preview data-max-mb="5"></label>
        <div class="span2"><button class="btn btn-primary">Add guardian</button></div>
      </form>
      <?php if ($available): ?>
      <hr>
      <h4>Or link a guardian who is already registered (for siblings)</h4>
      <form class="row-gap wrap" method="post" action="<?= url('students/linkGuardian/' . $s['id']) ?>">
        <?= csrf_field() ?>
        <select name="guardian_id" style="width:auto;min-width:220px"><?php foreach ($available as $g): ?><option value="<?= $g['id'] ?>"><?= e($g['full_name']) ?></option><?php endforeach; ?></select>
        <input name="relationship" placeholder="Relationship" required style="width:auto;min-width:180px">
        <button class="btn btn-ghost">Link guardian</button>
      </form>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="card">
      <h3>Recent history</h3>
      <?php if (!$history): ?><p class="empty">No activity recorded yet.</p><?php endif; ?>
      <ul class="feed">
        <?php foreach ($history as $r): ?>
          <li><span class="badge <?= action_class($r['action']) ?>"><?= e(action_label($r['action'])) ?></span>
            <div><?= e($r['released_to_name'] ?: '') ?><small><?= fmt_dt($r['logged_at']) ?></small></div></li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>
</div>
