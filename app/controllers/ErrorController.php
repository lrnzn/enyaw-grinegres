<?php
class ErrorController extends Controller
{
    public function notFound() { $this->view('errors/404', ['title' => 'Page not found']); }
}
