<?php
class StaffController extends Controller
{
    public function index()
    {
        $this->requireAdmin();
        $this->view('staff/index', ['title' => 'Staff accounts', 'active' => 'staff', 'users' => User::listAll()]);
    }

    public function create()
    {
        $this->requireAdmin();
        $this->view('staff/form', ['title' => 'Add staff account', 'active' => 'staff', 'mode' => 'create',
            'u' => ['role' => 'gate_staff', 'status' => 'active'], 'errors' => []]);
    }

    public function store()
    {
        $this->post();
        $this->requireAdmin();
        [$d, $errors] = $this->validated(0);
        $d['password'] = $_POST['password'] ?? '';
        if (strlen($d['password']) < 10) $errors[] = 'The password needs at least 10 characters.';
        if ($errors) {
            return $this->view('staff/form', ['title' => 'Add staff account', 'active' => 'staff', 'mode' => 'create', 'u' => $d, 'errors' => $errors]);
        }
        $id = User::create($d);
        if ($d['role'] === 'teacher') Teacher::ensure($id, $d['contact_number'], true);
        Audit::record('STAFF_CREATED', 'user', $id, $d['username'] . ' (' . role_label($d['role']) . ')');
        flash('success', 'Account created. Give the person their username and password in person.');
        $this->redirect(url('staff'));
    }

    public function edit($id = 0)
    {
        $this->requireAdmin();
        $u = User::find($id) ?: $this->redirect(url('staff'));
        $t = Teacher::findByUser($id);
        $this->view('staff/form', ['title' => 'Edit account', 'active' => 'staff', 'mode' => 'edit',
            'u' => $u + ['contact_number' => $t['contact_number'] ?? ''], 'errors' => []]);
    }

    public function update($id = 0)
    {
        $this->post();
        $this->requireAdmin();
        $u = User::find($id) ?: $this->redirect(url('staff'));
        [$d, $errors] = $this->validated((int)$id);
        $d['status'] = $this->input('status', 'active') === 'disabled' ? 'disabled' : 'active';
        $isSelf = (int)$id === (int)current_user()['id'];
        if ($isSelf && $d['status'] === 'disabled') $errors[] = 'You cannot disable your own account.';
        $losesAdmin = $u['role'] === 'admin' && $u['status'] === 'active' && ($d['role'] !== 'admin' || $d['status'] !== 'active');
        if ($losesAdmin && User::activeAdminCount() <= 1) $errors[] = 'There must be at least one active administrator.';
        if ($errors) {
            return $this->view('staff/form', ['title' => 'Edit account', 'active' => 'staff', 'mode' => 'edit', 'u' => $d + $u, 'errors' => $errors]);
        }
        User::update($id, $d);
        if ($d['role'] === 'teacher') Teacher::ensure($id, $d['contact_number'], $d['status'] === 'active');
        elseif (Teacher::findByUser($id)) Teacher::ensure($id, $d['contact_number'], false);
        Audit::record('STAFF_UPDATED', 'user', (int)$id, $d['username'] . ' (' . role_label($d['role']) . ', ' . $d['status'] . ')');
        flash('success', 'Account updated.');
        $this->redirect(url('staff'));
    }

    public function password($id = 0)
    {
        $this->post();
        $this->requireAdmin();
        $u = User::find($id) ?: $this->redirect(url('staff'));
        $pw = $_POST['new_password'] ?? '';
        if (strlen($pw) < 10) {
            flash('error', 'The new password needs at least 10 characters.');
        } else {
            User::setPassword($id, $pw);
            Audit::record('PASSWORD_RESET', 'user', (int)$id, $u['username']);
            flash('success', 'Password changed for ' . $u['name'] . '.');
        }
        $this->redirect(url('staff/edit/' . $id));
    }

    private function validated($exceptId)
    {
        $d = ['first_name' => $this->input('first_name'), 'middle_name' => $this->input('middle_name'),
              'last_name' => $this->input('last_name'), 'name_suffix' => $this->input('name_suffix'),
              'username' => strtolower($this->input('username')), 'email' => $this->input('email'),
              'role' => $this->input('role'), 'contact_number' => $this->input('contact_number'), 'status' => 'active'];
        $errors = [];
        if ($d['first_name'] === '' || $d['last_name'] === '') $errors[] = 'First name and last name are required.';
        if (!preg_match('/^[a-z0-9._-]{3,60}$/', $d['username'])) $errors[] = 'Username: 3–60 letters, numbers, dots, dashes or underscores.';
        if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'The email address is not valid.';
        if (!in_array($d['role'], ['admin', 'teacher', 'gate_staff'], true)) $errors[] = 'Choose a role.';
        $pdo = Database::pdo();
        $st = $pdo->prepare('SELECT 1 FROM users WHERE username = ? AND user_id <> ? LIMIT 1'); $st->execute([$d['username'], $exceptId]);
        if ($st->fetchColumn()) $errors[] = 'That username is already taken.';
        if ($d['email'] !== '') {
            $st = $pdo->prepare('SELECT 1 FROM users WHERE email = ? AND user_id <> ? LIMIT 1'); $st->execute([$d['email'], $exceptId]);
            if ($st->fetchColumn()) $errors[] = 'That email is already used by another account.';
        }
        return [$d, $errors];
    }
}
