<?php
class StudentsController extends Controller
{
    public function index()
    {
        $this->requireLogin();
        $q = trim($_GET['q'] ?? '');
        $this->view('students/index', [
            'title' => 'Students', 'active' => 'students',
            'students' => Student::search($q), 'q' => $q, 'status' => Log::statusMap(),
        ]);
    }

    public function show($id = 0)
    {
        $this->requireLogin();
        $s = Student::find($id) ?: $this->missing();
        $this->view('students/show', [
            'title' => $s['full_name'], 'active' => 'students',
            's' => $s, 'guardians' => Student::guardians($id),
            'available' => is_admin() ? Guardian::notLinkedTo($id) : [],
            'history' => Log::query(['student_id' => $id], 10),
            'state' => Log::statusOf((int)$id),
        ]);
    }

    public function create()
    {
        $this->requireAdmin();
        $this->view('students/form', ['title' => 'Register student', 'active' => 'students',
            'mode' => 'create', 's' => ['status' => 'enrolled'], 'errors' => [], 'sections' => Section::options()]);
    }

    public function store()
    {
        $this->post();
        $this->requireAdmin();
        [$d, $errors] = $this->validated(false);
        $photo = $this->uploadPhoto('photo');
        if ($photo === false) $errors[] = 'Please fix the photo and try again.';
        if (!$errors && $d['lrn'] !== '' && $this->lrnTaken($d['lrn'], 0)) $errors[] = 'Another student already has that LRN.';
        if ($errors) {
            if ($photo) remove_upload($photo);
            return $this->view('students/form', ['title' => 'Register student', 'active' => 'students',
                'mode' => 'create', 's' => $d, 'errors' => $errors, 'sections' => Section::options()]);
        }
        $d['photo'] = $photo;
        $id = Student::create($d);
        Audit::record('STUDENT_CREATED', 'student', $id, compose_name($d));
        flash('success', 'Student registered. Now add the authorized guardians.');
        $this->redirect(url('students/show/' . $id));
    }

    public function edit($id = 0)
    {
        $this->requireAdmin();
        $s = Student::find($id) ?: $this->missing();
        $this->view('students/form', ['title' => 'Edit student', 'active' => 'students',
            'mode' => 'edit', 's' => $s, 'errors' => [], 'sections' => Section::options()]);
    }

    public function update($id = 0)
    {
        $this->post();
        $this->requireAdmin();
        $s = Student::find($id) ?: $this->missing();
        [$d, $errors] = $this->validated(true);
        $photo = $this->uploadPhoto('photo');
        if ($photo === false) $errors[] = 'Please fix the photo and try again.';
        if (!$errors && $d['lrn'] !== '' && $this->lrnTaken($d['lrn'], (int)$id)) $errors[] = 'Another student already has that LRN.';
        if ($errors) {
            if ($photo) remove_upload($photo);
            return $this->view('students/form', ['title' => 'Edit student', 'active' => 'students',
                'mode' => 'edit', 's' => $d + $s, 'errors' => $errors, 'sections' => Section::options()]);
        }
        $d['photo'] = $photo;
        Student::update($id, $d);
        if ($photo) remove_upload($s['photo']);   // the replaced photo does not stay on the server
        Audit::record('STUDENT_UPDATED', 'student', (int)$id, compose_name($d));
        flash('success', 'Student updated.');
        $this->redirect(url('students/show/' . $id));
    }

    public function delete($id = 0)
    {
        $this->post();
        $this->requireAdmin();
        $s = Student::find($id) ?: $this->missing();
        $guardians = Student::guardians($id);
        $letters = Confirmation::lettersFor($id);
        Student::delete($id);
        // Clean up everything that belonged only to this student.
        remove_upload($s['photo']);
        foreach ($letters as $l) remove_upload($l);
        foreach ($guardians as $g) remove_upload(Guardian::deleteIfOrphan($g['id']));
        Audit::record('STUDENT_DELETED', 'student', (int)$id, $s['full_name']);
        flash('success', 'Student removed with their photo and any guardians who were linked only to them. Gate history is kept.');
        $this->redirect(url('students'));
    }

    /* ---------- Guardians (managed from the student page) ---------- */

    public function addGuardian($id = 0)
    {
        $this->post();
        $this->requireAdmin();
        $s = Student::find($id) ?: $this->missing();
        $back = url('students/show/' . $id);
        $first = $this->input('first_name'); $last = $this->input('last_name'); $rel = $this->input('relationship');
        if ($first === '' || $last === '' || $rel === '') {
            flash('error', 'Guardian first name, last name and relationship are required.');
            $this->redirect($back);
        }
        $photo = $this->uploadPhoto('photo');
        if ($photo === false) $this->redirect($back);
        $gid = Guardian::create([
            'first_name' => $first, 'middle_name' => $this->input('middle_name'), 'last_name' => $last,
            'name_suffix' => $this->input('name_suffix'), 'contact' => $this->input('contact'),
            'occupation' => $this->input('occupation'), 'photo' => $photo,
        ]);
        Guardian::link($id, $gid, $rel, 1);
        $name = compose_name(['first_name' => $first, 'last_name' => $last, 'name_suffix' => $this->input('name_suffix')]);
        Audit::record('GUARDIAN_ADDED', 'guardian', $gid, "$name ($rel) for " . $s['full_name']);
        flash('success', "$name was added as an authorized guardian.");
        $this->redirect($back);
    }

    public function linkGuardian($id = 0)
    {
        $this->post();
        $this->requireAdmin();
        $s = Student::find($id) ?: $this->missing();
        $gid = (int)($_POST['guardian_id'] ?? 0);
        $rel = $this->input('relationship');
        $g = Guardian::find($gid);
        if (!$g || $rel === '') {
            flash('error', 'Choose a guardian and enter the relationship.');
        } else {
            Guardian::link($id, $gid, $rel, 1);
            Audit::record('GUARDIAN_LINKED', 'guardian', $gid, $g['full_name'] . " ($rel) linked to " . $s['full_name']);
            flash('success', 'Existing guardian linked (useful for siblings).');
        }
        $this->redirect(url('students/show/' . $id));
    }

    public function toggleGuardian($linkId = 0)
    {
        $this->post();
        $this->requireAdmin();
        $link = Guardian::linkInfo($linkId) ?: $this->missing();
        $g = Guardian::find($link['guardian_id']); $s = Student::find($link['student_id']);
        $nowAuthorized = !$link['is_authorized'];
        Guardian::setAuthorized($linkId, $nowAuthorized ? 1 : 0);
        Audit::record($nowAuthorized ? 'PICKUP_RESTORED' : 'PICKUP_REVOKED', 'guardian', (int)$link['guardian_id'],
            ($g['full_name'] ?? '') . ' for ' . ($s['full_name'] ?? ''));
        flash('success', $nowAuthorized ? 'Pickup authorization restored.' : 'Pickup authorization revoked.');
        $this->redirect(url('students/show/' . $link['student_id']));
    }

    public function toggleNotify($linkId = 0)
    {
        $this->post();
        $this->requireAdmin();
        $link = Guardian::linkInfo($linkId) ?: $this->missing();
        $g = Guardian::find($link['guardian_id']); $s = Student::find($link['student_id']);
        $on = !$link['receive_notifications'];
        Guardian::setNotify($linkId, $on ? 1 : 0);
        Audit::record($on ? 'MESSAGES_TURNED_ON' : 'MESSAGES_TURNED_OFF', 'guardian', (int)$link['guardian_id'], ($g['full_name'] ?? '') . ' for ' . ($s['full_name'] ?? ''));
        flash('success', $on ? 'Messages to this guardian are on.' : 'Messages to this guardian are off.');
        $this->redirect(url('students/show/' . $link['student_id']));
    }

    public function removeGuardian($linkId = 0)
    {
        $this->post();
        $this->requireAdmin();
        $link = Guardian::linkInfo($linkId) ?: $this->missing();
        $g = Guardian::find($link['guardian_id']); $s = Student::find($link['student_id']);
        Guardian::unlink($linkId);
        remove_upload(Guardian::deleteIfOrphan($link['guardian_id']));
        Audit::record('GUARDIAN_UNLINKED', 'guardian', (int)$link['guardian_id'], ($g['full_name'] ?? '') . ' from ' . ($s['full_name'] ?? ''));
        flash('success', 'Guardian unlinked from this student.');
        $this->redirect(url('students/show/' . $link['student_id']));
    }

    /* ---------- QR ID cards ---------- */

    public function card($id = 0)
    {
        $this->requireLogin();
        $s = Student::find($id) ?: $this->missing();
        $this->view('students/cards', ['title' => 'ID card', 'active' => 'students', 'list' => [$s]]);
    }

    public function cards()
    {
        $this->requireAdmin();
        $this->view('students/cards', ['title' => 'Print ID cards', 'active' => 'students', 'list' => Student::search('', true)]);
    }

    /* ---------- helpers ---------- */

    private function validated($isEdit)
    {
        $d = [
            'first_name' => $this->input('first_name'), 'middle_name' => $this->input('middle_name'),
            'last_name' => $this->input('last_name'), 'name_suffix' => $this->input('name_suffix'),
            'section_id' => $this->input('section_id'), 'gender' => $this->input('gender'),
            'date_of_birth' => $this->input('date_of_birth'), 'lrn' => $this->input('lrn'),
            'address' => $this->input('address'),
            'status' => $isEdit ? $this->input('status', 'enrolled') : 'enrolled',
        ];
        $errors = [];
        if ($d['first_name'] === '' || $d['last_name'] === '') $errors[] = 'First name and last name are required.';
        if ($d['section_id'] !== '' && !Section::find($d['section_id'])) $errors[] = 'Choose a section from the list.';
        if (!in_array($d['gender'], ['', 'female', 'male'], true)) $errors[] = 'Choose a valid gender option.';
        if (!in_array($d['status'], ['enrolled', 'withdrawn', 'graduated'], true)) $errors[] = 'Choose a valid status.';
        if ($d['lrn'] !== '' && !preg_match('/^\d{6,20}$/', $d['lrn'])) $errors[] = 'LRN should contain digits only.';
        if ($d['date_of_birth'] !== '' && !strtotime($d['date_of_birth'])) $errors[] = 'Date of birth is not a valid date.';
        return [$d, $errors];
    }

    private function lrnTaken($lrn, $exceptId)
    {
        $st = Database::pdo()->prepare('SELECT 1 FROM students WHERE lrn = ? AND student_id <> ? LIMIT 1');
        $st->execute([$lrn, $exceptId]);
        return (bool)$st->fetchColumn();
    }

    private function missing()
    {
        http_response_code(404);
        (new ErrorController())->notFound();
        exit;
    }
}
