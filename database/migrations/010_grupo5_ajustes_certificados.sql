-- Migration 010 (rodada 3 / Grupo 5):
-- 1. Tirar Diego Costa das assinaturas do LOTO (instrutor da turma assina)
-- 2. Mover conteúdo programático para a primeira página (igual NR-06)
--    para: NR-11 (todos), NR-18 (geral e andaime), NR-20 (Cargill e Unilever),
--          NR-33 (trabalhador e supervisor), NR-34 (geral, observador, soldador) e NR-35.

-- 1. LOTO sem Diego
UPDATE tipos_certificado
SET tem_diego = 0,
    tem_diego_responsavel = 0
WHERE codigo = 'LOTO';

-- 2. Conteúdo programático na primeira página (conteudo_no_verso = 0)
UPDATE tipos_certificado
SET conteudo_no_verso = 0
WHERE codigo IN (
    'NR 11 MUNK', 'NR 11 PONTE ROLANTE', 'NR 11 RIGGER', 'NR 11 SINALEIRO',
    'NR 18 GERAL', 'NR 18 ANDAIME',
    'NR 20 UNILEVER', 'NR 20 CARGILL',
    'NR 33 TRABALHADOR', 'NR 33 SUPERVISOR',
    'NR 34 GERAL', 'NR 34 OBSERVADOR', 'NR 34 SOLDADOR',
    'NR 35'
);
