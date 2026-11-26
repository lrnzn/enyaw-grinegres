<?php
/** Date ranges, grade-level summaries and sheet data for the reports. Read-only. */
class Report extends Model
{
    public static function week($date)
    {
        $ts = strtotime($date);
        $mon = strtotime('-' . ((int)date('N', $ts) - 1) . ' days', $ts);
        return [date('Y-m-d', $mon), date('Y-m-d', strtotime('+6 days', $mon))];
    }

    private static function validDate($d)
    {
        return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) ? $d : null;
    }

    /**
     * Turns ?range=today|week|month|custom (&date=anchor, &from, &to) into [from, to, key].
     * Teachers only see the current week; the administrator can see any week, month or range.
     */
    public static function range(array $in, $teacherOnly)
    {
        $today = date('Y-m-d');
        $key = $in['range'] ?? 'today';
        if (!in_array($key, ['today', 'week', 'month', 'custom'], true)) $key = 'today';
        if ($teacherOnly) return $key === 'today' ? [$today, $today, 'today'] : array_merge(self::week($today), ['week']);

        $anchor = self::validDate($in['date'] ?? null) ?: $today;
        if ($key === 'week')  return array_merge(self::week($anchor), ['week']);
        if ($key === 'month') return [date('Y-m-01', strtotime($anchor)), date('Y-m-t', strtotime($anchor)), 'month'];
        if ($key === 'custom') {
            $from = self::validDate($in['from'] ?? null) ?: $today;
            $to = self::validDate($in['to'] ?? null) ?: $from;
            if ($from > $to) [$from, $to] = [$to, $from];
            if (strtotime($to) - strtotime($from) > 366 * 86400) $to = date('Y-m-d', strtotime($from . ' +366 days'));
            return [$from, $to, 'custom'];
        }
        return [$today, $today, 'today'];
    }

    public static function grades()
    {
        $mine = Section::mineIds();
        if ($mine !== null && !$mine) return [];
        $have = array_column(self::all('SELECT DISTINCT grade_level FROM sections' . ($mine !== null ? ' WHERE section_id IN (' . implode(',', $mine) . ')' : '')), 'grade_level');
        usort($have, function ($a, $b) {
            $i = array_search($a, Section::GRADES, true); $j = array_search($b, Section::GRADES, true);
            return ($i === false ? 99 : $i) <=> ($j === false ? 99 : $j) ?: strcmp($a, $b);
        });
        return $have;
    }

    /**
     * Counts per grade level (or per section) in a range.
     * $source: 'daily' (gate/daily attendance) or 'period' (per-class records; "No class" is left out).
     */
    public static function summary($from, $to, $grade, $sectionId, $by, $source)
    {
        $p = [$from, $to];
        if ($source === 'period') {
            $sql = 'SELECT sec.section_id, sec.grade_level, sec.section_name, pa.status, COUNT(*) AS c
                    FROM period_attendance pa JOIN class_schedules cs ON cs.schedule_id = pa.schedule_id
                    JOIN sections sec ON sec.section_id = cs.section_id
                    WHERE pa.attendance_date BETWEEN ? AND ? AND pa.status <> \'no_class\'';
        } else {
            $sql = 'SELECT sec.section_id, sec.grade_level, sec.section_name, a.status, COUNT(*) AS c
                    FROM attendance a JOIN students s ON s.student_id = a.student_id
                    JOIN sections sec ON sec.section_id = s.section_id
                    WHERE a.attendance_date BETWEEN ? AND ?';
        }
        $mine = $source === 'period' ? Section::mineIds() : Section::advisoryIds();   // teachers: whole-day figures only for the section they advise
        if ($mine !== null) $sql .= $mine ? ' AND sec.section_id IN (' . implode(',', $mine) . ')' : ' AND 1=0';
        if ($grade)     { $sql .= ' AND sec.grade_level = ?'; $p[] = $grade; }
        if ($sectionId) { $sql .= ' AND sec.section_id = ?';  $p[] = (int)$sectionId; }
        $sql .= ' GROUP BY sec.section_id, sec.grade_level, sec.section_name, ' . ($source === 'period' ? 'pa.status' : 'a.status');

        $out = [];
        foreach (self::all($sql, $p) as $r) {
            $k = $by === 'section' ? 's' . $r['section_id'] : 'g' . $r['grade_level'];
            $label = $by === 'section' ? $r['grade_level'] . ', ' . $r['section_name'] : $r['grade_level'];
            $out[$k] = $out[$k] ?? ['label' => $label, 'grade' => $r['grade_level'], 'present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0, 'total' => 0];
            if (isset($out[$k][$r['status']])) $out[$k][$r['status']] += (int)$r['c'];
            $out[$k]['total'] += (int)$r['c'];
        }
        uasort($out, function ($a, $b) {
            $i = array_search($a['grade'], Section::GRADES, true); $j = array_search($b['grade'], Section::GRADES, true);
            return ($i === false ? 99 : $i) <=> ($j === false ? 99 : $j) ?: strcmp($a['label'], $b['label']);
        });
        foreach ($out as &$r) $r['rate'] = $r['total'] ? round(($r['present'] + $r['late']) / $r['total'] * 100) : 0;
        return array_values($out);
    }

    /** Section with its adviser, for the sheet header. */
    public static function sectionInfo($sectionId)
    {
        return self::one("SELECT sec.*, sec.section_id AS id, CONCAT_WS(' ', u.first_name, u.last_name) AS adviser_name
                          FROM sections sec LEFT JOIN teachers t ON t.teacher_id = sec.adviser_id LEFT JOIN users u ON u.user_id = t.user_id
                          WHERE sec.section_id = ?", [(int)$sectionId]);
    }

    /** School days (Mon-Fri, plus Saturday when it has a record) between two dates. */
    private static function days($from, $to, array $withRecords, ?array $weekdays = null)
    {
        $days = [];
        for ($t = strtotime($from); $t <= strtotime($to); $t = strtotime('+1 day', $t)) {
            $d = date('Y-m-d', $t); $n = (int)date('N', $t);
            $ok = $weekdays !== null ? in_array($n, $weekdays, true) : ($n <= 5 || ($n === 6 && isset($withRecords[$d])));
            if ($ok) $days[] = $d;
        }
        return $days;
    }

    private static function pick($a, $b)
    {
        $rank = ['absent' => 4, 'late' => 3, 'excused' => 2, 'present' => 1, 'no_class' => 0];
        return !$a || ($rank[$b] ?? 0) > ($rank[$a] ?? 0) ? $b : $a;
    }

    /** Whole-day sheet for one section: [students, days, marks[student][date] => status]. */
    public static function dailySheet($sectionId, $from, $to)
    {
        $students = self::all("SELECT student_id AS id, first_name, last_name, name_suffix, lrn FROM students
                               WHERE section_id = ? AND status = 'enrolled' ORDER BY last_name, first_name", [(int)$sectionId]);
        $marks = $seen = [];
        foreach (self::all('SELECT a.student_id, a.attendance_date AS d, a.status FROM attendance a JOIN students s ON s.student_id = a.student_id
                            WHERE s.section_id = ? AND a.attendance_date BETWEEN ? AND ?', [(int)$sectionId, $from, $to]) as $r) {
            $marks[$r['student_id']][$r['d']] = $r['status'];
            $seen[$r['d']] = true;
        }
        foreach ($students as &$s) $s['full_name'] = compose_name($s);
        return ['students' => $students, 'days' => self::days($from, $to, $seen), 'marks' => $marks];
    }

    /** One subject of one section, across all its periods in the range. */
    public static function periodSheet($sectionId, $subjectId, $from, $to, $teacherId = null)
    {
        $sql = 'SELECT cs.schedule_id, cs.day_of_week FROM class_schedules cs WHERE cs.section_id = ? AND cs.subject_id = ?';
        $p = [(int)$sectionId, (int)$subjectId];
        if ($teacherId) { $sql .= ' AND cs.teacher_id = ?'; $p[] = (int)$teacherId; }
        $sched = self::all($sql, $p);
        if (!$sched) return null;
        $ids = array_map('intval', array_column($sched, 'schedule_id'));
        $weekdays = array_values(array_unique(array_map('intval', array_column($sched, 'day_of_week'))));

        $students = self::all("SELECT student_id AS id, first_name, last_name, name_suffix, lrn FROM students
                               WHERE section_id = ? AND status = 'enrolled' ORDER BY last_name, first_name", [(int)$sectionId]);
        $marks = [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        foreach (self::all("SELECT student_id, attendance_date AS d, status FROM period_attendance
                            WHERE schedule_id IN ($in) AND attendance_date BETWEEN ? AND ?", array_merge($ids, [$from, $to])) as $r) {
            $marks[$r['student_id']][$r['d']] = self::pick($marks[$r['student_id']][$r['d']] ?? null, $r['status']);
        }
        foreach ($students as &$s) $s['full_name'] = compose_name($s);
        return ['students' => $students, 'days' => self::days($from, $to, [], $weekdays), 'marks' => $marks];
    }

    /** Subjects a section has on its timetable (optionally only one teacher's), for the sheet picker. */
    public static function subjectsOf($sectionId, $teacherId = null)
    {
        $sql = 'SELECT DISTINCT sub.subject_id AS id, sub.name FROM class_schedules cs JOIN subjects sub ON sub.subject_id = cs.subject_id WHERE cs.section_id = ?';
        $p = [(int)$sectionId];
        if ($teacherId) { $sql .= ' AND cs.teacher_id = ?'; $p[] = (int)$teacherId; }
        return self::all($sql . ' ORDER BY sub.name', $p);
    }
}
