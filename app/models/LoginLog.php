<?php
class LoginLog extends Model
{
    public static function record($userId, $username, $success, $ip, $ua = '')
    {
        return self::insert(
            'INSERT INTO login_logs (user_id, username_tried, success, ip_address, user_agent, login_time) VALUES (?,?,?,?,?,?)',
            [$userId, substr($username, 0, 60), $success ? 1 : 0, $ip, substr((string)$ua, 0, 255) ?: null, date('Y-m-d H:i:s')]
        );
    }

    public static function markLogout($id)
    {
        self::run('UPDATE login_logs SET logout_time = ? WHERE login_log_id = ?', [date('Y-m-d H:i:s'), (int)$id]);
    }

    /** Too many failed tries (since the last successful sign-in) within the window. */
    public static function isLockedOut($ip, $username)
    {
        $since = date('Y-m-d H:i:s', time() - 60 * (int)config('login_window_minutes'));
        $row = self::one(
            "SELECT COUNT(*) AS c FROM login_logs
             WHERE ip_address = ? AND username_tried = ? AND success = 0 AND login_time >= ?
               AND login_time > COALESCE((SELECT MAX(login_time) FROM login_logs WHERE ip_address = ? AND username_tried = ? AND success = 1), '1970-01-01 00:00:00')",
            [$ip, substr($username, 0, 60), $since, $ip, substr($username, 0, 60)]
        );
        return (int)$row['c'] >= (int)config('login_max_attempts');
    }
}
