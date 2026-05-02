-- Migration 012 (rodada 3 / Grupo 8): refatoração do módulo RH
--
-- Novo fluxo: SESMT faz upload → documento aparece para o RH com um
-- checkbox "Enviado ao cliente". RH marca após enviar, e o checkbox
-- é zerado quando o documento é substituído por uma nova versão.
--
-- O fluxo antigo (RH upload + aprovação SESMT) é descontinuado, mas
-- as colunas aprovacao_* permanecem para histórico.

ALTER TABLE documentos
    ADD COLUMN IF NOT EXISTS enviado_cliente TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = RH já enviou este documento ao cliente final',
    ADD COLUMN IF NOT EXISTS enviado_cliente_em DATETIME NULL
        COMMENT 'Quando o RH marcou como enviado',
    ADD COLUMN IF NOT EXISTS enviado_cliente_por INT NULL
        COMMENT 'Usuário (RH) que marcou como enviado',
    ADD COLUMN IF NOT EXISTS substituido_por INT NULL
        COMMENT 'Aponta para o documento que substituiu este (versionamento)';

-- FK para enviado_cliente_por (idempotente: cria se não existir)
SET @fk := (SELECT COUNT(*) FROM information_schema.table_constraints
             WHERE table_schema = DATABASE()
               AND table_name = 'documentos'
               AND constraint_name = 'fk_doc_enviado_cliente_por');
SET @sql := IF(@fk = 0,
    'ALTER TABLE documentos ADD CONSTRAINT fk_doc_enviado_cliente_por FOREIGN KEY (enviado_cliente_por) REFERENCES usuarios(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index para a query do dashboard RH
CREATE INDEX IF NOT EXISTS idx_doc_enviado_cliente
    ON documentos (enviado_cliente, status, excluido_em);
