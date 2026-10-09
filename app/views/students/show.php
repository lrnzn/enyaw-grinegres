<div class="page-head">
  <div class="title-row">
    <a href="<?= url('students') ?>" class="btn btn-ghost btn-sm"><?= icon('back', 16) ?>Students</a>
    <h1><?= e($s['full_name']) ?></h1>
  </div>
  <div class="row-gap wrap">
    <a class="btn btn-ghost" href="<?= url('students/card/' . $s['id']) ?>"><?= icon('printer', 18) ?>ID card and QR</a>
    <?php if (is_admin()): ?>
      <a class="btn btn-ghost" href="<?= url('students/edit/' . $s['id']) ?>"><?= icon('edit', 18) ?>Edit</a>
      <form method="post" action="<?= url('students/delete/' . $s['id']) ?>" data-confirm="Remove this student and unlink all guardians?">
        <?= csrf_field() ?><button class="btn btn-danger-ghost"><?= icon('trash', 18) ?>Delete</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="two-col wide-left">
  <section class="idpanel">
    <div class="idpanel-top"><?= icon('shield', 18) ?><?= e(config('school_name')) ?></div>
    <div class="idpanel-body">
      <img class="photo-lg" src="<?= e(avatar($s['full_name'], $s['photo'])) ?>" alt="">
      <h2><?= e($s['full_name']) ?></h2>
      <p class="lead"><?= e($s['grade']) ?>, <?= e($s['section']) ?></p>
      <p><?= state_badge($state['state']) ?></p>
      <dl>
        <dt>LRN</dt><dd><?= e($s['lrn'] ?: '—') ?></dd>
        <dt>Date of birth</dt><dd><?= e($s['birthdate'] ? date('F j, Y', strtotime($s['birthdate'])) : '—') ?></dd>
        <dt>Address</dt><dd><?= e($s['address'] ?: '—') ?></dd>
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
            <small><?= icon('phone', 14) ?> <?= e($g['contact'] ?: '—') ?></small>
            <span class="badge <?= $g['is_authorized'] ? 'b-green' : 'b-red' ?>"><?= $g['is_authorized'] ? 'Authorized' : 'Not authorized' ?></span>
          </div>
          <?php if (is_admin()): ?>
          <div class="g-actions">
            <form method="post" action="<?= url('students/toggleGuardian/' . $g['link_id']) ?>"><?= csrf_field() ?>
              <button class="btn btn-ghost btn-sm"><?= $g['is_authorized'] ? 'Revoke pickup' : 'Restore pickup' ?></button></form>
            <form method="post" action="<?= url('students/removeGuardian/' . $g['link_id']) ?>" data-confirm="Unlink this guardian?"><?= csrf_field() ?>
              <button class="btn btn-danger-ghost btn-sm">Unlink</button></form>
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
        <label>Full name * <input name="full_name" required></label>
        <label>Relationship * <input name="relationship" placeholder="Mother, father, aunt…" required></label>
        <label>Contact number <input name="contact" placeholder="09xx xxx xxxx"></label>
        <label>Occupation <input name="occupation"></label>
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
            <div><?= e($r['guardian_name'] ?: ($r['person_name'] ?: '')) ?><small><?= fmt_dt($r['logged_at']) ?></small></div></li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>
</div>
