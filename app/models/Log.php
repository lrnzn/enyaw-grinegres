<?php
class Log extends Model
{
    public static function record($studentId, $action, $guardianId = null, $person = null, $note = null, $staffId = null)
    {
        return self::insert(
            'INSERT INTO logs (student_id, action, guardian_id, person_name, note, staff_id, logged_at) VALUES (?,?,?,?,?,?,?)',
            [(int)$studentId, $action, $guardianId, nn($person), nn($note), $staffId, date('Y-m-d H:i:s')]
        );
    }

    /** Filters: from, to (Y-m-d), action, student_id. Newest first. */
    public static function query($f = [], $limit = null)
    {
        $sql = 'SELECT l.*, s.first_name, s.last_name, s.grade, s.section,
                       g.full_name AS guardian_name, u.name AS staff_name
                FROM logs l
                LEFT JOIN students s ON s.id = l.student_id
                LEFT JOIN guardians g ON g.id = l.guardian_id
                LEFT JOIN users u ON u.id = l.staff_id WHERE 1=1';
        $p = [];
        if (!empty($f['from']))       { $sql .= ' AND l.logged_at >= ?'; $p[] = $f['from'] . ' 00:00:00'; }
        if (!empty($f['to']))         { $sql .= ' AND l.logged_at <= ?'; $p[] = $f['to'] . ' 23:59:59'; }
        if (!empty($f['action']))     { $sql .= ' AND l.action = ?';     $p[] = $f['action']; }
        if (!empty($f['id']))         { $sql .= ' AND l.id = ?';         $p[] = (int)$f['id']; }
        if (!empty($f['student_id'])) { $sql .= ' AND l.student_id = ?'; $p[] = (int)$f['student_id']; }
        $sql .= ' ORDER BY l.id DESC';
        if ($limit) $sql .= ' LIMIT ' . (int)$limit;
        return self::all($sql, $p);
    }

    public static function find($id)
    {
        $r = self::query(['id' => $id], 1);
        return $r[0] ?? null;
    }

    /** student_id => [state, in, out] for today. state: on_campus | released | absent */
    public static function statusMap()
    {
        $d = date('Y-m-d');
        $rows = self::all('SELECT * FROM logs WHERE logged_at BETWEEN ? AND ? ORDER BY id ASC', ["$d 00:00:00", "$d 23:59:59"]);
        $m = [];
        foreach ($rows as $r) {
            $id = $r['student_id'];
            $m[$id] = $m[$id] ?? ['state' => 'absent', 'in' => null, 'out' => null];
            if ($r['action'] === 'TIME_IN') {
                $m[$id]['state'] = 'on_campus';
                $m[$id]['in'] = $m[$id]['in'] ?: $r['logged_at'];
                $m[$id]['out'] = null;
            } elseif (in_array($r['action'], ['RELEASED', 'RELEASED_OVERRIDE'], true)) {
                $m[$id]['state'] = 'released';
                $m[$id]['out'] = $r['logged_at'];
            }
        }
        return $m;
    }

    public static function statusOf($studentId)
    {
        return self::statusMap()[$studentId] ?? ['state' => 'absent', 'in' => null, 'out' => null];
    }

    public static function countToday($action)
    {
        $d = date('Y-m-d');
        return (int)self::one('SELECT COUNT(*) AS c FROM logs WHERE action = ? AND logged_at BETWEEN ? AND ?',
            [$action, "$d 00:00:00", "$d 23:59:59"])['c'];
    }
}
