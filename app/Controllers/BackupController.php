<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Middleware\RoleMiddleware;
use App\Middleware\LoggerMiddleware;

class BackupController extends Controller
{
    private function getBackupDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return $dir;
    }

    public function index(): void
    {
        RoleMiddleware::requireAdmin();

        // Listar backups existentes
        $backups = [];
        $files = glob($this->getBackupDir() . '/db_*.sql.gz');
        rsort($files); // mais recente primeiro

        foreach ($files as $f) {
            $backups[] = [
                'nome' => basename($f),
                'tamanho' => $this->formatBytes(filesize($f)),
                'data' => date('d/m/Y H:i', filemtime($f)),
                'timestamp' => filemtime($f),
            ];
        }

        // Verificar cron configurado
        $cronAtivo = false;
        $cronOutput = shell_exec('crontab -l 2>/dev/null');
        if ($cronOutput && strpos($cronOutput, 'backup.sh') !== false) {
            $cronAtivo = true;
        }

        $this->view('backup/index', [
            'backups' => $backups,
            'cronAtivo' => $cronAtivo,
            'pageTitle' => 'Configurações',
        ]);
    }

    public function executar(): void
    {
        RoleMiddleware::requireAdmin();
        $this->requirePost();

        $date = date('Y-m-d_H-i');
        $file = $this->getBackupDir() . "/db_{$date}.sql";

        // Executar mysqldump
        $cmd = "mysqldump -h db -u sesmt -psesmt2026 sesmt_tse --single-transaction --routines --triggers > " . escapeshellarg($file) . " 2>/dev/null";
        exec($cmd, $output, $retval);

        if ($retval === 0 && file_exists($file) && filesize($file) > 100) {
            // Compactar
            exec("gzip " . escapeshellarg($file));
            $gzFile = $file . '.gz';
            $size = $this->formatBytes(filesize($gzFile));

            LoggerMiddleware::log('backup', "Backup manual executado: db_{$date}.sql.gz ({$size})");
            $this->flash('success', "Backup realizado com sucesso! Arquivo: db_{$date}.sql.gz ({$size})");
        } else {
            @unlink($file);
            $this->flash('error', 'Erro ao executar o backup do banco de dados.');
        }

        $this->redirect('/backup');
    }

    public function download(string $nome): void
    {
        RoleMiddleware::requireAdmin();

        $nome = basename($nome); // prevenir path traversal
        $path = $this->getBackupDir() . '/' . $nome;

        if (!file_exists($path) || !preg_match('/^db_[\d\-_]+\.sql\.gz$/', $nome)) {
            $this->flash('error', 'Arquivo de backup não encontrado.');
            $this->redirect('/backup');
            return;
        }

        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $nome . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function excluir(string $nome): void
    {
        RoleMiddleware::requireAdmin();
        $this->requirePost();

        $nome = basename($nome);
        $path = $this->getBackupDir() . '/' . $nome;

        if (file_exists($path) && preg_match('/^db_[\d\-_]+\.sql\.gz$/', $nome)) {
            unlink($path);
            LoggerMiddleware::log('backup', "Backup excluido: {$nome}");
            $this->flash('success', "Backup {$nome} excluido.");
        } else {
            $this->flash('error', 'Arquivo não encontrado.');
        }

        $this->redirect('/backup');
    }

    public function configurarCron(): void
    {
        RoleMiddleware::requireAdmin();
        $this->requirePost();

        $horario = $this->input('horario', '02:00');
        $partes = explode(':', $horario);
        $hora = (int)($partes[0] ?? 2);
        $minuto = (int)($partes[1] ?? 0);

        // Adicionar ao crontab
        $scriptPath = '/var/www/html/scripts/backup.sh';
        $cronLine = "{$minuto} {$hora} * * * /bin/bash {$scriptPath} >> /var/log/backup.log 2>&1";

        // Remover entrada anterior e adicionar nova
        exec("(crontab -l 2>/dev/null | grep -v 'backup.sh'; echo '{$cronLine}') | crontab -");

        LoggerMiddleware::log('backup', "Backup automático configurado: diario as {$horario}");
        $this->flash('success', "Backup automático configurado para todos os dias as {$horario}.");
        $this->redirect('/backup');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }
}
