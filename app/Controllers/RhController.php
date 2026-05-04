<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Middleware\RoleMiddleware;
use App\Models\RhProtocolo;
use App\Models\Cliente;

class RhController extends Controller
{
    // ------------------------------------------------------------------
    // GET /rh — painel principal com abas pendentes/enviados/confirmados
    // ------------------------------------------------------------------
    public function index(): void
    {
        RoleMiddleware::requireRhOrSesmt();

        $statusFiltro = $this->input('status', 'pendente');
        $clienteId    = (int)$this->input('cliente_id', 0);
        $tipoId       = (int)$this->input('tipo_id', 0);
        $q            = trim($this->input('q', ''));

        $filtros = [
            'status'     => $statusFiltro,
            'cliente_id' => $clienteId ?: null,
            'tipo_id'    => $tipoId    ?: null,
            'q'          => $q         ?: null,
        ];

        $linhas      = RhProtocolo::listarComFiltros($filtros);
        $contadores  = RhProtocolo::contadores();

        // Lista de clientes para o filtro
        $db       = Database::getInstance();
        $clientes = $db->query("SELECT id, nome_fantasia FROM clientes WHERE ativo = 1 ORDER BY nome_fantasia")->fetchAll(\PDO::FETCH_ASSOC);
        $tipos    = $db->query("SELECT id, nome FROM tipos_documento WHERE ativo = 1 ORDER BY nome")->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('rh/index', [
            'linhas'      => $linhas,
            'contadores'  => $contadores,
            'statusFiltro' => $statusFiltro,
            'clienteId'   => $clienteId,
            'tipoId'      => $tipoId,
            'q'           => $q,
            'clientes'    => $clientes,
            'tipos'       => $tipos,
            'pageTitle'   => 'Painel RH — Reprotocolo',
        ]);
    }

    // ------------------------------------------------------------------
    // POST /rh/{docId}/marcar-enviado — modal de protocolo
    // Recebe: numero_protocolo, data_protocolo, observacoes, comprovante (file)
    // ------------------------------------------------------------------
    public function marcarEnviado(int $docId): void
    {
        RoleMiddleware::requireRhOrSesmt();

        $db  = Database::getInstance();
        $doc = $db->prepare(
            "SELECT d.*, c.obra_id, c.id AS colab_id, c.nome_completo,
                    o.cliente_id, o.id AS obra_id_val,
                    td.nome AS tipo_nome, td.id AS tipo_id
             FROM documentos d
             JOIN colaboradores c ON d.colaborador_id = c.id
             JOIN obras o ON c.obra_id = o.id
             JOIN tipos_documento td ON d.tipo_documento_id = td.id
             WHERE d.id = :id"
        );
        $doc->execute(['id' => $docId]);
        $row = $doc->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            $this->json(['success' => false, 'error' => 'Documento não encontrado.'], 404);
            return;
        }

        $userId         = (int)Session::get('user_id');
        $numeroProtocolo = trim($this->input('numero_protocolo', ''));
        $dataProtocolo  = $this->input('data_protocolo', date('Y-m-d'));
        $observacoes    = trim($this->input('observacoes', ''));

        // Valida data
        if ($dataProtocolo && strtotime($dataProtocolo) > time() + 86400) {
            $this->json(['success' => false, 'error' => 'Data do protocolo não pode ser futura.']);
            return;
        }

        $temArquivo = isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] === UPLOAD_ERR_OK;

        try {
            $db->beginTransaction();

            $protocoloId = RhProtocolo::marcarEnviado(
                docId:           $docId,
                clienteId:       (int)$row['cliente_id'],
                colaboradorId:   (int)$row['colab_id'],
                tipoId:          (int)$row['tipo_id'],
                obraId:          (int)$row['obra_id_val'],
                userId:          $userId,
                numeroProtocolo: $numeroProtocolo,
                dataProtocolo:   $dataProtocolo,
                observacoes:     $observacoes,
                semComprovante:  !$temArquivo
            );

            if ($temArquivo) {
                $this->processarComprovante($protocoloId, $userId);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            $this->json(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()], 500);
            return;
        }

        $this->json(['success' => true, 'protocolo_id' => $protocoloId]);
    }

    // ------------------------------------------------------------------
    // POST /rh/protocolo/{id}/confirmar
    // ------------------------------------------------------------------
    public function confirmar(int $id): void
    {
        RoleMiddleware::requireRhOrSesmt();

        $userId = (int)Session::get('user_id');
        $ok     = RhProtocolo::confirmar($id, $userId);

        if (self::requestIsJson()) {
            $this->json(['success' => $ok]);
        } else {
            $this->flash($ok ? 'success' : 'error', $ok ? 'Protocolo confirmado.' : 'Não foi possível confirmar (status inválido).');
            $this->redirect('/rh?status=enviado');
        }
    }

    // ------------------------------------------------------------------
    // POST /rh/protocolo/{id}/rejeitar
    // ------------------------------------------------------------------
    public function rejeitar(int $id): void
    {
        RoleMiddleware::requireRhOrSesmt();

        $motivo = trim($this->input('motivo', ''));
        if ($motivo === '') {
            $this->flash('error', 'Informe o motivo da rejeição.');
            $this->redirect('/rh?status=enviado');
            return;
        }

        $userId = (int)Session::get('user_id');
        $ok     = RhProtocolo::rejeitar($id, $motivo, $userId);

        $this->flash($ok ? 'success' : 'error', $ok ? 'Protocolo rejeitado. Nova pendência criada.' : 'Não foi possível rejeitar (status inválido).');
        $this->redirect('/rh?status=enviado');
    }

    // ------------------------------------------------------------------
    // GET /rh/comprovante/{comprovanteId} — download do comprovante
    // ------------------------------------------------------------------
    public function downloadComprovante(int $comprovanteId): void
    {
        RoleMiddleware::requireRhOrSesmt();

        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM rh_protocolo_comprovantes WHERE id = :id");
        $stmt->execute(['id' => $comprovanteId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404); die('Comprovante não encontrado.');
        }

        $config = require __DIR__ . '/../config/app.php';
        $path   = $config['upload']['path'] . '/rh/comprovantes/' . $row['arquivo_path'];

        if (!file_exists($path)) {
            http_response_code(404); die('Arquivo não encontrado no disco.');
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . addslashes($row['arquivo_nome']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    // ------------------------------------------------------------------
    // Privado: salva o comprovante em disco e registra no DB
    // ------------------------------------------------------------------
    private function processarComprovante(int $protocoloId, int $userId): void
    {
        $file    = $_FILES['comprovante'];
        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        $mime    = mime_content_type($file['tmp_name']);

        if (!in_array($mime, $allowed, true)) {
            throw new \RuntimeException('Tipo de arquivo inválido para comprovante (apenas PDF, JPEG, PNG).');
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            throw new \RuntimeException('Comprovante maior que 10 MB.');
        }

        $config  = require __DIR__ . '/../config/app.php';
        $baseDir = $config['upload']['path'] . '/rh/comprovantes/' . $protocoloId;
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0775, true);
        }

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $safeName = date('Ymd_His') . '_' . substr(md5($file['name']), 0, 6) . '.' . $ext;
        $dest     = $baseDir . '/' . $safeName;

        if (!@move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('Falha ao salvar comprovante no disco.');
        }

        $hash = hash_file('sha256', $dest);

        RhProtocolo::salvarComprovante(
            protocoloId: $protocoloId,
            path:        $protocoloId . '/' . $safeName,
            nome:        $file['name'],
            hash:        $hash,
            tamanho:     $file['size'],
            userId:      $userId
        );
    }

    private static function requestIsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_contains($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'XMLHttpRequest');
    }
}
