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

    /** Run $fn inside a transaction; roll back if it throws. */
    public static function transaction(callable $fn)
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $r = $fn();
            $pdo->commit();
            return $r;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Demo helper: restore this morning's sample time-ins (demo mode only). */
    public static function seedLogs()
    {
        $pdo = self::pdo();
        foreach ($pdo->query('SELECT letter_path FROM guardian_confirmations WHERE letter_path IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN) as $f) remove_upload($f);
        $staff = $pdo->query("SELECT user_id FROM users WHERE username = 'guard'")->fetchColumn() ?: null;
        $pdo->beginTransaction();
        $pdo->exec('DELETE FROM gate_logs');
        $pdo->exec('DELETE FROM guardian_confirmations');
        $pdo->exec('DELETE FROM collectors');
        $pdo->exec('DELETE FROM attendance');
        $d = date('Y-m-d');
        $times = [1 => '07:12:00', 2 => '07:25:00', 3 => '07:31:00', 4 => '07:31:00', 5 => '07:48:00'];
        $name = $pdo->prepare("SELECT TRIM(CONCAT_WS(' ', first_name, last_name)) FROM students WHERE student_id = ?");
        $log  = $pdo->prepare("INSERT INTO gate_logs (student_id, action, recorded_by, student_name, logged_at) VALUES (?, 'TIME_IN', ?, ?, ?)");
        $att  = $pdo->prepare("INSERT INTO attendance (student_id, recorded_by, attendance_date, status) VALUES (?, ?, ?, 'present')");
        foreach ($times as $sid => $t) {
            $name->execute([$sid]);
            $n = $name->fetchColumn();
            if (!$n) continue;
            $log->execute([$sid, $staff, $n, "$d $t"]);
            $att->execute([$sid, $staff, $d]);
        }
        $pdo->commit();
    }
}
