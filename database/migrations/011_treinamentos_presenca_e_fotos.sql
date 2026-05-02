-- Migration 011 (rodada 3 / Grupo 7):
-- 1. Lista de presença com checkbox: campo 'presente' por participante (certificado da turma)
--    NULL = ainda não marcado; 1 = presente; 0 = ausente.
-- 2. Anexar fotos do treinamento (2 slots por turma).

ALTER TABLE certificados
    ADD COLUMN IF NOT EXISTS presente TINYINT(1) NULL
        COMMENT 'Presença na turma: NULL=não marcado, 1=presente, 0=ausente';

ALTER TABLE treinamentos
    ADD COLUMN IF NOT EXISTS foto1_path VARCHAR(255) NULL
        COMMENT 'Foto obrigatória do treinamento (relativa a storage/uploads)',
    ADD COLUMN IF NOT EXISTS foto2_path VARCHAR(255) NULL
        COMMENT 'Foto opcional do treinamento';
