-- ============================================
-- Migration: Verificacao de Conformidade
-- Data: 2026-03-20
-- ============================================
-- Auditoria completa do banco de dados realizada com os seguintes resultados:
--
-- PROBLEMAS ENCONTRADOS E CORRIGIDOS:
-- 1. 2 ASOs Periodicos (IDs 97, 265) com validade calculada em 24 meses em vez de 12
-- 2. 6.558 documentos duplicados (mesmo tipo/colaborador) marcados como obsoleto
-- 3. 80 registros com arquivo_path duplicado (18 marcados como obsoleto)
-- 4. 1 pasta orfã (colaborador_id=135 inexistente) com 9 PDFs - sem impacto
--
-- VERIFICACOES REALIZADAS (SEM PROBLEMAS):
-- - Status vs data_validade: 0 inconsistencias em documentos
-- - Status vs data_validade: 0 inconsistencias em certificados
-- - Arquivos fisicos vs BD: 0 arquivos faltantes (16.812 PDFs verificados)
-- - Arquivos orfãos: 0 detectados (amostra de 100)
-- - Nomes duplicados de colaboradores: 0
-- - Colaboradores ativos com excluido_em: 0
--
-- DADOS INFORMATIVOS (nao sao erros do sistema):
-- - 66 colaboradores ativos sem nenhum documento
-- - 76 colaboradores ativos sem ASO
-- - 96 colaboradores ativos sem EPI
-- - 100 colaboradores ativos sem OS
-- - 154 colaboradores ativos sem cliente/obra atribuido
-- - 830 colaboradores inativos com documentos vigentes (normal - docs nao vencem ao demitir)
--
-- RESULTADO FINAL:
-- Documentos (deduplicados): vigente=8.626, vencido=1.001, proximo_vencimento=189
-- Certificados: vigente=4.532, vencido=1.187, proximo_vencimento=35
-- Colaboradores: ativo=709, inativo=948
-- Inconsistencias: ZERO

USE sesmt_tse;

-- Fix 1: ASOs com validade errada
UPDATE documentos SET data_validade = DATE_ADD(data_emissao, INTERVAL 12 MONTH) WHERE id IN (97, 265);
UPDATE documentos SET status = 'vencido' WHERE id IN (97, 265);

-- Fix 2: Marcar duplicatas como obsoleto (manter apenas o mais recente por tipo/colaborador)
UPDATE documentos d
SET d.status = 'obsoleto'
WHERE d.excluido_em IS NULL
  AND d.status != 'obsoleto'
  AND d.id NOT IN (
    SELECT max_id FROM (
      SELECT MAX(sub.id) as max_id
      FROM (
        SELECT id, colaborador_id, tipo_documento_id,
               ROW_NUMBER() OVER (
                 PARTITION BY colaborador_id, tipo_documento_id
                 ORDER BY data_emissao DESC, id DESC
               ) as rn
        FROM documentos
        WHERE excluido_em IS NULL AND status != 'obsoleto'
      ) sub
      WHERE sub.rn = 1
      GROUP BY sub.colaborador_id, sub.tipo_documento_id
    ) latest
  );

-- Fix 3: Marcar registros com arquivo_path duplicado
UPDATE documentos d
JOIN (
    SELECT MIN(id) as old_id
    FROM documentos
    WHERE excluido_em IS NULL
    GROUP BY arquivo_path
    HAVING COUNT(*) > 1
) dup ON d.id = dup.old_id
SET d.status = 'obsoleto';
