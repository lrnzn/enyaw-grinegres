<?php
/**
 * MySQL connection (PDO). The tables are created by importing database/schema.sql
 * (phpMyAdmin → Import). The app itself never creates or alters tables.
 */
class Database
{
    private static $pdo = null;

    public static function pdo()
    {
        if (self::$pdo) return self::$pdo;
        $c = config('db');
        $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
        self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   // real prepared statements
        ]);
        return self::$pdo;
    }

    /** Demo helper: restore this morning's sample time-ins (demo mode only). */
    public static function seedLogs()
    {
        $pdo = self::pdo();
        $staff = $pdo->query("SELECT id FROM users WHERE username = 'guard'")->fetchColumn() ?: null;
        $pdo->exec('DELETE FROM logs');
        $ins = $pdo->prepare("INSERT INTO logs (student_id, action, staff_id, logged_at) VALUES (?, 'TIME_IN', ?, ?)");
        $d = date('Y-m-d');
        $times = [1 => '07:12:00', 2 => '07:25:00', 3 => '07:31:00', 4 => '07:31:00', 5 => '07:48:00'];
        foreach ($times as $sid => $t) $ins->execute([$sid, $staff, "$d $t"]);
    }
}
