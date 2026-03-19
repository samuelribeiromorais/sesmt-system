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
        $docModel   = new Documento();
        $certModel  = new Certificado();

        // --- Basic stats (existing) ---
        $data = [
            'colaboradores_status' => $colabModel->countByStatus(),
            'documentos_status'    => $docModel->countByStatus(),
            'certificados_status'  => $certModel->countByStatus(),
            'docs_expiring'        => $docModel->getExpiring(30, 10),
            'docs_expired'         => $docModel->getExpired(10),
            'certs_expiring'       => $certModel->getExpiring(30, 10),
            'pageTitle'            => 'Dashboard',
        ];

        // --- Document status counts by category ---
        $data['docs_by_category'] = $docModel->query(
            "SELECT td.categoria, d.status, COUNT(*) as total
             FROM documentos d
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE d.status != 'obsoleto'
             GROUP BY td.categoria, d.status"
        );

        // --- Top 10 collaborators with most expired/expiring items ---
        $data['top_pendentes'] = $docModel->query(
            "SELECT c.id as colaborador_id, c.nome_completo,
                    COALESCE(doc_cnt.total, 0) as docs_pendentes,
                    COALESCE(cert_cnt.total, 0) as certs_pendentes,
                    (COALESCE(doc_cnt.total, 0) + COALESCE(cert_cnt.total, 0)) as total_pendentes
             FROM colaboradores c
             LEFT JOIN (
                 SELECT colaborador_id, COUNT(*) as total
                 FROM documentos
                 WHERE status IN ('vencido', 'proximo_vencimento')
                 GROUP BY colaborador_id
             ) doc_cnt ON doc_cnt.colaborador_id = c.id
             LEFT JOIN (
                 SELECT colaborador_id, COUNT(*) as total
                 FROM certificados
                 WHERE status IN ('vencido', 'proximo_vencimento')
                 GROUP BY colaborador_id
             ) cert_cnt ON cert_cnt.colaborador_id = c.id
             WHERE c.status = 'ativo'
             HAVING total_pendentes > 0
             ORDER BY total_pendentes DESC
             LIMIT 10"
        );

        // --- Documents by client (top 10) ---
        $data['docs_by_client'] = $docModel->query(
            "SELECT cl.nome_fantasia, COUNT(*) as total,
                    SUM(CASE WHEN d.status='vencido' THEN 1 ELSE 0 END) as vencidos
             FROM documentos d
             JOIN colaboradores c ON d.colaborador_id = c.id
             JOIN clientes cl ON c.cliente_id = cl.id
             WHERE d.status != 'obsoleto'
             GROUP BY cl.id
             ORDER BY total DESC
             LIMIT 10"
        );

        // --- Active collaborators missing required doc categories ---
        $data['missing_docs_count'] = $docModel->query(
            "SELECT COUNT(DISTINCT c.id) as total
             FROM colaboradores c
             WHERE c.status = 'ativo'
               AND (
                   NOT EXISTS (SELECT 1 FROM documentos d JOIN tipos_documento td ON d.tipo_documento_id = td.id WHERE d.colaborador_id = c.id AND td.categoria = 'aso' AND d.status != 'obsoleto')
                   OR NOT EXISTS (SELECT 1 FROM documentos d JOIN tipos_documento td ON d.tipo_documento_id = td.id WHERE d.colaborador_id = c.id AND td.categoria = 'epi' AND d.status != 'obsoleto')
                   OR NOT EXISTS (SELECT 1 FROM documentos d JOIN tipos_documento td ON d.tipo_documento_id = td.id WHERE d.colaborador_id = c.id AND td.categoria = 'os' AND d.status != 'obsoleto')
                   OR NOT EXISTS (SELECT 1 FROM documentos d JOIN tipos_documento td ON d.tipo_documento_id = td.id WHERE d.colaborador_id = c.id AND td.categoria = 'treinamento' AND d.status != 'obsoleto')
               )"
        )[0]['total'] ?? 0;

        $this->view('dashboard/index', $data);
    }

    /**
     * AJAX endpoint: returns dashboard chart data as JSON.
     */
    public function dashboardData(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $docModel  = new Documento();
        $certModel = new Certificado();

        $response = [
            'documentos_status'  => $docModel->countByStatus(),
            'certificados_status' => $certModel->countByStatus(),
            'docs_by_category'   => $docModel->query(
                "SELECT td.categoria, d.status, COUNT(*) as total
                 FROM documentos d
                 JOIN tipos_documento td ON d.tipo_documento_id = td.id
                 WHERE d.status != 'obsoleto'
                 GROUP BY td.categoria, d.status"
            ),
            'docs_by_client'     => $docModel->query(
                "SELECT cl.nome_fantasia, COUNT(*) as total,
                        SUM(CASE WHEN d.status='vencido' THEN 1 ELSE 0 END) as vencidos
                 FROM documentos d
                 JOIN colaboradores c ON d.colaborador_id = c.id
                 JOIN clientes cl ON c.cliente_id = cl.id
                 WHERE d.status != 'obsoleto'
                 GROUP BY cl.id
                 ORDER BY total DESC
                 LIMIT 10"
            ),
        ];

        $this->json($response);
    }
}
