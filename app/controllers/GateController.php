<?php
/**
 * The gate: the heart of the system.
 * mode "in"  = morning drop-off (record time-in)
 * mode "out" = dismissal (verify guardian, record release)
 *
 * Every action that changes a student's status runs inside a database transaction that
 * locks the student's row, so two scans (or a replayed request) can never both succeed.
 */
class GateController extends Controller
{
    private function mode()
    {
        $m = $_GET['mode'] ?? $_POST['mode'] ?? (date('H') < 12 ? 'in' : 'out');   // mornings open on drop-off, afternoons on dismissal
        return $m === 'in' ? 'in' : 'out';
    }

    private function uid() { return current_user()['id']; }

    public function index()
    {
        $this->requireLogin();
        $this->view('gate/index', [
            'title' => 'Gate station', 'active' => 'gate', 'mode' => $this->mode(),
            'students' => demo() ? Student::search('', true) : [], 'status' => Log::statusMap(),
            'scripts' => ['js/html5-qrcode.min.js'],
        ]);
    }

    /** Scan result or typed search (enrolled students only). */
    public function lookup()
    {
        $this->requireLogin();
        $mode = $this->mode();
        $q = trim($_GET['q'] ?? '');
        if ($q === '') $this->redirect(url('gate') . '&mode=' . $mode);

        $s = Student::findByToken($q);
        if ($s && $s['status'] === 'enrolled') $this->redirect(url('gate/verify/' . $s['id']) . '&mode=' . $mode);
        $found = Student::search($q, true);
        if (count($found) === 1) $this->redirect(url('gate/verify/' . $found[0]['id']) . '&mode=' . $mode);
        if (!$found) {
            // Anything that does not look like a name or LRN is a QR code from somewhere else (Wi-Fi, website...). Never show what it contains.
            $isCode = stripos($q, 'STU-') === 0 || strlen($q) > 40 || preg_match('/[^\p{L}\p{N}\s.\'\-,]/u', $q);
            flash('error', $isCode ? 'This QR code is not registered in the system.'
                                   : 'No enrolled student found for "' . $q . '". Try the QR code, LRN or name.');
            $this->redirect(url('gate') . '&mode=' . $mode);
        }
        $this->view('gate/results', ['title' => 'Select student', 'active' => 'gate',
            'mode' => $mode, 'q' => $q, 'found' => $found, 'status' => Log::statusMap()]);
    }

    public function verify($id = 0)
    {
        $this->requireLogin();
        $s = Student::find($id);
        if (!$s || $s['status'] !== 'enrolled') { flash('error', 'Student not found.'); $this->redirect(url('gate')); }
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
        try {
            $logId = Database::transaction(function () use ($id, $s) {
                Log::lock($id);
                $state = Log::statusOf((int)$id);
                if ($state['state'] !== 'absent') {
                    throw new GateBlocked($s['full_name'] . ' already has a record for today (' . ($state['state'] === 'released' ? 'already released' : 'already timed in') . ').');
                }
                $logId = Log::record($id, 'TIME_IN', null, null, null, $this->uid());
                Attendance::markPresent($id, $this->uid());
                return $logId;
            });
        } catch (GateBlocked $e) {
            flash('warning', $e->getMessage());
            $this->redirect(url('gate') . '&mode=in');
        }
        Notifier::notify($id, 'arrival', $logId);   // after the time-in is saved; never blocks the gate
        $this->redirect(url('gate/done/' . $logId));
    }

    /** Dismissal: the registered guardian was matched by face. */
    public function release($id = 0)
    {
        $this->post();
        $this->requireLogin();
        $s = Student::find($id) ?: $this->back('Student not found.');
        $gid = (int)($_POST['guardian_id'] ?? 0);
        try {
            $logId = Database::transaction(function () use ($id, $gid, $s) {
                Log::lock($id);
                // Server-side checks: never trust the button on the page.
                if (!Guardian::authorizedLink($id, $gid)) {
                    throw new GateBlocked('That person is not an authorized guardian for this student. Release blocked.');
                }
                $state = Log::statusOf((int)$id);
                if ($state['state'] === 'released') {
                    throw new GateBlocked($s['full_name'] . ' was already released today at ' . fmt_time($state['out']) . '. Release blocked.');
                }
                $g = Guardian::find($gid);
                return Log::record($id, 'RELEASED', $gid, $g['full_name'], null, $this->uid());
            });
        } catch (GateBlocked $e) {
            flash('error', $e->getMessage());
            $this->redirect(url('gate/verify/' . $id) . '&mode=out');
        }
        Notifier::notify($id, 'pickup', $logId);
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

    /**
     * Substitute pickup: a person who is not a registered guardian.
     * The school's process is "a confirmation letter OR a phone call to the parent" (configurable: see
     * substitute_pickup.require). Either way the person's details are recorded, and a teacher or
     * administrator approves with their OWN username and password.
     */
    public function override($id = 0)
    {
        $this->post();
        $this->requireLogin();
        $s = Student::find($id) ?: $this->back('Student not found.');
        $back = url('gate/verify/' . $id) . '&mode=out';
        $both = config('substitute_pickup.require') === 'both';

        $first = $this->input('first_name'); $last = $this->input('last_name');
        $contact = $this->input('contact'); $rel = $this->input('relationship');
        $called = (int)($_POST['called_guardian_id'] ?? 0);
        $phone  = $this->input('phone_call_status', 'not_called');
        if (!in_array($phone, ['reached', 'no_answer', 'declined', 'not_called'], true)) $phone = 'not_called';
        if ($first === '' || $last === '' || $contact === '' || $rel === '') {
            flash('error', 'Enter the person\'s first name, last name, contact number and relationship.'); $this->redirect($back);
        }
        if ($phone !== 'not_called' && !Guardian::authorizedLink($id, $called)) {
            flash('error', 'Choose which registered guardian you phoned.'); $this->redirect($back);
        }
        if ($phone === 'declined') {
            flash('error', 'Release blocked: a registered guardian did not approve this pickup. Deny the release.'); $this->redirect($back);
        }
        $phoneOk   = $phone === 'reached';
        $hasLetter = !empty($_FILES['letter']['name']);
        if ($both && (!$phoneOk || !$hasLetter)) {
            flash('error', 'This school requires BOTH a phone call that reached a registered guardian AND an authorization letter.'); $this->redirect($back);
        }
        if (!$both && !$phoneOk && !$hasLetter) {
            flash('error', 'Confirm the pickup by a phone call to a registered guardian OR by an authorization letter. If neither is possible, deny the release.'); $this->redirect($back);
        }

        $letter = $this->uploadLetter('letter');
        if ($letter === false) $this->redirect($back);
        $photo = $this->uploadPhoto('collector_photo');
        if ($photo === false) { remove_upload($letter); $this->redirect($back); }
        $cleanup = function () use ($letter, $photo) { remove_upload($letter); remove_upload($photo); };

        $method = $phoneOk && $letter ? 'phone call and letter' : ($phoneOk ? 'phone call' : 'letter');
        try {
            $approver = User::verifyApprover($this->input('approver_username'), $_POST['approver_password'] ?? '',
                (array)config('substitute_pickup.approver_roles'));
            if (!$approver) { $cleanup(); flash('error', 'The approver\'s username or password is not correct, or that account cannot approve pickups. Release blocked.'); $this->redirect($back); }
            if ((int)$approver['id'] === (int)$this->uid() && $approver['role'] !== 'admin') {
                $cleanup(); flash('error', 'The approver must be a different person from the staff member at the gate.'); $this->redirect($back);
            }

            $name = compose_name(['first_name' => $first, 'last_name' => $last]);
            $logId = Database::transaction(function () use ($id, $s, $first, $last, $contact, $rel, $called, $phone, $method, $letter, $photo, $approver, $name) {
                Log::lock($id);
                $state = Log::statusOf((int)$id);
                if ($state['state'] === 'released') {
                    throw new GateBlocked($s['full_name'] . ' was already released today at ' . fmt_time($state['out']) . '. Release blocked.');
                }
                $cid = Collector::create(['first_name' => $first, 'last_name' => $last, 'contact' => $contact, 'photo' => $photo]);
                $conf = Confirmation::create(['student_id' => $id, 'guardian_id' => $called ?: null, 'collector_id' => $cid,
                    'approved_by' => $approver['id'], 'relationship' => $rel, 'letter_path' => $letter, 'phone_call_status' => $phone]);
                $note = "Substitute pickup. Relationship: $rel. Confirmed by $method. Approved by " . $approver['name'] . '.';
                return Log::record($id, 'RELEASED_OVERRIDE', null, $name, $note, $this->uid(), $conf);
            });
        } catch (GateBlocked $e) {
            $cleanup();
            flash('error', $e->getMessage());
            $this->redirect($back);
        }
        Audit::record('SUBSTITUTE_PICKUP_APPROVED', 'student', (int)$id, "$name collected " . $s['full_name'] . "; $method; approved by " . $approver['name']);
        Notifier::notify($id, 'pickup', $logId);   // parents are told who collected their child
        $this->redirect(url('gate/done/' . $logId));
    }

    public function done($logId = 0)
    {
        $this->requireLogin();
        $log = Log::find($logId);
        if (!$log) $this->redirect(url('gate'));
        $this->view('gate/done', ['title' => 'Result', 'active' => 'gate', 'log' => $log, 'messages' => Notification::forLog($logId),
            'photo' => $log['guardian_id'] ? (Guardian::find($log['guardian_id'])['photo'] ?? null) : null]);
    }

    private function back($msg)
    {
        flash('error', $msg);
        $this->redirect(url('gate'));
    }
}
