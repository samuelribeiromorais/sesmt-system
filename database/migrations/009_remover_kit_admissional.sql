-- Migration 009: remove Kit Admissional as document type
-- Rationale: Kit Admissional is generated at SOC, sent to the clinic, and only
-- returns signed by the collaborator and the doctor. An unsigned version in the
-- system is useless, so we deactivate the type and soft-delete existing records.

-- Soft-delete all existing Kit Admissional documents
UPDATE documentos d
JOIN tipos_documento t ON t.id = d.tipo_documento_id
SET d.excluido_em = NOW()
WHERE t.nome = 'Kit Admissional'
  AND d.excluido_em IS NULL;

-- Deactivate the type so it no longer appears in upload dropdowns
UPDATE tipos_documento
SET ativo = 0
WHERE nome = 'Kit Admissional';
