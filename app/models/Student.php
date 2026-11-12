<?php
class Student extends Model
{
    // photo, grade, section and birthdate are aliases so the views stay simple
    private const SEL = "SELECT s.*, s.student_id AS id, s.photo_path AS photo, s.date_of_birth AS birthdate,
                                sec.section_name AS section, sec.grade_level AS grade
                         FROM students s LEFT JOIN sections sec ON sec.section_id = s.section_id";

    private static function decorate($r)
    {
        if ($r) $r['full_name'] = compose_name($r);
        return $r;
    }

    public static function find($id)           { return self::decorate(self::one(self::SEL . ' WHERE s.student_id = ?', [(int)$id])); }
    public static function findByToken($token) { return self::decorate(self::one(self::SEL . ' WHERE s.qr_token = ?', [$token])); }
    public static function count()             { return (int)self::one("SELECT COUNT(*) AS c FROM students WHERE status = 'enrolled'")['c']; }

    /** Every search word must match first name, last name, LRN or QR code. */
    public static function search($q = '', $enrolledOnly = false)
    {
        $sql = self::SEL;
        $params = [];
        $where = [];
        if ($enrolledOnly) $where[] = "s.status = 'enrolled'";
        $terms = preg_split('/\s+/', trim($q), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($terms as $i => $t) {
            $where[] = "(s.first_name LIKE :a$i OR s.last_name LIKE :b$i OR s.lrn LIKE :c$i OR s.qr_token LIKE :d$i)";
            $like = '%' . addcslashes($t, '%_\\') . '%';
            foreach (['a', 'b', 'c', 'd'] as $k) $params[":$k$i"] = $like;
        }
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY s.last_name, s.first_name';
        return array_map([self::class, 'decorate'], self::all($sql, $params));
    }

    public static function newToken()
    {
        do { $t = 'STU-' . strtoupper(bin2hex(random_bytes(5))); }
        while (self::one('SELECT student_id FROM students WHERE qr_token = ?', [$t]));
        return $t;
    }

    public static function create($d)
    {
        return self::insert(
            'INSERT INTO students (section_id, lrn, qr_token, first_name, middle_name, last_name, name_suffix, gender, date_of_birth, address, photo_path, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [nn($d['section_id']), nn($d['lrn']), self::newToken(), $d['first_name'], nn($d['middle_name']), $d['last_name'],
             nn($d['name_suffix']), nn($d['gender']), nn($d['date_of_birth']), nn($d['address']), $d['photo'] ?? null, $d['status'] ?? 'enrolled']
        );
    }

    public static function update($id, $d)
    {
        $sql = 'UPDATE students SET section_id=?, lrn=?, first_name=?, middle_name=?, last_name=?, name_suffix=?, gender=?, date_of_birth=?, address=?, status=?';
        $p = [nn($d['section_id']), nn($d['lrn']), $d['first_name'], nn($d['middle_name']), $d['last_name'], nn($d['name_suffix']),
              nn($d['gender']), nn($d['date_of_birth']), nn($d['address']), $d['status']];
        if (!empty($d['photo'])) { $sql .= ', photo_path=?'; $p[] = $d['photo']; }
        $p[] = (int)$id;
        self::run($sql . ' WHERE student_id=?', $p);
    }

    /** Links, attendance and confirmations go with the student; gate history stays (names are copied into each row). */
    public static function delete($id) { self::run('DELETE FROM students WHERE student_id = ?', [(int)$id]); }

    /** Guardians registered for a student (with link info). */
    public static function guardians($id)
    {
        $rows = self::all(
            'SELECT g.*, g.guardian_id AS id, g.contact_number AS contact, g.photo_path AS photo,
                    sg.student_guardian_id AS link_id, sg.relationship, sg.is_authorized, sg.receive_notifications
             FROM student_guardians sg JOIN guardians g ON g.guardian_id = sg.guardian_id
             WHERE sg.student_id = ? ORDER BY sg.is_authorized DESC, g.last_name, g.first_name',
            [(int)$id]
        );
        foreach ($rows as &$r) $r['full_name'] = compose_name($r);
        return $rows;
    }

    /** Guardians who should get messages: authorized and opted in. */
    public static function notifyRecipients($id)
    {
        $rows = self::all(
            'SELECT g.guardian_id AS id, g.first_name, g.last_name, g.name_suffix, g.contact_number
             FROM student_guardians sg JOIN guardians g ON g.guardian_id = sg.guardian_id
             WHERE sg.student_id = ? AND sg.is_authorized = 1 AND sg.receive_notifications = 1 ORDER BY g.last_name, g.first_name',
            [(int)$id]);
        foreach ($rows as &$r) $r['full_name'] = compose_name($r);
        return $rows;
    }

    public static function indexById()
    {
        $m = [];
        foreach (self::search('', true) as $s) $m[$s['id']] = $s;
        return $m;
    }
}
