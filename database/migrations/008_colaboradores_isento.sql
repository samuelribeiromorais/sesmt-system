-- Migration 008: Adiciona flag 'isento' em colaboradores
-- Colaboradores PJ / Fazenda podem ser marcados como isentos de conformidade
-- Não são contabilizados nos totais do dashboard nem penalizam as métricas

ALTER TABLE colaboradores
    ADD COLUMN isento TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN isento_motivo VARCHAR(255) NULL AFTER isento;
