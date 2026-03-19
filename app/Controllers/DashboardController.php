<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Models\Colaborador;
use App\Models\Documento;
use App\Models\Certificado;

class DashboardController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $colabModel = new Colaborador();
        $docModel = new Documento();
        $certModel = new Certificado();

        $data = [
            'colaboradores_status' => $colabModel->countByStatus(),
            'documentos_status'    => $docModel->countByStatus(),
            'certificados_status'  => $certModel->countByStatus(),
            'docs_expiring'        => $docModel->getExpiring(30, 10),
            'docs_expired'         => $docModel->getExpired(10),
            'certs_expiring'       => $certModel->getExpiring(30, 10),
            'pageTitle'            => 'Dashboard',
        ];

        $this->view('dashboard/index', $data);
    }
}
