<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;

class ConfigController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdmin();

        $this->view('config/index', [
            'pageTitle' => 'Configuracoes',
        ]);
    }

    public function save(): void
    {
        RoleMiddleware::requireAdmin();
        $this->flash('info', 'Configuracoes salvas.');
        $this->redirect('/configuracoes');
    }
}
