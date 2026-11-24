<div class="page-head">
  <div><h1>Gate station</h1><p class="sub">Scan a student's QR code, or type a name or LRN and press Enter.</p></div>
</div>

<div class="seg" role="tablist">
  <a class="<?= $mode === 'in' ? 'on' : '' ?>" href="<?= url('gate') ?>&mode=in">
    <?= icon('sun', 28) ?><span>Morning drop-off<small>Record time-in</small></span></a>
  <a class="<?= $mode === 'out' ? 'on out' : '' ?>" href="<?= url('gate') ?>&mode=out">
    <?= icon('home', 28) ?><span>Dismissal<small>Verify the guardian and release</small></span></a>
</div>

<section class="card scan-card">
  <form method="get" action="<?= e(base_path()) ?>/index.php" id="scan-form">
    <input type="hidden" name="url" value="gate/lookup">
    <input type="hidden" name="mode" value="<?= e($mode) ?>">
    <div class="scan-field">
      <?= icon('gate', 26) ?>
      <input class="scan-input" type="text" name="q" id="scan-input" autofocus autocomplete="off"
             placeholder="<?= $mode === 'in' ? 'Scan the QR code to record time-in' : 'Scan the QR code to start dismissal' ?>">
    </div>
    <div class="row-gap wrap">
      <button class="btn btn-primary btn-lg"><?= icon('search', 18) ?>Find student</button>
      <button type="button" class="btn btn-ghost btn-lg" id="btn-camera"><?= icon('camera', 18) ?><span class="lbl">Use camera</span></button>
    </div>
  </form>
  <div id="reader" hidden></div>
  <p class="muted small" style="margin-top:14px">A USB scanner works like a keyboard. Click the box and scan.</p>
</section>

<?php if (demo()): ?>
<section class="card">
  <h3>Demo shortcuts</h3>
  <p class="muted small">Tap a student to act as if you just scanned their QR code.</p>
  <div class="roster">
    <?php foreach ($students as $s): $st = $status[$s['id']]['state'] ?? 'absent'; ?>
      <a class="pick" href="<?= url('gate/lookup') ?>&mode=<?= e($mode) ?>&q=<?= urlencode($s['qr_token']) ?>">
        <img src="<?= e(avatar($s['full_name'], $s['photo'])) ?>" alt="">
        <div><strong><?= e($s['full_name']) ?></strong><small><?= e($s['grade']) ?>, <?= e($s['section']) ?></small><?= state_badge($st) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
