<?php
/**
 * Aplica todas as migrations pendentes em database/migrations/, pulando
 * as já registradas em _migrations. Idempotente — pode rodar quantas
 * vezes quiser sem efeitos colaterais.
 *
 * Uso (no servidor):
 *   docker exec sesmt-web php /var/www/html/scripts/migrate.php
 *
 * Ou:
 *   docker exec -e DB_PASS=$DB_PASS sesmt-web php scripts/migrate.php
 */

$host = getenv('DB_HOST') ?: 'db';
$db   = getenv('DB_NAME') ?: 'sesmt_tse';
$user = getenv('DB_USER') ?: 'sesmt';
$pass = getenv('DB_PASS') ?: getenv('MARIADB_PASSWORD') ?: 'sesmt2026';

$migrationsDir = __DIR__ . '/../database/migrations';
if (!is_dir($migrationsDir)) {
    fwrite(STDERR, "Diretório de migrations não encontrado: {$migrationsDir}\n");
    exit(1);
}

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    fwrite(STDERR, "Erro de conexão: " . $e->getMessage() . "\n");
    exit(1);
}

// Garante que a tabela _migrations existe
$pdo->exec("CREATE TABLE IF NOT EXISTS _migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    checksum_sha256 CHAR(64) NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    applied_by VARCHAR(100) NULL,
    INDEX idx_applied (applied_at)
) ENGINE=InnoDB");

// Lista migrations já aplicadas
$applied = [];
$stmt = $pdo->query("SELECT filename FROM _migrations");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $applied[$row['filename']] = true;
}

// Lê arquivos .sql do diretório, ordenados
$files = glob($migrationsDir . '/*.sql');
sort($files);

$total = count($files);
$pending = [];
foreach ($files as $f) {
    $name = basename($f);
    if (!isset($applied[$name])) {
        $pending[] = $f;
    }
}

if (empty($pending)) {
    echo "✓ Banco já está atualizado ({$total} migrations conhecidas, todas aplicadas).\n";
    exit(0);
}

echo "Migrations pendentes: " . count($pending) . " de {$total}\n\n";

$user_label = getenv('USER') ?: 'cli';
foreach ($pending as $f) {
    $name = basename($f);
    $sql = file_get_contents($f);
    $checksum = hash('sha256', $sql);

    echo "→ Aplicando {$name}... ";
    try {
        // Executa a migration. PDO não suporta múltiplas statements numa
        // mesma chamada com prepared, então usamos exec direto.
        $pdo->exec($sql);

        $stmt = $pdo->prepare(
            "INSERT INTO _migrations (filename, checksum_sha256, applied_by) VALUES (?, ?, ?)"
        );
        $stmt->execute([$name, $checksum, $user_label]);

        echo "OK\n";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        // Erros benignos comuns: coluna duplicada, tabela já existe, índice duplicado.
        // Marcamos a migration como aplicada e seguimos (provavelmente uma migration
        // que tinha sido rodada manualmente antes da introdução do tracking).
        $benign = ['Duplicate column name', 'already exists', 'Duplicate key name'];
        $isBenign = false;
        foreach ($benign as $b) {
            if (strpos($msg, $b) !== false) { $isBenign = true; break; }
        }
        if ($isBenign) {
            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO _migrations (filename, checksum_sha256, applied_by) VALUES (?, ?, ?)"
            );
            $stmt->execute([$name, $checksum, $user_label . ' (benign)']);
            echo "JÁ APLICADA (registrada agora)\n";
        } else {
            echo "ERRO: {$msg}\n";
            exit(1);
        }
    }
}

echo "\n✓ Concluído.\n";
