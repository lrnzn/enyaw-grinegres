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
      <div class="two-col">
        <form method="post" class="stack" action="<?= url('gate/deny/' . $s['id']) ?>">
          <h4>Deny the release</h4>
          <?= csrf_field() ?>
          <label>Name of the person, if known <input name="person_name"></label>
          <label>Reason <input name="note" placeholder="Not registered. Asked to bring an authorization letter."></label>
          <button class="btn btn-danger"><?= icon('x', 18) ?>Deny and record</button>
        </form>
        <form method="post" class="stack" action="<?= url('gate/override/' . $s['id']) ?>">
          <h4>They have an authorization letter</h4>
          <?= csrf_field() ?>
          <label>Name of the person * <input name="person_name" required></label>
          <label>Letter details * <input name="note" placeholder="Signed by mother, dated today" required></label>
          <label>Supervisor password * <input type="password" name="admin_password" required autocomplete="off">
            <?php if (demo()): ?><small class="muted">Demo password: admin123</small><?php endif; ?></label>
          <button class="btn btn-amber"><?= icon('letter', 18) ?>Release with letter</button>
        </form>
      </div>
    </details>
  <?php endif; ?>
  </div>
</div>
