<div class="login-page">
  <?php $bg = login_bg(); ?>
  <section class="lp-left<?= $bg ? ' has-photo' : '' ?>">
    <?php if ($bg): ?><div class="lp-bg" style="background-image:url('<?= e($bg) ?>')"></div><?php endif; ?>
    <div class="brand">
      <div class="brand-logo<?= school_logo() ? " has-logo" : "" ?>"><?= brand_mark() ?></div>
      <div><strong><?= e(config('school_name')) ?></strong><small>Student Security &amp; Guardian Verification</small></div>
    </div>
    <h2>Every child goes home with the right person.</h2>
    <p class="lp-lead">Record arrivals, verify guardians at dismissal and keep daily attendance, all in one place.</p>
    <div class="lp-cards">
      <div><?= icon('camera', 24) ?><strong>QR gate scanning</strong><span>Time-in at drop-off in one scan.</span></div>
      <div><?= icon('shield', 24) ?><strong>Guardian check</strong><span>Compare the face with the registered photo.</span></div>
      <div><?= icon('calendar', 24) ?><strong>Attendance</strong><span>By period, with weekly and monthly sheets.</span></div>
      <div><?= icon('letter', 24) ?><strong>Parent emails</strong><span>Guardians are notified at arrival and pickup.</span></div>
    </div>
  </section>

  <section class="lp-right">
    <div class="lp-card">
      <h1>Sign in</h1>
      <p class="muted">Use the account given to you by the school.</p>
      <form method="post" action="<?= url('auth/login') ?>">
        <?= csrf_field() ?>
        <label>Username <input name="username" autofocus required autocomplete="username" placeholder="Username"></label>
        <label>Password
          <span class="pw"><input type="password" name="password" required autocomplete="current-password" placeholder="Password"><button type="button" data-toggle-password>Show</button></span>
        </label>
        <button class="btn btn-primary btn-lg">Sign in</button>
      </form>
      <p class="lp-help">Forgot your username or password? Please ask the Vice Principal.</p>
    <?php if (demo()): ?>
    <div class="demo-box">
      <strong>Demo accounts</strong>
      <div><code>admin</code> <code>admin123</code> Administrator</div>
      <div><code>teacher</code> <code>teacher123</code> Teacher</div>
      <div><code>guard</code> <code>guard123</code> Gate staff</div>
    </div>
    <?php endif; ?>
    </div>
    <p class="lp-foot">&copy; <?= date('Y') ?> <?= e(config('school_name')) ?> &middot; SGVMS</p>
  </section>
</div>
