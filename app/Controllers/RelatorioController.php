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

        $db = Database::getInstance();
        $colabModel = new Colaborador();
        $clienteModel = new Cliente();
        $obraModel = new Obra();

        // Tipos de documento para filtro
        $stmt = $db->query("SELECT id, nome, categoria FROM tipos_documento ORDER BY nome ASC");
        $tiposDocumento = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Contagem docs mês corrente
        $stmt = $db->query(
            "SELECT COUNT(*) FROM documentos
             WHERE MONTH(criado_em) = MONTH(CURDATE()) AND YEAR(criado_em) = YEAR(CURDATE())
               AND excluido_em IS NULL"
        );
        $docsMesCount = (int)$stmt->fetchColumn();

        $this->view('relatorios/index', [
            'colaboradores'   => $colabModel->all(['status' => 'ativo'], 'nome_completo ASC'),
            'clientes'        => $clienteModel->all(['ativo' => 1], 'nome_fantasia ASC'),
            'obras'           => $obraModel->all([], 'nome ASC'),
            'tiposDocumento'  => $tiposDocumento,
            'docsMesCount'    => $docsMesCount,
            'pageTitle'       => 'Relatórios',
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
            $this->flash('error', 'Obra não encontrada.');
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

        LoggerMiddleware::log('relatório', "Relatório de obra ID: {$id} visualizado.");

        $this->view('relatorios/obra', [
            'obra'               => $obra,
            'colaboradores'      => $dadosColaboradores,
            'conformidadeGeral'  => $conformidadeGeral,
            'totalReqDocs'       => count($reqDocs),
            'totalReqCerts'      => count($reqCerts),
            'pageTitle'          => 'Relatório da Obra',
        ]);
    }

    public function mensal(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $db = Database::getInstance();
        $mes = (int)($this->input('mes') ?: date('m'));
        $ano = (int)($this->input('ano') ?: date('Y'));
        $mes = max(1, min(12, $mes));
        $ano = max(2020, min((int)date('Y'), $ano));

        // Documentos criados no período
        $stmt = $db->prepare(
            "SELECT d.id, d.status, d.data_emissao, d.data_validade, d.criado_em,
                    c.nome_completo, c.cargo, c.setor,
                    td.nome as tipo_nome, td.categoria
             FROM documentos d
             JOIN colaboradores c ON d.colaborador_id = c.id
             LEFT JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE MONTH(d.criado_em) = :mes AND YEAR(d.criado_em) = :ano
               AND d.excluido_em IS NULL
             ORDER BY d.criado_em DESC
             LIMIT 500"
        );
        $stmt->execute(['mes' => $mes, 'ano' => $ano]);
        $documentos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Certificados criados no período
        $stmt = $db->prepare(
            "SELECT cert.id, cert.status, cert.data_realizacao, cert.data_validade, cert.criado_em,
                    c.nome_completo, c.cargo, c.setor,
                    tc.codigo as tipo_codigo, tc.titulo as tipo_titulo
             FROM certificados cert
             JOIN colaboradores c ON cert.colaborador_id = c.id
             LEFT JOIN tipos_certificado tc ON cert.tipo_certificado_id = tc.id
             WHERE MONTH(cert.criado_em) = :mes AND YEAR(cert.criado_em) = :ano
             ORDER BY cert.criado_em DESC"
        );
        $stmt->execute(['mes' => $mes, 'ano' => $ano]);
        $certificados = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Resumo por categoria de documento
        $stmt = $db->prepare(
            "SELECT td.categoria, COUNT(*) as total
             FROM documentos d
             LEFT JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE MONTH(d.criado_em) = :mes AND YEAR(d.criado_em) = :ano AND d.excluido_em IS NULL
             GROUP BY td.categoria"
        );
        $stmt->execute(['mes' => $mes, 'ano' => $ano]);
        $resumoCategoria = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        LoggerMiddleware::log('relatório', "Relatório mensal {$mes}/{$ano} visualizado.");

        $this->view('relatorios/mensal', [
            'documentos'      => $documentos,
            'certificados'    => $certificados,
            'resumoCategoria' => $resumoCategoria,
            'mes'             => $mes,
            'ano'             => $ano,
            'pageTitle'       => 'Relatório Mensal',
        ]);
    }

    public function porTipoDocumento(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $db = Database::getInstance();
        $tipoId  = (int)($this->input('tipo_documento_id') ?: 0);
        $status  = $this->input('status', '');
        $mes     = (int)($this->input('mes') ?: 0);
        $ano     = (int)($this->input('ano') ?: 0);

        // Tipos disponíveis
        $stmt = $db->query("SELECT id, nome, categoria FROM tipos_documento ORDER BY nome ASC");
        $tiposDocumento = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $documentos = [];
        $tipoSelecionado = null;

        if ($tipoId) {
            foreach ($tiposDocumento as $t) {
                if ($t['id'] === $tipoId) { $tipoSelecionado = $t; break; }
            }

            $where = "WHERE d.tipo_documento_id = :tid AND d.excluido_em IS NULL";
            $params = ['tid' => $tipoId];

            if ($status) { $where .= " AND d.status = :status"; $params['status'] = $status; }
            if ($mes && $ano) {
                $where .= " AND MONTH(d.data_emissao) = :mes AND YEAR(d.data_emissao) = :ano";
                $params['mes'] = $mes; $params['ano'] = $ano;
            }

            $stmt = $db->prepare(
                "SELECT d.id, d.status, d.data_emissao, d.data_validade, d.criado_em,
                        c.nome_completo, c.cargo, c.setor
                 FROM documentos d
                 JOIN colaboradores c ON d.colaborador_id = c.id
                 {$where}
                 ORDER BY c.nome_completo ASC, d.data_emissao DESC"
            );
            $stmt->execute($params);
            $documentos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            LoggerMiddleware::log('relatório', "Relatório por tipo documento ID: {$tipoId} gerado.");
        }

        $this->view('relatorios/tipo-documento', [
            'tiposDocumento'  => $tiposDocumento,
            'tipoSelecionado' => $tipoSelecionado,
            'documentos'      => $documentos,
            'tipoId'          => $tipoId,
            'status'          => $status,
            'mes'             => $mes,
            'ano'             => $ano,
            'pageTitle'       => 'Relatório por Tipo de Documento',
        ]);
    }

    public function vencidos(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $db = Database::getInstance();
        $tipoId = (int)($this->input('tipo_documento_id') ?: 0);
        $categoria = $this->input('categoria', '');

        // Tipos de documento para filtro
        $stmt = $db->query("SELECT id, nome, categoria FROM tipos_documento ORDER BY nome ASC");
        $tiposDocumento = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Documentos vencidos
        $whereDoc = "WHERE d.status = 'vencido' AND d.excluido_em IS NULL";
        $paramsDoc = [];
        if ($tipoId) {
            $whereDoc .= " AND d.tipo_documento_id = :tid";
            $paramsDoc['tid'] = $tipoId;
        }
        if ($categoria) {
            $whereDoc .= " AND td.categoria = :cat";
            $paramsDoc['cat'] = $categoria;
        }
        $stmt = $db->prepare(
            "SELECT d.id, d.data_emissao, d.data_validade,
                    DATEDIFF(CURDATE(), d.data_validade) AS dias_vencido,
                    c.nome_completo, c.cargo, c.setor,
                    td.nome AS tipo_nome, td.categoria
             FROM documentos d
             JOIN colaboradores c ON d.colaborador_id = c.id
             LEFT JOIN tipos_documento td ON d.tipo_documento_id = td.id
             {$whereDoc}
             ORDER BY td.nome ASC, dias_vencido DESC
             LIMIT 1000"
        );
        $stmt->execute($paramsDoc);
        $documentosVencidos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Certificados vencidos
        $stmt = $db->prepare(
            "SELECT cert.id, cert.data_realizacao, cert.data_validade,
                    DATEDIFF(CURDATE(), cert.data_validade) AS dias_vencido,
                    c.nome_completo, c.cargo, c.setor,
                    tc.codigo AS tipo_codigo, tc.titulo AS tipo_titulo
             FROM certificados cert
             JOIN colaboradores c ON cert.colaborador_id = c.id
             LEFT JOIN tipos_certificado tc ON cert.tipo_certificado_id = tc.id
             WHERE cert.status = 'vencido'
             ORDER BY tc.titulo ASC, dias_vencido DESC
             LIMIT 500"
        );
        $stmt->execute();
        $certificadosVencidos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Resumo por tipo de documento
        $stmt = $db->query(
            "SELECT td.nome AS tipo_nome, td.categoria, COUNT(*) AS total
             FROM documentos d
             LEFT JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE d.status = 'vencido' AND d.excluido_em IS NULL
             GROUP BY d.tipo_documento_id
             ORDER BY total DESC"
        );
        $resumoPorTipo = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        LoggerMiddleware::log('relatório', 'Relatório de vencidos visualizado.');

        $this->view('relatorios/vencidos', [
            'documentosVencidos'  => $documentosVencidos,
            'certificadosVencidos'=> $certificadosVencidos,
            'resumoPorTipo'       => $resumoPorTipo,
            'tiposDocumento'      => $tiposDocumento,
            'tipoId'              => $tipoId,
            'categoria'           => $categoria,
            'pageTitle'           => 'Documentos Vencidos',
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
            'pageTitle'    => 'Relatórios',
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

            LoggerMiddleware::log('relatório', "Relatório de conformidade gerado para cliente ID: {$id}");

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, must-revalidate');
            readfile($filePath);
            unlink($filePath);
            exit;
        } catch (\Exception $e) {
            $this->flash('error', 'Erro ao gerar relatório: ' . $e->getMessage());
            $this->redirect('/relatorios');
        }
    }

    private function exportExcelObra(int $id): void
    {
        try {
            $service = new ReportService();
            $filePath = $service->excelObra($id);
            $fileName = basename($filePath);

            LoggerMiddleware::log('relatório', "Relatório Excel de obra gerado para obra ID: {$id}");

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, must-revalidate');
            readfile($filePath);
            unlink($filePath);
            exit;
        } catch (\Exception $e) {
            $this->flash('error', 'Erro ao gerar relatório: ' . $e->getMessage());
            $this->redirect('/relatorios');
        }
    }

    private function exportExcelColaborador(int $id): void
    {
        try {
            $service = new ReportService();
            $filePath = $service->excelColaborador($id);
            $fileName = basename($filePath);

            LoggerMiddleware::log('relatório', "Relatório Excel gerado para colaborador ID: {$id}");

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, must-revalidate');
            readfile($filePath);
            unlink($filePath);
            exit;
        } catch (\Exception $e) {
            $this->flash('error', 'Erro ao gerar relatório: ' . $e->getMessage());
            $this->redirect('/relatorios');
        }
    }
}
