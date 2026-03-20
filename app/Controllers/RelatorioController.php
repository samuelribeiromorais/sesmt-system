<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Models\Colaborador;
use App\Models\Certificado;
use App\Models\Documento;
use App\Models\Cliente;
use App\Models\Obra;
use App\Services\ReportService;

class RelatorioController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $colabModel = new Colaborador();
        $clienteModel = new Cliente();
        $obraModel = new Obra();

        $this->view('relatorios/index', [
            'colaboradores' => $colabModel->all(['status' => 'ativo'], 'nome_completo ASC'),
            'clientes'      => $clienteModel->all(['ativo' => 1], 'nome_fantasia ASC'),
            'obras'         => $obraModel->all([], 'nome ASC'),
            'pageTitle'     => 'Relatorios',
        ]);
    }

    public function porObra(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $format = $this->input('format', 'html');

        if ($format === 'excel') {
            $this->exportExcelObra((int)$id);
            return;
        }

        $db = Database::getInstance();

        // Get obra with client info
        $stmt = $db->prepare(
            "SELECT o.*, c.razao_social, c.nome_fantasia
             FROM obras o
             JOIN clientes c ON o.cliente_id = c.id
             WHERE o.id = :id"
        );
        $stmt->execute(['id' => (int)$id]);
        $obra = $stmt->fetch();

        if (!$obra) {
            $this->flash('error', 'Obra nao encontrada.');
            $this->redirect('/relatorios');
        }

        // Get all active collaborators assigned to this obra
        $stmt = $db->prepare(
            "SELECT * FROM colaboradores WHERE obra_id = :oid AND status = 'ativo' ORDER BY nome_completo"
        );
        $stmt->execute(['oid' => (int)$id]);
        $colaboradores = $stmt->fetchAll();

        // Get client requirements
        $stmt = $db->prepare(
            "SELECT ccd.*, td.nome as doc_nome, td.categoria as doc_categoria,
                    tc.codigo as cert_codigo
             FROM config_cliente_docs ccd
             LEFT JOIN tipos_documento td ON ccd.tipo_documento_id = td.id
             LEFT JOIN tipos_certificado tc ON ccd.tipo_certificado_id = tc.id
             WHERE ccd.cliente_id = :cid"
        );
        $stmt->execute(['cid' => $obra['cliente_id']]);
        $requisitos = $stmt->fetchAll();

        $reqDocs = [];
        $reqCerts = [];
        foreach ($requisitos as $r) {
            if ($r['tipo_documento_id']) {
                $reqDocs[] = $r;
            }
            if ($r['tipo_certificado_id']) {
                $reqCerts[] = $r;
            }
        }

        // Calculate compliance for each collaborator
        $dadosColaboradores = [];
        $totalConformidade = 0;

        foreach ($colaboradores as $colab) {
            $docsEmDia = 0;
            $docsVencidos = 0;
            $docsFaltantes = 0;
            $certsEmDia = 0;
            $certsVencidos = 0;

            // Check documents
            foreach ($reqDocs as $req) {
                $stmt = $db->prepare(
                    "SELECT status FROM documentos
                     WHERE colaborador_id = :cid AND tipo_documento_id = :tid
                       AND status != 'obsoleto' AND excluido_em IS NULL
                     ORDER BY data_emissao DESC LIMIT 1"
                );
                $stmt->execute(['cid' => $colab['id'], 'tid' => $req['tipo_documento_id']]);
                $doc = $stmt->fetch();

                if (!$doc) {
                    $docsFaltantes++;
                } elseif ($doc['status'] === 'vigente') {
                    $docsEmDia++;
                } else {
                    $docsVencidos++;
                }
            }

            // Check certificates
            foreach ($reqCerts as $req) {
                $stmt = $db->prepare(
                    "SELECT status FROM certificados
                     WHERE colaborador_id = :cid AND tipo_certificado_id = :tid
                     ORDER BY data_realizacao DESC LIMIT 1"
                );
                $stmt->execute(['cid' => $colab['id'], 'tid' => $req['tipo_certificado_id']]);
                $cert = $stmt->fetch();

                if (!$cert || $cert['status'] === 'vencido' || $cert['status'] === 'proximo_vencimento') {
                    $certsVencidos++;
                } elseif ($cert['status'] === 'vigente') {
                    $certsEmDia++;
                }
            }

            $totalReq = count($reqDocs) + count($reqCerts);
            $totalOk = $docsEmDia + $certsEmDia;
            $pctConformidade = $totalReq > 0 ? round(($totalOk / $totalReq) * 100, 1) : 100;
            $totalConformidade += $pctConformidade;

            $dadosColaboradores[] = [
                'id'               => $colab['id'],
                'nome_completo'    => $colab['nome_completo'],
                'docs_em_dia'      => $docsEmDia,
                'docs_vencidos'    => $docsVencidos,
                'docs_faltantes'   => $docsFaltantes,
                'certs_em_dia'     => $certsEmDia,
                'certs_vencidos'   => $certsVencidos,
                'conformidade'     => $pctConformidade,
            ];
        }

        $conformidadeGeral = count($colaboradores) > 0
            ? round($totalConformidade / count($colaboradores), 1)
            : 0;

        LoggerMiddleware::log('relatorio', "Relatorio de obra ID: {$id} visualizado.");

        $this->view('relatorios/obra', [
            'obra'               => $obra,
            'colaboradores'      => $dadosColaboradores,
            'conformidadeGeral'  => $conformidadeGeral,
            'totalReqDocs'       => count($reqDocs),
            'totalReqCerts'      => count($reqCerts),
            'pageTitle'          => 'Relatorio da Obra',
        ]);
    }

    public function mensal(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $dir = dirname(__DIR__, 2) . '/storage/relatorios';
        $relatorios = [];

        if (is_dir($dir)) {
            $files = glob($dir . '/relatorio_mensal_*.html');
            if ($files) {
                rsort($files);
                foreach ($files as $file) {
                    $basename = basename($file);
                    // Extract date from filename: relatorio_mensal_YYYY-MM-DD.html
                    preg_match('/relatorio_mensal_(\d{4}-\d{2}-\d{2})/', $basename, $m);
                    $relatorios[] = [
                        'arquivo'  => $basename,
                        'data'     => $m[1] ?? '-',
                        'tamanho'  => filesize($file),
                    ];
                }
            }
        }

        $this->view('relatorios/mensal', [
            'relatorios' => $relatorios,
            'pageTitle'  => 'Relatorios Mensais',
        ]);
    }

    public function porColaborador(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $format = $this->input('format', 'html');

        if ($format === 'excel') {
            $this->exportExcelColaborador((int)$id);
            return;
        }

        $colabModel = new Colaborador();
        $colab = $colabModel->findWithRelations((int)$id);
        if (!$colab) $this->redirect('/relatorios');

        $certModel = new Certificado();
        $docModel = new Documento();

        $this->view('relatorios/colaborador', [
            'colab'        => $colab,
            'certificados' => $certModel->getLatestByColaborador((int)$id),
            'documentos'   => $docModel->findByColaborador((int)$id),
            'pageTitle'    => 'Relatorios',
        ]);
    }

    public function porCliente(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $format = $this->input('format', 'excel');

        try {
            $service = new ReportService();
            $filePath = $service->excelCliente((int)$id);
            $fileName = basename($filePath);

            LoggerMiddleware::log('relatorio', "Relatorio de conformidade gerado para cliente ID: {$id}");

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, must-revalidate');
            readfile($filePath);
            unlink($filePath);
            exit;
        } catch (\Exception $e) {
            $this->flash('error', 'Erro ao gerar relatorio: ' . $e->getMessage());
            $this->redirect('/relatorios');
        }
    }

    private function exportExcelObra(int $id): void
    {
        try {
            $service = new ReportService();
            $filePath = $service->excelObra($id);
            $fileName = basename($filePath);

            LoggerMiddleware::log('relatorio', "Relatorio Excel de obra gerado para obra ID: {$id}");

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, must-revalidate');
            readfile($filePath);
            unlink($filePath);
            exit;
        } catch (\Exception $e) {
            $this->flash('error', 'Erro ao gerar relatorio: ' . $e->getMessage());
            $this->redirect('/relatorios');
        }
    }

    private function exportExcelColaborador(int $id): void
    {
        try {
            $service = new ReportService();
            $filePath = $service->excelColaborador($id);
            $fileName = basename($filePath);

            LoggerMiddleware::log('relatorio', "Relatorio Excel gerado para colaborador ID: {$id}");

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, must-revalidate');
            readfile($filePath);
            unlink($filePath);
            exit;
        } catch (\Exception $e) {
            $this->flash('error', 'Erro ao gerar relatorio: ' . $e->getMessage());
            $this->redirect('/relatorios');
        }
    }
}
