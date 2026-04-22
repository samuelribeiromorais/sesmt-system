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

$status = 'ERRO';
$descricao = "Backup cron falhou (exit={$exitCode})";
if ($exitCode === 0 && file_exists($filePath) && filesize($filePath) > 0) {
    $size = round(filesize($filePath) / 1024, 1);
    $status = 'OK';
    $descricao = "Backup cron concluido: {$fileName} ({$size} KB)";
    echo "[{$ts}] {$descricao}\n";
} else {
    echo "[{$ts}] {$descricao}\n";
}

// Limpar backups com mais de 7 dias
$count = 0;
foreach (glob("{$backupDir}/sesmt_backup_*.sql.gz") as $file) {
    if (filemtime($file) < strtotime('-7 days')) {
        unlink($file);
        $count++;
    }
}
if ($count > 0) {
    echo "[{$ts}] {$count} backup(s) antigo(s) removido(s).\n";
}

// Registrar no log de acessos para aparecer na tela /backup
try {
    $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare(
        "INSERT INTO logs_acesso (usuario_id, acao, descricao, ip_address, criado_em)
         VALUES (NULL, 'backup', :desc, 'cron:backup.php', NOW())"
    );
    $stmt->execute(['desc' => "{$status} - {$descricao}"]);
} catch (\Throwable $e) {
    echo "[{$ts}] Falha ao registrar log: " . $e->getMessage() . "\n";
}
