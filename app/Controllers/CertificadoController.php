<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Models\Certificado;
use App\Models\TipoCertificado;
use App\Models\Colaborador;
use App\Models\Ministrante;
use App\Services\CryptoService;

class CertificadoController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $colabModel = new Colaborador();
        $tipoModel = new TipoCertificado();
        $mostrarInativos = (int)$this->input('mostrar_inativos', 0);
        $statusFilter = $this->input('status', '');
        $search = trim($this->input('q', ''));

        $colaboradores = $mostrarInativos
            ? $colabModel->all([], 'nome_completo ASC')
            : $colabModel->all(['status' => 'ativo'], 'nome_completo ASC');
        $tipos = $tipoModel->all(['ativo' => 1], 'codigo ASC');

        $db = \App\Core\Database::getInstance();

        // Build base where clause (sem status filter para os contadores)
        $whereBase = "cert.excluido_em IS NULL AND c.status = 'ativo'";
        $paramsBase = [];
        if ($search !== '') {
            $whereBase .= " AND (c.nome_completo LIKE :search OR tc.codigo LIKE :search2)";
            $paramsBase['search'] = "%{$search}%";
            $paramsBase['search2'] = "%{$search}%";
        }

        // Contadores por status (respeitam a busca atual)
        $countSql = "SELECT cert.status, COUNT(*) as total
                     FROM certificados cert
                     JOIN colaboradores c ON cert.colaborador_id = c.id
                     JOIN tipos_certificado tc ON cert.tipo_certificado_id = tc.id
                     WHERE {$whereBase} AND cert.arquivo_assinado IS NOT NULL
                     GROUP BY cert.status";
        $cStmt = $db->prepare($countSql);
        $cStmt->execute($paramsBase);
        $contadores = [];
        foreach ($cStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $contadores[$row['status']] = (int)$row['total'];
        }
        $contadores['total'] = array_sum($contadores);

        // Listagem de certificados emitidos (com filtro)
        $page = max(1, (int)$this->input('page', 1));
        $perPage = 30;
        $offset = (int)(($page - 1) * $perPage);

        // Build query with optional status filter
        $where = $whereBase;
        $params = $paramsBase;
        if ($statusFilter && in_array($statusFilter, ['vigente', 'proximo_vencimento', 'vencido'])) {
            $where .= " AND cert.status = :status";
            $params['status'] = $statusFilter;
        }

        // Count
        $countStmt = $db->prepare("SELECT COUNT(*) FROM certificados cert JOIN colaboradores c ON cert.colaborador_id = c.id JOIN tipos_certificado tc ON cert.tipo_certificado_id = tc.id WHERE {$where}");
        $countStmt->execute($params);
        $totalRecords = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRecords / $perPage));

        // Fetch
        $sql = "SELECT cert.*, c.nome_completo, tc.codigo as tipo_codigo, tc.titulo as tipo_titulo, tc.duracao, tc.validade_meses
                FROM certificados cert
                JOIN colaboradores c ON cert.colaborador_id = c.id
                JOIN tipos_certificado tc ON cert.tipo_certificado_id = tc.id
                WHERE {$where}
                ORDER BY c.nome_completo, tc.codigo
                LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $certificadosList = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('certificados/index', [
            'colaboradores'    => $colaboradores,
            'tipos'            => $tipos,
            'mostrarInativos'  => $mostrarInativos,
            'contadores'       => $contadores,
            'certificadosList' => $certificadosList,
            'status'           => $statusFilter,
            'search'           => $search,
            'page'             => $page,
            'totalPages'       => $totalPages,
            'pageTitle'        => 'Certificados',
        ]);
    }

    public function emitir(string $colaboradorId): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $colabModel = new Colaborador();
        $colab = $colabModel->find((int)$colaboradorId);
        if (!$colab) {
            $this->redirect('/certificados');
        }

        $tipoModel = new TipoCertificado();
        $tipos = $tipoModel->all(['ativo' => 1], 'codigo ASC');

        $certModel = new Certificado();
        $certs = $certModel->findByColaborador((int)$colaboradorId);

        $ministranteModel = new Ministrante();
        $ministrantes = $ministranteModel->all(['ativo' => 1], 'nome ASC');

        // Decrypt CPF for certificate display
        $cpfFormatado = '***.***.***-**';
        if (!empty($colab['cpf_encrypted'])) {
            try {
                $cpfRaw = CryptoService::decrypt($colab['cpf_encrypted']);
                if (strlen($cpfRaw) === 11) {
                    $cpfFormatado = substr($cpfRaw, 0, 3) . '.' . substr($cpfRaw, 3, 3) . '.' . substr($cpfRaw, 6, 3) . '-' . substr($cpfRaw, 9, 2);
                }
            } catch (\Exception $e) {}
        }

        $this->view('certificados/emitir', [
            'colab'         => $colab,
            'tipos'         => $tipos,
            'certs'         => $certs,
            'ministrantes'  => $ministrantes,
            'cpfFormatado'  => $cpfFormatado,
            'pageTitle'     => 'Certificados',
        ]);
    }

    public function store(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $colaboradorId = (int)$this->input('colaborador_id');
        $tipoCertificadoId = (int)$this->input('tipo_certificado_id');
        $dataRealizacao = $this->input('data_realizacao');
        $dataRealizacaoFim = $this->input('data_realizacao_fim') ?: null;
        $dataEmissao = $this->input('data_emissao');
        $ministranteId = (int)$this->input('ministrante_id') ?: null;

        if (!$colaboradorId || !$tipoCertificadoId || !$dataRealizacao || !$dataEmissao) {
            $this->flash('error', 'Preencha todos os campos.');
            $this->redirect("/certificados/emitir/{$colaboradorId}");
        }

        $tipoModel = new TipoCertificado();
        $tipo = $tipoModel->find($tipoCertificadoId);

        $dataValidade = date('Y-m-d', strtotime("{$dataEmissao} + {$tipo['validade_meses']} months"));

        $status = 'vigente';
        $daysLeft = (strtotime($dataValidade) - time()) / 86400;
        if ($daysLeft < 0) $status = 'vencido';
        elseif ($daysLeft <= 30) $status = 'proximo_vencimento';

        $certData = [
            'colaborador_id'      => $colaboradorId,
            'tipo_certificado_id' => $tipoCertificadoId,
            'data_realizacao'     => $dataRealizacao,
            'data_emissao'        => $dataEmissao,
            'data_validade'       => $dataValidade,
            'status'              => $status,
            'criado_por'          => Session::get('user_id'),
        ];

        if ($dataRealizacaoFim) {
            $certData['data_realizacao_fim'] = $dataRealizacaoFim;
        }

        if ($ministranteId) {
            $certData['ministrante_id'] = $ministranteId;
        }

        $certModel = new Certificado();
        $id = $certModel->create($certData);

        $colabModel = new Colaborador();
        $colab = $colabModel->find($colaboradorId);
        LoggerMiddleware::log('criar', "Certificado emitido: {$tipo['codigo']} para {$colab['nome_completo']} (Cert ID: {$id})");

        $this->flash('success', "Certificado {$tipo['codigo']} emitido com sucesso.");
        $this->redirect("/colaboradores/{$colaboradorId}");
    }

    public function preview(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $certModel = new Certificado();
        $cert = $certModel->query(
            "SELECT cert.*, tc.*, c.nome_completo, c.cpf_encrypted, c.funcao, c.cargo,
                    m.nome as ministrante_nome, m.cargo_titulo as ministrante_cargo, m.registro as ministrante_registro
             FROM certificados cert
             JOIN tipos_certificado tc ON cert.tipo_certificado_id = tc.id
             JOIN colaboradores c ON cert.colaborador_id = c.id
             LEFT JOIN ministrantes m ON cert.ministrante_id = m.id
             WHERE cert.id = :id",
            ['id' => (int)$id]
        );

        if (empty($cert)) {
            $this->redirect('/certificados');
        }

        // Decrypt CPF for certificate display
        $cpfFormatado = '***.***.***-**';
        if (!empty($cert[0]['cpf_encrypted'])) {
            try {
                $cpfRaw = CryptoService::decrypt($cert[0]['cpf_encrypted']);
                if (strlen($cpfRaw) === 11) {
                    $cpfFormatado = substr($cpfRaw, 0, 3) . '.' . substr($cpfRaw, 3, 3) . '.' . substr($cpfRaw, 6, 3) . '-' . substr($cpfRaw, 9, 2);
                }
            } catch (\Exception $e) {}
        }

        $this->view('certificados/preview', [
            'cert'          => $cert[0],
            'cpfFormatado'  => $cpfFormatado,
            'pageTitle'     => 'Certificados',
        ], '');
    }

    public function tiposJson(): void
    {
        $tipoModel = new TipoCertificado();
        $tipos = $tipoModel->all(['ativo' => 1], 'id ASC');
        $this->json($tipos);
    }

    public function dadosJson(string $colaboradorId): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $certModel = new Certificado();
        $certs = $certModel->findByColaborador((int)$colaboradorId);

        $colabModel = new Colaborador();
        $colab = $colabModel->find((int)$colaboradorId);

        $this->json([
            'colaborador'  => $colab,
            'certificados' => $certs,
        ]);
    }

    public function ministrantesJson(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $ministranteModel = new Ministrante();
        $ministrantes = $ministranteModel->all(['ativo' => 1], 'nome ASC');
        $this->json($ministrantes);
    }

    /**
     * Exibe o formulário de edição de um certificado emitido.
     */
    public function editForm(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $certModel = new Certificado();
        $cert = $certModel->find((int)$id);

        if (!$cert || $cert['excluido_em']) {
            $this->flash('error', 'Certificado não encontrado.');
            $this->redirect('/certificados');
        }

        $ministranteModel = new Ministrante();
        $ministrantes = $ministranteModel->all(['ativo' => 1], 'nome ASC');

        $this->view('certificados/editar', [
            'cert'        => $cert,
            'ministrantes' => $ministrantes,
            'pageTitle'   => 'Editar Certificado',
        ]);
    }

    /**
     * Salva a edição de um certificado.
     */
    public function update(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $certModel = new Certificado();
        $cert = $certModel->find((int)$id);

        if (!$cert || $cert['excluido_em']) {
            $this->flash('error', 'Certificado não encontrado.');
            $this->redirect('/certificados');
        }

        $dataRealizacao    = $this->input('data_realizacao');
        $dataRealizacaoFim = $this->input('data_realizacao_fim') ?: null;
        $dataEmissao       = $this->input('data_emissao');
        $ministranteId     = (int)$this->input('ministrante_id') ?: null;

        if (!$dataRealizacao || !$dataEmissao) {
            $this->flash('error', 'Preencha os campos obrigatórios.');
            $this->redirect("/certificados/{$id}/editar");
        }

        $tipoModel = new TipoCertificado();
        $tipo = $tipoModel->find((int)$cert['tipo_certificado_id']);
        $dataValidade = date('Y-m-d', strtotime("{$dataEmissao} + {$tipo['validade_meses']} months"));

        $daysLeft = (strtotime($dataValidade) - time()) / 86400;
        $status = 'vigente';
        if ($daysLeft < 0) $status = 'vencido';
        elseif ($daysLeft <= 30) $status = 'proximo_vencimento';

        $updateData = [
            'data_realizacao'     => $dataRealizacao,
            'data_realizacao_fim' => $dataRealizacaoFim,
            'data_emissao'        => $dataEmissao,
            'data_validade'       => $dataValidade,
            'status'              => $status,
            'ministrante_id'      => $ministranteId,
        ];

        $certModel->update((int)$id, $updateData);
        DashboardController::clearCache();
        LoggerMiddleware::log('editar', "Certificado editado (ID: {$id})");

        $this->flash('success', 'Certificado atualizado com sucesso.');
        $this->redirect("/colaboradores/{$cert['colaborador_id']}");
    }

    /**
     * Soft-delete de um certificado.
     */
    public function destroy(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $certModel = new Certificado();
        $cert = $certModel->find((int)$id);

        if (!$cert || $cert['excluido_em']) {
            $this->flash('error', 'Certificado não encontrado.');
            $this->redirect('/certificados');
        }

        $certModel->update((int)$id, ['excluido_em' => date('Y-m-d H:i:s')]);
        DashboardController::clearCache();
        LoggerMiddleware::log('excluir', "Certificado excluído (ID: {$id})");

        $this->flash('success', 'Certificado movido para a lixeira.');
        $this->redirect("/colaboradores/{$cert['colaborador_id']}");
    }

    /**
     * Upload do PDF assinado de um certificado.
     * Vincula o arquivo ao certificado e o contabiliza nos totais.
     */
    public function uploadAssinado(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $certModel = new Certificado();
        $cert = $certModel->find((int)$id);

        if (!$cert || $cert['excluido_em']) {
            $this->flash('error', 'Certificado não encontrado.');
            $this->redirect('/certificados');
        }

        if (empty($_FILES['arquivo_assinado']) || $_FILES['arquivo_assinado']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Selecione um arquivo PDF para enviar.');
            $this->redirect("/colaboradores/{$cert['colaborador_id']}");
        }

        $file = $_FILES['arquivo_assinado'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if ($mime !== 'application/pdf') {
            $this->flash('error', 'Apenas arquivos PDF são permitidos.');
            $this->redirect("/colaboradores/{$cert['colaborador_id']}");
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            $this->flash('error', 'Arquivo excede o tamanho máximo de 10MB.');
            $this->redirect("/colaboradores/{$cert['colaborador_id']}");
        }

        $config = require dirname(__DIR__) . '/config/app.php';
        $colabModel = new Colaborador();
        $colab = $colabModel->find((int)$cert['colaborador_id']);
        $fileService = new \App\Services\FileService();
        $pastaNome = $fileService->getDiretorioColaborador((int)$cert['colaborador_id'], $colab['nome_completo'] ?? '');
        $uploadDir = $config['upload']['path'] . '/' . $pastaNome;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0750, true);
        }

        $tipoModel = new TipoCertificado();
        $tipo = $tipoModel->find((int)$cert['tipo_certificado_id']);
        $safeName = $fileService->gerarNomeArquivo(
            $colab['nome_completo'] ?? '',
            ($tipo['codigo'] ?? 'CERT') . ' - Assinado',
            $cert['data_emissao'],
            'pdf'
        );

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $safeName)) {
            $this->flash('error', 'Falha ao salvar o arquivo.');
            $this->redirect("/colaboradores/{$cert['colaborador_id']}");
        }

        $certModel->update((int)$id, [
            'arquivo_assinado' => $pastaNome . '/' . $safeName,
            'assinado_em'      => date('Y-m-d H:i:s'),
        ]);

        DashboardController::clearCache();
        LoggerMiddleware::log('upload', "PDF assinado do certificado ID {$id} enviado por " . (Session::get('user_name') ?? 'sistema'));

        $this->flash('success', 'PDF assinado vinculado ao certificado com sucesso.');
        $this->redirect("/colaboradores/{$cert['colaborador_id']}");
    }
}
