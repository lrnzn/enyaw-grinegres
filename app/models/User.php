<?php
class User extends Model
{
    public static function findByUsername($u) { return self::one('SELECT * FROM users WHERE username = ?', [$u]); }

    /** Used for supervisor approval of letter-based releases. */
    public static function verifyAnyAdmin($password)
    {
        foreach (self::all("SELECT password_hash FROM users WHERE role = 'admin'") as $a) {
            if (password_verify($password, $a['password_hash'])) return true;
        }
        return false;
    }

    /* ---- sign-in throttling (slows down password guessing) ---- */

    public static function isLockedOut($ip, $username)
    {
        $since = date('Y-m-d H:i:s', time() - 60 * (int)config('login_window_minutes'));
        $n = (int)self::one('SELECT COUNT(*) AS c FROM login_attempts WHERE ip = ? AND username = ? AND attempted_at >= ?',
            [$ip, $username, $since])['c'];
        return $n >= (int)config('login_max_attempts');
    }

    public static function recordFailure($ip, $username)
    {
        self::run('INSERT INTO login_attempts (ip, username, attempted_at) VALUES (?,?,?)', [$ip, $username, date('Y-m-d H:i:s')]);
        // keep the table small
        self::run('DELETE FROM login_attempts WHERE attempted_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }

    public static function clearFailures($ip, $username)
    {
        self::run('DELETE FROM login_attempts WHERE ip = ? AND username = ?', [$ip, $username]);
    }
}
