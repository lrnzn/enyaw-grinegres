<div class="login-split">
  <section class="login-intro">
    <div class="brand">
      <div class="brand-logo"><?= icon('shield', 24) ?></div>
      <div><strong><?= e(config('app_name')) ?></strong><small><?= e(config('school_name')) ?></small></div>
    </div>
    <h2>Every child goes home with the right person.</h2>
    <ol class="steps">
      <li>Scan the student's QR code at the gate</li>
      <li>Compare the guardian's face with the registered photo</li>
      <li>Release the student; the record is saved automatically</li>
    </ol>
  </section>
  <section class="login-form">
    <h1>Sign in</h1>
    <p class="muted">Student Security &amp; Guardian Verification</p>
    <form method="post" action="<?= url('auth/login') ?>">
      <?= csrf_field() ?>
      <label>Username <input name="username" autofocus required autocomplete="username"></label>
      <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
      <button class="btn btn-primary btn-lg">Sign in</button>
    </form>
    <?php if (demo()): ?>
    <div class="demo-box">
      <strong>Demo accounts</strong>
      <div><code>admin</code> <code>admin123</code> Administrator</div>
      <div><code>guard</code> <code>guard123</code> Gate staff</div>
    </div>
    <?php endif; ?>
  </section>
</div>
