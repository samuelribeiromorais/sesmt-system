-- Migration 016: Módulo RH Fase 2 — Vínculos N:N + exigências por obra
-- Cria rh_vinculos_obra e adiciona dimensão obra_id em config_cliente_docs.

-- ─── 1. rh_vinculos_obra ──────────────────────────────────────────────────
-- Vínculos adicionais do colaborador a obras (além do vínculo primário do GCO).
CREATE TABLE IF NOT EXISTS rh_vinculos_obra (
    id              INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    colaborador_id  INT NOT NULL,
    obra_id         INT NOT NULL,
    desde           DATE NOT NULL,
    ate_quando      DATE NULL COMMENT 'NULL = vínculo aberto',
    funcao_no_site  VARCHAR(120) NULL,
    criado_por      INT NOT NULL,
    criado_em       DATETIME NOT NULL DEFAULT NOW(),
    excluido_em     DATETIME NULL,

    CONSTRAINT fk_rhv_colaborador FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    CONSTRAINT fk_rhv_obra        FOREIGN KEY (obra_id)        REFERENCES obras(id)         ON DELETE RESTRICT,
    CONSTRAINT fk_rhv_criado_por  FOREIGN KEY (criado_por)     REFERENCES usuarios(id)
);

-- Evita vínculo duplicado em aberto (mesmo colab + mesma obra + ate_quando NULL).
-- MariaDB permite múltiplos NULL no índice unique, então isso só bloqueia o segundo
-- vínculo aberto idêntico — vínculos encerrados (com data) podem coexistir.
CREATE UNIQUE INDEX IF NOT EXISTS idx_rhv_uniq_aberto
    ON rh_vinculos_obra (colaborador_id, obra_id, ate_quando);

CREATE INDEX IF NOT EXISTS idx_rhv_colab_aberto
    ON rh_vinculos_obra (colaborador_id, ate_quando);

CREATE INDEX IF NOT EXISTS idx_rhv_obra
    ON rh_vinculos_obra (obra_id);

-- ─── 2. obra_id em config_cliente_docs ────────────────────────────────────
-- Permite exigências diferenciadas por obra (ex.: Cargill Anápolis x Cargill Goiânia).
-- NULL = exigência vale para TODAS as obras do cliente (comportamento atual preservado).
ALTER TABLE config_cliente_docs
    ADD COLUMN obra_id INT NULL AFTER cliente_id,
    ADD CONSTRAINT fk_ccd_obra FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE,
    ADD INDEX idx_ccd_obra (obra_id);
