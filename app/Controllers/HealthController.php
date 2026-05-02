<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/**
 * Health check público — pode ser pingado por UptimeRobot, Grafana ou
 * cron de monitoramento. NÃO requer autenticação por design (precisa
 * funcionar quando o sistema está parcialmente quebrado).
 *
 * Resposta: 200 com JSON quando tudo OK, 503 com JSON quando algo
 * crítico falha.
 */
class HealthController extends Controller
{
    public function check(): void
    {
        $checks = [];
        $okGeral = true;

        // 1. Banco de dados respondendo
        try {
            $db = Database::getInstance();
            $r = $db->query("SELECT 1")->fetchColumn();
            $checks['database'] = ['ok' => $r === '1' || $r === 1, 'detail' => 'SELECT 1'];
        } catch (\Throwable $e) {
            $checks['database'] = ['ok' => false, 'detail' => $e->getMessage()];
            $okGeral = false;
        }

        // 2. Espaço em disco — pelo menos 10% livre
        $storage = '/var/www/html/storage';
        if (is_dir($storage)) {
            $free = @disk_free_space($storage);
            $total = @disk_total_space($storage);
            if ($free && $total) {
                $pctLivre = round(($free / $total) * 100, 1);
                $checks['disk'] = [
                    'ok'      => $pctLivre >= 10,
                    'detail'  => "{$pctLivre}% livre",
                    'free_gb' => round($free / 1024 / 1024 / 1024, 2),
                    'total_gb'=> round($total / 1024 / 1024 / 1024, 2),
                ];
                if ($pctLivre < 10) $okGeral = false;
            } else {
                $checks['disk'] = ['ok' => true, 'detail' => 'não foi possível medir'];
            }
        }

        // 3. Último backup do banco — alerta se mais antigo que 48h
        $backupDir = '/var/www/html/storage/backups';
        if (is_dir($backupDir)) {
            $backups = glob($backupDir . '/db_*.sql.gz') ?: [];
            if (empty($backups)) {
                $checks['backup'] = ['ok' => false, 'detail' => 'nenhum backup encontrado'];
                $okGeral = false;
            } else {
                $latest = max(array_map('filemtime', $backups));
                $idadeHoras = round((time() - $latest) / 3600, 1);
                $checks['backup'] = [
                    'ok'         => $idadeHoras <= 48,
                    'detail'     => "ultimo ha {$idadeHoras}h",
                    'count'      => count($backups),
                    'latest_age_hours' => $idadeHoras,
                ];
                if ($idadeHoras > 48) $okGeral = false;
            }
        }

        // 4. Documentos vencidos (informativo)
        try {
            $db = Database::getInstance();
            $vencidos = (int)$db->query(
                "SELECT COUNT(*) FROM documentos WHERE status = 'vencido' AND excluido_em IS NULL"
            )->fetchColumn();
            $checks['docs_vencidos'] = ['ok' => true, 'detail' => "{$vencidos} documentos vencidos"];
        } catch (\Throwable $e) {
            $checks['docs_vencidos'] = ['ok' => false, 'detail' => 'query falhou'];
        }

        // 5. Versão da aplicação (a partir do último commit)
        $version = trim(@file_get_contents('/var/www/html/.git/refs/heads/master') ?: 'desconhecida');
        $checks['version'] = ['ok' => true, 'detail' => substr($version, 0, 7)];

        $payload = [
            'status'  => $okGeral ? 'ok' : 'degraded',
            'time'    => date('c'),
            'checks'  => $checks,
        ];

        http_response_code($okGeral ? 200 : 503);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
