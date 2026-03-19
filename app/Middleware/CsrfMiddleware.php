<?php

namespace App\Middleware;

use App\Core\Session;

class CsrfMiddleware
{
    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['_csrf_token'] ?? '';
            if (!Session::verifyCsrfToken($token)) {
                http_response_code(403);
                die('Token CSRF invalido. Recarregue a pagina e tente novamente.');
            }
            Session::generateCsrfToken();
        }
    }
}
