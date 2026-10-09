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
            'title' => 'Activity Logs', 'active' => 'logs',
            'date' => $date, 'action' => $action,
            'rows' => Log::query(['from' => $date, 'to' => $date, 'action' => $action]),
        ]);
    }

    public function export()
    {
        $this->requireLogin();
        [$date, $action] = $this->filters();
        $rows = Log::query(['from' => $date, 'to' => $date, 'action' => $action]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sgvms-logs-' . $date . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Time', 'Student', 'Grade', 'Section', 'Action', 'Picked up by', 'Note', 'Staff']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['logged_at'], trim($r['first_name'] . ' ' . $r['last_name']), $r['grade'], $r['section'],
                action_label($r['action']), $r['guardian_name'] ?: $r['person_name'], $r['note'], $r['staff_name'],
            ]);
        }
        fclose($out);
    }
}
