<?php

namespace App\Middleware;

use App\Core\Session;

class AuthMiddleware
{
    public function handle(): void
    {
        if (!Session::isLoggedIn()) {
            header('Location: /login');
            exit;
        }
    }
}
