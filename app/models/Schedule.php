<?php
/** Class schedule: one subject, taught by one teacher to one section, on one weekday in one period. */
class Schedule extends Model
{
    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

    private const SEL = "SELECT cs.schedule_id AS id, cs.section_id, cs.subject_id, cs.teacher_id, cs.day_of_week, cs.period_id,
                                p.name AS period, TIME_FORMAT(p.start_time, '%H:%i') AS start_time, TIME_FORMAT(p.end_time, '%H:%i') AS end_time,
                                sub.name AS subject, sec.grade_level AS grade, sec.section_name AS section, sec.school_year,
                                CONCAT_WS(' ', u.first_name, u.last_name) AS teacher
                         FROM class_schedules cs
                         JOIN periods p ON p.period_id = cs.period_id
                         JOIN subjects sub ON sub.subject_id = cs.subject_id
                         JOIN sections sec ON sec.section_id = cs.section_id
                         JOIN teachers t ON t.teacher_id = cs.teacher_id
                         JOIN users u ON u.user_id = t.user_id ";

    public static function find($id) { return self::one(self::SEL . 'WHERE cs.schedule_id = ?', [(int)$id]); }

    public static function forSection($sectionId)
    {
        return self::all(self::SEL . 'WHERE cs.section_id = ? ORDER BY cs.day_of_week, p.start_time', [(int)$sectionId]);
    }

    /** Classes on a weekday (1 = Monday), optionally only one teacher's, in time order. */
    public static function forDay($weekday, $teacherId = null)
    {
        $sql = self::SEL . 'WHERE cs.day_of_week = ?';
        $p = [(int)$weekday];
        if ($teacherId !== null) { $sql .= ' AND cs.teacher_id = ?'; $p[] = (int)$teacherId; }
        return self::all($sql . ' ORDER BY p.start_time, sec.school_year DESC, LENGTH(sec.grade_level), sec.grade_level, sec.section_name', $p);
    }

    /** A message when the class would clash with another one. Periods that overlap in time count as a clash, even if they are different periods. */
    public static function conflict($sectionId, $teacherId, $day, $periodId)
    {
        $per = self::one('SELECT start_time, end_time FROM periods WHERE period_id = ?', [(int)$periodId]);
        if (!$per) return 'Choose a period.';
        $sql = 'SELECT 1 AS x FROM class_schedules cs JOIN periods p ON p.period_id = cs.period_id
                WHERE cs.day_of_week = ? AND p.start_time < ? AND p.end_time > ? AND ';
        $t = [(int)$day, $per['end_time'], $per['start_time']];
        if (self::one($sql . 'cs.section_id = ?', array_merge($t, [(int)$sectionId])))
            return 'That section already has a class at this time on that day.';
        if (self::one($sql . 'cs.teacher_id = ?', array_merge($t, [(int)$teacherId])))
            return 'That teacher already has a class at this time on that day (another section\'s period overlaps).';
        return null;
    }

    public static function create($sectionId, $subjectId, $teacherId, $periodId, $day)
    {
        return self::insert('INSERT INTO class_schedules (section_id, subject_id, teacher_id, period_id, day_of_week) VALUES (?,?,?,?,?)',
            [(int)$sectionId, (int)$subjectId, (int)$teacherId, (int)$periodId, (int)$day]);
    }

    public static function hasAttendance($id)
    {
        return (bool)self::one('SELECT 1 AS x FROM period_attendance WHERE schedule_id = ? LIMIT 1', [(int)$id]);
    }

    public static function sectionHasAttendance($sectionId)
    {
        return (bool)self::one('SELECT 1 AS x FROM period_attendance pa JOIN class_schedules cs ON cs.schedule_id = pa.schedule_id WHERE cs.section_id = ? LIMIT 1', [(int)$sectionId]);
    }

    public static function delete($id) { self::run('DELETE FROM class_schedules WHERE schedule_id = ?', [(int)$id]); }

    /** Label for drop-downs, e.g. "Period 2 (08:20-09:10) - Grade 7, Rizal - Math - Ms. Cruz". */
    public static function label(array $c, $withTeacher = true)
    {
        return "{$c['period']} ({$c['start_time']}-{$c['end_time']}) - {$c['grade']}, {$c['section']} - {$c['subject']}" . ($withTeacher ? " - {$c['teacher']}" : '');
    }
}
