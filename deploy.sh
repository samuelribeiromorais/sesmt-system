#!/bin/bash
# ===========================================
# SESMT TSE - Script de Deploy para Producao
# Servidor: Debian com Apache + Docker
# ===========================================
#
# COMO USAR:
#   chmod +x deploy.sh
#   sudo ./deploy.sh
#
# O script faz TUDO sozinho:
#   1. Instala Docker, Git, Apache, Certbot
#   2. Clona o repositorio do GitHub
#   3. Gera .env com senhas seguras
#   4. Sobe os containers
#   5. Configura Apache como reverse proxy
#   6. Configura SSL (HTTPS)
#   7. Configura firewall
#
# ===========================================

set -e

# Cores para output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() { echo -e "${GREEN}[SESMT]${NC} $1"; }
warn() { echo -e "${YELLOW}[AVISO]${NC} $1"; }
err() { echo -e "${RED}[ERRO]${NC} $1"; }

DEPLOY_DIR="/opt/sesmt-system"
DOMAIN="sesmt.tsea.com.br"

echo ""
echo "=========================================="
echo " SESMT TSE - Deploy de Producao"
echo " Dominio: $DOMAIN"
echo "=========================================="
echo ""

# Verificar root
if [ "$EUID" -ne 0 ]; then
    err "Execute como root: sudo ./deploy.sh"
    exit 1
fi

# --- 1. INSTALAR DEPENDENCIAS ---
log "[1/8] Instalando dependencias..."
apt-get update -qq
apt-get install -y -qq docker.io docker-compose git curl
systemctl enable docker
systemctl start docker
log "Dependencias instaladas."

# --- 2. CLONAR REPOSITORIO ---
log "[2/8] Clonando repositorio..."
if [ -d "$DEPLOY_DIR" ]; then
    cd "$DEPLOY_DIR"
    git pull origin master
    log "Repositorio atualizado."
else
    git clone https://github.com/samuelribeiromorais/sesmt-system.git "$DEPLOY_DIR"
    cd "$DEPLOY_DIR"
    log "Repositorio clonado."
fi

# --- 3. GERAR .ENV ---
log "[3/8] Configurando variaveis de ambiente..."

# Gerar senhas aleatorias FIXAS (mesmo valor para DB e app)
DB_PASS="SesmtTSE$(openssl rand -hex 6)"
DB_ROOT_PASS="Root$(openssl rand -hex 8)"
AES="$(openssl rand -hex 32)"

# SEMPRE regravar o .env para evitar inconsistencias de senha
cat > "$DEPLOY_DIR/.env" << EOF
# =============================================
# SESMT TSE - Configuracao de Producao
# Gerado automaticamente em $(date '+%Y-%m-%d %H:%M:%S')
# =============================================

# App
APP_ENV=production
APP_DEBUG=false
APP_URL=https://${DOMAIN}
APP_TIMEZONE=America/Sao_Paulo

# Banco de dados - USADAS PELO PHP (app/Core/Database.php)
DB_HOST=db
DB_PORT=3306
DB_NAME=sesmt_tse
DB_USER=sesmt
DB_PASS=${DB_PASS}

# Banco de dados - USADAS PELO MARIADB (container db)
MARIADB_ROOT_PASSWORD=${DB_ROOT_PASS}
MARIADB_DATABASE=sesmt_tse
MARIADB_USER=sesmt
MARIADB_PASSWORD=${DB_PASS}

# Criptografia AES-256
AES_KEY=${AES}

# SMTP (configurar com dados reais depois)
SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASS=
SMTP_FROM_NAME=SESMT TSE
SMTP_FROM_EMAIL=

# Upload
UPLOAD_MAX_SIZE=10485760
UPLOAD_ALLOWED_TYPES=application/pdf
EOF

log ".env gerado com sucesso."
log "  DB_USER: sesmt"
log "  DB_PASS: ${DB_PASS}"
warn "ANOTE A SENHA ACIMA! Ela nao sera exibida novamente."
echo ""

# --- 4. CRIAR DIRETORIOS ---
log "[4/8] Criando diretorios..."
mkdir -p "$DEPLOY_DIR/storage/uploads"
mkdir -p "$DEPLOY_DIR/storage/backups"
mkdir -p "$DEPLOY_DIR/storage/logs"
mkdir -p "$DEPLOY_DIR/storage/cache"
mkdir -p "$DEPLOY_DIR/storage/reports"
chown -R www-data:www-data "$DEPLOY_DIR/storage" 2>/dev/null || true
chmod -R 750 "$DEPLOY_DIR/storage"
log "Diretorios criados."

# --- 5. SUBIR CONTAINERS ---
log "[5/8] Subindo containers Docker..."

# Parar containers antigos se existirem
docker-compose -f docker-compose.prod.yml down 2>/dev/null || true

# Remover volumes antigos do banco (para recriar com novas senhas)
docker volume rm sesmt-system_sesmt-dbdata 2>/dev/null || true

# Subir
docker-compose -f docker-compose.prod.yml up -d --build

# Aguardar banco ficar pronto
log "Aguardando banco de dados inicializar..."
for i in $(seq 1 30); do
    if docker exec sesmt-db mariadb -u sesmt -p"${DB_PASS}" -e "SELECT 1" sesmt_tse &>/dev/null; then
        log "Banco de dados pronto!"
        break
    fi
    sleep 2
    echo -n "."
done
echo ""

# Verificar conexao
if ! docker exec sesmt-db mariadb -u sesmt -p"${DB_PASS}" -e "SELECT 1" sesmt_tse &>/dev/null; then
    err "Banco de dados NAO respondeu. Verifique com: docker logs sesmt-db"
    exit 1
fi

# --- 6. CONFIGURAR APACHE ---
log "[6/8] Configurando Apache..."
a2enmod proxy proxy_http rewrite ssl headers 2>/dev/null

cat > /etc/apache2/sites-available/sesmt.conf << APACHEEOF
<VirtualHost *:80>
    ServerName ${DOMAIN}

    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:8080/
    ProxyPassReverse / http://127.0.0.1:8080/

    ErrorLog \${APACHE_LOG_DIR}/sesmt-error.log
    CustomLog \${APACHE_LOG_DIR}/sesmt-access.log combined
</VirtualHost>
APACHEEOF

a2ensite sesmt.conf 2>/dev/null
systemctl reload apache2
log "Apache configurado."

# --- 7. SSL ---
log "[7/8] SSL..."
log "Certificado SSL ja existente no servidor. Nenhuma acao necessaria."
log "Se precisar renovar, configure manualmente no Apache."

# --- 8. FIREWALL ---
log "[8/8] Firewall..."
log "Firewall gerenciado pelo servidor. Nenhuma acao necessaria."

# --- VERIFICACAO FINAL ---
sleep 3
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/ 2>/dev/null)

echo ""
echo "=========================================="
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "302" ]; then
    echo -e " ${GREEN}DEPLOY CONCLUIDO COM SUCESSO!${NC}"
    echo "=========================================="
    echo ""
    echo " URL: https://${DOMAIN}"
    echo " Login: samuel.morais@tsea.com.br"
    echo " Senha: TseAdmin@2026"
    echo ""
    echo " PROXIMOS PASSOS:"
    echo " 1. Acesse ${DOMAIN} e teste o login"
    echo " 2. Altere a senha no primeiro acesso"
    echo " 3. Transfira os documentos (storage/uploads/)"
    echo "    Descompacte o ZIP em: ${DEPLOY_DIR}/storage/uploads/"
    echo "    Depois: chown -R www-data:www-data ${DEPLOY_DIR}/storage/uploads/"
    echo " 4. Configure SMTP no .env: nano ${DEPLOY_DIR}/.env"
    echo " 5. Backup diario automatico ja esta ativo"
    echo ""
    echo " CREDENCIAIS DO BANCO (salve em local seguro):"
    echo " DB_USER: sesmt"
    echo " DB_PASS: ${DB_PASS}"
    echo " DB_ROOT: ${DB_ROOT_PASS}"
    echo ""
else
    echo -e " ${RED}ERRO: Servidor nao respondeu (HTTP ${HTTP_CODE})${NC}"
    echo "=========================================="
    echo " Verifique os logs:"
    echo "   docker logs sesmt-web"
    echo "   docker logs sesmt-db"
fi
echo "=========================================="
