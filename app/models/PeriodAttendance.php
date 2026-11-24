<?php
class PeriodAttendance extends Model
{
    public const STATUSES = ['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'excused' => 'Excused', 'no_class' => 'No class'];

    /** Enrolled students of the class's section, with the saved period status and that day's gate/daily status. */
    public static function rows($scheduleId, $sectionId, $date)
    {
        $rows = self::all("SELECT s.student_id AS id, s.first_name, s.last_name, s.name_suffix, s.photo_path AS photo,
                                  pa.status, pa.remarks, a.status AS daily_status
                           FROM students s
                           LEFT JOIN period_attendance pa ON pa.student_id = s.student_id AND pa.schedule_id = ? AND pa.attendance_date = ?
                           LEFT JOIN attendance a ON a.student_id = s.student_id AND a.attendance_date = ?
                           WHERE s.status = 'enrolled' AND s.section_id = ?
                           ORDER BY s.last_name, s.first_name", [(int)$scheduleId, $date, $date, (int)$sectionId]);
        foreach ($rows as &$r) $r['full_name'] = compose_name($r);
        return $rows;
    }

    public static function set($scheduleId, $studentId, $date, $status, $remarks, $userId)
    {
        self::run('INSERT INTO period_attendance (schedule_id, student_id, attendance_date, status, remarks, recorded_by) VALUES (?,?,?,?,?,?)
                   ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks), recorded_by = VALUES(recorded_by)',
            [(int)$scheduleId, (int)$studentId, $date, $status, nn($remarks), $userId]);
    }

    /** Saved period records in a date range, for the Excel export. */
    public static function export($from, $to, $sectionId = null, $teacherId = null)
    {
        $sql = "SELECT pa.attendance_date AS date, sec.grade_level AS grade, sec.section_name AS section, sub.name AS subject,
                       p.name AS period, TIME_FORMAT(p.start_time, '%H:%i') AS start_time, TIME_FORMAT(p.end_time, '%H:%i') AS end_time,
                       CONCAT_WS(' ', tu.first_name, tu.last_name) AS teacher,
                       s.first_name, s.last_name, s.name_suffix, s.lrn, pa.status, pa.remarks
                FROM period_attendance pa
                JOIN class_schedules cs ON cs.schedule_id = pa.schedule_id
                JOIN periods p ON p.period_id = cs.period_id
                JOIN subjects sub ON sub.subject_id = cs.subject_id
                JOIN sections sec ON sec.section_id = cs.section_id
                JOIN teachers t ON t.teacher_id = cs.teacher_id
                JOIN users tu ON tu.user_id = t.user_id
                JOIN students s ON s.student_id = pa.student_id
                WHERE pa.attendance_date BETWEEN ? AND ?";
        $p = [$from, $to];
        if ($sectionId)       { $sql .= ' AND cs.section_id = ?'; $p[] = (int)$sectionId; }
        if ($teacherId)       { $sql .= ' AND cs.teacher_id = ?'; $p[] = (int)$teacherId; }
        return self::all($sql . ' ORDER BY pa.attendance_date, p.start_time, LENGTH(sec.grade_level), sec.grade_level, sec.section_name, s.last_name, s.first_name', $p);
    }
}
