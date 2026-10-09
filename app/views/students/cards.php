<div class="page-head no-print">
  <div><h1>Student ID cards</h1><p class="sub">Each card carries a unique QR code. Print and laminate, or show the QR on a phone.</p></div>
  <button class="btn btn-primary" data-print><?= icon('printer', 18) ?>Print</button>
</div>
<div class="cards-grid">
<?php foreach ($list as $s): ?>
  <div class="id-card">
    <div class="id-head"><?= icon('shield', 22) ?><div><?= e(config('school_name')) ?><small>Student ID</small></div></div>
    <div class="id-body">
      <img src="<?= e(avatar($s['full_name'], $s['photo'])) ?>" alt="">
      <div class="id-qr" data-qr="<?= e($s['qr_token']) ?>"></div>
    </div>
    <div class="id-name"><?= e($s['full_name']) ?></div>
    <div class="id-meta"><?= e($s['grade']) ?>, <?= e($s['section']) ?></div>
    <code><?= e($s['qr_token']) ?></code>
  </div>
<?php endforeach; ?>
</div>
