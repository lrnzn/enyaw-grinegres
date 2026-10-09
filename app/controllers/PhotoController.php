<?php
/** Student/guardian photos are private: only signed-in staff can view them. */
class PhotoController extends Controller
{
    public function show($name = '')
    {
        $this->requireLogin();
        if (!preg_match('/^[a-f0-9]{24}\.(jpg|png|webp)$/', $name) || !is_file(ROOT . '/uploads/' . $name)) {
            http_response_code(404); exit;
        }
        $types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        header('Content-Type: ' . $types[pathinfo($name, PATHINFO_EXTENSION)]);
        header('Cache-Control: private, max-age=86400');
        readfile(ROOT . '/uploads/' . $name);
    }
}
