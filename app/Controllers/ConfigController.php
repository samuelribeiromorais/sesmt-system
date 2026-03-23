<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Models\TipoDocumento;
use App\Models\TipoCertificado;
use App\Models\Ministrante;

class ConfigController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdmin();

        $tipoDocModel  = new TipoDocumento();
        $tipoCertModel = new TipoCertificado();
        $ministranteModel = new Ministrante();

        // Load tipos_certificado with ministrante name
        $db = Database::getInstance();
        $tiposCert = $db->query(
            "SELECT tc.*, m.nome as ministrante_nome
             FROM tipos_certificado tc
             LEFT JOIN ministrantes m ON tc.ministrante_id = m.id
             ORDER BY tc.codigo ASC"
        )->fetchAll();

        $this->view('config/index', [
            'pageTitle'     => 'Configuracoes',
            'tiposDocs'     => $tipoDocModel->all([], 'categoria ASC, nome ASC'),
            'tiposCerts'    => $tiposCert,
            'ministrantes'  => $ministranteModel->all([], 'nome ASC'),
            'smtp'          => [
                'host'       => $_ENV['SMTP_HOST'] ?? '',
                'port'       => $_ENV['SMTP_PORT'] ?? '587',
                'user'       => $_ENV['SMTP_USER'] ?? '',
                'from_name'  => $_ENV['SMTP_FROM_NAME'] ?? '',
                'from_email' => $_ENV['SMTP_FROM_EMAIL'] ?? '',
            ],
        ]);
    }

    // ========================================================================
    // Tipos de Documento
    // ========================================================================

    public function salvarTipoDoc(): void
    {
        RoleMiddleware::requireAdmin();

        $id = (int)$this->input('id', 0);
        $data = [
            'nome'           => trim($this->input('nome', '')),
            'categoria'      => $this->input('categoria', 'outro'),
            'validade_meses' => $this->input('validade_meses') ?: null,
            'obrigatorio'    => (int)$this->input('obrigatorio', 0),
            'descricao'      => trim($this->input('descricao', '')),
            'ativo'          => (int)$this->input('ativo', 1),
        ];

        if (empty($data['nome'])) {
            $this->flash('error', 'Nome do tipo de documento e obrigatorio.');
            $this->redirect('/configuracoes');
        }

        $model = new TipoDocumento();
        if ($id > 0) {
            $model->update($id, $data);
            LoggerMiddleware::log('editar', "Tipo de documento atualizado: {$data['nome']} (ID: {$id})");
            $this->flash('success', "Tipo de documento '{$data['nome']}' atualizado.");
        } else {
            $newId = $model->create($data);
            LoggerMiddleware::log('criar', "Tipo de documento criado: {$data['nome']} (ID: {$newId})");
            $this->flash('success', "Tipo de documento '{$data['nome']}' criado.");
        }

        $this->redirect('/configuracoes#tipos-documento');
    }

    public function excluirTipoDoc(string $id): void
    {
        RoleMiddleware::requireAdmin();

        $model = new TipoDocumento();
        $tipo = $model->find((int)$id);

        if ($tipo) {
            // Check if there are documents using this type
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT COUNT(*) FROM documentos WHERE tipo_documento_id = :id AND excluido_em IS NULL");
            $stmt->execute(['id' => (int)$id]);
            $count = (int)$stmt->fetchColumn();

            if ($count > 0) {
                // Soft deactivate instead of delete
                $model->update((int)$id, ['ativo' => 0]);
                LoggerMiddleware::log('editar', "Tipo de documento desativado (tem {$count} docs vinculados): {$tipo['nome']}");
                $this->flash('warning', "Tipo '{$tipo['nome']}' desativado (possui {$count} documento(s) vinculado(s)).");
            } else {
                $model->delete((int)$id);
                LoggerMiddleware::log('excluir', "Tipo de documento excluido: {$tipo['nome']}");
                $this->flash('success', "Tipo '{$tipo['nome']}' excluido.");
            }
        }
        $this->redirect('/configuracoes#tipos-documento');
    }

    // ========================================================================
    // Tipos de Certificado
    // ========================================================================

    public function salvarTipoCert(): void
    {
        RoleMiddleware::requireAdmin();

        $id = (int)$this->input('id', 0);
        $data = [
            'codigo'               => trim($this->input('codigo', '')),
            'titulo'               => trim($this->input('titulo', '')),
            'duracao'              => trim($this->input('duracao', '8h')),
            'validade_meses'       => (int)$this->input('validade_meses', 12),
            'tem_anuencia'         => (int)$this->input('tem_anuencia', 0),
            'tem_diego'            => (int)$this->input('tem_diego', 0),
            'conteudo_no_verso'    => (int)$this->input('conteudo_no_verso', 0),
            'conteudo_programatico'=> trim($this->input('conteudo_programatico', '')),
            'ministrante_id'       => $this->input('ministrante_id') ?: null,
            'ativo'                => (int)$this->input('ativo', 1),
        ];

        if (empty($data['codigo']) || empty($data['titulo'])) {
            $this->flash('error', 'Codigo e titulo sao obrigatorios.');
            $this->redirect('/configuracoes#tipos-certificado');
        }

        $model = new TipoCertificado();
        if ($id > 0) {
            $model->update($id, $data);
            LoggerMiddleware::log('editar', "Tipo de certificado atualizado: {$data['codigo']} (ID: {$id})");
            $this->flash('success', "Tipo de certificado '{$data['codigo']}' atualizado.");
        } else {
            $newId = $model->create($data);
            LoggerMiddleware::log('criar', "Tipo de certificado criado: {$data['codigo']} (ID: {$newId})");
            $this->flash('success', "Tipo de certificado '{$data['codigo']}' criado.");
        }

        $this->redirect('/configuracoes#tipos-certificado');
    }

    // ========================================================================
    // Ministrantes (Instrutores)
    // ========================================================================

    public function salvarMinistrante(): void
    {
        RoleMiddleware::requireAdmin();

        $id = (int)$this->input('id', 0);
        $data = [
            'nome'         => trim($this->input('nome', '')),
            'cargo_titulo' => trim($this->input('cargo_titulo', '')),
            'registro'     => trim($this->input('registro', '')),
            'ativo'        => (int)$this->input('ativo', 1),
        ];

        if (empty($data['nome']) || empty($data['cargo_titulo'])) {
            $this->flash('error', 'Nome e cargo/titulo sao obrigatorios.');
            $this->redirect('/configuracoes#ministrantes');
        }

        $model = new Ministrante();
        if ($id > 0) {
            $model->update($id, $data);
            LoggerMiddleware::log('editar', "Ministrante atualizado: {$data['nome']} (ID: {$id})");
            $this->flash('success', "Ministrante '{$data['nome']}' atualizado.");
        } else {
            $newId = $model->create($data);
            LoggerMiddleware::log('criar', "Ministrante criado: {$data['nome']} (ID: {$newId})");
            $this->flash('success', "Ministrante '{$data['nome']}' cadastrado.");
        }

        $this->redirect('/configuracoes#ministrantes');
    }

    public function excluirMinistrante(string $id): void
    {
        RoleMiddleware::requireAdmin();

        $model = new Ministrante();
        $ministrante = $model->find((int)$id);

        if ($ministrante) {
            // Check if there are certificates using this ministrante
            $db = Database::getInstance();
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM certificados WHERE ministrante_id = :id
                 UNION ALL
                 SELECT COUNT(*) FROM tipos_certificado WHERE ministrante_id = :id2"
            );
            $stmt->execute(['id' => (int)$id, 'id2' => (int)$id]);
            $counts = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $totalUsage = array_sum($counts);

            if ($totalUsage > 0) {
                $model->update((int)$id, ['ativo' => 0]);
                LoggerMiddleware::log('editar', "Ministrante desativado (vinculado a certificados): {$ministrante['nome']}");
                $this->flash('warning', "Ministrante '{$ministrante['nome']}' desativado (vinculado a certificados).");
            } else {
                $model->delete((int)$id);
                LoggerMiddleware::log('excluir', "Ministrante excluido: {$ministrante['nome']}");
                $this->flash('success', "Ministrante '{$ministrante['nome']}' excluido.");
            }
        }
        $this->redirect('/configuracoes#ministrantes');
    }

    // ========================================================================
    // Configuracao SMTP
    // ========================================================================

    public function salvarSmtp(): void
    {
        RoleMiddleware::requireAdmin();

        $envData = [
            'SMTP_HOST'       => trim($this->input('smtp_host', '')),
            'SMTP_PORT'       => trim($this->input('smtp_port', '587')),
            'SMTP_USER'       => trim($this->input('smtp_user', '')),
            'SMTP_PASS'       => trim($this->input('smtp_pass', '')),
            'SMTP_FROM_NAME'  => trim($this->input('smtp_from_name', '')),
            'SMTP_FROM_EMAIL' => trim($this->input('smtp_from_email', '')),
        ];

        // Update .env file
        $envFile = dirname(__DIR__, 2) . '/.env';
        if (file_exists($envFile)) {
            $content = file_get_contents($envFile);

            foreach ($envData as $key => $value) {
                // Skip password if empty (don't overwrite existing)
                if ($key === 'SMTP_PASS' && empty($value)) {
                    continue;
                }

                // If value has spaces, wrap in quotes
                $quotedValue = str_contains($value, ' ') ? "\"{$value}\"" : $value;

                if (preg_match("/^{$key}=.*/m", $content)) {
                    $content = preg_replace("/^{$key}=.*/m", "{$key}={$quotedValue}", $content);
                } else {
                    $content .= "\n{$key}={$quotedValue}";
                }

                // Also update runtime env
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }

            file_put_contents($envFile, $content, LOCK_EX);
        }

        LoggerMiddleware::log('config', 'Configuracoes SMTP atualizadas');
        $this->flash('success', 'Configuracoes SMTP salvas com sucesso.');
        $this->redirect('/configuracoes#smtp');
    }

    public function testarSmtp(): void
    {
        RoleMiddleware::requireAdmin();

        $emailTeste = trim($this->input('email_teste', ''));
        if (empty($emailTeste) || !filter_var($emailTeste, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'error' => 'Informe um email valido para teste.']);
            return;
        }

        try {
            $host = $_ENV['SMTP_HOST'] ?? '';
            $port = (int)($_ENV['SMTP_PORT'] ?? 587);
            $user = $_ENV['SMTP_USER'] ?? '';
            $pass = $_ENV['SMTP_PASS'] ?? '';
            $fromName = $_ENV['SMTP_FROM_NAME'] ?? 'SESMT TSE';
            $fromEmail = $_ENV['SMTP_FROM_EMAIL'] ?? '';

            if (empty($host) || empty($user)) {
                $this->json(['success' => false, 'error' => 'Configure o servidor SMTP antes de testar.']);
                return;
            }

            // Test connection using fsockopen
            $fp = @fsockopen($host, $port, $errno, $errstr, 10);
            if (!$fp) {
                $this->json(['success' => false, 'error' => "Nao foi possivel conectar ao servidor SMTP: {$errstr} ({$errno})"]);
                return;
            }
            fclose($fp);

            $this->json(['success' => true, 'message' => "Conexao ao servidor SMTP ({$host}:{$port}) estabelecida com sucesso."]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => 'Erro ao testar SMTP: ' . $e->getMessage()]);
        }
    }

    /**
     * Gera preview de um certificado usando dados de exemplo.
     */
    public function previewCertificado(string $id): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT tc.*, m.nome as ministrante_nome, m.cargo_titulo, m.registro
             FROM tipos_certificado tc
             LEFT JOIN ministrantes m ON tc.ministrante_id = m.id
             WHERE tc.id = :id"
        );
        $stmt->execute(['id' => (int)$id]);
        $tipoCert = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$tipoCert) {
            $this->flash('error', 'Tipo de certificado nao encontrado.');
            $this->redirect('/configuracoes');
            return;
        }

        // Buscar ministrantes vinculados
        $stmt = $db->prepare(
            "SELECT m.*, tcm.papel
             FROM tipo_certificado_ministrante tcm
             JOIN ministrantes m ON tcm.ministrante_id = m.id
             WHERE tcm.tipo_certificado_id = :id"
        );
        $stmt->execute(['id' => (int)$id]);
        $ministrantes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $instrutor = null;
        $responsavel = null;
        foreach ($ministrantes as $m) {
            if ($m['papel'] === 'instrutor' && !$instrutor) $instrutor = $m;
            if ($m['papel'] === 'responsavel_tecnico') $responsavel = $m;
        }

        $this->view('config/preview-certificado', [
            'tipoCert' => $tipoCert,
            'instrutor' => $instrutor,
            'responsavel' => $responsavel,
        ], '');
    }
}
