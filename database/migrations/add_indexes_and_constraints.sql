-- Migration: add_indexes_and_constraints.sql
-- Data: 2026-03-20
-- Descrição: Adiciona índices compostos para melhorar performance das consultas mais comuns
-- MariaDB 10.11+

-- ============================================================
-- 1. DOCUMENTOS - Índices compostos para queries frequentes
-- ============================================================

-- Índice composto para busca por colaborador + tipo + status (tela de documentos do colaborador)
ALTER TABLE documentos ADD INDEX idx_docs_colab_tipo_status (colaborador_id, tipo_documento_id, status);

-- Índice composto para filtro de status com soft-delete (listagens gerais)
ALTER TABLE documentos ADD INDEX idx_docs_status_excluido (status, excluido_em);

-- Índice para busca por data de emissão (relatórios por período)
ALTER TABLE documentos ADD INDEX idx_docs_emissao (data_emissao);

-- ============================================================
-- 2. CERTIFICADOS - Índice composto para queries frequentes
-- ============================================================

-- Índice composto para busca por colaborador + status (tela de certificados do colaborador)
ALTER TABLE certificados ADD INDEX idx_certs_colab_status (colaborador_id, status);

-- ============================================================
-- NOTA: Os seguintes índices já existem e NÃO foram duplicados:
--
-- documentos:
--   idx_colaborador (colaborador_id)
--   idx_tipo (tipo_documento_id)
--   idx_status (status)
--   idx_validade (data_validade)
--   idx_excluido_em (excluido_em)
--
-- certificados:
--   idx_colaborador (colaborador_id)
--   idx_status (status)
--   idx_validade (data_validade)
--   idx_excluido_em (excluido_em)
--
-- colaboradores:
--   idx_status (status)
--   idx_cliente (cliente_id)
--
-- logs_acesso:
--   idx_usuario (usuario_id)
--   idx_criado (criado_em)
-- ============================================================
