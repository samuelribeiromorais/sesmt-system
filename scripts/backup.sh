#!/bin/bash
# SESMT - Script de Backup Automatizado
# Gera backup do banco de dados e compacta
# Configurado para rodar via cron dentro do container

BACKUP_DIR="/var/www/html/storage/backups"
DATE=$(date +%Y-%m-%d_%H-%M)
KEEP_DAYS=30

mkdir -p "$BACKUP_DIR"

# Backup do banco de dados
echo "[$(date)] Iniciando backup do banco..."
mysqldump -h db -u sesmt -psesmt2026 sesmt_tse --single-transaction --routines --triggers > "$BACKUP_DIR/db_${DATE}.sql" 2>/dev/null

if [ $? -eq 0 ]; then
    # Compactar
    gzip "$BACKUP_DIR/db_${DATE}.sql"
    SIZE=$(du -h "$BACKUP_DIR/db_${DATE}.sql.gz" | cut -f1)
    echo "[$(date)] Backup concluido: db_${DATE}.sql.gz ($SIZE)"

    # Limpar backups antigos
    find "$BACKUP_DIR" -name "db_*.sql.gz" -mtime +$KEEP_DAYS -delete 2>/dev/null
    REMOVED=$(find "$BACKUP_DIR" -name "db_*.sql.gz" -mtime +$KEEP_DAYS 2>/dev/null | wc -l)
    echo "[$(date)] Backups com mais de ${KEEP_DAYS} dias removidos."
else
    echo "[$(date)] ERRO no backup do banco!"
    exit 1
fi

echo "[$(date)] Backup finalizado com sucesso."
