<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Models\Colaborador;
use App\Models\Certificado;
use App\Models\Documento;
use App\Models\Cliente;
use App\Models\Obra;
use App\Services\CryptoService;
use App\Services\ValidationService;

class ColaboradorController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAny();

        $model = new Colaborador();
        $search = trim($this->input('q', ''));
        $status = $this->input('status', 'ativo');
        $clienteId = $this->input('cliente_id', '');
        $obraId = $this->input('obra_id', '');
        $format = $this->input('format', '');
        $page = max(1, (int)$this->input('page', 1));
        $perPage = 30;
        $offset = ($page - 1) * $perPage;

        // 'todos' means no status filter
        $statusFilter = ($status && $status !== 'todos') ? $status : '';

        // Build conditions
        $conditions = [];
        if ($statusFilter) $conditions['status'] = $statusFilter;
        if ($clienteId) $conditions['cliente_id'] = (int)$clienteId;
        if ($obraId) $conditions['obra_id'] = (int)$obraId;

        // JSON response for live search and lazy loading
        if ($format === 'json') {
            $jsonLimit = ($page > 1) ? $perPage : 10;
            $jsonOffset = ($page > 1) ? $offset : 0;

            if ($search) {
                $results = $model->search($search, $statusFilter, $jsonLimit, $jsonOffset);
            } else {
                $results = $model->allWithRelations($conditions, 'c.nome_completo ASC', $jsonLimit, $jsonOffset);
            }

            $this->json(array_map(fn($c) => [
                'id' => $c['id'],
                'nome_completo' => $c['nome_completo'],
                'cargo' => $c['cargo'] ?? $c['funcao'] ?? '',
                'setor' => $c['setor'] ?? '',
                'status' => $c['status'] ?? 'ativo',
                'cliente_nome' => $c['cliente_nome'] ?? '',
                'obra_nome' => $c['obra_nome'] ?? '',
            ], $results));
            return;
        }

        if ($search) {
            $colaboradores = $model->search($search, $statusFilter, $perPage, $offset);
            $total = $model->searchCount($search, $statusFilter);
        } else {
            $colaboradores = $model->allWithRelations($conditions, 'c.nome_completo ASC', $perPage, $offset);
            $total = $model->count($conditions);
        }

        // Buscar clientes e obras para os selects de filtro
        $clienteModel = new Cliente();
        $obraModel = new Obra();

        $this->view('colaboradores/index', [
            'colaboradores' => $colaboradores,
            'search'        => $search,
            'status'        => $status,
            'clienteId'     => $clienteId,
            'obraId'        => $obraId,
            'clientes'      => $clienteModel->all(['ativo' => 1], 'nome_fantasia ASC'),
            'obras'         => $obraModel->all(['status' => 'ativa'], 'nome ASC'),
            'page'          => $page,
            'totalPages'    => max(1, ceil($total / $perPage)),
            'total'         => $total,
            'pageTitle'     => 'Colaboradores',
            'isReadOnly'    => Session::get('user_perfil') === 'rh',
        ]);
    }

    public function show(string $id): void
    {
        RoleMiddleware::requireAny();

        $model = new Colaborador();
        $colab = $model->findWithRelations((int)$id);
        if (!$colab) {
            $this->redirect('/colaboradores');
        }

        // Decrypt CPF for display (masked)
        $cpfDisplay = '***.***.***-**';
        if (!empty($colab['cpf_encrypted'])) {
            try {
                $cpf = CryptoService::decrypt($colab['cpf_encrypted']);
                $cpfDisplay = substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.***-' . substr($cpf, -2);
            } catch (\Exception $e) {}
        }

        $certModel = new Certificado();
        $docModel = new Documento();

        $historico = $docModel->query(
            "SELECT l.*, u.nome as usuario_nome FROM logs_acesso l
             LEFT JOIN usuarios u ON l.usuario_id = u.id
             WHERE l.descricao LIKE :term
             ORDER BY l.criado_em DESC LIMIT 20",
            ['term' => "%{$colab['nome_completo']}%"]
        );

        $certificados = $certModel->getLatestByColaborador((int)$id);
        $documentos   = $docModel->findByColaborador((int)$id);

        // Count documents by status
        $docsVigentes = count(array_filter($documentos, fn($d) => ($d['status'] ?? '') === 'vigente'));
        $docsVencendo = count(array_filter($documentos, fn($d) => ($d['status'] ?? '') === 'proximo_vencimento'));
        $docsVencidos = count(array_filter($documentos, fn($d) => ($d['status'] ?? '') === 'vencido'));

        // Count certificates by status
        $certsVigentes = count(array_filter($certificados, fn($c) => ($c['status'] ?? '') === 'vigente'));
        $certsVencendo = count(array_filter($certificados, fn($c) => ($c['status'] ?? '') === 'proximo_vencimento'));
        $certsVencidos = count(array_filter($certificados, fn($c) => ($c['status'] ?? '') === 'vencido'));

        // Conformidade rate
        $totalItens = count($documentos) + count($certificados);
        $taxaConformidade = $totalItens > 0
            ? (($docsVigentes + $certsVigentes) / $totalItens) * 100
            : 100;

        $this->view('colaboradores/show', [
            'colab'              => $colab,
            'cpfDisplay'         => $cpfDisplay,
            'certificados'       => $certificados,
            'documentos'         => $documentos,
            'historico'          => $historico,
            'docsVigentes'       => $docsVigentes,
            'docsVencendo'       => $docsVencendo,
            'docsVencidos'       => $docsVencidos,
            'certsVigentes'      => $certsVigentes,
            'certsVencendo'      => $certsVencendo,
            'certsVencidos'      => $certsVencidos,
            'taxaConformidade'   => $taxaConformidade,
            'pageTitle'          => 'Colaboradores',
            'isReadOnly'         => Session::get('user_perfil') === 'rh',
        ]);
    }

    public function create(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $clienteModel = new Cliente();
        $obraModel = new Obra();

        $this->view('colaboradores/form', [
            'colab'     => null,
            'clientes'  => $clienteModel->all(['ativo' => 1], 'nome_fantasia ASC'),
            'obras'     => $obraModel->all(['status' => 'ativa'], 'nome ASC'),
            'pageTitle' => 'Colaboradores',
        ]);
    }

    public function store(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $cpf = preg_replace('/\D/', '', $this->input('cpf', ''));

        if ($cpf && !ValidationService::validarCPF($cpf)) {
            $this->flash('error', 'CPF inválido.');
            $this->redirect('/colaboradores/novo');
        }

        $data = [
            'nome_completo'  => trim($this->input('nome_completo', '')),
            'cpf_encrypted'  => $cpf ? CryptoService::encrypt($cpf) : null,
            'cpf_hash'       => $cpf ? CryptoService::hash($cpf) : null,
            'matricula'      => trim($this->input('matricula', '')),
            'cargo'          => trim($this->input('cargo', '')),
            'funcao'         => trim($this->input('funcao', '')),
            'setor'          => trim($this->input('setor', '')),
            'cliente_id'     => $this->input('cliente_id') ?: null,
            'obra_id'        => $this->input('obra_id') ?: null,
            'data_admissao'  => $this->input('data_admissao') ?: null,
            'status'         => $this->input('status', 'ativo'),
            'unidade'        => trim($this->input('unidade', '')),
            'data_nascimento'=> $this->input('data_nascimento') ?: null,
            'telefone'       => trim($this->input('telefone', '')),
            'email'          => trim($this->input('email', '')),
        ];

        $model = new Colaborador();
        $id = $model->create($data);

        LoggerMiddleware::log('criar', "Colaborador criado: {$data['nome_completo']} (ID: {$id})");
        $this->flash('success', 'Colaborador cadastrado com sucesso.');
        $this->redirect("/colaboradores/{$id}");
    }

    public function edit(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $model = new Colaborador();
        $colab = $model->find((int)$id);
        if (!$colab) {
            $this->redirect('/colaboradores');
        }

        // Decrypt CPF for edit form
        if (!empty($colab['cpf_encrypted'])) {
            try {
                $colab['cpf_plain'] = CryptoService::decrypt($colab['cpf_encrypted']);
            } catch (\Exception $e) {
                $colab['cpf_plain'] = '';
            }
        }

        $clienteModel = new Cliente();
        $obraModel = new Obra();

        $this->view('colaboradores/form', [
            'colab'     => $colab,
            'clientes'  => $clienteModel->all(['ativo' => 1], 'nome_fantasia ASC'),
            'obras'     => $obraModel->all(['status' => 'ativa'], 'nome ASC'),
            'pageTitle' => 'Colaboradores',
        ]);
    }

    public function update(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $cpf = preg_replace('/\D/', '', $this->input('cpf', ''));

        if ($cpf && !ValidationService::validarCPF($cpf)) {
            $this->flash('error', 'CPF inválido.');
            $this->redirect("/colaboradores/{$id}/editar");
        }

        $data = [
            'nome_completo'  => trim($this->input('nome_completo', '')),
            'matricula'      => trim($this->input('matricula', '')),
            'cargo'          => trim($this->input('cargo', '')),
            'funcao'         => trim($this->input('funcao', '')),
            'setor'          => trim($this->input('setor', '')),
            'cliente_id'     => $this->input('cliente_id') ?: null,
            'obra_id'        => $this->input('obra_id') ?: null,
            'data_admissao'  => $this->input('data_admissao') ?: null,
            'data_demissao'  => $this->input('data_demissao') ?: null,
            'status'         => $this->input('status', 'ativo'),
            'unidade'        => trim($this->input('unidade', '')),
            'data_nascimento'=> $this->input('data_nascimento') ?: null,
            'telefone'       => trim($this->input('telefone', '')),
            'email'          => trim($this->input('email', '')),
        ];

        if ($cpf) {
            $data['cpf_encrypted'] = CryptoService::encrypt($cpf);
            $data['cpf_hash'] = CryptoService::hash($cpf);
        }

        $model = new Colaborador();
        $before = $model->find((int)$id);
        $model->update((int)$id, $data);

        // Audit diff
        \App\Services\AuditService::registrarAlteracao('colaboradores', (int)$id, $before, $data);

        LoggerMiddleware::log('editar', "Colaborador atualizado: {$data['nome_completo']} (ID: {$id})");
        $this->flash('success', 'Colaborador atualizado com sucesso.');
        $this->redirect("/colaboradores/{$id}");
    }

    public function downloadZip(string $id): void
    {
        RoleMiddleware::requireAny();

        $model = new Colaborador();
        $colab = $model->find((int)$id);
        if (!$colab) {
            $this->flash('error', 'Colaborador nao encontrado.');
            $this->redirect('/colaboradores');
            return;
        }

        $docModel = new Documento();
        $documentos = $docModel->findByColaborador((int)$id);

        if (empty($documentos)) {
            $this->flash('warning', 'Este colaborador nao possui documentos.');
            $this->redirect("/colaboradores/{$id}");
            return;
        }

        $config = require dirname(__DIR__) . '/config/app.php';
        $uploadPath = $config['upload']['path'];

        $zipName = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $colab['nome_completo']);
        $zipName = str_replace(' ', '_', $zipName);
        $tmpFile = tempnam(sys_get_temp_dir(), 'sesmt_zip_');

        $zip = new \ZipArchive();
        if ($zip->open($tmpFile, \ZipArchive::OVERWRITE) !== true) {
            $this->flash('error', 'Erro ao criar arquivo ZIP.');
            $this->redirect("/colaboradores/{$id}");
            return;
        }

        $added = 0;
        foreach ($documentos as $doc) {
            $filePath = $uploadPath . '/' . $doc['arquivo_path'];
            if (!file_exists($filePath)) continue;

            $categoria = strtoupper($doc['categoria'] ?? 'OUTROS');
            $fileName = $doc['arquivo_nome'];
            $entryName = "{$categoria}/{$fileName}";

            // Avoid duplicate names inside the same category
            $counter = 1;
            while ($zip->locateName($entryName) !== false) {
                $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                $base = pathinfo($fileName, PATHINFO_FILENAME);
                $entryName = "{$categoria}/{$base}_{$counter}.{$ext}";
                $counter++;
            }

            $zip->addFile($filePath, $entryName);
            $added++;
        }

        $zip->close();

        if ($added === 0) {
            unlink($tmpFile);
            $this->flash('warning', 'Nenhum arquivo encontrado no servidor.');
            $this->redirect("/colaboradores/{$id}");
            return;
        }

        LoggerMiddleware::log('download', "Download ZIP: {$added} documentos de {$colab['nome_completo']}");

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="Documentos_' . $zipName . '.zip"');
        header('Content-Length: ' . filesize($tmpFile));
        header('Cache-Control: no-cache, must-revalidate');
        readfile($tmpFile);
        unlink($tmpFile);
        exit;
    }

    /**
     * Bulk update de setor, cargo, funcao, cliente ou obra para multiplos colaboradores.
     */
    public function bulkUpdate(): void
    {
        RoleMiddleware::requireAdminOrSesmt();
        $this->requirePost();

        $ids = $this->input('colaborador_ids');
        $campo = $this->input('campo');
        $valor = trim($this->input('valor', ''));

        if (empty($ids) || !is_array($ids) || empty($campo)) {
            $this->flash('error', 'Selecione colaboradores e o campo a atualizar.');
            $this->redirect('/colaboradores');
            return;
        }

        $camposPermitidos = ['cargo', 'funcao', 'setor', 'status', 'cliente_id', 'obra_id', 'unidade'];
        if (!in_array($campo, $camposPermitidos)) {
            $this->flash('error', 'Campo invalido.');
            $this->redirect('/colaboradores');
            return;
        }

        $model = new Colaborador();
        $db = \App\Core\Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $params = [$valor];
        foreach ($ids as $id) {
            $params[] = (int)$id;
        }

        $stmt = $db->prepare(
            "UPDATE colaboradores SET {$campo} = ? WHERE id IN ({$placeholders}) AND excluido_em IS NULL"
        );
        $stmt->execute($params);
        $affected = $stmt->rowCount();

        LoggerMiddleware::log('editar', "Bulk update: {$campo} = '{$valor}' para {$affected} colaboradores");

        $this->flash('success', "{$affected} colaboradores atualizados ({$campo}).");
        $this->redirect('/colaboradores');
    }

    public function destroy(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $model = new Colaborador();
        $colab = $model->find((int)$id);
        if ($colab) {
            // Soft delete: set excluido_em instead of hard delete
            $model->update((int)$id, [
                'excluido_em' => date('Y-m-d H:i:s'),
            ]);
            LoggerMiddleware::log('excluir', "Colaborador movido para lixeira: {$colab['nome_completo']} (ID: {$id})");
            $this->flash('success', 'Colaborador movido para a lixeira.');
        }
        $this->redirect('/colaboradores');
    }
}
