<?php
class AttendanceController extends Controller
{
    private const STATUSES = ['present', 'absent', 'late', 'excused'];

    private function filters()
    {
        $date = $_GET['date'] ?? $_POST['date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) $date = date('Y-m-d');
        $section = $_GET['section'] ?? $_POST['section'] ?? null;
        if ($section === null && is_teacher()) $section = Section::forUser(current_user()['id'])['id'] ?? '';
        return [$date, (string)$section];
    }

    public function index()
    {
        $this->requireRole(['teacher']);
        [$date, $section] = $this->filters();
        $this->view('attendance/index', ['title' => 'Attendance', 'active' => 'attendance',
            'date' => $date, 'section' => $section, 'sections' => Section::options(),
            'rows' => Attendance::rows($date, $section ?: null)]);
    }

    public function save()
    {
        $this->post();
        $this->requireRole(['teacher']);
        [$date, $section] = $this->filters();
        $statuses = $_POST['status'] ?? [];
        $remarks  = $_POST['remarks'] ?? [];
        $saved = 0;
        foreach ($statuses as $sid => $status) {
            if (!in_array($status, self::STATUSES, true)) continue;
            Attendance::set((int)$sid, $date, $status, substr(trim($remarks[$sid] ?? ''), 0, 255), current_user()['id']);
            $saved++;
        }
        if ($saved) Audit::record('ATTENDANCE_SAVED', 'attendance', null, "$saved students on $date");
        flash('success', $saved ? "Attendance saved for $saved students." : 'Nothing to save. Choose a status for at least one student.');
        $this->redirect(url('attendance') . '&date=' . urlencode($date) . '&section=' . urlencode($section));
    }
}
