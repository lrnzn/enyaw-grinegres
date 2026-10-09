<?php
/** Router: index.php?url=controller/method/param1/param2 */
class App
{
    public function __construct()
    {
        $url   = trim($_GET['url'] ?? '', '/');
        $parts = $url === '' ? [] : explode('/', $url);

        if (!$parts) {
            $dest = !current_user() ? 'auth/login' : (is_admin() ? 'dashboard' : 'gate');
            header('Location: ' . url($dest));
            exit;
        }

        $name   = $parts[0];
        $method = $parts[1] ?? 'index';
        $params = array_slice($parts, 2);

        try {
            if (!preg_match('/^[a-z]+$/i', $name) || !preg_match('/^[a-z]+$/i', $method)) return $this->notFound();
            $class = ucfirst(strtolower($name)) . 'Controller';
            if (!is_file(ROOT . "/app/controllers/$class.php")) return $this->notFound();

            $controller = new $class();
            if (!method_exists($controller, $method)) return $this->notFound();
            $ref = new ReflectionMethod($controller, $method);
            if (!$ref->isPublic() || $ref->isConstructor()) return $this->notFound();

            $ref->invokeArgs($controller, array_slice($params, 0, $ref->getNumberOfParameters()));
        } catch (PDOException $ex) {
            http_response_code(500);
            error_log('SGVMS database error: ' . $ex->getMessage());
            if (config('debug')) {
                echo '<h2>Database error</h2><pre>' . e($ex->getMessage()) . '</pre>'
                   . '<p>Did you import <code>database/schema.sql</code> and check <code>config/config.php</code>?</p>';
            } else {
                echo '<h2>Service temporarily unavailable</h2><p>Please try again later.</p>';
            }
        }
    }

    private function notFound()
    {
        http_response_code(404);
        (new ErrorController())->notFound();
    }
}
