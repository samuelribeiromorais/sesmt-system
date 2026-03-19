<?php

namespace App\Middleware;

use App\Core\Session;

class RoleMiddleware
{
    private array $allowedRoles;

    public function __construct(array $roles = [])
    {
        $this->allowedRoles = $roles;
    }

    public function handle(): void
    {
        $user = Session::user();
        if (!$user || !in_array($user['perfil'], $this->allowedRoles)) {
            http_response_code(403);
            die('Acesso negado. Voce nao tem permissao para acessar esta pagina.');
        }
    }

    public static function requireAdmin(): void
    {
        $mw = new self(['admin']);
        $mw->handle();
    }

    public static function requireAdminOrSesmt(): void
    {
        $mw = new self(['admin', 'sesmt']);
        $mw->handle();
    }

    public static function requireAny(): void
    {
        $mw = new self(['admin', 'sesmt', 'rh']);
        $mw->handle();
    }
}
