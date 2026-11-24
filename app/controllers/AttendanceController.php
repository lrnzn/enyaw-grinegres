<?php
class AttendanceController extends Controller
{
    private const STATUSES = ['present', 'absent', 'late', 'excused'];   // daily (gate) attendance

    /** Monday and Sunday of the week that contains $date. */
    private function week($date)
    {
        $ts = strtotime($date);
        $mon = strtotime('-' . ((int)date('N', $ts) - 1) . ' days', $ts);
        return [date('Y-m-d', $mon), date('Y-m-d', strtotime('+6 days', $mon))];
    }

    private function validDate($d, $default)
    {
        return (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d)) ? $d : $default;
    }

    /** Teachers can work on today and earlier days of the current week; the administrator on any date. */
    private function limits()
    {
        if (!is_teacher()) return [null, null];
        return [$this->week(date('Y-m-d'))[0], date('Y-m-d')];
    }

    private function clamp($date, $min, $max)
    {
        if ($min && $date < $min) return $min;
        if ($max && $date > $max) return $max;
        return $date;
    }

    private function teacherId()
    {
        return is_teacher() ? (int)(Teacher::findByUser(current_user()['id'])['teacher_id'] ?? 0) : null;
    }

    /* ---------- Attendance per class period ---------- */

    public function index()
    {
        $this->requireRole(['teacher']);
        [$min, $max] = $this->limits();
        $date = $this->clamp($this->validDate($_GET['date'] ?? null, date('Y-m-d')), $min, $max);
        $classes = Schedule::forDay((int)date('N', strtotime($date)), $this->teacherId());

        $current = null;
        $wanted = (int)($_GET['class'] ?? 0);
        foreach ($classes as $c) if ((int)$c['id'] === $wanted) $current = $c;
        if (!$current && $classes) $current = $this->autoClass($classes, $date);   // "Show class" happens by itself

        [$weekFrom, $weekTo] = $this->week($date);
        $this->view('attendance/index', [
            'title' => 'Attendance', 'active' => 'attendance', 'date' => $date, 'min' => $min, 'max' => $max,
            'classes' => $classes, 'current' => $current,
            'rows' => $current ? PeriodAttendance::rows($current['id'], $current['section_id'], $date) : [],
            'weekFrom' => $weekFrom, 'weekTo' => $max && $weekTo > $max ? $max : $weekTo,
            'sections' => Section::options(), 'hasSchedule' => Period::count() > 0,
        ]);
    }

    /** Today: the class happening now, else the next one, else the last one of the day. Other days: the first class. */
    private function autoClass(array $classes, $date)
    {
        if ($date === date('Y-m-d')) {
            $now = date('H:i');
            foreach ($classes as $c) if ($c['start_time'] <= $now && $now < $c['end_time']) return $c;
            foreach ($classes as $c) if ($c['start_time'] > $now) return $c;
            return end($classes);
        }
        return $classes[0];
    }

    public function save()
    {
        $this->post();
        $this->requireRole(['teacher']);
        [$min, $max] = $this->limits();
        $asked = $this->validDate($_POST['date'] ?? null, date('Y-m-d'));
        $date = $this->clamp($asked, $min, $max);
        $cls = Schedule::find((int)($_POST['class'] ?? 0));
        $back = url('attendance') . '&date=' . urlencode($date) . '&class=' . (int)($_POST['class'] ?? 0);
        if ($date !== $asked) { flash('error', 'Teachers can only record today and earlier days of this week.'); $this->redirect($back); }

        $teacherId = $this->teacherId();
        if (!$cls || ($teacherId !== null && (int)$cls['teacher_id'] !== $teacherId)) { flash('error', 'You can only mark attendance for your own classes.'); $this->redirect(url('attendance')); }
        if ((int)$cls['day_of_week'] !== (int)date('N', strtotime($date))) { flash('error', 'That class is not scheduled on this day.'); $this->redirect($back); }

        $allowed = array_column(PeriodAttendance::rows($cls['id'], $cls['section_id'], $date), 'id');
        $statuses = $_POST['status'] ?? [];
        $remarks  = $_POST['remarks'] ?? [];
        $saved = 0;
        foreach ($statuses as $sid => $status) {
            if (!isset(PeriodAttendance::STATUSES[$status]) || !in_array((int)$sid, array_map('intval', $allowed), true)) continue;
            PeriodAttendance::set($cls['id'], (int)$sid, $date, $status, substr(trim($remarks[$sid] ?? ''), 0, 255), current_user()['id']);
            $saved++;
        }
        if ($saved) Audit::record('ATTENDANCE_SAVED', 'attendance', (int)$cls['id'], "$saved students, " . Schedule::label($cls, false) . " on $date");
        flash('success', $saved ? "Attendance saved for $saved students." : 'Nothing to save. Choose a status for at least one student.');
        $this->redirect($back);
    }

    /* ---------- Daily attendance (gate time-in; one status per student per day) ---------- */

    private function dailyFilters()
    {
        $date = $this->validDate($_GET['date'] ?? $_POST['date'] ?? null, date('Y-m-d'));
        $section = $_GET['section'] ?? $_POST['section'] ?? null;
        if (is_teacher()) {   // teachers only see the sections they handle
            $own = Section::dailyOptions();
            $ids = array_map('strval', array_column($own, 'id'));
            if (!in_array((string)$section, $ids, true)) $section = $ids[0] ?? -1;
        }
        return [$date, (string)$section];
    }

    private function needDaily()
    {
        if (!Section::hasDaily()) {
            flash('error', 'Whole-day attendance is taken by the class adviser. Use attendance by period for your classes.');
            $this->redirect(url('attendance'));
        }
    }

    public function daily()
    {
        $this->requireRole(['teacher']);
        $this->needDaily();
        [$date, $section] = $this->dailyFilters();
        [$weekFrom, $weekTo] = $this->week($date);
        $this->view('attendance/daily', ['title' => 'Daily attendance', 'active' => 'attendance',
            'date' => $date, 'section' => $section, 'sections' => Section::dailyOptions(),
            'rows' => Attendance::rows($date, $section ?: null), 'weekFrom' => $weekFrom, 'weekTo' => $weekTo]);
    }

    public function saveDaily()
    {
        $this->post();
        $this->requireRole(['teacher']);
        $this->needDaily();
        [$date, $section] = $this->dailyFilters();
        $statuses = $_POST['status'] ?? [];
        $remarks  = $_POST['remarks'] ?? [];
        $saved = 0;
        foreach ($statuses as $sid => $status) {
            if (!in_array($status, self::STATUSES, true)) continue;
            $st = Student::find((int)$sid);
            if (!$st || !Section::canSeeDaily($st['section_id'] ?? 0)) continue;
            Attendance::set((int)$sid, $date, $status, substr(trim($remarks[$sid] ?? ''), 0, 255), current_user()['id']);
            $saved++;
        }
        if ($saved) Audit::record('ATTENDANCE_SAVED', 'attendance', null, "$saved students on $date");
        flash('success', $saved ? "Attendance saved for $saved students." : 'Nothing to save. Choose a status for at least one student.');
        $this->redirect(url('attendance/daily') . '&date=' . urlencode($date) . '&section=' . urlencode($section));
    }

    /* ---------- Excel export ---------- */

    public function export()
    {
        $this->requireRole(['teacher']);
        [$min, $max] = $this->limits();
        $today = date('Y-m-d');
        $from = $this->clamp($this->validDate($_GET['from'] ?? null, $this->week($today)[0]), $min, $max);
        $to   = $this->clamp($this->validDate($_GET['to'] ?? null, $today), $min, $max);
        if ($to < $from) [$from, $to] = [$to, $from];
        if (strtotime($to) - strtotime($from) > 366 * 86400) { flash('error', 'Choose a range of one year or less.'); $this->redirect(url('attendance')); }
        $section = (int)($_GET['section'] ?? 0);
        $type = ($_GET['type'] ?? 'period') === 'daily' ? 'daily' : 'period';
        $file = "sgvms-attendance-{$type}-{$from}_to_{$to}.xlsx";
        Audit::record('ATTENDANCE_EXPORTED', 'attendance', null, "$type $from to $to");

        if ($type === 'daily') {
            if (is_teacher() && !Section::canSeeDaily($section)) $section = (int)(Section::dailyOptions()[0]['id'] ?? -1);   // only the section the teacher advises
            $rows = [['Date', 'Grade', 'Section', 'Student', 'LRN', 'Status', 'Remarks']];
            foreach (Attendance::export($from, $to, $section ?: null) as $r) {
                $rows[] = [$r['date'], $r['grade'], $r['section'], $r['last_name'] . ', ' . $r['first_name'] . ($r['name_suffix'] ? ' ' . $r['name_suffix'] : ''),
                           $r['lrn'], ucfirst($r['status']), $r['remarks']];
            }
            Xlsx::download($file, ['Daily attendance' => $rows]);
        }

        $statuses = PeriodAttendance::STATUSES;
        $rows = [['Date', 'Day', 'Grade', 'Section', 'Subject', 'Period', 'Time', 'Teacher', 'Student', 'LRN', 'Status', 'Remarks']];
        $summary = [];
        foreach (PeriodAttendance::export($from, $to, $section ?: null, $this->teacherId()) as $r) {
            $name = $r['last_name'] . ', ' . $r['first_name'] . ($r['name_suffix'] ? ' ' . $r['name_suffix'] : '');
            $rows[] = [$r['date'], date('l', strtotime($r['date'])), $r['grade'], $r['section'], $r['subject'], $r['period'],
                       $r['start_time'] . '-' . $r['end_time'], $r['teacher'], $name, $r['lrn'], $statuses[$r['status']], $r['remarks']];
            $key = $r['grade'] . '|' . $r['section'] . '|' . $name . '|' . $r['lrn'];
            $summary[$key] = $summary[$key] ?? ['grade' => $r['grade'], 'section' => $r['section'], 'name' => $name, 'lrn' => $r['lrn']] + array_fill_keys(array_keys($statuses), 0);
            $summary[$key][$r['status']]++;
        }
        uasort($summary, function ($a, $b) { return [strlen($a['grade']), $a['grade'], $a['section'], $a['name']] <=> [strlen($b['grade']), $b['grade'], $b['section'], $b['name']]; });
        $sum = [['Grade', 'Section', 'Student', 'LRN', 'Present', 'Late', 'Absent', 'Excused', 'No class', 'Periods marked']];
        foreach ($summary as $s) {
            $sum[] = [$s['grade'], $s['section'], $s['name'], $s['lrn'], $s['present'], $s['late'], $s['absent'], $s['excused'], $s['no_class'],
                      $s['present'] + $s['late'] + $s['absent'] + $s['excused'] + $s['no_class']];
        }
        Xlsx::download($file, ['Attendance' => $rows, 'Summary' => $sum]);
    }
}
