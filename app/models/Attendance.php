<?php
class Attendance extends Model
{
    /** Enrolled students with their status on a date (optionally for one section). */
    public static function rows($date, $sectionId = null)
    {
        $sql = "SELECT s.student_id AS id, s.first_name, s.last_name, s.name_suffix, s.photo_path AS photo,
                       sec.grade_level AS grade, sec.section_name AS section, a.status, a.remarks
                FROM students s
                LEFT JOIN sections sec ON sec.section_id = s.section_id
                LEFT JOIN attendance a ON a.student_id = s.student_id AND a.attendance_date = ?
                WHERE s.status = 'enrolled'";
        $p = [$date];
        if ($sectionId) { $sql .= ' AND s.section_id = ?'; $p[] = (int)$sectionId; }
        $rows = self::all($sql . ' ORDER BY s.last_name, s.first_name', $p);
        foreach ($rows as &$r) $r['full_name'] = compose_name($r);
        return $rows;
    }

    /** Saved daily records in a date range, for the Excel export. */
    public static function export($from, $to, $sectionId = null)
    {
        $sql = "SELECT a.attendance_date AS date, sec.grade_level AS grade, sec.section_name AS section,
                       s.first_name, s.last_name, s.name_suffix, s.lrn, a.status, a.remarks
                FROM attendance a JOIN students s ON s.student_id = a.student_id LEFT JOIN sections sec ON sec.section_id = s.section_id
                WHERE a.attendance_date BETWEEN ? AND ?";
        $p = [$from, $to];
        if ($sectionId) { $sql .= ' AND s.section_id = ?'; $p[] = (int)$sectionId; }
        return self::all($sql . ' ORDER BY a.attendance_date, LENGTH(sec.grade_level), sec.grade_level, sec.section_name, s.last_name, s.first_name', $p);
    }

    public static function set($studentId, $date, $status, $remarks, $userId)
    {
        self::run('INSERT INTO attendance (student_id, recorded_by, attendance_date, status, remarks) VALUES (?,?,?,?,?)
                   ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks), recorded_by = VALUES(recorded_by)',
            [(int)$studentId, $userId, $date, $status, nn($remarks)]);
    }

    /** Called when a student is timed in at the gate. Keeps "late" and "excused" as they are. */
    public static function markPresent($studentId, $userId)
    {
        self::run("INSERT INTO attendance (student_id, recorded_by, attendance_date, status) VALUES (?,?,?, 'present')
                   ON DUPLICATE KEY UPDATE status = IF(status = 'absent', 'present', status)",
            [(int)$studentId, $userId, date('Y-m-d')]);
    }
}
