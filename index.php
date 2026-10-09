<?php
// Front controller: every request enters here.
define('ROOT', __DIR__);

$config = require ROOT . '/config/config.php';
if (is_file(ROOT . '/config/config.local.php')) {
    $config = array_replace_recursive($config, require ROOT . '/config/config.local.php');
}
$GLOBALS['config'] = $config;
date_default_timezone_set($config['timezone']);

// Errors: detailed while developing, hidden (but logged) in production.
ini_set('display_errors', $config['debug'] ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Sessions: HttpOnly + SameSite, and Secure when served over HTTPS.
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
session_start();

// Basic security headers.
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
if ($https) header('Strict-Transport-Security: max-age=31536000');

require ROOT . '/app/core/helpers.php';

spl_autoload_register(function ($class) {
    foreach (['app/core', 'app/models', 'app/controllers'] as $dir) {
        $file = ROOT . "/$dir/$class.php";
        if (is_file($file)) { require $file; return; }
    }
});

new App();
