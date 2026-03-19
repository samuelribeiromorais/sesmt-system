#!/bin/bash
set -e

echo "[SESMT] Aguardando banco de dados..."
sleep 3

echo "[SESMT] Instalando dependencias Composer..."
if [ -f /var/www/html/composer.json ] && [ ! -d /var/www/html/vendor ]; then
    cd /var/www/html && composer install --no-dev --optimize-autoloader --no-interaction 2>&1 || true
fi

echo "[SESMT] Criando diretorios de storage..."
mkdir -p /var/www/html/storage/{uploads,reports,logs,backups}
chown -R www-data:www-data /var/www/html/storage
chmod -R 750 /var/www/html/storage

echo "[SESMT] Corrigindo senhas bcrypt..."
php <<'PHPSCRIPT'
<?php
$hash = password_hash('TseAdmin@2026', PASSWORD_BCRYPT, ['cost' => 12]);
$host = getenv('DB_HOST') ?: 'db';
$db   = getenv('DB_NAME') ?: 'sesmt_tse';
$user = getenv('DB_USER') ?: 'sesmt';
$pass = getenv('DB_PASS') ?: 'sesmt2026';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $stmt = $pdo->prepare("UPDATE usuarios SET senha_hash = ?");
    $stmt->execute([$hash]);
    echo "[SESMT] Senhas atualizadas com sucesso (" . $stmt->rowCount() . " usuarios).\n";
} catch (Exception $e) {
    echo "[SESMT] Aviso: " . $e->getMessage() . "\n";
}
PHPSCRIPT

echo "[SESMT] Sistema pronto! Acesse http://localhost:8080"
echo "[SESMT] Login: samuel.morais@tsea.com.br / TseAdmin@2026"

# Iniciar Apache
exec apache2-foreground
