<?php
abstract class Controller
{
    protected function view($name, $data = [])
    {
        $data += ['title' => config('app_name'), 'active' => '', 'scripts' => []];
        extract($data);
        $user = current_user();
        require ROOT . '/app/views/layouts/header.php';
        require ROOT . '/app/views/' . $name . '.php';
        require ROOT . '/app/views/layouts/footer.php';
    }

    protected function redirect($url) { header('Location: ' . $url); exit; }

    protected function requireLogin()
    {
        $u = current_user();
        if (!$u) $this->redirect(url('auth/login'));
        // Re-check on every request, so a disabled account or a changed role takes effect at once.
        $row = User::find($u['id']);
        if (!$row || $row['status'] !== 'active') {
            $_SESSION = [];
            session_destroy();
            session_start();
            flash('error', 'Your account is no longer active. Please contact the administrator.');
            $this->redirect(url('auth/login'));
        }
        $_SESSION['user']['role'] = $row['role'];
        $_SESSION['user']['name'] = $row['name'];
    }

    /** Allow only the listed roles (admin is always allowed). */
    protected function requireRole(array $roles)
    {
        $this->requireLogin();
        if (!is_admin() && !in_array(current_role(), $roles, true)) {
            flash('error', 'You do not have access to that page.');
            $this->redirect(url('gate'));
        }
    }

    protected function requireAdmin()
    {
        $this->requireLogin();
        if (!is_admin()) {
            flash('error', 'That action is for the school administrator only.');
            $this->redirect(url('gate'));
        }
    }

    protected function isPost() { return $_SERVER['REQUEST_METHOD'] === 'POST'; }

    /** Require a POST with a valid CSRF token. */
    protected function post()
    {
        if (!$this->isPost()) $this->redirect(url('dashboard'));
        csrf_check();
    }

    protected function input($key, $default = '') { return trim($_POST[$key] ?? $default); }

    /** Optional image upload (photo). Returns file name, null (no file) or false (invalid; error flashed). */
    protected function uploadPhoto($field)
    {
        return $this->storeUpload($field, ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
                                  'Photo must be a JPG, PNG or WEBP image.', 'Photo');
    }

    /** Optional letter upload (image or PDF). */
    protected function uploadLetter($field)
    {
        return $this->storeUpload($field, ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'],
                                  'The letter must be a JPG, PNG, WEBP or PDF file.', 'The letter');
    }

    private function storeUpload($field, array $types, $typeMsg, $label)
    {
        if (empty($_FILES[$field]['name'])) return null;
        $f = $_FILES[$field];
        if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
            flash('error', "$label must be 5 MB or smaller."); return false;
        }
        if ($f['error'] !== UPLOAD_ERR_OK) { flash('error', "$label upload failed. Please try again."); return false; }
        if ($f['size'] > 5 * 1024 * 1024)  { flash('error', "$label must be 5 MB or smaller."); return false; }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $ext  = $types[$mime] ?? null;
        if (!$ext) { flash('error', $typeMsg); return false; }
        if (!is_dir(ROOT . '/uploads')) mkdir(ROOT . '/uploads', 0775, true);
        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        move_uploaded_file($f['tmp_name'], ROOT . '/uploads/' . $name);
        return $name;
    }
}
