<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Models\Alerta;
use App\Models\Cliente;
use App\Models\Documento;
use App\Models\Certificado;

class AlertaController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $clienteFilter = $this->input('cliente', '');
        $tipoFilter = $this->input('tipo', '');

        $docModel = new Documento();
        $certModel = new Certificado();

        $docs_vencidos = $docModel->getExpired(200);
        $docs_expiring = $docModel->getExpiring(30, 200);
        $certs_expiring = $certModel->getExpiring(30, 200);

        // Filter by client if set
        if ($clienteFilter !== '') {
            $cid = (int)$clienteFilter;
            $docs_vencidos = array_values(array_filter($docs_vencidos, fn($d) => (int)($d['cliente_id'] ?? 0) === $cid));
            $docs_expiring = array_values(array_filter($docs_expiring, fn($d) => (int)($d['cliente_id'] ?? 0) === $cid));
            $certs_expiring = array_values(array_filter($certs_expiring, fn($c) => (int)($c['cliente_id'] ?? 0) === $cid));
        }

        // Filter by type if set
        if ($tipoFilter === 'docs_vencidos') {
            $docs_expiring = [];
            $certs_expiring = [];
        } elseif ($tipoFilter === 'docs_vencendo') {
            $docs_vencidos = [];
            $certs_expiring = [];
        } elseif ($tipoFilter === 'certs_vencendo') {
            $docs_vencidos = [];
            $docs_expiring = [];
        }

        // Load clients for the filter dropdown
        $clienteModel = new Cliente();
        $clientes = $clienteModel->all(['ativo' => 1], 'nome_fantasia ASC');

        $this->view('alertas/index', [
            'docs_vencidos'    => $docs_vencidos,
            'docs_expiring'    => $docs_expiring,
            'certs_expiring'   => $certs_expiring,
            'clientes'         => $clientes,
            'clienteFilter'    => $clienteFilter,
            'tipoFilter'       => $tipoFilter,
            'pageTitle'        => 'Alertas',
        ]);
    }
}
