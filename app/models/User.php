<?php
class User extends Model
{
    private static function decorate($r)
    {
        if ($r) { $r['id'] = (int)$r['user_id']; $r['name'] = compose_name($r); }
        return $r;
    }

    public static function find($id)           { return self::decorate(self::one('SELECT * FROM users WHERE user_id = ?', [(int)$id])); }
    public static function findByUsername($u)  { return self::decorate(self::one('SELECT * FROM users WHERE username = ?', [$u])); }

    public static function listAll()
    {
        return array_map([self::class, 'decorate'], self::all(
            "SELECT u.*, t.teacher_id, t.contact_number,
                    (SELECT MAX(login_time) FROM login_logs ll WHERE ll.user_id = u.user_id AND ll.success = 1) AS last_login
             FROM users u LEFT JOIN teachers t ON t.user_id = u.user_id
             ORDER BY FIELD(u.role,'admin','teacher','gate_staff'), u.last_name, u.first_name"
        ));
    }

    public static function create($d)
    {
        return self::insert(
            'INSERT INTO users (username, email, password_hash, first_name, middle_name, last_name, name_suffix, role, status)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [$d['username'], nn($d['email']), password_hash($d['password'], PASSWORD_DEFAULT), $d['first_name'],
             nn($d['middle_name']), $d['last_name'], nn($d['name_suffix']), $d['role'], 'active']
        );
    }

    public static function update($id, $d)
    {
        self::run('UPDATE users SET username=?, email=?, first_name=?, middle_name=?, last_name=?, name_suffix=?, role=?, status=? WHERE user_id=?',
            [$d['username'], nn($d['email']), $d['first_name'], nn($d['middle_name']), $d['last_name'], nn($d['name_suffix']),
             $d['role'], $d['status'], (int)$id]);
    }

    public static function setPassword($id, $password)
    {
        self::run('UPDATE users SET password_hash = ? WHERE user_id = ?', [password_hash($password, PASSWORD_DEFAULT), (int)$id]);
    }

    public static function activeAdminCount()
    {
        return (int)self::one("SELECT COUNT(*) AS c FROM users WHERE role = 'admin' AND status = 'active'")['c'];
    }

    /**
     * Supervisor approval: the approver types their OWN username and password.
     * Wrong attempts are logged and locked out like normal sign-ins.
     * Returns the approver's row, or null when the details are wrong. Throws GateBlocked when locked out.
     */
    public static function verifyApprover($username, $password, array $roles = ['admin'])
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = substr('approval: ' . ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        if (LoginLog::isLockedOut($ip, $username)) {
            throw new GateBlocked('Too many failed attempts. Please wait ' . (int)config('login_window_minutes') . ' minutes.');
        }
        $u = self::findByUsername($username);
        $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        if (password_verify($password, $hash) && $u && in_array($u['role'], $roles, true) && $u['status'] === 'active') return $u;
        LoginLog::record($u['user_id'] ?? null, $username, 0, $ip, $ua);
        return null;
    }
}
