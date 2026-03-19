<?php

namespace App\Core;

class View
{
    public static function render(string $view, array $data = [], string $layout = 'app'): void
    {
        $viewPath = dirname(__DIR__) . '/Views/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            http_response_code(500);
            die("View not found: {$view}");
        }

        extract($data);
        $user = Session::user();
        $csrfToken = Session::get('csrf_token') ?: Session::generateCsrfToken();

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        if ($layout) {
            $layoutPath = dirname(__DIR__) . '/Views/layouts/' . $layout . '.php';
            if (file_exists($layoutPath)) {
                require $layoutPath;
                return;
            }
        }

        echo $content;
    }

    public static function partial(string $partial, array $data = []): void
    {
        $path = dirname(__DIR__) . '/Views/' . str_replace('.', '/', $partial) . '.php';
        if (file_exists($path)) {
            extract($data);
            require $path;
        }
    }

    public static function csrfField(): string
    {
        $token = Session::get('csrf_token') ?: Session::generateCsrfToken();
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token) . '">';
    }
}
