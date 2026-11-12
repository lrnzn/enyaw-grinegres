<?php
class AuthController extends Controller
{
    public function index() { $this->redirect(url('auth/login')); }

    public function login()
    {
        if (current_user()) $this->redirect(url(is_admin() ? 'dashboard' : 'gate'));

        if ($this->isPost()) {
            csrf_check();
            $username = substr($this->input('username'), 0, 60);
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if (LoginLog::isLockedOut($ip, $username)) {
                flash('error', 'Too many failed attempts. Please wait ' . (int)config('login_window_minutes') . ' minutes and try again.');
                $this->redirect(url('auth/login'));
            }
            $user = User::findByUsername($username);
            // Always run a hash check so response time does not reveal whether the username exists.
            $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
            if (password_verify($_POST['password'] ?? '', $hash) && $user && $user['status'] === 'active') {
                $logId = LoginLog::record($user['id'], $username, true, $ip, $ua);
                session_regenerate_id(true);
                $_SESSION['user'] = ['id' => $user['id'], 'name' => $user['name'], 'username' => $user['username'], 'role' => $user['role']];
                $_SESSION['login_log_id'] = $logId;
                $this->redirect(url($user['role'] === 'admin' ? 'dashboard' : 'gate'));
            }
            LoginLog::record($user['id'] ?? null, $username, false, $ip, $ua);
            flash('error', 'Incorrect username or password.');
            $this->redirect(url('auth/login'));
        }
        $this->view('auth/login', ['title' => 'Sign in']);
    }

    public function logout()
    {
        $this->post();
        if (!empty($_SESSION['login_log_id'])) LoginLog::markLogout($_SESSION['login_log_id']);
        $_SESSION = [];
        session_destroy();
        session_start();
        flash('success', 'You have been signed out.');
        $this->redirect(url('auth/login'));
    }
}
