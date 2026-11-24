<?php
class Section extends Model
{
    public const GRADES = ['Kinder', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10'];

    /** School year that is running now, e.g. 2026-2027 (a new year starts in June). */
    public static function currentYear()
    {
        $y = (int)date('Y');
        return date('n') >= 6 ? $y . '-' . ($y + 1) : ($y - 1) . '-' . $y;
    }

    public static function findByKey($grade, $name, $year)
    {
        return self::one('SELECT *, section_id AS id FROM sections WHERE grade_level = ? AND section_name = ? AND school_year = ?', [$grade, $name, $year]);
    }

    public static function listAll()
    {
        return self::all(
            "SELECT sec.*, sec.section_id AS id, CONCAT_WS(' ', u.first_name, u.last_name) AS adviser_name,
                    (SELECT COUNT(*) FROM students st WHERE st.section_id = sec.section_id) AS student_count
             FROM sections sec LEFT JOIN teachers t ON t.teacher_id = sec.adviser_id LEFT JOIN users u ON u.user_id = t.user_id
             ORDER BY sec.school_year DESC, LENGTH(sec.grade_level), sec.grade_level, sec.section_name");
    }
    public static function find($id) { return self::one('SELECT *, section_id AS id FROM sections WHERE section_id = ?', [(int)$id]); }

    /**
     * Section ids the signed-in teacher handles (advisory section or any class on their timetable).
     * Returns null for everyone else, meaning "no restriction".
     */
    public static function mineIds()
    {
        if (!is_teacher()) return null;
        static $cache = [];
        $uid = (int)current_user()['id'];
        if (!isset($cache[$uid])) {
            $t = (int)(Teacher::findByUser($uid)['teacher_id'] ?? 0);
            $cache[$uid] = array_map('intval', array_column(self::all(
                'SELECT section_id FROM sections WHERE adviser_id = ? UNION SELECT section_id FROM class_schedules WHERE teacher_id = ?', [$t, $t]), 'section_id'));
        }
        return $cache[$uid];
    }

    /** Sections the signed-in teacher advises (they alone take whole-day attendance). Null for everyone else. */
    public static function advisoryIds()
    {
        if (!is_teacher()) return null;
        $t = (int)(Teacher::findByUser((int)current_user()['id'])['teacher_id'] ?? 0);
        return array_map('intval', array_column(self::all('SELECT section_id FROM sections WHERE adviser_id = ?', [$t]), 'section_id'));
    }

    public static function canSeeDaily($sectionId)
    {
        $a = self::advisoryIds();
        return $a === null || in_array((int)$sectionId, $a, true);
    }

    /** Whole-day attendance is available to the administrator and to teachers who advise a section. */
    public static function hasDaily()
    {
        $a = self::advisoryIds();
        return $a === null || (bool)$a;
    }

    /** Drop-down options limited to the sections the teacher advises (everything for the administrator). */
    public static function dailyOptions()
    {
        $a = self::advisoryIds();
        if ($a === null) return self::options();
        return array_values(array_filter(self::options(), function ($o) use ($a) { return in_array((int)$o['id'], $a, true); }));
    }

    public static function canSee($sectionId)
    {
        $mine = self::mineIds();
        return $mine === null || in_array((int)$sectionId, $mine, true);
    }

    /** For drop-downs: id + label. Teachers only get the sections they handle. */
    public static function options()
    {
        $mine = self::mineIds();
        if ($mine !== null && !$mine) return [];
        $where = $mine !== null ? ' WHERE section_id IN (' . implode(',', $mine) . ')' : '';
        return self::all("SELECT section_id AS id, CONCAT(grade_level, ', ', section_name) AS label, school_year
                          FROM sections$where ORDER BY school_year DESC, LENGTH(grade_level), grade_level, section_name");
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
