#!/bin/bash
# ===========================================
# SESMT TSE - Script de Deploy para Producao
# Servidor: Debian com Apache + Docker
# Dominio: sesmt.tsea.com.br
# ===========================================

set -e

echo "=========================================="
echo " SESMT TSE - Deploy de Producao"
echo "=========================================="

# --- 1. INSTALAR DEPENDENCIAS ---
echo "[1/8] Instalando dependencias..."
apt-get update -qq
apt-get install -y -qq docker.io docker-compose git certbot python3-certbot-apache ufw

# Habilitar Docker
systemctl enable docker
systemctl start docker

# --- 2. CLONAR REPOSITORIO ---
echo "[2/8] Clonando repositorio..."
DEPLOY_DIR="/opt/sesmt-system"
if [ -d "$DEPLOY_DIR" ]; then
    cd "$DEPLOY_DIR"
    git pull origin master
else
    git clone https://github.com/samuelribeiromorais/sesmt-system.git "$DEPLOY_DIR"
    cd "$DEPLOY_DIR"
fi

# --- 3. CONFIGURAR .ENV ---
echo "[3/8] Configurando variaveis de ambiente..."
if [ ! -f "$DEPLOY_DIR/.env" ]; then
    DB_ROOT_PASS=$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 24)
    DB_USER_PASS=$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 24)
    AES_KEY=$(openssl rand -hex 32)

    cat > "$DEPLOY_DIR/.env" << ENVEOF
# Producao - SESMT TSE
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sesmt.tsea.com.br

# Banco de dados
DB_HOST=db
DB_PORT=3306
DB_DATABASE=sesmt_tse
DB_USER=sesmt_prod
DB_PASSWORD=${DB_USER_PASS}
DB_ROOT_PASSWORD=${DB_ROOT_PASS}

# Criptografia
AES_KEY=${AES_KEY}

# SMTP (configurar com dados reais)
SMTP_HOST=smtp.tsea.com.br
SMTP_PORT=587
SMTP_USER=sesmt@tsea.com.br
SMTP_PASS=TROCAR_SENHA_SMTP
SMTP_FROM=sesmt@tsea.com.br
SMTP_FROM_NAME=SESMT TSE
ENVEOF

    echo "  .env criado com senhas aleatorias."
    echo "  IMPORTANTE: Edite o .env para configurar SMTP!"
else
    echo "  .env ja existe, mantendo configuracao atual."
fi

# --- 4. SUBIR CONTAINERS ---
echo "[4/8] Subindo containers Docker..."
docker-compose -f docker-compose.prod.yml up -d --build

# Aguardar banco ficar pronto
echo "  Aguardando banco de dados..."
sleep 15

# --- 5. CONFIGURAR APACHE REVERSE PROXY ---
echo "[5/8] Configurando Apache..."
cat > /etc/apache2/sites-available/sesmt.conf << 'APACHEEOF'
<VirtualHost *:80>
    ServerName sesmt.tsea.com.br

    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:8080/
    ProxyPassReverse / http://127.0.0.1:8080/

    ErrorLog ${APACHE_LOG_DIR}/sesmt-error.log
    CustomLog ${APACHE_LOG_DIR}/sesmt-access.log combined
</VirtualHost>
APACHEEOF

a2enmod proxy proxy_http rewrite ssl headers
a2ensite sesmt.conf
systemctl reload apache2

# --- 6. CERTIFICADO SSL ---
echo "[6/8] Configurando SSL com Let's Encrypt..."
certbot --apache -d sesmt.tsea.com.br --non-interactive --agree-tos --email samuel.morais@tsea.com.br || echo "  AVISO: Certbot falhou. Configure SSL manualmente."

# --- 7. FIREWALL ---
echo "[7/8] Configurando firewall..."
ufw allow 22/tcp    # SSH
ufw allow 80/tcp    # HTTP
ufw allow 443/tcp   # HTTPS
ufw --force enable

# --- 8. VERIFICAR ---
echo "[8/8] Verificando deploy..."
sleep 5
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/ 2>/dev/null)
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "302" ]; then
    echo ""
    echo "=========================================="
    echo " DEPLOY CONCLUIDO COM SUCESSO!"
    echo "=========================================="
    echo ""
    echo " URL: https://sesmt.tsea.com.br"
    echo " Backup: diario automatico (7 dias retidos)"
    echo ""
    echo " PROXIMOS PASSOS:"
    echo " 1. Edite /opt/sesmt-system/.env com SMTP real"
    echo " 2. Acesse https://sesmt.tsea.com.br"
    echo " 3. Login: samuel.morais@tsea.com.br / TseAdmin@2026"
    echo " 4. Altere a senha no primeiro acesso!"
    echo " 5. Transfira os documentos (storage/uploads/)"
    echo ""
    echo " Para migrar documentos do PC local:"
    echo " scp -r storage/uploads/ root@SERVIDOR:/opt/sesmt-system/storage/"
    echo "=========================================="
else
    echo "ERRO: Servidor nao respondeu (HTTP $HTTP_CODE)"
    echo "Verifique: docker-compose -f docker-compose.prod.yml logs"
fi
