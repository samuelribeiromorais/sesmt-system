<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\LogAcesso;
use App\Middleware\RoleMiddleware;

class BackupController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdmin();

        $model = new LogAcesso();
        $dataInicio = $this->input('data_inicio', '');
        $dataFim = $this->input('data_fim', '');
        $page = max(1, (int)$this->input('page', 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $logs = $model->search('backup', 0, $dataInicio, $dataFim, $perPage, $offset);

        $this->view('backup/index', [
            'logs'        => $logs,
            'data_inicio' => $dataInicio,
            'data_fim'    => $dataFim,
            'page'        => $page,
            'pageTitle'   => 'Backup',
        ]);
    }

    public function exportar(): void
    {
        RoleMiddleware::requireAdmin();

        $model = new LogAcesso();
        $logs = $model->search('backup', 0, '', '', 10000, 0);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="logs_backup_' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['Data/Hora', 'Usuario', 'Descricao', 'IP'], ';');

        foreach ($logs as $log) {
            fputcsv($out, [
                $log['criado_em'],
                $log['usuario_nome'] ?? 'Sistema',
                $log['descricao'],
                $log['ip_address'],
            ], ';');
        }

        fclose($out);
        exit;
    }
}
