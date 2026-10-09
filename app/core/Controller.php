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
        if (!current_user()) $this->redirect(url('auth/login'));
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

    /**
     * Handle an optional image upload.
     * Returns a stored filename, null (no file) or false (invalid file; error already flashed).
     */
    protected function uploadPhoto($field)
    {
        if (empty($_FILES[$field]['name'])) return null;
        $f = $_FILES[$field];
        if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
            flash('error', 'Photo must be 5 MB or smaller.'); return false;
        }
        if ($f['error'] !== UPLOAD_ERR_OK) { flash('error', 'Photo upload failed. Please try again.'); return false; }
        if ($f['size'] > 5 * 1024 * 1024)   { flash('error', 'Photo must be 5 MB or smaller.'); return false; }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        if (!$ext) { flash('error', 'Photo must be a JPG, PNG or WEBP image.'); return false; }
        if (!is_dir(ROOT . '/uploads')) mkdir(ROOT . '/uploads', 0775, true);
        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        move_uploaded_file($f['tmp_name'], ROOT . '/uploads/' . $name);
        return $name;
    }
}
