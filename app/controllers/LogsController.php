<?php
class LogsController extends Controller
{
    private function filters()
    {
        $date = $_GET['date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
        $action = $_GET['action'] ?? '';
        if (!in_array($action, ['TIME_IN', 'RELEASED', 'RELEASED_OVERRIDE', 'DENIED'], true)) $action = '';
        return [$date, $action];
    }

    public function index()
    {
        $this->requireLogin();
        [$date, $action] = $this->filters();
        $this->view('logs/index', [
            'title' => 'Activity logs', 'active' => 'logs',
            'date' => $date, 'action' => $action,
            'rows' => Log::query(['from' => $date, 'to' => $date, 'action' => $action]),
            'times' => Log::dailyTimes($date),
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
        [$date, $action] = $this->filters();
        $rows = Log::query(['from' => $date, 'to' => $date, 'action' => $action]);
        // A cell that starts with = + - @ could run as a formula when opened in a spreadsheet.
        $safe = function ($v) { $v = (string)$v; return $v !== '' && strpos('=+-@', $v[0]) !== false ? "'" . $v : $v; };
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sgvms-logs-' . $date . '.csv"');
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
