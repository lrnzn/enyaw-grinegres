<?php
class Student extends Model
{
    private static function decorate($r)
    {
        if ($r) $r['full_name'] = trim($r['first_name'] . ' ' . $r['last_name']);
        return $r;
    }

    public static function find($id)           { return self::decorate(self::one('SELECT * FROM students WHERE id = ?', [(int)$id])); }
    public static function findByToken($token) { return self::decorate(self::one('SELECT * FROM students WHERE qr_token = ?', [$token])); }
    public static function count()             { return (int)self::one('SELECT COUNT(*) AS c FROM students')['c']; }

    /** Every search word must match first name, last name, LRN or QR code. */
    public static function search($q = '')
    {
        $sql = 'SELECT * FROM students';
        $params = [];
        $terms = preg_split('/\s+/', trim($q), -1, PREG_SPLIT_NO_EMPTY);
        $where = [];
        foreach ($terms as $i => $t) {
            $where[] = "(first_name LIKE :a$i OR last_name LIKE :b$i OR lrn LIKE :c$i OR qr_token LIKE :d$i)";
            $like = '%' . addcslashes($t, '%_\\') . '%';
            foreach (['a', 'b', 'c', 'd'] as $k) $params[":$k$i"] = $like;
        }
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY last_name, first_name';
        return array_map([self::class, 'decorate'], self::all($sql, $params));
    }

    public static function newToken()
    {
        do { $t = 'STU-' . strtoupper(bin2hex(random_bytes(5))); }
        while (self::one('SELECT id FROM students WHERE qr_token = ?', [$t]));
        return $t;
    }

    public static function create($d)
    {
        return self::insert(
            'INSERT INTO students (lrn, first_name, last_name, grade, section, birthdate, address, photo, qr_token, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [nn($d['lrn']), $d['first_name'], $d['last_name'], $d['grade'], $d['section'], nn($d['birthdate']),
             nn($d['address']), $d['photo'] ?? null, self::newToken(), date('Y-m-d H:i:s')]
        );
    }

    public static function update($id, $d)
    {
        $sql = 'UPDATE students SET lrn=?, first_name=?, last_name=?, grade=?, section=?, birthdate=?, address=?';
        $p = [nn($d['lrn']), $d['first_name'], $d['last_name'], $d['grade'], $d['section'], nn($d['birthdate']), nn($d['address'])];
        if (!empty($d['photo'])) { $sql .= ', photo=?'; $p[] = $d['photo']; }
        $p[] = (int)$id;
        self::run($sql . ' WHERE id=?', $p);
    }

    public static function delete($id)
    {
        self::run('DELETE FROM student_guardians WHERE student_id = ?', [(int)$id]);
        self::run('DELETE FROM students WHERE id = ?', [(int)$id]);
    }

    /** Guardians registered for a student (with link info). */
    public static function guardians($id)
    {
        return self::all(
            'SELECT g.*, sg.id AS link_id, sg.relationship, sg.is_authorized
             FROM student_guardians sg JOIN guardians g ON g.id = sg.guardian_id
             WHERE sg.student_id = ? ORDER BY sg.is_authorized DESC, g.full_name',
            [(int)$id]
        );
    }

    public static function indexById()
    {
        $m = [];
        foreach (self::search() as $s) $m[$s['id']] = $s;
        return $m;
    }
}
