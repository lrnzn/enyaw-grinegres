<div class="page-head">
  <div><h1>Audit trail</h1><p class="sub">What administrators changed: students, guardians, sections, accounts and approvals. Records cannot be edited.</p></div>
</div>
<form method="get" action="<?= e(base_path()) ?>/index.php" class="filters card">
  <input type="hidden" name="url" value="logs/audit">
  <label>Date (leave empty for the latest records) <input type="date" name="date" value="<?= e($date) ?>"></label>
  <button class="btn btn-primary">Show records</button>
</form>
<div class="card table-wrap">
  <table>
    <thead><tr><th>When</th><th>Who</th><th>What happened</th><th>Details</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= fmt_dt($r['created_at']) ?></td>
        <td><?= e($r['user_name'] ?: '—') ?></td>
        <td><span class="badge b-blue"><?= e(ucfirst(strtolower(str_replace('_', ' ', $r['action'])))) ?></span></td>
        <td><?= e($r['details'] ?: '—') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4" class="muted center">No records yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
