-- Migration 013: tabela de tracking de migrations aplicadas.
-- Permite ao script scripts/migrate.php saber quais migrations já rodaram
-- e pular as anteriores em deploys subsequentes.

CREATE TABLE IF NOT EXISTS _migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    checksum_sha256 CHAR(64) NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    applied_by VARCHAR(100) NULL,
    INDEX idx_applied (applied_at)
) ENGINE=InnoDB;
