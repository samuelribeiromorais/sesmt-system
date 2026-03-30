<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Models\LogAcesso;
use App\Models\Usuario;

class LogController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $model = new LogAcesso();
        $userModel = new Usuario();

        $acao = $this->input('acao', '');
        $usuarioId = (int)$this->input('usuario_id', 0);
        $dataInicio = $this->input('data_inicio', '');
        $dataFim = $this->input('data_fim', '');
        $page = max(1, (int)$this->input('page', 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $logs = $model->search($acao, $usuarioId, $dataInicio, $dataFim, $perPage, $offset);
        $usuarios = $userModel->all([], 'nome ASC');

        $this->view('logs/index', [
            'logs'       => $logs,
            'usuarios'   => $usuarios,
            'acao'       => $acao,
            'usuario_id' => $usuarioId,
            'data_inicio' => $dataInicio,
            'data_fim'   => $dataFim,
            'page'       => $page,
            'pageTitle'  => 'Logs',
        ]);
    }

    public function exportar(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $model = new LogAcesso();
        $logs = $model->search('', 0, '', '', 10000, 0);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="logs_sesmt_' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
        fputcsv($out, ['Data/Hora', 'Usuario', 'Email', 'Acao', 'Descrição', 'IP'], ';');

        foreach ($logs as $log) {
            fputcsv($out, [
                $log['criado_em'],
                $log['usuario_nome'] ?? '',
                $log['usuario_email'] ?? '',
                $log['acao'],
                $log['descricao'],
                $log['ip_address'],
            ], ';');
        }

        fclose($out);
        exit;
    }
}
