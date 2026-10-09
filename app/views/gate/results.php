<div class="page-head">
  <div><h1>Which student?</h1><p class="sub"><?= count($found) ?> students match “<?= e($q) ?>”.</p></div>
  <a class="btn btn-ghost" href="<?= url('gate') ?>&mode=<?= e($mode) ?>"><?= icon('back', 16) ?>Back to gate</a>
</div>
<div class="roster">
  <?php foreach ($found as $s): $st = $status[$s['id']]['state'] ?? 'absent'; ?>
    <a class="pick" href="<?= url('gate/verify/' . $s['id']) ?>&mode=<?= e($mode) ?>">
      <img src="<?= e(avatar($s['full_name'], $s['photo'])) ?>" alt="">
      <div><strong><?= e($s['full_name']) ?></strong><small><?= e($s['grade']) ?>, <?= e($s['section']) ?></small><?= state_badge($st) ?></div>
    </a>
  <?php endforeach; ?>
</div>
