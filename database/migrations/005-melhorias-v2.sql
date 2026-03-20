-- ============================================
-- Migration 005 - Melhorias V2 (17 features)
-- SESMT System - TSE Engenharia e Automacao
-- Data: 2026-03-19
-- ============================================

USE sesmt_tse;

-- ============================================
-- 1. 2FA TOTP - Colunas na tabela usuarios
-- ============================================
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS totp_ativo TINYINT(1) NOT NULL DEFAULT 0;

-- ============================================
-- 2. Sessoes Ativas
-- ============================================
CREATE TABLE IF NOT EXISTS sessoes_ativas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    ultimo_acesso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_session_id (session_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_ultimo_acesso (ultimo_acesso)
) ENGINE=InnoDB;

-- ============================================
-- 3. Politica de Senhas - Colunas na tabela usuarios
-- ============================================
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS senha_alterada_em DATETIME NULL,
    ADD COLUMN IF NOT EXISTS senha_historico JSON NULL;

-- ============================================
-- 7. Notificacoes In-App
-- ============================================
CREATE TABLE IF NOT EXISTS notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    tipo ENUM('alerta','info','sucesso','erro') NOT NULL DEFAULT 'info',
    titulo VARCHAR(200) NOT NULL,
    mensagem TEXT NOT NULL,
    lida TINYINT(1) NOT NULL DEFAULT 0,
    link VARCHAR(500) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_lida (lida),
    INDEX idx_tipo (tipo),
    INDEX idx_criado (criado_em)
) ENGINE=InnoDB;

-- ============================================
-- 8. Dark Mode - Coluna na tabela usuarios
-- ============================================
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS tema VARCHAR(10) NOT NULL DEFAULT 'light';

-- ============================================
-- 11. REST API - Tokens
-- ============================================
CREATE TABLE IF NOT EXISTS api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    nome VARCHAR(100) NOT NULL,
    ultimo_uso DATETIME NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_token (token),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB;

-- ============================================
-- 13. eSocial - Eventos
-- ============================================
CREATE TABLE IF NOT EXISTS esocial_eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    tipo_evento ENUM('S-2210','S-2220','S-2240') NOT NULL,
    payload JSON NOT NULL,
    status ENUM('pendente','enviado','aceito','rejeitado') NOT NULL DEFAULT 'pendente',
    protocolo VARCHAR(100) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    enviado_em DATETIME NULL,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_tipo_evento (tipo_evento),
    INDEX idx_status (status),
    INDEX idx_criado (criado_em)
) ENGINE=InnoDB;

-- ============================================
-- 14. Versionamento de Documentos
-- ============================================
ALTER TABLE documentos
    ADD COLUMN IF NOT EXISTS versao INT NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS documento_pai_id INT NULL;

-- FK auto-referencia para versionamento
-- Usamos procedimento para evitar erro se FK ja existir
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'documentos'
      AND CONSTRAINT_NAME = 'fk_documento_pai'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE documentos ADD CONSTRAINT fk_documento_pai FOREIGN KEY (documento_pai_id) REFERENCES documentos(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 15. Assinatura Digital
-- ============================================
ALTER TABLE documentos
    ADD COLUMN IF NOT EXISTS assinatura_digital TEXT NULL,
    ADD COLUMN IF NOT EXISTS assinado_por VARCHAR(200) NULL,
    ADD COLUMN IF NOT EXISTS assinado_em DATETIME NULL;

-- ============================================
-- 16. Soft Delete (Lixeira)
-- ============================================
ALTER TABLE colaboradores
    ADD COLUMN IF NOT EXISTS excluido_em DATETIME NULL;

ALTER TABLE documentos
    ADD COLUMN IF NOT EXISTS excluido_em DATETIME NULL;

ALTER TABLE certificados
    ADD COLUMN IF NOT EXISTS excluido_em DATETIME NULL;

-- Indices para filtrar soft-deleted rapidamente
CREATE INDEX IF NOT EXISTS idx_excluido_em ON colaboradores(excluido_em);
CREATE INDEX IF NOT EXISTS idx_excluido_em ON documentos(excluido_em);
CREATE INDEX IF NOT EXISTS idx_excluido_em ON certificados(excluido_em);

-- ============================================
-- 17. Audit Log Viewer - Index na descricao
-- ============================================
CREATE INDEX IF NOT EXISTS idx_descricao ON logs_acesso(descricao(100));
