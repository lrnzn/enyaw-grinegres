<?php
class Section extends Model
{
    public static function listAll()
    {
        return self::all(
            "SELECT sec.*, sec.section_id AS id, CONCAT_WS(' ', u.first_name, u.last_name) AS adviser_name,
                    (SELECT COUNT(*) FROM students st WHERE st.section_id = sec.section_id) AS student_count
             FROM sections sec LEFT JOIN teachers t ON t.teacher_id = sec.adviser_id LEFT JOIN users u ON u.user_id = t.user_id
             ORDER BY sec.school_year DESC, sec.grade_level, sec.section_name");
    }
    public static function find($id) { return self::one('SELECT *, section_id AS id FROM sections WHERE section_id = ?', [(int)$id]); }

    /** For drop-downs: id + label. */
    public static function options()
    {
        return self::all("SELECT section_id AS id, CONCAT(grade_level, ', ', section_name) AS label, school_year
                          FROM sections ORDER BY school_year DESC, grade_level, section_name");
    }

    /** Section advised by this user (teachers), or null. */
    public static function forUser($userId)
    {
        return self::one("SELECT sec.section_id AS id FROM sections sec JOIN teachers t ON t.teacher_id = sec.adviser_id
                          WHERE t.user_id = ? ORDER BY sec.school_year DESC LIMIT 1", [(int)$userId]);
    }

    public static function create($d)
    {
        return self::insert('INSERT INTO sections (adviser_id, section_name, grade_level, school_year) VALUES (?,?,?,?)',
            [nn($d['adviser_id']), $d['section_name'], $d['grade_level'], $d['school_year']]);
    }
    public static function update($id, $d)
    {
        self::run('UPDATE sections SET adviser_id=?, section_name=?, grade_level=?, school_year=? WHERE section_id=?',
            [nn($d['adviser_id']), $d['section_name'], $d['grade_level'], $d['school_year'], (int)$id]);
    }
    public static function delete($id) { self::run('DELETE FROM sections WHERE section_id = ?', [(int)$id]); }
}
