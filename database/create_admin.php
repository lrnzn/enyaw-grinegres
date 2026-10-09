<?php
/**
 * Creates the SQL for a real account (for a live deployment).
 *
 *   php create_admin.php "Juan Dela Cruz" juan "a-Long-Password-Here"          (administrator)
 *   php create_admin.php "Maria Gate" maria "another-Long-Password" guard      (gate staff)
 *
 * Copy the printed INSERT statement and run it in phpMyAdmin (SQL tab).
 * The password is never stored in plain text — only its hash.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if ($argc < 4) { fwrite(STDERR, "Usage: php create_admin.php \"Full Name\" username password [admin|guard]\n"); exit(1); }
[$script, $name, $username, $password] = $argv;
$role = $argv[4] ?? 'admin';
if (!in_array($role, ['admin', 'guard'], true)) { fwrite(STDERR, "Role must be admin or guard.\n"); exit(1); }
if (strlen($password) < 10) { fwrite(STDERR, "Use a password of at least 10 characters.\n"); exit(1); }
$q = function ($s) { return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $s) . "'"; };
echo "INSERT INTO `users` (`name`, `username`, `password_hash`, `role`) VALUES ("
   . $q($name) . ', ' . $q($username) . ', ' . $q(password_hash($password, PASSWORD_DEFAULT)) . ', ' . $q($role) . ");\n";
