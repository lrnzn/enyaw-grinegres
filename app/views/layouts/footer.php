<?php if ($user): ?></div><?php endif; ?>
</main>
<?php if ($user): ?></div><?php endif; ?>
<script>window.SGVMS = { gateUrl: "<?= url('gate/lookup') ?>" };</script>
<script src="<?= asset('assets/js/qrcode.js') ?>"></script>
<?php foreach ($scripts as $js): ?><script src="<?= asset('assets/' . $js) ?>"></script><?php endforeach; ?>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
