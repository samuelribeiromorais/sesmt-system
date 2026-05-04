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
        $allowedPerPage = [30, 50, 100, 500];
        $perPage = in_array((int)$this->input('per_page', 30), $allowedPerPage) ? (int)$this->input('per_page', 30) : 30;
        $offset = ($page - 1) * $perPage;

        // Filtro de colaboradores ativos por padrao
        $colabFilter = $mostrarInativos ? "" : " AND c.status = 'ativo'";

        // View base: apenas o documento mais recente de cada tipo por colaborador (sem duplicatas)
        // Desempate por id ASC: menor ID = documento original assinado (não réplica do sistema)
        $viewBase = "(
            SELECT d2.* FROM documentos d2
            INNER JOIN (
                SELECT colaborador_id, tipo_documento_id, MIN(id) as min_id
                FROM (
                    SELECT id, colaborador_id, tipo_documento_id,
                           ROW_NUMBER() OVER (
                               PARTITION BY colaborador_id, tipo_documento_id
                               ORDER BY data_emissao DESC, id ASC
                           ) as rn
                    FROM documentos
                    WHERE status != 'obsoleto' AND excluido_em IS NULL
                ) ranked
                WHERE rn = 1
                GROUP BY colaborador_id, tipo_documento_id
            ) latest ON d2.id = latest.min_id
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

        $sql .= " ORDER BY c.nome_completo ASC, td.nome ASC LIMIT :_limit OFFSET :_offset";
        $stmt = \App\Core\Database::getInstance()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':_limit', (int)$perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':_offset', (int)$offset, \PDO::PARAM_INT);
        $stmt->execute();
        $documentos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

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
            'perPage'         => $perPage,
            'pageTitle'       => 'Documentos',
        ]);
    }

    public function uploadForm(string $colaboradorId): void
    {
        RoleMiddleware::requireRhOrSesmt();

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
        RoleMiddleware::requireRhOrSesmt();

        $colaboradorId = (int)$this->input('colaborador_id');
        $tipoDocumentoId = (int)$this->input('tipo_documento_id');
        $dataEmissao = $this->input('data_emissao');
        $observacoes = trim($this->input('observacoes', ''));

        if (!$colaboradorId || !$tipoDocumentoId || !$dataEmissao) {
            $this->flash('error', 'Preencha todos os campos obrigatórios.');
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
                $this->flash('error', 'Apenas arquivos PDF sao permitidos. Arquivo inválido: ' . $files['name'][$i]);
                $this->redirect("/documentos/upload/{$colaboradorId}");
            }

            if ($files['size'][$i] > $config['upload']['max_size']) {
                $this->flash('error', 'Arquivo excede o tamanho máximo de 10MB: ' . $files['name'][$i]);
                $this->redirect("/documentos/upload/{$colaboradorId}");
            }
        }

        // Calculate validity
        $tipoModel = new TipoDocumento();
        $tipo = $tipoModel->find($tipoDocumentoId);
        $dataValidade = null;
        if ($tipo && $tipo['validade_meses']) {
            $validadeMeses = (int)$tipo['validade_meses'];

            // EPI: verificar se a obra do colaborador tem validade customizada
            if ($tipoDocumentoId == 6) {
                $colabModel = new Colaborador();
                $colab = $colabModel->find($colaboradorId);
                if ($colab && $colab['obra_id']) {
                    $db = \App\Core\Database::getInstance();
                    $stmtObra = $db->prepare("SELECT epi_validade_meses FROM obras WHERE id = :oid AND epi_validade_meses IS NOT NULL");
                    $stmtObra->execute(['oid' => $colab['obra_id']]);
                    $obraEpi = $stmtObra->fetchColumn();
                    if ($obraEpi) $validadeMeses = (int)$obraEpi;
                }
            }

            $dataValidade = date('Y-m-d', strtotime("{$dataEmissao} + {$validadeMeses} months"));
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

        $colabModel = new Colaborador();
        $colab = $colabModel->find($colaboradorId);
        $nomeColaborador = $colab['nome_completo'] ?? '';

        // Create upload directory with readable name
        $fileService = new \App\Services\FileService();
        $pastaNome = $fileService->getDiretorioColaborador($colaboradorId, $nomeColaborador);
        $uploadDir = $config['upload']['path'] . '/' . $pastaNome;
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }
        if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
            @chmod($uploadDir, 0775);
            if (!is_writable($uploadDir)) {
                $this->flash('error', 'Pasta do colaborador sem permissão de escrita. Contate o administrador do servidor para ajustar permissões em: ' . $pastaNome);
                $this->redirect("/documentos/upload/{$colaboradorId}");
                return;
            }
        }

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

                // Generate readable filename
                $tipoNome = $tipo['nome'] ?? 'Documento';
                $safeName = $fileService->gerarNomeArquivo($nomeColaborador, $tipoNome, $dataEmissao, strtolower($ext));

                // Handle duplicates
                if (file_exists($uploadDir . '/' . $safeName)) {
                    $base = pathinfo($safeName, PATHINFO_FILENAME);
                    $counter = 2;
                    while (file_exists($uploadDir . '/' . $base . ' (' . $counter . ').' . strtolower($ext))) {
                        $counter++;
                    }
                    $safeName = $base . ' (' . $counter . ').' . strtolower($ext);
                }

                $destPath = $uploadDir . '/' . $safeName;

                if (!@move_uploaded_file($files['tmp_name'][$i], $destPath)) {
                    throw new \Exception('Falha ao salvar o arquivo "' . $files['name'][$i] . '". Verifique as permissões da pasta do colaborador.');
                }
                $movedFiles[] = $destPath;

                $perfilEnviou = Session::user()['perfil'] ?? 'sesmt';
                $isRh = ($perfilEnviou === 'rh');
                $createData = [
                    'colaborador_id'      => $colaboradorId,
                    'tipo_documento_id'   => $tipoDocumentoId,
                    'arquivo_nome'        => $files['name'][$i],
                    'arquivo_path'        => $pastaNome . '/' . $safeName,
                    'arquivo_hash'        => $fileHash,
                    'arquivo_tamanho'     => $files['size'][$i],
                    'data_emissao'        => $dataEmissao,
                    'data_validade'       => $dataValidade,
                    'status'              => $status,
                    'observacoes'         => $observacoes,
                    'enviado_por'         => Session::get('user_id'),
                    'enviado_por_perfil'  => $perfilEnviou,
                    'aprovacao_status'    => $isRh ? 'pendente' : 'aprovado',
                    'aprovado_por'        => $isRh ? null : Session::get('user_id'),
                    'aprovado_em'         => $isRh ? null : date('Y-m-d H:i:s'),
                    'versao'              => $nextVersion,
                    'documento_pai_id'    => $documentoPaiId,
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

            // Fase 2: dispara recálculo de pendências do RH para esse colaborador
            // (cria pendências em cada cliente onde o doc deve ser protocolado)
            try {
                \App\Services\RhPendenciaService::recalcularColaborador((int)$colaboradorId);
            } catch (\Throwable $e) {
                error_log("[RH] Falha ao recalcular pendências (upload colab {$colaboradorId}): " . $e->getMessage());
            }
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
            $this->flash('error', 'Documento não encontrado.');
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
            $this->flash('error', 'Arquivo não encontrado no servidor.');
            $this->redirect("/colaboradores/{$doc['colaborador_id']}");
        }

        LoggerMiddleware::log('download', "Download: {$doc['arquivo_nome']} (Doc ID: {$id})");

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $doc['arquivo_nome'] . '"');
        header('Content-Length: ' . filesize($filePath));
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
            die('Documento não encontrado.');
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
            die('Arquivo não encontrado.');
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $doc['arquivo_nome'] . '"');
        header('Content-Length: ' . filesize($filePath));
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
            $this->flash('error', 'Documento não encontrado.');
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
     * Atualizar data de emissão de um documento
     */
    public function atualizarEmissao(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $docModel = new Documento();
        $doc = $docModel->find((int)$id);
        if (!$doc) {
            $this->flash('error', 'Documento não encontrado.');
            $this->redirect('/documentos');
            return;
        }

        $dataEmissao = $this->input('data_emissao');
        if (!$dataEmissao || !strtotime($dataEmissao)) {
            $this->flash('error', 'Data de emissão invalida.');
            $this->redirect("/documentos/{$id}");
            return;
        }

        // Recalcular validade com base na nova emissão
        $tipoModel = new TipoDocumento();
        $tipo = $tipoModel->find((int)$doc['tipo_documento_id']);
        $dataValidade = null;
        if ($tipo && $tipo['validade_meses']) {
            $validadeMeses = (int)$tipo['validade_meses'];

            // EPI: verificar validade customizada por obra
            if ((int)$doc['tipo_documento_id'] === 6) {
                $colabModel = new Colaborador();
                $colab = $colabModel->find((int)$doc['colaborador_id']);
                if ($colab && $colab['obra_id']) {
                    $db = \App\Core\Database::getInstance();
                    $stmtObra = $db->prepare("SELECT epi_validade_meses FROM obras WHERE id = :oid AND epi_validade_meses IS NOT NULL");
                    $stmtObra->execute(['oid' => $colab['obra_id']]);
                    $obraEpi = $stmtObra->fetchColumn();
                    if ($obraEpi) $validadeMeses = (int)$obraEpi;
                }
            }

            $dataValidade = date('Y-m-d', strtotime("{$dataEmissao} + {$validadeMeses} months"));
        }

        // Recalcular status
        $status = 'vigente';
        if ($dataValidade) {
            $daysLeft = (strtotime($dataValidade) - time()) / 86400;
            if ($daysLeft < 0) $status = 'vencido';
            elseif ($daysLeft <= 30) $status = 'proximo_vencimento';
        }

        $updateData = [
            'data_emissao'  => $dataEmissao,
            'data_validade' => $dataValidade,
            'status'        => $status,
        ];

        $docModel->update((int)$id, $updateData);

        LoggerMiddleware::log('editar', "Data de emissão alterada para {$dataEmissao} (Doc ID: {$id})");
        DashboardController::clearCache();

        $this->flash('success', 'Data de emissão atualizada com sucesso.');

        // Voltar para a página do colaborador se veio de la
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (strpos($referer, '/colaboradores/') !== false) {
            $this->redirect("/colaboradores/{$doc['colaborador_id']}");
        } else {
            $this->redirect("/documentos/{$id}");
        }
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

        LoggerMiddleware::log('excluir', "Exclusão em lote: {$count} documento(s) movidos para lixeira");
        DashboardController::clearCache();

        $this->json([
            'success' => true,
            'message' => "{$count} documento(s) movido(s) para a lixeira.",
            'count' => $count,
        ]);
    }

    /**
     * Aprovar ou rejeitar documento (SESMT/Admin).
     */
    public function aprovar(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $this->requirePost();

        $docModel = new Documento();
        $doc = $docModel->find((int)$id);
        if (!$doc) {
            $this->flash('error', 'Documento não encontrado.');
            $this->redirect('/documentos');
            return;
        }

        $decisao = $this->input('decisao'); // 'aprovado' ou 'rejeitado'
        $obs = trim($this->input('aprovacao_obs', ''));

        if (!in_array($decisao, ['aprovado', 'rejeitado'])) {
            $this->flash('error', 'Decisao invalida.');
            $this->redirect("/colaboradores/{$doc['colaborador_id']}");
            return;
        }

        $before = $doc;
        $docModel->update((int)$id, [
            'aprovacao_status' => $decisao,
            'aprovado_por' => Session::get('user_id'),
            'aprovado_em' => date('Y-m-d H:i:s'),
            'aprovacao_obs' => $obs ?: null,
        ]);

        \App\Services\AuditService::registrarAlteracao('documentos', (int)$id, $before, [
            'aprovacao_status' => $decisao,
            'aprovacao_obs' => $obs,
        ]);

        LoggerMiddleware::log('editar', "Documento {$decisao}: ID {$id} (" . ($doc['arquivo_nome'] ?? '') . ")");
        $this->flash('success', "Documento {$decisao} com sucesso.");

        // Voltar para a página de origem
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (str_contains($referer, '/dashboard')) {
            $this->redirect('/dashboard');
        } else {
            $this->redirect("/colaboradores/{$doc['colaborador_id']}");
        }
    }

    /**
     * Aprovar todos os documentos pendentes de um colaborador.
     */
    public function aprovarTodos(string $colaboradorId): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $this->requirePost();

        $db = \App\Core\Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE documentos SET aprovacao_status = 'aprovado', aprovado_por = :uid, aprovado_em = NOW()
             WHERE colaborador_id = :cid AND aprovacao_status = 'pendente' AND excluido_em IS NULL"
        );
        $stmt->execute(['uid' => Session::get('user_id'), 'cid' => (int)$colaboradorId]);
        $count = $stmt->rowCount();

        LoggerMiddleware::log('editar', "Aprovação em massa: {$count} documentos aprovados para colaborador ID {$colaboradorId}");
        $this->flash('success', "{$count} documentos aprovados com sucesso.");
        $this->redirect("/colaboradores/{$colaboradorId}");
    }

    /**
     * OCR: analisar PDF via upload AJAX e retornar dados extraídos.
     */
    public function ocrAnalise(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'Nenhum arquivo enviado.']);
            return;
        }

        $tmpPath = $_FILES['arquivo']['tmp_name'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        if ($finfo->file($tmpPath) !== 'application/pdf') {
            $this->json(['error' => 'Apenas PDF.']);
            return;
        }

        try {
            $texto = \App\Services\OcrService::extrairTexto($tmpPath, 2);
            $dataEmissao = \App\Services\OcrService::extrairDataEmissao($texto);
            $dadosASO = \App\Services\OcrService::extrairDadosASO($texto);

            $this->json([
                'success' => true,
                'data_emissao' => $dataEmissao,
                'tipo_aso' => $dadosASO['tipo_aso'],
                'medico' => $dadosASO['medico'],
                'apto' => $dadosASO['apto'],
                'texto_preview' => mb_substr($texto, 0, 500),
            ]);
        } catch (\Exception $e) {
            $this->json(['error' => 'Erro no OCR: ' . $e->getMessage()]);
        }
    }

    /**
     * Tela dedicada de aprovações pendentes (todas, com paginação e busca).
     */
    public function aprovacoes(): void
    {
        \App\Middleware\RoleMiddleware::requireAdminOrSesmt();

        $db     = \App\Core\Database::getInstance();
        $search = trim($this->input('q', ''));
        $page   = max(1, (int)$this->input('page', 1));
        $perPage = 30;
        $offset  = ($page - 1) * $perPage;

        $where  = "d.aprovacao_status = 'pendente'
                   AND d.status != 'obsoleto' AND d.excluido_em IS NULL
                   AND c.excluido_em IS NULL AND c.status = 'ativo'";
        $params = [];

        if ($search !== '') {
            $where .= " AND (c.nome_completo LIKE :q OR td.nome LIKE :q2)";
            $params['q']  = "%{$search}%";
            $params['q2'] = "%{$search}%";
        }

        $countStmt = $db->prepare(
            "SELECT COUNT(*) FROM documentos d
             JOIN colaboradores c ON d.colaborador_id = c.id
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE {$where}"
        );
        $countStmt->execute($params);
        $total      = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($total / $perPage));

        $sql = "SELECT d.id, d.arquivo_nome, d.data_emissao, d.criado_em,
                       c.id as colaborador_id, c.nome_completo,
                       td.nome as tipo_nome, u.nome as enviado_por_nome
                FROM documentos d
                JOIN colaboradores c ON d.colaborador_id = c.id
                JOIN tipos_documento td ON d.tipo_documento_id = td.id
                LEFT JOIN usuarios u ON d.enviado_por = u.id
                WHERE {$where}
                ORDER BY d.criado_em DESC
                LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $aprovacoes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('documentos/aprovacoes', [
            'aprovacoes'  => $aprovacoes,
            'total'       => $total,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'search'      => $search,
            'pageTitle'   => 'Aprovações Pendentes',
        ]);
    }

    /**
     * Marca/desmarca documento como "Enviado ao cliente" (perfil RH/admin).
     * Não impacta indicadores do SESMT.
     */
    public function marcarEnviadoCliente(string $id): void
    {
        $perfil = Session::get('user_perfil');
        if (!in_array($perfil, ['admin', 'rh'], true)) {
            http_response_code(403);
            echo json_encode(['error' => 'Sem permissão.']);
            exit;
        }

        $marcar = $this->input('marcar') === '1';

        $model = new Documento();
        $doc = $model->find((int)$id);
        if (!$doc) {
            http_response_code(404);
            echo json_encode(['error' => 'Documento não encontrado.']);
            exit;
        }

        $update = [
            'enviado_cliente'    => $marcar ? 1 : 0,
            'enviado_cliente_em' => $marcar ? date('Y-m-d H:i:s') : null,
            'enviado_cliente_por'=> $marcar ? (int)Session::get('user_id') : null,
        ];
        $model->update((int)$id, $update);

        LoggerMiddleware::log('rh', ($marcar ? 'Marcado' : 'Desmarcado') . " 'enviado ao cliente' doc ID {$id}");

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'enviado_cliente' => $marcar ? 1 : 0]);
        exit;
    }

    /**
     * Substituir documento: cria novo registro como nova versão e marca o
     * antigo como soft-deleted apontando para o novo via substituido_por.
     * O novo documento inicia com enviado_cliente=0 (RH precisa reenviar).
     */
    public function substituir(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $oldModel = new Documento();
        $oldDoc = $oldModel->find((int)$id);
        if (!$oldDoc) {
            $this->flash('error', 'Documento original não encontrado.');
            $this->redirect('/documentos');
            return;
        }

        $colaboradorId = (int)$oldDoc['colaborador_id'];
        $tipoDocumentoId = (int)$oldDoc['tipo_documento_id'];

        $dataEmissao  = $this->input('data_emissao');
        $dataValidade = $this->input('data_validade') ?: null;

        if (!$dataEmissao) {
            $this->flash('error', 'Data de emissão obrigatória.');
            $this->redirect("/colaboradores/{$colaboradorId}");
            return;
        }

        if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Arquivo inválido.');
            $this->redirect("/colaboradores/{$colaboradorId}");
            return;
        }

        $file = $_FILES['arquivo'];
        $config = require dirname(__DIR__) . '/config/app.php';
        if ($file['size'] > ($config['upload']['max_size'] ?? 10485760)) {
            $this->flash('error', 'Arquivo excede o tamanho máximo.');
            $this->redirect("/colaboradores/{$colaboradorId}");
            return;
        }

        $colabModel = new Colaborador();
        $colab = $colabModel->find($colaboradorId);
        $tipoModel = new TipoDocumento();
        $tipo = $tipoModel->find($tipoDocumentoId);

        $fileService = new FileService();
        $pastaNome = $fileService->getDiretorioColaborador($colaboradorId, $colab['nome_completo'] ?? '');
        $uploadDir = $config['upload']['path'] . '/' . $pastaNome;
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);
        if (!is_writable($uploadDir)) @chmod($uploadDir, 0775);
        if (!is_writable($uploadDir)) {
            $this->flash('error', 'Pasta sem permissão de escrita.');
            $this->redirect("/colaboradores/{$colaboradorId}");
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $safeName = $fileService->gerarNomeArquivo(
            $colab['nome_completo'] ?? '',
            ($tipo['nome'] ?? 'Documento') . ' - v' . (int)((($oldDoc['versao'] ?? 1)) + 1),
            $dataEmissao,
            $ext
        );

        if (!@move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $safeName)) {
            $this->flash('error', 'Falha ao salvar o arquivo.');
            $this->redirect("/colaboradores/{$colaboradorId}");
            return;
        }

        $status = 'vigente';
        if ($dataValidade) {
            $daysLeft = (strtotime($dataValidade) - time()) / 86400;
            if ($daysLeft < 0) $status = 'vencido';
            elseif ($daysLeft <= 30) $status = 'proximo_vencimento';
        }

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $newId = $oldModel->create([
                'colaborador_id'    => $colaboradorId,
                'tipo_documento_id' => $tipoDocumentoId,
                'arquivo_nome'      => $file['name'],
                'arquivo_path'      => $pastaNome . '/' . $safeName,
                'arquivo_hash'      => hash_file('sha256', $uploadDir . '/' . $safeName),
                'arquivo_tamanho'   => $file['size'],
                'data_emissao'      => $dataEmissao,
                'data_validade'     => $dataValidade,
                'status'            => $status,
                'enviado_por'       => Session::get('user_id'),
                'aprovacao_status'  => 'aprovado',
                'aprovado_por'      => Session::get('user_id'),
                'aprovado_em'       => date('Y-m-d H:i:s'),
                'documento_pai_id'  => $oldDoc['documento_pai_id'] ?: (int)$id,
                'versao'            => (int)($oldDoc['versao'] ?? 1) + 1,
                'enviado_cliente'   => 0,
            ]);

            // Soft-delete o antigo + aponta substituido_por
            $oldModel->update((int)$id, [
                'excluido_em'      => date('Y-m-d H:i:s'),
                'substituido_por'  => (int)$newId,
                'status'           => 'obsoleto',
            ]);

            $db->commit();
            LoggerMiddleware::log('substituir', "Documento ID {$id} substituído pelo {$newId}");

            // Fase 2: dispara recálculo de pendências para esse colaborador
            // (cria pendências em cada cliente onde a nova versão precisa ser reprotocolada)
            try {
                \App\Services\RhPendenciaService::recalcularColaborador($colaboradorId);
            } catch (\Throwable $e) {
                // Não bloqueia a substituição se o motor falhar
                error_log("[RH] Falha ao recalcular pendências do colab {$colaboradorId}: " . $e->getMessage());
            }

            $this->flash('success', 'Documento substituído. Pendências de reprotocolo nos clientes foram atualizadas automaticamente.');
        } catch (\Exception $e) {
            $db->rollBack();
            @unlink($uploadDir . '/' . $safeName);
            $this->flash('error', 'Erro: ' . $e->getMessage());
        }

        $this->redirect("/colaboradores/{$colaboradorId}");
    }
}
