<?php
class LogsController extends Controller
{
    private function filters()
    {
        [$from, $to, $range] = Report::range($_GET, is_teacher());
        $action = $_GET['action'] ?? '';
        if (!in_array($action, ['TIME_IN', 'RELEASED', 'RELEASED_OVERRIDE', 'DENIED'], true)) $action = '';
        $grade = $_GET['grade'] ?? '';
        if (!in_array($grade, Report::grades(), true)) $grade = '';
        $section = (int)($_GET['section'] ?? 0);
        $source = ($_GET['source'] ?? '') === 'period' ? 'period' : 'daily';
        return compact('from', 'to', 'range', 'action', 'grade', 'section', 'source');
    }

    private function query(array $f)
    {
        return Log::query(['from' => $f['from'], 'to' => $f['to'], 'action' => $f['action'], 'grade' => $f['grade'], 'section_id' => $f['section']], 3000);
    }

    public function index()
    {
        $this->requireLogin();
        $f = $this->filters();
        $canReport = is_admin() || is_teacher();
        $this->view('logs/index', $f + [
            'title' => 'Activity logs', 'active' => 'logs',
            'rows' => $this->query($f),
            'grades' => Report::grades(), 'sections' => Section::options(), 'canReport' => $canReport,
            'summary' => $canReport ? Report::summary($f['from'], $f['to'], $f['grade'], $f['section'], ($f['grade'] || $f['section']) ? 'section' : 'grade', $f['source']) : [],
            'isAdmin' => is_admin(),
        ]);
    }

    /** What administrators changed. */
    public function audit()
    {
        $this->requireAdmin();
        $date = $_GET['date'] ?? '';
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = '';
        $this->view('logs/audit', ['title' => 'Audit trail', 'active' => 'audit', 'date' => $date,
            'rows' => Audit::query($date ?: null, $date ?: null)]);
    }

    public function export()
    {
        $this->requireLogin();
        $f = $this->filters();
        $rows = $this->query($f);
        // A cell that starts with = + - @ could run as a formula when opened in a spreadsheet.
        $safe = function ($v) { $v = (string)$v; return $v !== '' && strpos('=+-@', $v[0]) !== false ? "'" . $v : $v; };
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sgvms-logs-' . $f['from'] . ($f['to'] !== $f['from'] ? '_to_' . $f['to'] : '') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Time', 'Student', 'Grade', 'Section', 'Action', 'Picked up by', 'Approved by', 'Note', 'Recorded by']);
        foreach ($rows as $r) {
            fputcsv($out, array_map($safe, [
                $r['logged_at'], $r['student_name'], $r['grade'], $r['section'], action_label($r['action']),
                $r['released_to_name'], $r['approver_name'], $r['note'], $r['staff_name'],
            ]));
        }
        fclose($out);
    }
}
