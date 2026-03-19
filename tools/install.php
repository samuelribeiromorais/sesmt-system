<?php
/**
 * Script de Instalacao - SESMT TSE
 * Executa o schema e seed no MariaDB
 *
 * Uso: php tools/install.php
 *
 * Pre-requisitos:
 *   1. MariaDB/MySQL rodando
 *   2. .env configurado com credenciais do banco
 *   3. composer install executado
 */

require dirname(__DIR__) . '/cron/bootstrap.php';

echo "===========================================\n";
echo " SESMT TSE - Instalacao do Banco de Dados\n";
echo "===========================================\n\n";

$dbConfig = require dirname(__DIR__) . '/app/config/database.php';

echo "Host: {$dbConfig['host']}:{$dbConfig['port']}\n";
echo "Banco: {$dbConfig['name']}\n";
echo "Usuario: {$dbConfig['user']}\n\n";

// Conectar sem selecionar banco (para criar se nao existir)
try {
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "[OK] Conexao com MariaDB estabelecida.\n";
} catch (PDOException $e) {
    echo "[ERRO] Falha na conexao: " . $e->getMessage() . "\n";
    exit(1);
}

// Executar schema
$schemaFile = dirname(__DIR__) . '/database/schema.sql';
if (!file_exists($schemaFile)) {
    echo "[ERRO] Arquivo schema.sql nao encontrado.\n";
    exit(1);
}

echo "[...] Executando schema.sql...\n";
$schema = file_get_contents($schemaFile);
try {
    $pdo->exec($schema);
    echo "[OK] Schema criado com sucesso.\n";
} catch (PDOException $e) {
    echo "[ERRO] Falha no schema: " . $e->getMessage() . "\n";
    exit(1);
}

// Executar seed
$seedFile = dirname(__DIR__) . '/database/seed.sql';
if (!file_exists($seedFile)) {
    echo "[AVISO] Arquivo seed.sql nao encontrado. Pulando.\n";
} else {
    echo "[...] Executando seed.sql...\n";

    // Gerar hash bcrypt real para a senha padrao
    $senhaHash = password_hash('TseAdmin@2026', PASSWORD_BCRYPT, ['cost' => 12]);

    $seed = file_get_contents($seedFile);
    // Substituir o hash placeholder no seed pelo hash real
    $seed = preg_replace(
        '/\$2y\$12\$[A-Za-z0-9.\/]{53}/',
        $senhaHash,
        $seed
    );

    try {
        $pdo->exec($seed);
        echo "[OK] Dados iniciais inseridos.\n";
    } catch (PDOException $e) {
        // Ignorar erros de duplicata (seed ja executado antes)
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "[AVISO] Dados iniciais ja existem (ignorando duplicatas).\n";
        } else {
            echo "[ERRO] Falha no seed: " . $e->getMessage() . "\n";
        }
    }
}

// Criar diretorios de storage
$dirs = [
    dirname(__DIR__) . '/storage/uploads',
    dirname(__DIR__) . '/storage/reports',
    dirname(__DIR__) . '/storage/logs',
    dirname(__DIR__) . '/storage/backups',
];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
        echo "[OK] Diretorio criado: " . basename(dirname($dir)) . '/' . basename($dir) . "\n";
    }
}

// Criar .env se nao existe
$envFile = dirname(__DIR__) . '/.env';
$envExample = dirname(__DIR__) . '/.env.example';
if (!file_exists($envFile) && file_exists($envExample)) {
    copy($envExample, $envFile);
    echo "[OK] .env criado a partir de .env.example. CONFIGURE AS CREDENCIAIS!\n";
}

echo "\n===========================================\n";
echo " INSTALACAO CONCLUIDA\n";
echo "===========================================\n";
echo "\n Proximos passos:\n";
echo "  1. Edite o .env com as credenciais corretas\n";
echo "  2. Execute: composer install\n";
echo "  3. Configure o Apache (DocumentRoot = /public)\n";
echo "  4. Acesse o sistema e faca login:\n";
echo "     Email: samuel.morais@tsea.com.br\n";
echo "     Senha: TseAdmin@2026\n";
echo "  5. TROQUE A SENHA no primeiro acesso!\n";
echo "\n Crontab (adicionar com: crontab -e):\n";
echo "  0 6 * * * php /caminho/cron/check_validades.php\n";
echo "  0 7 * * * php /caminho/cron/gerar_alertas.php\n";
echo "  30 7 * * * php /caminho/cron/enviar_emails.php\n";
echo "===========================================\n";
