<?php
/**
 * Creates the SQL for a real account (for a live deployment).
 *
 *   php create_admin.php "Juan" "Dela Cruz" juan "a-Long-Password-Here"            (administrator)
 *   php create_admin.php "Maria" "Gate" maria "another-Long-Password" gate_staff   (gate staff)
 *   php create_admin.php "Ana" "Lopez" ana "yet-Another-Long-Pass" teacher         (teacher)
 *
 * Copy the printed INSERT statement and run it in phpMyAdmin (SQL tab).
 * The password is never stored in plain text; only its hash is.
 * (Once an administrator exists, add other staff from the Staff accounts page instead.)
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if ($argc < 5) { fwrite(STDERR, "Usage: php create_admin.php \"First name\" \"Last name\" username password [admin|teacher|gate_staff]\n"); exit(1); }
[$script, $first, $last, $username, $password] = $argv;
$role = $argv[5] ?? 'admin';
if (!in_array($role, ['admin', 'teacher', 'gate_staff'], true)) { fwrite(STDERR, "Role must be admin, teacher or gate_staff.\n"); exit(1); }
if (strlen($password) < 10) { fwrite(STDERR, "Use a password of at least 10 characters.\n"); exit(1); }
if (!preg_match('/^[a-z0-9._-]{3,60}$/', $username)) { fwrite(STDERR, "Username: 3-60 lowercase letters, numbers, dots, dashes or underscores.\n"); exit(1); }
$q = function ($s) { return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $s) . "'"; };
echo "INSERT INTO `users` (`first_name`, `last_name`, `username`, `password_hash`, `role`) VALUES ("
   . $q($first) . ', ' . $q($last) . ', ' . $q($username) . ', ' . $q(password_hash($password, PASSWORD_DEFAULT)) . ', ' . $q($role) . ");\n";
