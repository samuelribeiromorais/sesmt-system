<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Models\Alerta;
use App\Models\Documento;
use App\Models\Certificado;

class AlertaController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $docModel = new Documento();
        $certModel = new Certificado();

        $this->view('alertas/index', [
            'docs_vencidos'    => $docModel->getExpired(50),
            'docs_expiring'    => $docModel->getExpiring(30, 50),
            'certs_expiring'   => $certModel->getExpiring(30, 50),
            'pageTitle'        => 'Alertas',
        ]);
    }
}
