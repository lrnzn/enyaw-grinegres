<?php
class DashboardController extends Controller
{
    public function index()
    {
        $this->requireAdmin();
        $status   = Log::statusMap();
        $students = Student::indexById();
        $count = ['on_campus' => 0, 'released' => 0];
        $onCampus = [];
        foreach ($status as $sid => $st) {
            if ($st['state'] === 'on_campus') { $count['on_campus']++; if (isset($students[$sid])) $onCampus[] = $students[$sid] + ['since' => $st['in']]; }
            if ($st['state'] === 'released')  $count['released']++;
        }
        $this->view('dashboard/index', [
            'title'    => 'Dashboard', 'active' => 'dashboard',
            'total'    => count($students),
            'count'    => $count,
            'absent'   => max(0, count($students) - count($status)),
            'denied'   => Log::countToday('DENIED'),
            'override' => Log::countToday('RELEASED_OVERRIDE'),
            'onCampus' => $onCampus,
            'recent'   => Log::query([], 8),
        ]);
    }

    /** Demo helper: restore the sample morning time-ins. */
    public function resetDemo()
    {
        $this->post();
        $this->requireAdmin();
        if (!demo()) { flash('error', 'Demo reset is disabled on a live system.'); $this->redirect(url('dashboard')); }
        Database::seedLogs();
        flash('success', 'Demo logs were reset: students 1–5 are timed-in for today.');
        $this->redirect(url('dashboard'));
    }
}
