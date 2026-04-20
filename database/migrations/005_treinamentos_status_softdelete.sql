-- Migration 005: Adiciona status e soft-delete em treinamentos
-- Compatibilidade retroativa: ADD COLUMN NULL/DEFAULT apenas

ALTER TABLE treinamentos
    ADD COLUMN IF NOT EXISTS status ENUM('em_andamento','aguardando_assinaturas','finalizada') NOT NULL DEFAULT 'em_andamento' AFTER observacoes,
    ADD COLUMN IF NOT EXISTS excluido_em DATETIME NULL AFTER atualizado_em;
