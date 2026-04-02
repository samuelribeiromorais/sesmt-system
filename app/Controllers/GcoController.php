<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Services\GcoSyncService;

class GcoController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $db = Database::getInstance();

        // Últimas 10 sincronizações
        $stmt = $db->query(
            "SELECT g.*, u.nome as usuario_nome
             FROM gco_sync_logs g
             LEFT JOIN usuarios u ON g.executado_por = u.id
             ORDER BY g.iniciado_em DESC
             LIMIT 10"
        );
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Totais de colaboradores por origem
        $stmt = $db->query(
            "SELECT
                COUNT(*) as total,
                SUM(codigo_gco IS NOT NULL) as com_gco,
                SUM(codigo_gco IS NULL) as sem_gco,
                SUM(status = 'ativo') as ativos,
                SUM(status = 'inativo') as inativos
             FROM colaboradores WHERE excluido_em IS NULL"
        );
        $totais = $stmt->fetch(\PDO::FETCH_ASSOC);

        $gcoConfigurado = !empty($_ENV['GCO_TOKEN']);

        $this->view('gco/index', [
            'logs'            => $logs,
            'totais'          => $totais,
            'gcoConfigurado'  => $gcoConfigurado,
            'pageTitle'       => 'Integração GCO',
        ]);
    }

    public function sincronizar(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/gco');
        }

        if (empty($_ENV['GCO_TOKEN'])) {
            $this->flash('error', 'Token GCO não configurado no .env (GCO_TOKEN).');
            $this->redirect('/gco');
        }

        try {
            $usuarioId = (int)($_SESSION['user_id'] ?? 0) ?: null;
            $service   = new GcoSyncService();
            $resultado = $service->sincronizar($usuarioId);

            LoggerMiddleware::log('gco_sync', "Sincronização GCO concluída: {$resultado['criados']} criados, {$resultado['atualizados']} atualizados, {$resultado['desativados']} desativados.");

            $msg = "Sincronização concluída! "
                . "{$resultado['criados']} criados · "
                . "{$resultado['atualizados']} atualizados · "
                . "{$resultado['desativados']} desativados";

            if ($resultado['erros'] > 0) {
                $msg .= " · {$resultado['erros']} erro(s)";
            }

            $this->flash('success', $msg);

        } catch (\Throwable $e) {
            $this->flash('error', 'Erro na sincronização: ' . $e->getMessage());
        }

        $this->redirect('/gco');
    }
}
