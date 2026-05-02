-- Migration 006: integração GCO - adiciona codigo_gco, celular e celular_manual
ALTER TABLE colaboradores
    ADD COLUMN IF NOT EXISTS codigo_gco VARCHAR(20) NULL AFTER id,
    ADD COLUMN IF NOT EXISTS celular VARCHAR(20) NULL AFTER telefone,
    ADD COLUMN IF NOT EXISTS celular_manual VARCHAR(20) NULL COMMENT 'Celular inserido manualmente pelo SESMT quando o GCO nao fornece' AFTER celular;

ALTER TABLE colaboradores
    ADD INDEX IF NOT EXISTS idx_codigo_gco (codigo_gco);

-- Tabela de log das sincronizações
CREATE TABLE IF NOT EXISTS gco_sync_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    iniciado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    concluido_em DATETIME NULL,
    total_api INT NULL COMMENT 'Total retornado pela API',
    criados INT NOT NULL DEFAULT 0,
    atualizados INT NOT NULL DEFAULT 0,
    desativados INT NOT NULL DEFAULT 0,
    erros INT NOT NULL DEFAULT 0,
    status ENUM('em_andamento','concluido','erro') NOT NULL DEFAULT 'em_andamento',
    mensagem TEXT NULL,
    executado_por INT NULL,
    FOREIGN KEY (executado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;
