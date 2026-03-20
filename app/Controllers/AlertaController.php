<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Models\Alerta;
use App\Models\Cliente;
use App\Models\Documento;
use App\Models\Certificado;
use App\Services\AlertService;
use App\Services\EmailService;

class AlertaController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $clienteFilter = $this->input('cliente', '');
        $tipoFilter = $this->input('tipo', '');

        $docModel = new Documento();
        $certModel = new Certificado();

        $docs_vencidos = $docModel->getExpired(200);
        $docs_expiring = $docModel->getExpiring(30, 200);
        $certs_expiring = $certModel->getExpiring(30, 200);

        // Filter by client if set
        if ($clienteFilter !== '') {
            $cid = (int)$clienteFilter;
            $docs_vencidos = array_values(array_filter($docs_vencidos, fn($d) => (int)($d['cliente_id'] ?? 0) === $cid));
            $docs_expiring = array_values(array_filter($docs_expiring, fn($d) => (int)($d['cliente_id'] ?? 0) === $cid));
            $certs_expiring = array_values(array_filter($certs_expiring, fn($c) => (int)($c['cliente_id'] ?? 0) === $cid));
        }

        // Filter by type if set
        if ($tipoFilter === 'docs_vencidos') {
            $docs_expiring = [];
            $certs_expiring = [];
        } elseif ($tipoFilter === 'docs_vencendo') {
            $docs_vencidos = [];
            $certs_expiring = [];
        } elseif ($tipoFilter === 'certs_vencendo') {
            $docs_vencidos = [];
            $docs_expiring = [];
        }

        // Load clients for the filter dropdown
        $clienteModel = new Cliente();
        $clientes = $clienteModel->all(['ativo' => 1], 'nome_fantasia ASC');

        // Historico de alertas (ultimos 50 enviados)
        $db = Database::getInstance();
        $historico = $db->query(
            "SELECT a.*, c.nome_completo,
                    td.nome as doc_tipo_nome, tc.codigo as cert_codigo
             FROM alertas a
             JOIN colaboradores c ON a.colaborador_id = c.id
             LEFT JOIN documentos d ON a.documento_id = d.id
             LEFT JOIN tipos_documento td ON d.tipo_documento_id = td.id
             LEFT JOIN certificados cert ON a.certificado_id = cert.id
             LEFT JOIN tipos_certificado tc ON cert.tipo_certificado_id = tc.id
             ORDER BY a.criado_em DESC
             LIMIT 50"
        )->fetchAll();

        // Stats de alertas
        $alertaStats = $db->query(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN email_enviado = 1 THEN 1 ELSE 0 END) as enviados,
                SUM(CASE WHEN email_enviado = 0 THEN 1 ELSE 0 END) as pendentes,
                MAX(CASE WHEN email_enviado = 1 THEN email_enviado_em END) as ultimo_envio
             FROM alertas"
        )->fetch();

        // Ultima verificacao (do log)
        $ultimaVerif = $db->query(
            "SELECT criado_em FROM logs_acesso
             WHERE acao LIKE '%Verificacao de validades%'
             ORDER BY criado_em DESC LIMIT 1"
        )->fetch();

        // SMTP configurado?
        $smtpOk = !empty($_ENV['SMTP_HOST']) && !empty($_ENV['SMTP_USER']);

        $this->view('alertas/index', [
            'docs_vencidos'    => $docs_vencidos,
            'docs_expiring'    => $docs_expiring,
            'certs_expiring'   => $certs_expiring,
            'clientes'         => $clientes,
            'clienteFilter'    => $clienteFilter,
            'tipoFilter'       => $tipoFilter,
            'historico'        => $historico,
            'alertaStats'      => $alertaStats,
            'ultimaVerif'      => $ultimaVerif,
            'smtpOk'           => $smtpOk,
            'pageTitle'        => 'Alertas',
        ]);
    }

    /**
     * Executar verificacao de validades + gerar alertas (manual via botao)
     */
    public function executarVerificacao(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $alertService = new AlertService();

        // Passo 1: Atualizar status de validades
        $statsValidade = $alertService->atualizarValidades();

        // Passo 2: Gerar alertas
        $statsAlertas = $alertService->gerarAlertas();

        LoggerMiddleware::log('alertas', sprintf(
            'Verificacao de validades executada manualmente. Status atualizados: %d docs vencidos, %d docs proximos, %d certs vencidos, %d certs proximos. Alertas criados: %d',
            $statsValidade['docs_vencidos'],
            $statsValidade['docs_proximos'],
            $statsValidade['certs_vencidos'],
            $statsValidade['certs_proximos'],
            $statsAlertas['criados']
        ));

        // Limpar cache do dashboard
        $cacheDir = dirname(__DIR__, 2) . '/storage/cache';
        array_map('unlink', glob("{$cacheDir}/*.cache") ?: []);

        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => sprintf(
                'Verificacao concluida! Status atualizados: %d doc(s) vencido(s), %d doc(s) proximo(s), %d cert(s) vencido(s), %d cert(s) proximo(s). %d alerta(s) criado(s).',
                $statsValidade['docs_vencidos'],
                $statsValidade['docs_proximos'],
                $statsValidade['certs_vencidos'],
                $statsValidade['certs_proximos'],
                $statsAlertas['criados']
            )
        ];
        header('Location: /alertas');
        exit;
    }

    /**
     * Enviar emails de alertas pendentes (manual via botao)
     */
    public function enviarEmails(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $alertService = new AlertService();
        $emailService = new EmailService();

        $alertas = $alertService->getAlertasPendentesEmail(100);

        if (empty($alertas)) {
            $_SESSION['flash'] = [
                'type' => 'info',
                'message' => 'Nenhum alerta pendente de envio por email.'
            ];
            header('Location: /alertas');
            exit;
        }

        // Envia resumo diario
        $ok = $emailService->enviarResumoDiario($alertas);

        if ($ok) {
            foreach ($alertas as $alerta) {
                $alertService->marcarEmailEnviado($alerta['id']);
            }

            LoggerMiddleware::log('alertas', sprintf(
                'Resumo diario de alertas enviado por email. %d alerta(s) notificado(s).',
                count($alertas)
            ));

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => sprintf('Email enviado com sucesso! %d alerta(s) notificado(s).', count($alertas))
            ];
        } else {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Erro ao enviar email. Verifique as configuracoes SMTP em Configuracoes.'
            ];
        }

        header('Location: /alertas');
        exit;
    }

    /**
     * Limpar alertas antigos ja enviados (mais de 90 dias)
     */
    public function limparHistorico(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "DELETE FROM alertas WHERE email_enviado = 1 AND criado_em < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
        $stmt->execute();
        $count = $stmt->rowCount();

        LoggerMiddleware::log('alertas', "Historico de alertas limpo: {$count} alerta(s) antigo(s) removido(s).");

        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => "{$count} alerta(s) com mais de 90 dias removido(s) do historico."
        ];
        header('Location: /alertas');
        exit;
    }
}
