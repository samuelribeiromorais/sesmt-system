<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Core\Database;
use App\Models\Documento;
use App\Models\Colaborador;
use App\Models\TipoDocumento;
use App\Services\FileService;

class DocumentoController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $model = new Documento();
        $status = $this->input('status', '');
        $categoria = $this->input('categoria', '');
        $search = trim($this->input('q', ''));
        $mostrarInativos = (int)$this->input('mostrar_inativos', 0);
        $page = max(1, (int)$this->input('page', 1));
        $perPage = 30;
        $offset = ($page - 1) * $perPage;

        // Filtro de colaboradores ativos por padrao
        $colabFilter = $mostrarInativos ? "" : " AND c.status = 'ativo'";

        // View base: apenas o documento mais recente de cada tipo por colaborador (sem duplicatas)
        $viewBase = "(
            SELECT d2.* FROM documentos d2
            INNER JOIN (
                SELECT colaborador_id, tipo_documento_id, MAX(id) as max_id
                FROM (
                    SELECT id, colaborador_id, tipo_documento_id,
                           ROW_NUMBER() OVER (
                               PARTITION BY colaborador_id, tipo_documento_id
                               ORDER BY data_emissao DESC, id DESC
                           ) as rn
                    FROM documentos
                    WHERE status != 'obsoleto' AND excluido_em IS NULL
                ) ranked
                WHERE rn = 1
                GROUP BY colaborador_id, tipo_documento_id
            ) latest ON d2.id = latest.max_id
        )";

        // Contadores (respeitando filtro de colaboradores ativos)
        $contadores = [
            'total' => 0,
            'vigente' => 0,
            'proximo_vencimento' => 0,
            'vencido' => 0,
        ];
        $countRows = $model->query(
            "SELECT d.status, COUNT(*) as total FROM {$viewBase} d
             JOIN colaboradores c ON d.colaborador_id = c.id
             WHERE 1=1{$colabFilter} GROUP BY d.status"
        );
        foreach ($countRows as $row) {
            $contadores[$row['status']] = (int)$row['total'];
            $contadores['total'] += (int)$row['total'];
        }

        // Query principal
        $sql = "SELECT d.*, c.nome_completo, td.nome as tipo_nome, td.categoria
                FROM {$viewBase} d
                JOIN colaboradores c ON d.colaborador_id = c.id
                JOIN tipos_documento td ON d.tipo_documento_id = td.id
                WHERE 1=1{$colabFilter}";
        $countSql = "SELECT COUNT(*) as total FROM {$viewBase} d
                     JOIN colaboradores c ON d.colaborador_id = c.id
                     JOIN tipos_documento td ON d.tipo_documento_id = td.id
                     WHERE 1=1{$colabFilter}";
        $params = [];

        if ($status) {
            $sql .= " AND d.status = :status";
            $countSql .= " AND d.status = :status";
            $params['status'] = $status;
        }
        if ($categoria) {
            $sql .= " AND td.categoria = :categoria";
            $countSql .= " AND td.categoria = :categoria";
            $params['categoria'] = $categoria;
        }
        if ($search) {
            $sql .= " AND (c.nome_completo LIKE :q OR td.nome LIKE :q2 OR d.arquivo_nome LIKE :q3)";
            $countSql .= " AND (c.nome_completo LIKE :q OR td.nome LIKE :q2 OR d.arquivo_nome LIKE :q3)";
            $params['q'] = "%{$search}%";
            $params['q2'] = "%{$search}%";
            $params['q3'] = "%{$search}%";
        }

        $totalResult = $model->query($countSql, $params);
        $totalItems = (int)($totalResult[0]['total'] ?? 0);
        $totalPages = max(1, ceil($totalItems / $perPage));

        $perPage = (int)$perPage;
        $offset = (int)$offset;
        $sql .= " ORDER BY c.nome_completo ASC, td.nome ASC LIMIT {$perPage} OFFSET {$offset}";
        $documentos = $model->query($sql, $params);

        // JSON response for lazy loading
        $format = $this->input('format', '');
        if ($format === 'json') {
            $this->json([
                'documentos' => array_map(fn($d) => [
                    'id'              => $d['id'],
                    'colaborador_id'  => $d['colaborador_id'],
                    'nome_completo'   => $d['nome_completo'],
                    'tipo_nome'       => $d['tipo_nome'],
                    'categoria'       => $d['categoria'],
                    'data_emissao'    => $d['data_emissao'],
                    'data_validade'   => $d['data_validade'],
                    'status'          => $d['status'],
                    'arquivo_nome'    => $d['arquivo_nome'] ?? '',
                ], $documentos),
                'page'            => $page,
                'totalPages'      => $totalPages,
                'total'           => $totalItems,
                'mostrarInativos' => $mostrarInativos,
            ]);
            return;
        }

        $this->view('documentos/index', [
            'documentos'      => $documentos,
            'contadores'      => $contadores,
            'status'          => $status,
            'categoria'       => $categoria,
            'search'          => $search,
            'mostrarInativos' => $mostrarInativos,
            'page'            => $page,
            'totalPages'      => $totalPages,
            'pageTitle'       => 'Documentos',
        ]);
    }

    public function uploadForm(string $colaboradorId): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $colabModel = new Colaborador();
        $colab = $colabModel->find((int)$colaboradorId);
        if (!$colab) {
            $this->redirect('/colaboradores');
        }

        $tipoModel = new TipoDocumento();
        $tipos = $tipoModel->all(['ativo' => 1], 'categoria, nome ASC');

        $this->view('documentos/upload', [
            'colab'     => $colab,
            'tipos'     => $tipos,
            'pageTitle' => 'Documentos',
        ]);
    }

    public function upload(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $colaboradorId = (int)$this->input('colaborador_id');
        $tipoDocumentoId = (int)$this->input('tipo_documento_id');
        $dataEmissao = $this->input('data_emissao');
        $observacoes = trim($this->input('observacoes', ''));

        if (!$colaboradorId || !$tipoDocumentoId || !$dataEmissao) {
            $this->flash('error', 'Preencha todos os campos obrigatorios.');
            $this->redirect("/documentos/upload/{$colaboradorId}");
        }

        // Validate files
        if (empty($_FILES['arquivos']) || !is_array($_FILES['arquivos']['name'])) {
            $this->flash('error', 'Selecione ao menos um arquivo PDF para enviar.');
            $this->redirect("/documentos/upload/{$colaboradorId}");
        }

        $files = $_FILES['arquivos'];
        $fileCount = count($files['name']);

        // Check that at least one valid file was submitted
        $hasValidFile = false;
        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $hasValidFile = true;
                break;
            }
        }
        if (!$hasValidFile) {
            $this->flash('error', 'Selecione ao menos um arquivo PDF para enviar.');
            $this->redirect("/documentos/upload/{$colaboradorId}");
        }

        $config = require dirname(__DIR__) . '/config/app.php';
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        // Validate all files first before processing
        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

            $mime = $finfo->file($files['tmp_name'][$i]);
            if (!in_array($mime, $config['upload']['allowed_types'])) {
                $this->flash('error', 'Apenas arquivos PDF sao permitidos. Arquivo invalido: ' . $files['name'][$i]);
                $this->redirect("/documentos/upload/{$colaboradorId}");
            }

            if ($files['size'][$i] > $config['upload']['max_size']) {
                $this->flash('error', 'Arquivo excede o tamanho maximo de 10MB: ' . $files['name'][$i]);
                $this->redirect("/documentos/upload/{$colaboradorId}");
            }
        }

        // Calculate validity
        $tipoModel = new TipoDocumento();
        $tipo = $tipoModel->find($tipoDocumentoId);
        $dataValidade = null;
        if ($tipo && $tipo['validade_meses']) {
            $dataValidade = date('Y-m-d', strtotime("{$dataEmissao} + {$tipo['validade_meses']} months"));
        }

        // Versioning: find original document of same type for this collaborator
        $docModel = new Documento();
        $original = $docModel->findOriginal($colaboradorId, $tipoDocumentoId);
        $documentoPaiId = null;
        $nextVersion = 1;

        if ($original) {
            $documentoPaiId = (int) $original['id'];
            $nextVersion = $docModel->getLatestVersion($documentoPaiId) + 1;
        }

        // Determine status
        $status = 'vigente';
        if ($dataValidade) {
            $daysLeft = (strtotime($dataValidade) - time()) / 86400;
            if ($daysLeft < 0) $status = 'vencido';
            elseif ($daysLeft <= 30) $status = 'proximo_vencimento';
        }

        // Create upload directory
        $uploadDir = $config['upload']['path'] . '/' . $colaboradorId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0750, true);
        }

        $colabModel = new Colaborador();
        $colab = $colabModel->find($colaboradorId);
        $uploadedCount = 0;
        $movedFiles = [];

        // Atomic transaction: wrap DB + file operations
        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            // Mark previous documents of same type as obsolete (once for the batch)
            $docModel->markAsObsolete($colaboradorId, $tipoDocumentoId);

            // Process each file
            for ($i = 0; $i < $fileCount; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

                $fileHash = hash_file('sha256', $files['tmp_name'][$i]);
                $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                $safeName = $fileHash . '.' . strtolower($ext);
                $destPath = $uploadDir . '/' . $safeName;

                if (!move_uploaded_file($files['tmp_name'][$i], $destPath)) {
                    throw new \Exception('Falha ao mover arquivo: ' . $files['name'][$i]);
                }
                $movedFiles[] = $destPath;

                $createData = [
                    'colaborador_id'    => $colaboradorId,
                    'tipo_documento_id' => $tipoDocumentoId,
                    'arquivo_nome'      => $files['name'][$i],
                    'arquivo_path'      => $colaboradorId . '/' . $safeName,
                    'arquivo_hash'      => $fileHash,
                    'arquivo_tamanho'   => $files['size'][$i],
                    'data_emissao'      => $dataEmissao,
                    'data_validade'     => $dataValidade,
                    'status'            => $status,
                    'observacoes'       => $observacoes,
                    'enviado_por'       => Session::get('user_id'),
                    'versao'            => $nextVersion,
                    'documento_pai_id'  => $documentoPaiId,
                ];

                $id = $docModel->create($createData);

                // If this is the first document (no original existed), it becomes its own root
                // documento_pai_id stays NULL for the root document

                LoggerMiddleware::log('upload', "Documento enviado: {$tipo['nome']} v{$nextVersion} para {$colab['nome_completo']} (Doc ID: {$id})");
                $uploadedCount++;
                $nextVersion++;
            }

            if ($uploadedCount === 0) {
                throw new \Exception('Nenhum arquivo foi processado com sucesso.');
            }

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            // Clean up any files that were moved
            foreach ($movedFiles as $filePath) {
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            $this->flash('error', 'Erro ao enviar documento: ' . $e->getMessage());
            $this->redirect("/documentos/upload/{$colaboradorId}");
        }

        // Invalidate dashboard cache
        DashboardController::clearCache();

        if ($uploadedCount === 1) {
            $this->flash('success', 'Documento enviado com sucesso.');
        } else {
            $this->flash('success', "{$uploadedCount} documentos enviados com sucesso.");
        }
        $this->redirect("/colaboradores/{$colaboradorId}");
    }

    public function download(string $id): void
    {
        RoleMiddleware::requireAny();

        $docModel = new Documento();
        $doc = $docModel->find((int)$id);
        if (!$doc) {
            $this->flash('error', 'Documento nao encontrado.');
            $this->redirect('/documentos');
        }

        $config = require dirname(__DIR__) . '/config/app.php';
        $fullPath = $config['upload']['path'] . '/' . $doc['arquivo_path'];

        // Path traversal protection
        $basePath = realpath($config['upload']['path']);
        $filePath = realpath($fullPath);
        if (!$filePath || !$basePath || strpos($filePath, $basePath) !== 0) {
            http_response_code(403);
            exit('Acesso negado');
        }

        if (!file_exists($filePath)) {
            $this->flash('error', 'Arquivo nao encontrado no servidor.');
            $this->redirect("/colaboradores/{$doc['colaborador_id']}");
        }

        LoggerMiddleware::log('download', "Download: {$doc['arquivo_nome']} (Doc ID: {$id})");

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $doc['arquivo_nome'] . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        readfile($filePath);
        exit;
    }

    public function visualizar(string $id): void
    {
        RoleMiddleware::requireAny();

        $docModel = new Documento();
        $doc = $docModel->find((int)$id);
        if (!$doc) {
            http_response_code(404);
            die('Documento nao encontrado.');
        }

        $config = require dirname(__DIR__) . '/config/app.php';
        $fullPath = $config['upload']['path'] . '/' . $doc['arquivo_path'];

        // Path traversal protection
        $basePath = realpath($config['upload']['path']);
        $filePath = realpath($fullPath);
        if (!$filePath || !$basePath || strpos($filePath, $basePath) !== 0) {
            http_response_code(403);
            exit('Acesso negado');
        }

        if (!file_exists($filePath)) {
            http_response_code(404);
            die('Arquivo nao encontrado.');
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $doc['arquivo_nome'] . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: private, max-age=3600');
        readfile($filePath);
        exit;
    }

    public function show(string $id): void
    {
        RoleMiddleware::requireAny();

        $docModel = new Documento();
        $doc = $docModel->find((int)$id);
        if (!$doc) {
            $this->redirect('/documentos');
        }

        // Count versions for this document
        $versions = $docModel->findVersions((int)$id);
        $versionCount = count($versions);

        $this->view('documentos/show', [
            'doc'          => $doc,
            'versionCount' => $versionCount,
            'pageTitle'    => 'Documentos',
        ]);
    }

    public function versoes(string $id): void
    {
        RoleMiddleware::requireAny();

        $docModel = new Documento();
        $doc = $docModel->find((int)$id);
        if (!$doc) {
            $this->redirect('/documentos');
        }

        $versions = $docModel->findVersions((int)$id);

        $this->view('documentos/versoes', [
            'doc'       => $doc,
            'versions'  => $versions,
            'pageTitle' => 'Documentos',
        ]);
    }

    public function assinar(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $docModel = new Documento();
        $doc = $docModel->find((int)$id);
        if (!$doc) {
            $this->redirect('/documentos');
        }

        if ($doc['assinatura_digital']) {
            $this->flash('warning', 'Este documento ja foi assinado.');
            $this->redirect("/documentos/{$id}");
        }

        $this->view('documentos/assinar', [
            'doc'       => $doc,
            'pageTitle' => 'Documentos',
        ]);
    }

    public function registrarAssinatura(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $docModel = new Documento();
        $doc = $docModel->find((int)$id);
        if (!$doc) {
            $this->flash('error', 'Documento nao encontrado.');
            $this->redirect('/documentos');
        }

        if ($doc['assinatura_digital']) {
            $this->flash('warning', 'Este documento ja foi assinado.');
            $this->redirect("/documentos/{$id}");
        }

        $signerName = trim($this->input('assinado_por', ''));
        if (!$signerName) {
            $this->flash('error', 'Informe o nome do assinante.');
            $this->redirect("/documentos/{$id}/assinar");
        }

        $timestamp = date('Y-m-d H:i:s');
        $signature = hash('sha256', $doc['arquivo_hash'] . $signerName . $timestamp);

        $docModel->update((int)$id, [
            'assinatura_digital' => $signature,
            'assinado_por'       => $signerName,
            'assinado_em'        => $timestamp,
        ]);

        LoggerMiddleware::log('assinar', "Documento assinado por {$signerName} (Doc ID: {$id})");
        $this->flash('success', 'Documento assinado com sucesso.');
        $this->redirect("/documentos/{$id}");
    }

    public function destroy(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $docModel = new Documento();
        $doc = $docModel->find((int)$id);
        if ($doc) {
            // Soft delete: set excluido_em instead of hard delete
            $docModel->update((int)$id, [
                'excluido_em' => date('Y-m-d H:i:s'),
            ]);
            LoggerMiddleware::log('excluir', "Documento movido para lixeira: {$doc['arquivo_nome']} (ID: {$id})");
            DashboardController::clearCache();
            $this->flash('success', 'Documento movido para a lixeira.');
        }
        $this->redirect("/colaboradores/{$doc['colaborador_id']}");
    }

    /**
     * Exclusao em lote (soft delete) via AJAX
     */
    public function destroyBatch(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $input = json_decode(file_get_contents('php://input'), true);
        $ids = $input['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            $this->json(['success' => false, 'error' => 'Nenhum documento selecionado.'], 400);
            return;
        }

        $docModel = new Documento();
        $count = 0;
        $now = date('Y-m-d H:i:s');

        foreach ($ids as $id) {
            $id = (int)$id;
            $doc = $docModel->find($id);
            if ($doc && empty($doc['excluido_em'])) {
                $docModel->update($id, ['excluido_em' => $now]);
                $count++;
            }
        }

        LoggerMiddleware::log('excluir', "Exclusao em lote: {$count} documento(s) movidos para lixeira");
        DashboardController::clearCache();

        $this->json([
            'success' => true,
            'message' => "{$count} documento(s) movido(s) para a lixeira.",
            'count' => $count,
        ]);
    }
}
