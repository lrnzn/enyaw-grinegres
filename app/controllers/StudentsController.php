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
            'available' => Guardian::notLinkedTo($id),
            'history' => Log::query(['student_id' => $id], 10),
            'state' => Log::statusOf((int)$id),
        ]);
    }

    public function create()
    {
        $this->requireAdmin();
        $this->view('students/form', ['title' => 'Register Student', 'active' => 'students',
            'mode' => 'create', 's' => ['grade' => 'Grade 1'], 'errors' => []]);
    }

    public function store()
    {
        $this->post();
        $this->requireAdmin();
        [$d, $errors] = $this->validated();
        $photo = $this->uploadPhoto('photo');
        if ($photo === false) $errors[] = 'Please fix the photo and try again.';
        if ($errors) {
            return $this->view('students/form', ['title' => 'Register Student', 'active' => 'students',
                'mode' => 'create', 's' => $d, 'errors' => $errors]);
        }
        $d['photo'] = $photo;
        $id = Student::create($d);
        flash('success', 'Student registered. Now add the authorized guardians.');
        $this->redirect(url('students/show/' . $id));
    }

    public function edit($id = 0)
    {
        $this->requireAdmin();
        $s = Student::find($id) ?: $this->missing();
        $this->view('students/form', ['title' => 'Edit Student', 'active' => 'students',
            'mode' => 'edit', 's' => $s, 'errors' => []]);
    }

    public function update($id = 0)
    {
        $this->post();
        $this->requireAdmin();
        $s = Student::find($id) ?: $this->missing();
        [$d, $errors] = $this->validated();
        $photo = $this->uploadPhoto('photo');
        if ($photo === false) $errors[] = 'Please fix the photo and try again.';
        if ($errors) {
            return $this->view('students/form', ['title' => 'Edit Student', 'active' => 'students',
                'mode' => 'edit', 's' => $d + $s, 'errors' => $errors]);
        }
        $d['photo'] = $photo;
        Student::update($id, $d);
        flash('success', 'Student updated.');
        $this->redirect(url('students/show/' . $id));
    }

    public function delete($id = 0)
    {
        $this->post();
        $this->requireAdmin();
        Student::delete($id);
        flash('success', 'Student removed. Past logs are kept for the record.');
        $this->redirect(url('students'));
    }

    /* ---------- Guardians (managed from the student page) ---------- */

    public function addGuardian($id = 0)
    {
        $this->post();
        $this->requireAdmin();
        Student::find($id) ?: $this->missing();
        $name = $this->input('full_name');
        $rel  = $this->input('relationship');
        if ($name === '' || $rel === '') {
            flash('error', 'Guardian name and relationship are required.');
            $this->redirect(url('students/show/' . $id));
        }
        $photo = $this->uploadPhoto('photo');
        if ($photo === false) $this->redirect(url('students/show/' . $id));
        $gid = Guardian::create([
            'full_name' => $name, 'contact' => $this->input('contact'),
            'occupation' => $this->input('occupation'), 'photo' => $photo,
        ]);
        Guardian::link($id, $gid, $rel, 1);
        flash('success', "$name was added as an authorized guardian.");
        $this->redirect(url('students/show/' . $id));
    }

    public function linkGuardian($id = 0)
    {
        $this->post();
        $this->requireAdmin();
        Student::find($id) ?: $this->missing();
        $gid = (int)($_POST['guardian_id'] ?? 0);
        $rel = $this->input('relationship');
        if (!Guardian::find($gid) || $rel === '') {
            flash('error', 'Choose a guardian and enter the relationship.');
        } else {
            Guardian::link($id, $gid, $rel, 1);
            flash('success', 'Existing guardian linked (useful for siblings).');
        }
        $this->redirect(url('students/show/' . $id));
    }

    public function toggleGuardian($linkId = 0)
    {
        $this->post();
        $this->requireAdmin();
        $link = Guardian::linkInfo($linkId) ?: $this->missing();
        Guardian::setAuthorized($linkId, $link['is_authorized'] ? 0 : 1);
        flash('success', $link['is_authorized'] ? 'Pickup authorization revoked.' : 'Pickup authorization restored.');
        $this->redirect(url('students/show/' . $link['student_id']));
    }

    public function removeGuardian($linkId = 0)
    {
        $this->post();
        $this->requireAdmin();
        $link = Guardian::linkInfo($linkId) ?: $this->missing();
        Guardian::unlink($linkId);
        flash('success', 'Guardian unlinked from this student.');
        $this->redirect(url('students/show/' . $link['student_id']));
    }

    /* ---------- QR ID cards ---------- */

    public function card($id = 0)
    {
        $this->requireLogin();
        $s = Student::find($id) ?: $this->missing();
        $this->view('students/cards', ['title' => 'ID Card', 'active' => 'students', 'list' => [$s]]);
    }

    public function cards()
    {
        $this->requireAdmin();
        $this->view('students/cards', ['title' => 'Print ID Cards', 'active' => 'students', 'list' => Student::search()]);
    }

    /* ---------- helpers ---------- */

    private function validated()
    {
        $d = [
            'lrn' => $this->input('lrn'), 'first_name' => $this->input('first_name'),
            'last_name' => $this->input('last_name'), 'grade' => $this->input('grade'),
            'section' => $this->input('section'), 'birthdate' => $this->input('birthdate'),
            'address' => $this->input('address'),
        ];
        $errors = [];
        if ($d['first_name'] === '' || $d['last_name'] === '') $errors[] = 'First name and last name are required.';
        if ($d['grade'] === '' || $d['section'] === '')        $errors[] = 'Grade level and section are required.';
        if ($d['lrn'] !== '' && !preg_match('/^\d{6,20}$/', $d['lrn'])) $errors[] = 'LRN should contain digits only.';
        if ($d['birthdate'] !== '' && !strtotime($d['birthdate']))      $errors[] = 'Birthdate is not a valid date.';
        return [$d, $errors];
    }

    private function missing()
    {
        http_response_code(404);
        (new ErrorController())->notFound();
        exit;
    }
}
