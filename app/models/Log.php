<?php
/** gate_logs: every time-in, release and denial. Rows are only ever added. */
class Log extends Model
{
    private const SEL = "SELECT l.*, l.log_id AS id,
                CONCAT_WS(' ', u.first_name, u.last_name) AS staff_name,
                sec.grade_level AS grade, sec.section_name AS section,
                gc.letter_path, gc.relationship AS collector_relationship, gc.phone_call_status,
                CONCAT_WS(' ', ap.first_name, ap.last_name) AS approver_name
            FROM gate_logs l
            LEFT JOIN users u    ON u.user_id = l.recorded_by
            LEFT JOIN students s ON s.student_id = l.student_id
            LEFT JOIN sections sec ON sec.section_id = s.section_id
            LEFT JOIN guardian_confirmations gc ON gc.confirmation_id = l.confirmation_id
            LEFT JOIN users ap   ON ap.user_id = gc.approved_by";

    public static function record($studentId, $action, $guardianId = null, $releasedTo = null, $note = null, $staffId = null, $confirmationId = null)
    {
        $s = Student::find($studentId);
        return self::insert(
            'INSERT INTO gate_logs (student_id, guardian_id, confirmation_id, recorded_by, action, student_name, released_to_name, note, logged_at)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [(int)$studentId, $guardianId, $confirmationId, $staffId, $action, $s ? $s['full_name'] : '(unknown student)',
             nn($releasedTo), nn($note), date('Y-m-d H:i:s')]
        );
    }

    /** Lock the student's row so two simultaneous scans cannot both succeed. Call inside a transaction. */
    public static function lock($studentId)
    {
        self::run('SELECT student_id FROM students WHERE student_id = ? FOR UPDATE', [(int)$studentId]);
    }

    /** Filters: from, to (Y-m-d), action, student_id, id. Newest first. */
    public static function query($f = [], $limit = null)
    {
        $sql = self::SEL . ' WHERE 1=1';
        $p = [];
        if (!empty($f['from']))       { $sql .= ' AND l.logged_at >= ?'; $p[] = $f['from'] . ' 00:00:00'; }
        if (!empty($f['to']))         { $sql .= ' AND l.logged_at <= ?'; $p[] = $f['to'] . ' 23:59:59'; }
        if (!empty($f['action']))     { $sql .= ' AND l.action = ?';     $p[] = $f['action']; }
        if (!empty($f['id']))         { $sql .= ' AND l.log_id = ?';     $p[] = (int)$f['id']; }
        if (!empty($f['student_id'])) { $sql .= ' AND l.student_id = ?'; $p[] = (int)$f['student_id']; }
        $sql .= ' ORDER BY l.log_id DESC';
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
        $rows = self::all('SELECT student_id, action, logged_at FROM gate_logs WHERE student_id IS NOT NULL AND logged_at BETWEEN ? AND ? ORDER BY log_id ASC',
            ["$d 00:00:00", "$d 23:59:59"]);
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
        return (int)self::one('SELECT COUNT(*) AS c FROM gate_logs WHERE action = ? AND logged_at BETWEEN ? AND ?',
            [$action, "$d 00:00:00", "$d 23:59:59"])['c'];
    }
}
