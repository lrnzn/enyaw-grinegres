<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#14463A">
<title><?= e($title) ?> · <?= e(config('app_name')) ?></title>
<link rel="preload" href="<?= asset('assets/fonts/lexend-400.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body>
<?php if ($user): ?>
<div class="app">
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-logo"><?= icon('shield', 24) ?></div>
      <div><strong><?= e(config('app_name')) ?></strong><small><?= e(config('school_name')) ?></small></div>
    </div>
    <nav aria-label="Main">
      <?php if (is_admin()): ?>
        <a href="<?= url('dashboard') ?>" class="<?= $active === 'dashboard' ? 'on' : '' ?>"><?= icon('dashboard') ?>Dashboard</a>
      <?php endif; ?>
      <a href="<?= url('gate') ?>"     class="<?= $active === 'gate' ? 'on' : '' ?>"><?= icon('gate') ?>Gate station</a>
      <a href="<?= url('students') ?>" class="<?= $active === 'students' ? 'on' : '' ?>"><?= icon('students') ?>Students</a>
      <a href="<?= url('logs') ?>"     class="<?= $active === 'logs' ? 'on' : '' ?>"><?= icon('logs') ?>Activity logs</a>
    </nav>
    <div class="side-user">
      <div class="clock" data-clock></div>
      <div class="date" data-date></div>
      <div class="who">
        <div class="av"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></div>
        <div><b><?= e($user['name']) ?></b><small><?= $user['role'] === 'admin' ? 'Administrator' : 'Gate staff' ?></small></div>
      </div>
      <form method="post" action="<?= url('auth/logout') ?>"><?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm"><?= icon('logout', 16) ?>Sign out</button></form>
    </div>
  </aside>
  <main class="main"><div class="wrap">
    <?php if (demo()): ?><div class="proto-bar">Prototype with sample data. Do not enter real student information.</div><?php endif; ?>
<?php else: ?>
<main class="auth-wrap">
<?php endif; ?>
<?php foreach (get_flash() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?>" role="status"><?= icon($f['type'] === 'success' ? 'check' : 'alert', 20) ?><span><?= e($f['msg']) ?></span></div>
<?php endforeach; ?>
