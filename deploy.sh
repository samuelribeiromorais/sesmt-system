#!/bin/bash
# ===========================================
# SESMT TSE - Script de Deploy para Producao
# ===========================================
#
# USO:
#   PRIMEIRA INSTALACAO:  sudo ./deploy.sh instalar
#   ATUALIZAR CODIGO:     sudo ./deploy.sh atualizar
#   IMPORTAR DUMP:        sudo ./deploy.sh importar dump_sesmt.sql
#   STATUS:               sudo ./deploy.sh status
#
# ===========================================

set -e

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() { echo -e "${GREEN}[SESMT]${NC} $1"; }
warn() { echo -e "${YELLOW}[AVISO]${NC} $1"; }
err() { echo -e "${RED}[ERRO]${NC} $1"; }

DEPLOY_DIR="/opt/sesmt-system"
DOMAIN="sesmt.tsea.com.br"
COMPOSE_FILE="docker-compose.prod.yml"
ACAO="${1:-}"

# ---- FUNCOES ----

instalar() {
    echo ""
    echo "=========================================="
    echo " SESMT TSE - Instalacao Completa"
    echo " Dominio: $DOMAIN"
    echo "=========================================="
    echo ""

    if [ "$EUID" -ne 0 ]; then
        err "Execute como root: sudo ./deploy.sh instalar"
        exit 1
    fi

    # 1. Instalar dependencias
    log "[1/7] Instalando dependencias..."
    apt-get update -qq
    apt-get install -y -qq docker.io docker-compose git curl apache2
    systemctl enable docker
    systemctl start docker
    a2enmod proxy proxy_http rewrite ssl headers 2>/dev/null || true
    log "Dependencias instaladas."

    # 2. Clonar repositorio
    log "[2/7] Clonando repositorio..."
    if [ -d "$DEPLOY_DIR/.git" ]; then
        cd "$DEPLOY_DIR"
        git pull origin master
        log "Repositorio atualizado."
    else
        git clone https://github.com/samuelribeiromorais/sesmt-system.git "$DEPLOY_DIR"
        cd "$DEPLOY_DIR"
        log "Repositorio clonado."
    fi

    # 3. Gerar .env (somente se nao existir)
    if [ -f "$DEPLOY_DIR/.env" ]; then
        log "[3/7] .env ja existe, mantendo configuracao atual."
        # Carregar senha existente
        DB_PASS=$(grep "^DB_PASS=" "$DEPLOY_DIR/.env" | cut -d'=' -f2)
    else
        log "[3/7] Gerando .env..."
        DB_PASS="SesmtTSE$(openssl rand -hex 6)"
        DB_ROOT_PASS="Root$(openssl rand -hex 8)"
        AES_KEY="$(openssl rand -hex 32)"

        cat > "$DEPLOY_DIR/.env" << ENVEOF
# SESMT TSE - Producao
# Gerado em $(date '+%Y-%m-%d %H:%M:%S')

APP_ENV=production
APP_DEBUG=false
APP_URL=https://${DOMAIN}
APP_TIMEZONE=America/Sao_Paulo

DB_HOST=db
DB_PORT=3306
DB_NAME=sesmt_tse
DB_USER=sesmt
DB_PASS=${DB_PASS}

MARIADB_ROOT_PASSWORD=${DB_ROOT_PASS}
MARIADB_DATABASE=sesmt_tse
MARIADB_USER=sesmt
MARIADB_PASSWORD=${DB_PASS}

AES_KEY=${AES_KEY}

SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASS=
SMTP_FROM_NAME=SESMT TSE
SMTP_FROM_EMAIL=

UPLOAD_MAX_SIZE=10485760
UPLOAD_ALLOWED_TYPES=application/pdf
ENVEOF

        log ".env gerado."
        log "  DB_PASS: ${DB_PASS}"
        warn "ANOTE A SENHA ACIMA!"
    fi

    # 4. Criar diretorios
    log "[4/7] Criando diretorios..."
    mkdir -p "$DEPLOY_DIR/storage"/{uploads,backups,logs,cache,reports}
    chown -R www-data:www-data "$DEPLOY_DIR/storage" 2>/dev/null || true
    chmod -R 750 "$DEPLOY_DIR/storage"

    # 5. Subir containers
    log "[5/7] Subindo containers..."
    cd "$DEPLOY_DIR"
    docker-compose -f "$COMPOSE_FILE" down 2>/dev/null || true
    docker-compose -f "$COMPOSE_FILE" up -d --build

    # Aguardar banco
    log "Aguardando banco de dados..."
    DB_PASS=$(grep "^DB_PASS=" "$DEPLOY_DIR/.env" | cut -d'=' -f2)
    for i in $(seq 1 30); do
        if docker exec sesmt-db mariadb -u sesmt -p"${DB_PASS}" -e "SELECT 1" sesmt_tse &>/dev/null; then
            log "Banco pronto!"
            break
        fi
        sleep 2
        echo -n "."
    done
    echo ""

    # Verificar se tabelas existem
    TABELAS=$(docker exec sesmt-db mariadb -u sesmt -p"${DB_PASS}" sesmt_tse -e "SHOW TABLES" 2>/dev/null | wc -l)
    if [ "$TABELAS" -lt 5 ]; then
        warn "Banco vazio. Importando schema..."
        docker exec -i sesmt-db mariadb -u sesmt -p"${DB_PASS}" sesmt_tse < "$DEPLOY_DIR/docker/init/01-schema.sql"
        docker exec -i sesmt-db mariadb -u sesmt -p"${DB_PASS}" sesmt_tse < "$DEPLOY_DIR/docker/init/02-seed.sql" 2>/dev/null || true
        log "Schema importado."
    fi

    # Verificar se tem dados
    COLAB_COUNT=$(docker exec sesmt-db mariadb -u sesmt -p"${DB_PASS}" sesmt_tse -e "SELECT COUNT(*) FROM colaboradores" -N 2>/dev/null || echo "0")
    USU_COUNT=$(docker exec sesmt-db mariadb -u sesmt -p"${DB_PASS}" sesmt_tse -e "SELECT COUNT(*) FROM usuarios" -N 2>/dev/null || echo "0")

    # NUNCA importar dump automaticamente se ja ha usuarios alem dos 3 do seed
    # (evita reset de senhas ao rodar 'instalar' por engano em producao)
    if [ "$USU_COUNT" -gt 3 ]; then
        log "Banco ja em producao (${USU_COUNT} usuarios, ${COLAB_COUNT} colaboradores). NAO importando dump."
    elif [ "$COLAB_COUNT" -lt 10 ]; then
        if [ -f "$DEPLOY_DIR/database/dump_producao.sql" ] && [ "${FORCE_IMPORT:-0}" = "1" ]; then
            log "FORCE_IMPORT=1 — importando dump de producao (${COLAB_COUNT} colaboradores)..."
            docker exec -i sesmt-db mariadb -u sesmt -p"${DB_PASS}" sesmt_tse < "$DEPLOY_DIR/database/dump_producao.sql"
            log "Dump importado."
        else
            warn "Banco com poucos dados (${COLAB_COUNT} colaboradores, ${USU_COUNT} usuarios)."
            warn "Para importar o dump manualmente:"
            warn "  sudo ./deploy.sh importar database/dump_producao.sql"
            warn "Ou, para reimportar automaticamente em uma instalacao nova:"
            warn "  sudo FORCE_IMPORT=1 ./deploy.sh instalar"
        fi
    else
        log "Banco ja contem ${COLAB_COUNT} colaboradores e ${USU_COUNT} usuarios."
    fi

    # 6. Apache
    log "[6/7] Configurando Apache..."
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

    a2ensite sesmt.conf 2>/dev/null || true
    systemctl reload apache2
    log "Apache configurado."

    # 7. Verificacao final
    log "[7/7] Verificacao final..."
    sleep 3
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/ 2>/dev/null)

    echo ""
    echo "=========================================="
    if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "302" ]; then
        echo -e " ${GREEN}INSTALACAO CONCLUIDA!${NC}"
        echo "=========================================="
        echo ""
        echo " URL: https://${DOMAIN}"
        echo " Login: samuel.morais@tsea.com.br"
        echo " Senha: TseAdmin@2026"
        echo ""
        echo " PROXIMO PASSO:"
        echo " - Copie os PDFs para: ${DEPLOY_DIR}/storage/uploads/"
        echo " - Depois: chown -R www-data:www-data ${DEPLOY_DIR}/storage/uploads/"
        echo ""
    else
        echo -e " ${RED}ERRO: HTTP ${HTTP_CODE}${NC}"
        echo " Verifique: docker logs sesmt-web"
        echo "            docker logs sesmt-db"
    fi
    echo "=========================================="
}

atualizar() {
    log "Atualizando sistema (NAO mexe no banco de dados)..."
    cd "$DEPLOY_DIR"
    git pull origin master
    docker-compose -f "$COMPOSE_FILE" up -d --build
    log "Atualizado! Banco NAO foi alterado. Senhas e usuarios preservados."
}

reset_senhas_padrao() {
    # Utilitario: reseta as senhas dos 3 usuarios do seed para TseAdmin@2026
    # Use apenas quando alguem esqueceu a senha padrao
    DB_PASS=$(grep "^DB_PASS=" "$DEPLOY_DIR/.env" | cut -d'=' -f2)
    HASH='$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
    log "Resetando senhas dos 3 usuarios do seed para 'TseAdmin@2026'..."
    docker exec sesmt-db mariadb -u sesmt -p"${DB_PASS}" sesmt_tse -e "
      UPDATE usuarios SET senha_hash='${HASH}'
       WHERE email IN ('mariana.rios@tsea.com.br','samuel.morais@tsea.com.br','allyff.sousa@tsea.com.br');
    "
    log "Senhas resetadas."
}

importar_dump() {
    DUMP_FILE="${2:-}"
    if [ -z "$DUMP_FILE" ] || [ ! -f "$DUMP_FILE" ]; then
        err "Uso: sudo ./deploy.sh importar <arquivo.sql>"
        err "Exemplo: sudo ./deploy.sh importar /tmp/dump_sesmt.sql"
        exit 1
    fi

    DB_PASS=$(grep "^DB_PASS=" "$DEPLOY_DIR/.env" | cut -d'=' -f2)
    log "Importando $DUMP_FILE..."
    docker exec -i sesmt-db mariadb -u sesmt -p"${DB_PASS}" sesmt_tse < "$DUMP_FILE"

    COLAB_COUNT=$(docker exec sesmt-db mariadb -u sesmt -p"${DB_PASS}" sesmt_tse -e "SELECT COUNT(*) FROM colaboradores WHERE excluido_em IS NULL" -N 2>/dev/null)
    log "Importado! ${COLAB_COUNT} colaboradores no banco."

    # Corrigir permissoes do storage
    chown -R www-data:www-data "$DEPLOY_DIR/storage" 2>/dev/null || true
    chmod -R 750 "$DEPLOY_DIR/storage"
    log "Permissoes corrigidas."
}

status_sistema() {
    echo ""
    echo "=== SESMT TSE - Status ==="
    echo ""

    # Containers
    echo "Containers:"
    docker ps --format "  {{.Names}}: {{.Status}}" --filter "name=sesmt" 2>/dev/null || echo "  Docker nao esta rodando"
    echo ""

    # Banco
    if [ -f "$DEPLOY_DIR/.env" ]; then
        DB_PASS=$(grep "^DB_PASS=" "$DEPLOY_DIR/.env" | cut -d'=' -f2)
        COLAB=$(docker exec sesmt-db mariadb -u sesmt -p"${DB_PASS}" sesmt_tse -e "SELECT COUNT(*) FROM colaboradores WHERE excluido_em IS NULL" -N 2>/dev/null || echo "ERRO")
        DOCS=$(docker exec sesmt-db mariadb -u sesmt -p"${DB_PASS}" sesmt_tse -e "SELECT COUNT(*) FROM documentos WHERE excluido_em IS NULL" -N 2>/dev/null || echo "ERRO")
        CERTS=$(docker exec sesmt-db mariadb -u sesmt -p"${DB_PASS}" sesmt_tse -e "SELECT COUNT(*) FROM certificados WHERE excluido_em IS NULL" -N 2>/dev/null || echo "ERRO")
        echo "Banco de dados:"
        echo "  Colaboradores: $COLAB"
        echo "  Documentos: $DOCS"
        echo "  Certificados: $CERTS"
    fi
    echo ""

    # Web
    HTTP=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/ 2>/dev/null)
    echo "Web: HTTP $HTTP"

    # Storage
    UPLOADS=$(find "$DEPLOY_DIR/storage/uploads" -name "*.pdf" 2>/dev/null | wc -l)
    echo "PDFs no storage: $UPLOADS"
    echo ""
}

# ---- MAIN ----
case "$ACAO" in
    instalar)
        instalar
        ;;
    atualizar)
        atualizar
        ;;
    importar)
        importar_dump "$@"
        ;;
    status)
        status_sistema
        ;;
    reset-senhas)
        reset_senhas_padrao
        ;;
    *)
        echo ""
        echo "SESMT TSE - Script de Deploy"
        echo ""
        echo "Uso: sudo ./deploy.sh <comando>"
        echo ""
        echo "Comandos:"
        echo "  instalar    Instalacao completa (primeira vez)"
        echo "  atualizar   Atualiza codigo sem tocar no banco"
        echo "  importar    Importa dump SQL: ./deploy.sh importar arquivo.sql"
        echo "  status      Mostra status do sistema"
        echo ""
        ;;
esac
