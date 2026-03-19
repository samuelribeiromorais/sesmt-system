<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
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
        $page = max(1, (int)$this->input('page', 1));
        $perPage = 30;
        $offset = ($page - 1) * $perPage;

        // Contadores
        $contadores = [
            'total' => 0,
            'vigente' => 0,
            'proximo_vencimento' => 0,
            'vencido' => 0,
        ];
        $countRows = $model->query(
            "SELECT status, COUNT(*) as total FROM documentos WHERE status != 'obsoleto' GROUP BY status"
        );
        foreach ($countRows as $row) {
            $contadores[$row['status']] = (int)$row['total'];
            $contadores['total'] += (int)$row['total'];
        }

        // Query principal
        $sql = "SELECT d.*, c.nome_completo, td.nome as tipo_nome, td.categoria
                FROM documentos d
                JOIN colaboradores c ON d.colaborador_id = c.id
                JOIN tipos_documento td ON d.tipo_documento_id = td.id
                WHERE d.status != 'obsoleto'";
        $countSql = "SELECT COUNT(*) as total FROM documentos d
                     JOIN colaboradores c ON d.colaborador_id = c.id
                     JOIN tipos_documento td ON d.tipo_documento_id = td.id
                     WHERE d.status != 'obsoleto'";
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
            $sql .= " AND c.nome_completo LIKE :q";
            $countSql .= " AND c.nome_completo LIKE :q";
            $params['q'] = "%{$search}%";
        }

        $totalResult = $model->query($countSql, $params);
        $totalItems = (int)($totalResult[0]['total'] ?? 0);
        $totalPages = max(1, ceil($totalItems / $perPage));

        $sql .= " ORDER BY d.criado_em DESC LIMIT {$perPage} OFFSET {$offset}";
        $documentos = $model->query($sql, $params);

        $this->view('documentos/index', [
            'documentos' => $documentos,
            'contadores' => $contadores,
            'status'     => $status,
            'categoria'  => $categoria,
            'search'     => $search,
            'page'       => $page,
            'totalPages' => $totalPages,
            'pageTitle'  => 'Documentos',
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

        // Validate file
        if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Selecione um arquivo PDF para enviar.');
            $this->redirect("/documentos/upload/{$colaboradorId}");
        }

        $file = $_FILES['arquivo'];
        $config = require dirname(__DIR__) . '/config/app.php';

        // Check MIME type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $config['upload']['allowed_types'])) {
            $this->flash('error', 'Apenas arquivos PDF sao permitidos.');
            $this->redirect("/documentos/upload/{$colaboradorId}");
        }

        // Check size
        if ($file['size'] > $config['upload']['max_size']) {
            $this->flash('error', 'Arquivo excede o tamanho maximo de 10MB.');
            $this->redirect("/documentos/upload/{$colaboradorId}");
        }

        // Calculate hash
        $fileHash = hash_file('sha256', $file['tmp_name']);

        // Create upload directory
        $uploadDir = $config['upload']['path'] . '/' . $colaboradorId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0750, true);
        }

        // Generate safe filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeName = $fileHash . '.' . strtolower($ext);
        $destPath = $uploadDir . '/' . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $this->flash('error', 'Erro ao salvar o arquivo. Tente novamente.');
            $this->redirect("/documentos/upload/{$colaboradorId}");
        }

        // Calculate validity
        $tipoModel = new TipoDocumento();
        $tipo = $tipoModel->find($tipoDocumentoId);
        $dataValidade = null;
        if ($tipo && $tipo['validade_meses']) {
            $dataValidade = date('Y-m-d', strtotime("{$dataEmissao} + {$tipo['validade_meses']} months"));
        }

        // Mark previous documents of same type as obsolete
        $docModel = new Documento();
        $docModel->markAsObsolete($colaboradorId, $tipoDocumentoId);

        // Determine status
        $status = 'vigente';
        if ($dataValidade) {
            $daysLeft = (strtotime($dataValidade) - time()) / 86400;
            if ($daysLeft < 0) $status = 'vencido';
            elseif ($daysLeft <= 30) $status = 'proximo_vencimento';
        }

        // Save to database
        $id = $docModel->create([
            'colaborador_id'    => $colaboradorId,
            'tipo_documento_id' => $tipoDocumentoId,
            'arquivo_nome'      => $file['name'],
            'arquivo_path'      => $colaboradorId . '/' . $safeName,
            'arquivo_hash'      => $fileHash,
            'arquivo_tamanho'   => $file['size'],
            'data_emissao'      => $dataEmissao,
            'data_validade'     => $dataValidade,
            'status'            => $status,
            'observacoes'       => $observacoes,
            'enviado_por'       => Session::get('user_id'),
        ]);

        $colabModel = new Colaborador();
        $colab = $colabModel->find($colaboradorId);
        LoggerMiddleware::log('upload', "Documento enviado: {$tipo['nome']} para {$colab['nome_completo']} (Doc ID: {$id})");

        $this->flash('success', 'Documento enviado com sucesso.');
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
        $filePath = $config['upload']['path'] . '/' . $doc['arquivo_path'];

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
        $filePath = $config['upload']['path'] . '/' . $doc['arquivo_path'];

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

        $this->view('documentos/show', [
            'doc'       => $doc,
            'pageTitle' => 'Documentos',
        ]);
    }

    public function destroy(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $docModel = new Documento();
        $doc = $docModel->find((int)$id);
        if ($doc) {
            $config = require dirname(__DIR__) . '/config/app.php';
            $filePath = $config['upload']['path'] . '/' . $doc['arquivo_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $docModel->delete((int)$id);
            LoggerMiddleware::log('excluir', "Documento excluido: {$doc['arquivo_nome']} (ID: {$id})");
            $this->flash('success', 'Documento excluido.');
        }
        $this->redirect("/colaboradores/{$doc['colaborador_id']}");
    }
}
