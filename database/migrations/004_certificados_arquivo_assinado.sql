-- Migration 004: Adiciona controle de assinatura em certificados
-- Certificados só devem ser contabilizados após upload do PDF assinado
-- Compatibilidade retroativa: ADD COLUMN NULL apenas

ALTER TABLE certificados
    ADD COLUMN IF NOT EXISTS arquivo_assinado VARCHAR(500) NULL AFTER ministrante_id,
    ADD COLUMN IF NOT EXISTS assinado_em DATETIME NULL AFTER arquivo_assinado;
