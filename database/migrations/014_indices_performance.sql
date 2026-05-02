-- Migration 014: índices para acelerar a latestSubquery (mais
-- recente de cada documento por colaborador/tipo).
--
-- A subquery usa: WHERE colaborador_id = ? AND status != 'obsoleto'
-- AND excluido_em IS NULL ORDER BY data_emissao DESC, id ASC.
-- Um índice cobrindo (colaborador_id, tipo_documento_id, data_emissao)
-- elimina o filesort no painel de obra e na ficha do colaborador.

CREATE INDEX IF NOT EXISTS idx_docs_latest_lookup
    ON documentos (colaborador_id, tipo_documento_id, data_emissao DESC, id);

-- Acelera a query de KPIs que filtra por status='vencido' nas
-- últimas versões — usado em obra, cliente e dashboard.
CREATE INDEX IF NOT EXISTS idx_docs_status_colab
    ON documentos (status, colaborador_id, excluido_em);
