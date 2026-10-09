<?php
/**
 * The gate: the heart of the system.
 * mode "in"  = morning drop-off (record time-in)
 * mode "out" = dismissal (verify guardian, record release)
 */
class GateController extends Controller
{
    private function mode()
    {
        $m = $_GET['mode'] ?? $_POST['mode'] ?? 'out';
        return $m === 'in' ? 'in' : 'out';
    }

    private function uid() { return current_user()['id']; }

    public function index()
    {
        $this->requireLogin();
        $this->view('gate/index', [
            'title' => 'Gate Station', 'active' => 'gate', 'mode' => $this->mode(),
            'students' => Student::search(), 'status' => Log::statusMap(),
            'scripts' => ['js/html5-qrcode.min.js'],
        ]);
    }

    /** Scan result or typed search. */
    public function lookup()
    {
        $this->requireLogin();
        $mode = $this->mode();
        $q = trim($_GET['q'] ?? '');
        if ($q === '') $this->redirect(url('gate') . '&mode=' . $mode);

        if ($s = Student::findByToken($q)) {
            $this->redirect(url('gate/verify/' . $s['id']) . '&mode=' . $mode);
        }
        $found = Student::search($q);
        if (count($found) === 1) $this->redirect(url('gate/verify/' . $found[0]['id']) . '&mode=' . $mode);
        if (!$found) {
            flash('error', 'No student found for "' . $q . '". Try the QR code, LRN or name.');
            $this->redirect(url('gate') . '&mode=' . $mode);
        }
        $this->view('gate/results', ['title' => 'Select student', 'active' => 'gate',
            'mode' => $mode, 'q' => $q, 'found' => $found, 'status' => Log::statusMap()]);
    }

    public function verify($id = 0)
    {
        $this->requireLogin();
        $s = Student::find($id);
        if (!$s) { flash('error', 'Student not found.'); $this->redirect(url('gate')); }
        $this->view('gate/verify', [
            'title' => 'Verify', 'active' => 'gate', 'mode' => $this->mode(),
            's' => $s, 'guardians' => Student::guardians($id), 'state' => Log::statusOf((int)$id),
        ]);
    }

    /** Morning drop-off. */
    public function timein($id = 0)
    {
        $this->post();
        $this->requireLogin();
        $s = Student::find($id) ?: $this->back('Student not found.');
        $state = Log::statusOf((int)$id);
        if ($state['state'] !== 'absent') {
            flash('warning', $s['full_name'] . ' already has a record for today (' . ($state['state'] === 'released' ? 'already released' : 'already timed-in') . ').');
            $this->redirect(url('gate') . '&mode=in');
        }
        $logId = Log::record($id, 'TIME_IN', null, null, null, $this->uid());
        $this->redirect(url('gate/done/' . $logId));
    }

    /** Dismissal: the registered guardian was matched by face. */
    public function release($id = 0)
    {
        $this->post();
        $this->requireLogin();
        $s = Student::find($id) ?: $this->back('Student not found.');
        $gid = (int)($_POST['guardian_id'] ?? 0);
        // Server-side check: never trust the button, re-verify the link.
        if (!Guardian::authorizedLink($id, $gid)) {
            flash('error', 'That person is not an authorized guardian for this student. Release blocked.');
            $this->redirect(url('gate/verify/' . $id) . '&mode=out');
        }
        $logId = Log::record($id, 'RELEASED', $gid, null, null, $this->uid());
        $this->redirect(url('gate/done/' . $logId));
    }

    public function deny($id = 0)
    {
        $this->post();
        $this->requireLogin();
        Student::find($id) ?: $this->back('Student not found.');
        $reason = $this->input('note') ?: 'Person at the gate could not be verified.';
        $logId = Log::record($id, 'DENIED', null, $this->input('person_name') ?: null, $reason, $this->uid());
        $this->redirect(url('gate/done/' . $logId));
    }

    /** Unlisted person with an authorization letter — needs supervisor password. */
    public function override($id = 0)
    {
        $this->post();
        $this->requireLogin();
        Student::find($id) ?: $this->back('Student not found.');
        $person = $this->input('person_name');
        $note   = $this->input('note');
        if ($person === '' || $note === '') {
            flash('error', 'Enter the name of the person and the letter details.');
            $this->redirect(url('gate/verify/' . $id) . '&mode=out');
        }
        if (!User::verifyAnyAdmin($_POST['admin_password'] ?? '')) {
            flash('error', 'Supervisor password is incorrect. Release blocked.');
            $this->redirect(url('gate/verify/' . $id) . '&mode=out');
        }
        $logId = Log::record($id, 'RELEASED_OVERRIDE', null, $person, $note, $this->uid());
        $this->redirect(url('gate/done/' . $logId));
    }

    public function done($logId = 0)
    {
        $this->requireLogin();
        $log = Log::find($logId);
        if (!$log) $this->redirect(url('gate'));
        $this->view('gate/done', ['title' => 'Result', 'active' => 'gate', 'log' => $log,
            'photo' => $log['guardian_id'] ? (Guardian::find($log['guardian_id'])['photo'] ?? null) : null]);
    }

    private function back($msg)
    {
        flash('error', $msg);
        $this->redirect(url('gate'));
    }
}
