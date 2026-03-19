<?php
/**
 * Cron Job: Backup do banco de dados
 * Executar semanalmente aos domingos as 02:00
 */

require __DIR__ . '/bootstrap.php';

$host = getenv('DB_HOST') ?: 'db';
$db   = getenv('DB_NAME') ?: 'sesmt_tse';
$user = getenv('DB_USER') ?: 'sesmt';
$pass = getenv('DB_PASS') ?: 'sesmt2026';

$backupDir = '/var/www/html/storage/backups';
if (!is_dir($backupDir)) mkdir($backupDir, 0750, true);

$fileName = "sesmt_backup_" . date('Y-m-d_His') . ".sql.gz";
$filePath = "{$backupDir}/{$fileName}";

$ts = date('Y-m-d H:i:s');
echo "[{$ts}] Iniciando backup...\n";

$cmd = sprintf(
    'mysqldump -h %s -u %s -p%s %s 2>/dev/null | gzip > %s',
    escapeshellarg($host),
    escapeshellarg($user),
    escapeshellarg($pass),
    escapeshellarg($db),
    escapeshellarg($filePath)
);

exec($cmd, $output, $exitCode);

if ($exitCode === 0 && file_exists($filePath) && filesize($filePath) > 0) {
    $size = round(filesize($filePath) / 1024, 1);
    echo "[{$ts}] Backup concluido: {$fileName} ({$size} KB)\n";
} else {
    echo "[{$ts}] ERRO ao gerar backup (exit code: {$exitCode})\n";
}

// Limpar backups com mais de 30 dias
$count = 0;
foreach (glob("{$backupDir}/sesmt_backup_*.sql.gz") as $file) {
    if (filemtime($file) < strtotime('-30 days')) {
        unlink($file);
        $count++;
    }
}
if ($count > 0) {
    echo "[{$ts}] {$count} backup(s) antigo(s) removido(s).\n";
}
