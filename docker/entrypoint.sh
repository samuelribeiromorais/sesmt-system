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
chown www-data:www-data /var/www/html/storage /var/www/html/storage/uploads /var/www/html/storage/reports /var/www/html/storage/logs /var/www/html/storage/backups
chmod 750 /var/www/html/storage /var/www/html/storage/uploads /var/www/html/storage/reports /var/www/html/storage/logs /var/www/html/storage/backups

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

echo "[SESMT] Sistema pronto! Acesse http://localhost:8081"
echo "[SESMT] Login: samuel.morais@tsea.com.br / TseAdmin@2026"

# Exportar variaveis de ambiente para o cron
printenv | grep -E '^(DB_|APP_|AES_|SMTP_|UPLOAD_)' >> /etc/environment

# Iniciar cron
echo "[SESMT] Iniciando servico cron..."
service cron start

# Iniciar Apache
exec apache2-foreground
