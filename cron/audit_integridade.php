<?php
/**
 * Cron Job: Auditoria de integridade SHA-256
 * Executar mensalmente no dia 1 as 03:00
 */

require __DIR__ . '/bootstrap.php';

use App\Core\Database;

$db = Database::getInstance();
$ts = date('Y-m-d H:i:s');
echo "[{$ts}] Iniciando auditoria de integridade...\n";

$stmt = $db->query(
    "SELECT id, arquivo_path, arquivo_hash, arquivo_nome FROM documentos WHERE status != 'obsoleto'"
);
$docs = $stmt->fetchAll();

$uploadDir = '/var/www/html/storage/uploads';
$total = count($docs);
$ok = 0;
$missing = 0;
$mismatch = 0;
$errors = [];

foreach ($docs as $doc) {
    $filePath = $uploadDir . '/' . $doc['arquivo_path'];

    if (!file_exists($filePath)) {
        $missing++;
        $errors[] = "FALTANTE: Doc #{$doc['id']} - {$doc['arquivo_nome']} ({$doc['arquivo_path']})";
        continue;
    }

    $hash = hash_file('sha256', $filePath);
    if ($hash !== $doc['arquivo_hash']) {
        $mismatch++;
        $errors[] = "HASH DIFERENTE: Doc #{$doc['id']} - {$doc['arquivo_nome']} (esperado: {$doc['arquivo_hash']}, encontrado: {$hash})";
    } else {
        $ok++;
    }
}

echo "[{$ts}] Auditoria concluida.\n";
echo "  Total verificados: {$total}\n";
echo "  OK: {$ok}\n";
echo "  Arquivos faltantes: {$missing}\n";
echo "  Hash divergente: {$mismatch}\n";

if (!empty($errors)) {
    echo "\nERROS ENCONTRADOS:\n";
    foreach ($errors as $e) {
        echo "  - {$e}\n";
    }
}
