<?php $in = $mode === 'in'; $first = explode(' ', $s['first_name'])[0]; ?>
<div class="page-head">
  <div>
    <h1><?= $in ? 'Welcome, ' . e($first) : 'Who is picking up ' . e($first) . '?' ?></h1>
    <p class="sub"><?= $in ? 'Morning drop-off' : 'Dismissal' ?></p>
  </div>
  <a class="btn btn-ghost" href="<?= url('gate') ?>&mode=<?= e($mode) ?>"><?= icon('x', 16) ?>Cancel</a>
</div>

<div class="verify-grid">
  <section class="idpanel student-panel">
    <div class="idpanel-top"><?= icon('shield', 18) ?><?= e(config('school_name')) ?></div>
    <div class="idpanel-body">
      <img class="photo-xl" src="<?= e(avatar($s['full_name'], $s['photo'])) ?>" alt="Photo of <?= e($s['full_name']) ?>">
      <h2><?= e($s['full_name']) ?></h2>
      <p class="lead"><?= e($s['grade']) ?>, <?= e($s['section']) ?></p>
      <p class="muted small">LRN <?= e($s['lrn'] ?: '—') ?></p>
      <p><?= state_badge($state['state']) ?></p>
      <?php if ($state['in']): ?><p class="muted small">Timed in at <?= fmt_time($state['in']) ?></p><?php endif; ?>

      <?php if ($in && $state['state'] !== 'absent'): ?>
        <div class="alert alert-warning"><?= icon('alert', 20) ?><span>This student already has a record today. No second time-in is needed.</span></div>
      <?php endif; ?>
      <?php if (!$in && $state['state'] === 'released'): ?>
        <div class="alert alert-error"><?= icon('alert', 20) ?><span>Already released today at <?= fmt_time($state['out']) ?>. Do not release again. Ask the supervisor.</span></div>
      <?php elseif (!$in && $state['state'] === 'absent'): ?>
        <div class="alert alert-warning"><?= icon('alert', 20) ?><span>No time-in was recorded today. Confirm the student attended before releasing.</span></div>
      <?php endif; ?>
    </div>
  </section>

  <div class="stack">
  <?php if ($in): ?>
    <section class="card">
      <h3>Confirm drop-off</h3>
      <p>Recording saves today's time-in with the current time.</p>
      <form method="post" action="<?= url('gate/timein/' . $s['id']) ?>" style="margin-top:16px">
        <?= csrf_field() ?>
        <button class="btn btn-success btn-xl" <?= $state['state'] !== 'absent' ? 'disabled' : '' ?>><?= icon('check', 24) ?>Record time-in</button>
      </form>
    </section>
  <?php else: ?>
    <section class="card">
      <h3>Compare the person at the gate with these photos</h3>
      <p class="muted">Only guardians marked <b>Authorized</b> can receive <?= e($first) ?>.</p>
      <?php if (!$guardians): ?><div class="alert alert-error"><?= icon('alert', 20) ?><span>No guardians are registered for this student. Do not release. Contact the administrator.</span></div><?php endif; ?>

      <div class="guardian-list big" style="margin-top:14px">
      <?php foreach ($guardians as $g): ?>
        <div class="guardian <?= $g['is_authorized'] ? '' : 'revoked' ?>">
          <div class="g-photo <?= $g['is_authorized'] ? 'ok' : 'no' ?>"><img src="<?= e(avatar($g['full_name'], $g['photo'])) ?>" alt="Photo of <?= e($g['full_name']) ?>"><span class="g-flag"><?= icon($g['is_authorized'] ? 'check' : 'x', 14) ?></span></div>
          <div class="g-info">
            <strong><?= e($g['full_name']) ?></strong>
            <small><?= e($g['relationship']) ?></small>
            <small><?= icon('phone', 14) ?> <?= e($g['contact'] ?: '—') ?></small>
          </div>
          <?php if ($g['is_authorized']): ?>
            <form method="post" action="<?= url('gate/release/' . $s['id']) ?>"
                  data-confirm="Release <?= e($s['full_name']) ?> to <?= e($g['full_name']) ?>?">
              <?= csrf_field() ?><input type="hidden" name="guardian_id" value="<?= (int)$g['id'] ?>">
              <button class="btn btn-success btn-lg" <?= $state['state'] === 'released' ? 'disabled' : '' ?>><?= icon('check', 26) ?>This is the person<small>Release <?= e($first) ?></small></button>
            </form>
          <?php else: ?>
            <div class="stop-box"><?= icon('ban', 26) ?>Not authorized<small>Do not release to this person</small></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
    </section>

    <details class="card" <?= !$guardians ? 'open' : '' ?>>
      <summary><?= icon('users', 22) ?>The person at the gate is not on this list</summary>
      <div class="stack">
        <form method="post" class="inline-form" action="<?= url('gate/deny/' . $s['id']) ?>">
          <?= csrf_field() ?>
          <label>Name of the person, if known <input name="person_name"></label>
          <label>Reason <input name="note" placeholder="Not registered. Asked to bring an authorization letter."></label>
          <button class="btn btn-danger"><?= icon('x', 18) ?>Deny and record</button>
        </form>

        <form method="post" enctype="multipart/form-data" class="stack sub-form" action="<?= url('gate/override/' . $s['id']) ?>">
          <h4>Substitute pickup</h4>
          <div class="notice-box">
            <?php if (config('substitute_pickup.require') === 'both'): ?>
              This school needs <b>both</b> a phone call to a registered guardian and an authorization letter, then a teacher's or administrator's approval.
            <?php else: ?>
              Confirm it with a <b>phone call to a registered guardian</b> or an <b>authorization letter</b> (or both), then get a teacher's or administrator's approval.
            <?php endif; ?>
          </div>
          <?= csrf_field() ?>
          <div class="form-grid">
            <label>Their first name * <input name="first_name" required></label>
            <label>Their last name * <input name="last_name" required></label>
            <label>Their contact number * <input name="contact" required></label>
            <label>Relationship to <?= e($first) ?> * <input name="relationship" placeholder="Aunt, neighbor, driver…" required></label>
            <label>Registered guardian you phoned
              <select name="called_guardian_id">
                <option value="">I did not phone anyone</option>
                <?php foreach ($guardians as $g): if (!$g['is_authorized']) continue; ?>
                  <option value="<?= (int)$g['id'] ?>"><?= e($g['full_name']) ?> (<?= e($g['relationship']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>What happened on the call?
              <select name="phone_call_status">
                <option value="not_called">Not called</option>
                <option value="reached">The guardian confirmed the pickup</option>
                <option value="no_answer">No answer</option>
                <option value="declined">The guardian did not approve</option>
              </select>
            </label>
            <label class="span2">Photo or scan of the authorization letter (JPG, PNG, WEBP or PDF, up to 5 MB)
              <input type="file" name="letter" accept="image/jpeg,image/png,image/webp,application/pdf" data-preview data-max-mb="5">
            </label>
            <label class="span2">Photo of the person (optional)
              <input type="file" name="collector_photo" accept="image/jpeg,image/png,image/webp" data-preview data-max-mb="5">
            </label>
            <label>Approving teacher or administrator: username * <input name="approver_username" required autocomplete="off"></label>
            <label>Their password * <input type="password" name="approver_password" required autocomplete="off">
              <?php if (demo()): ?><small class="muted">Demo: admin / admin123 or teacher / teacher123</small><?php endif; ?></label>
          </div>
          <div><button class="btn btn-amber btn-lg"><?= icon('letter', 20) ?>Release to this person</button></div>
        </form>
      </div>
    </details>
  <?php endif; ?>
  </div>
</div>
