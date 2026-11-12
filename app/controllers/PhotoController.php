<?php
/** Photos and letters are private: only signed-in staff can view them (letters: administrators only). */
class PhotoController extends Controller
{
    private const TYPES = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];

    private function send($name, $allowPdf)
    {
        if (!preg_match('/^[a-f0-9]{24}\.(jpg|png|webp|pdf)$/', $name) || !is_file(ROOT . '/uploads/' . $name)) { http_response_code(404); exit; }
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if ($ext === 'pdf' && !$allowPdf) { http_response_code(404); exit; }
        header('Content-Type: ' . self::TYPES[$ext]);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=86400');
        readfile(ROOT . '/uploads/' . $name);
    }

    public function show($name = '')
    {
        $this->requireLogin();
        $this->send($name, false);
    }

    public function letter($name = '')
    {
        $this->requireAdmin();
        $this->send($name, true);
    }
}
