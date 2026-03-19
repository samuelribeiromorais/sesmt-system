-- ============================================
-- SESMT System - Dados Iniciais (Seed)
-- ============================================

USE sesmt_tse;

-- ============================================
-- USUARIOS INICIAIS (Admin)
-- Senhas temporarias: Trocar no primeiro acesso
-- Senha padrao: TseAdmin@2026 (bcrypt hash abaixo)
-- ============================================
INSERT INTO usuarios (nome, email, senha_hash, perfil, ativo, criado_em) VALUES
('Mariana Toscano Rios', 'mariana.rios@tsea.com.br', '$2y$12$LK7dP3kP8E5Ue8RVJ5Y9r.xVzq1VXkN3VGp1CqNz3kQ8H5mWfYxKy', 'admin', 1, NOW()),
('Samuel Morais', 'samuel.morais@tsea.com.br', '$2y$12$LK7dP3kP8E5Ue8RVJ5Y9r.xVzq1VXkN3VGp1CqNz3kQ8H5mWfYxKy', 'admin', 1, NOW()),
('Allyff Sousa', 'allyff.sousa@tsea.com.br', '$2y$12$LK7dP3kP8E5Ue8RVJ5Y9r.xVzq1VXkN3VGp1CqNz3kQ8H5mWfYxKy', 'admin', 1, NOW());

-- ============================================
-- TIPOS DE CERTIFICADO (26 tipos do sistema atual)
-- ============================================
INSERT INTO tipos_certificado (codigo, titulo, duracao, validade_meses, tem_anuencia, tem_diego, conteudo_no_verso, conteudo_programatico) VALUES

('DIRECAO DEFENSIVA', 'DIRECAO DEFENSIVA', '16h', 60, 0, 0, 1,
'["1. Legislacao de transito","2. Direcao defensiva","3. Nocoes de primeiros socorros","4. Meio ambiente e cidadania","5. Prevencao de acidentes"]'),

('LOTO', 'LOTO - BLOQUEIO E ETIQUETAGEM DE ENERGIAS PERIGOSAS', '8h', 12, 0, 1, 1,
'["1. Conceitos de energias perigosas","2. Procedimentos de bloqueio","3. Tipos de dispositivos de bloqueio","4. Etiquetagem","5. Procedimentos de liberacao","6. Responsabilidades"]'),

('NR 06', 'NR - 06: EQUIPAMENTO DE PROTECAO INDIVIDUAL - EPI', '4h', 12, 0, 0, 0,
'["1. Definicao de EPI","2. Obrigacoes do empregador e empregado","3. Certificado de Aprovacao (CA)","4. Tipos de EPI","5. Uso correto e conservacao","6. Higienizacao e guarda"]'),

('NR 10 BASICO', 'NR - 10: BASICO - SEGURANCA EM INSTALACOES E SERVICOS EM ELETRICIDADE', '40h', 24, 1, 1, 1,
'["1. Introducao a seguranca com eletricidade","2. Riscos em instalacoes e servicos com eletricidade","3. Tecnicas de Analise de Risco","4. Medidas de Controle do Risco Eletrico","5. Normas Tecnicas Brasileiras","6. Regulamentacoes do MTE","7. Equipamentos de protecao coletiva","8. Equipamentos de protecao individual","9. Rotinas de trabalho e procedimentos","10. Documentacao de instalacoes eletricas","11. Riscos adicionais","12. Protecao e combate a incendios","13. Acidentes de origem eletrica","14. Primeiros socorros","15. Responsabilidades"]'),

('NR 10 RECICLAGEM', 'NR - 10: RECICLAGEM - SEGURANCA EM INSTALACOES E SERVICOS EM ELETRICIDADE', '20h', 24, 1, 1, 1,
'["1. Introducao a seguranca com eletricidade","2. Riscos em instalacoes e servicos com eletricidade","3. Tecnicas de Analise de Risco","4. Medidas de Controle do Risco Eletrico","5. Normas Tecnicas Brasileiras","6. Regulamentacoes do MTE","7. Equipamentos de protecao coletiva","8. Equipamentos de protecao individual","9. Rotinas de trabalho e procedimentos","10. Documentacao de instalacoes eletricas","11. Riscos adicionais","12. Protecao e combate a incendios","13. Acidentes de origem eletrica","14. Primeiros socorros","15. Responsabilidades"]'),

('NR 10 SEP', 'NR - 10: SEP - SISTEMA ELETRICO DE POTENCIA', '40h', 24, 1, 1, 1,
'["1. Organizacao do Sistema Eletrico de Potencia (SEP)","2. Organizacao do trabalho","3. Aspectos comportamentais","4. Condicoes impeditivas para servicos","5. Riscos tipicos no SEP","6. Tecnicas de trabalho sob tensao","7. Equipamentos e ferramentas de trabalho","8. Sistemas de protecao coletiva","9. Equipamentos de protecao individual","10. Posturas e vestuarios de trabalho","11. Seguranca com veiculos e transporte","12. Sinalizacao e isolamento de areas","13. Liberacao de instalacao para servico","14. Procedimentos de trabalho","15. Acidentes tipicos e analises"]'),

('NR 11 MUNK', 'NR - 11: OPERADOR DE MUNCK', '16h', 12, 0, 0, 1,
'["1. Legislacao e normas","2. Tipos de guindastes articulados","3. Capacidade de carga","4. Inspecao pre-operacional","5. Operacao segura","6. Sinalizacao","7. Manutencao preventiva","8. Situacoes de emergencia"]'),

('NR 11 PONTE ROLANTE', 'NR - 11: OPERADOR DE PONTE ROLANTE', '16h', 12, 0, 0, 1,
'["1. Legislacao e normas","2. Tipos de pontes rolantes","3. Capacidade de carga e tabelas","4. Inspecao pre-operacional","5. Operacao segura","6. Acessorios de icamento","7. Sinalizacao convencional","8. Manutencao preventiva"]'),

('NR 11 RIGGER', 'NR - 11: RIGGER - MOVIMENTACAO DE CARGAS', '40h', 12, 0, 0, 1,
'["1. Legislacao e normas aplicaveis","2. Tipos de cargas e centro de gravidade","3. Acessorios de icamento","4. Plano de Rigging","5. Tabelas de capacidade","6. Sinalizacao convencional","7. Procedimentos de seguranca","8. Inspecao de equipamentos"]'),

('NR 11 SINALEIRO', 'NR - 11: SINALEIRO DE GUINDASTES', '8h', 12, 0, 0, 1,
'["1. Legislacao e normas","2. Funcoes do sinaleiro","3. Sinais manuais convencionais","4. Comunicacao por radio","5. Procedimentos de seguranca","6. Situacoes de emergencia"]'),

('NR 12', 'NR - 12: SEGURANCA NO TRABALHO EM MAQUINAS E EQUIPAMENTOS', '8h', 24, 0, 0, 1,
'["1. Principios gerais da NR-12","2. Arranjo fisico e instalacoes","3. Dispositivos de seguranca","4. Sistemas de protecao","5. Dispositivos de parada de emergencia","6. Meios de acesso permanentes","7. Manutencao e inspecao","8. Sinalizacao","9. Procedimentos de trabalho","10. Capacitacao dos trabalhadores"]'),

('NR 18 ANDAIME', 'NR - 18: ANDAIMES - MONTAGEM E DESMONTAGEM', '8h', 12, 0, 0, 1,
'["1. Legislacao e normas","2. Tipos de andaimes","3. Montagem e desmontagem","4. Inspecao","5. Uso correto","6. EPI obrigatorios","7. Procedimentos de seguranca"]'),

('NR 18 GERAL', 'NR - 18: CONDICOES DE SEGURANCA E SAUDE NO TRABALHO NA INDUSTRIA DA CONSTRUCAO', '8h', 12, 0, 0, 1,
'["1. Condicoes e meio ambiente de trabalho","2. Areas de vivencia","3. Ordem e limpeza","4. Sinalizacao de seguranca","5. EPI e EPC","6. Armazenamento de materiais","7. Protecao contra incendio","8. Primeiros socorros"]'),

('NR 20 UNILEVER', 'NR - 20: SEGURANCA COM INFLAMAVEIS E COMBUSTIVEIS - UNILEVER', '4h', 12, 0, 0, 1,
'["1. Inflamaveis: conceito e classificacao","2. Combustiveis: conceito e classificacao","3. Controle de fontes de ignicao","4. Protecao contra incendio","5. Procedimentos em situacoes de emergencia","6. Requisitos especificos Unilever"]'),

('NR 20 CARGILL', 'NR - 20: SEGURANCA COM INFLAMAVEIS E COMBUSTIVEIS - CARGILL', '16h', 12, 0, 0, 1,
'["1. Inflamaveis: conceito e classificacao","2. Combustiveis: conceito e classificacao","3. Controle de fontes de ignicao","4. Protecao contra incendio","5. Procedimentos basicos em emergencia","6. Zona de atmosfera explosiva","7. Plano de resposta a emergencia","8. Requisitos especificos Cargill"]'),

('NR 33 SUPERVISOR', 'NR - 33: SUPERVISOR DE ENTRADA EM ESPACOS CONFINADOS', '40h', 12, 1, 1, 1,
'["1. Definicao de espaco confinado","2. Reconhecimento e avaliacao de riscos","3. Monitoramento atmosferico","4. Ventilacao e resgate","5. Permissao de Entrada e Trabalho (PET)","6. Procedimentos de emergencia","7. Primeiros socorros","8. Funcoes do supervisor","9. Responsabilidades legais","10. Praticas simuladas"]'),

('NR 33 TRABALHADOR', 'NR - 33: TRABALHADOR AUTORIZADO - ESPACOS CONFINADOS', '16h', 12, 1, 1, 1,
'["1. Definicao de espaco confinado","2. Riscos em espacos confinados","3. Monitoramento atmosferico","4. Medidas de controle","5. Permissao de Entrada e Trabalho (PET)","6. Nocoes de resgate","7. Primeiros socorros","8. EPI obrigatorios"]'),

('NR 34 GERAL', 'NR - 34: CONDICOES E MEIO AMBIENTE DE TRABALHO NA INDUSTRIA DA CONSTRUCAO E REPARACAO NAVAL', '8h', 12, 0, 0, 1,
'["1. Condicoes e meio ambiente de trabalho","2. Trabalho a quente","3. Ensaios nao destrutivos","4. Pintura","5. Movimentacao de cargas","6. Jateamento e hidrojateamento","7. Montagem e desmontagem de andaimes","8. Atividades com exposicao a radiacao"]'),

('NR 34 SOLDADOR', 'NR - 34: SOLDADOR - TRABALHO A QUENTE', '8h', 24, 0, 0, 1,
'["1. Riscos do trabalho a quente","2. Procedimentos de seguranca","3. Permissao de trabalho","4. Inspencao da area","5. EPI obrigatorios","6. Prevencao e combate a incendio","7. Procedimentos de emergencia"]'),

('NR 34 OBSERVADOR', 'NR - 34: OBSERVADOR DE TRABALHO A QUENTE', '4h', 24, 0, 0, 1,
'["1. Funcoes do observador","2. Riscos do trabalho a quente","3. Monitoramento da area","4. Uso de extintores","5. Comunicacao de emergencia","6. Procedimentos pos-trabalho"]'),

('NR 35', 'NR - 35: TRABALHO EM ALTURA', '8h', 24, 1, 1, 1,
'["1. Normas e regulamentos aplicaveis","2. Analise de risco e condicoes impeditivas","3. Riscos potenciais inerentes ao trabalho em altura","4. Medidas de prevencao e controle","5. Sistemas, equipamentos e procedimentos de protecao coletiva","6. EPI para trabalho em altura","7. Acidentes tipicos em trabalhos em altura","8. Conductas em situacoes de emergencia","9. Nocoes de tecnicas de resgate e primeiros socorros"]'),

('NR 10 BASICO RECICL 20H', 'NR - 10: RECICLAGEM 20H - SEGURANCA EM INSTALACOES E SERVICOS EM ELETRICIDADE', '20h', 24, 1, 1, 1,
'["1. Introducao a seguranca com eletricidade","2. Riscos em instalacoes e servicos","3. Tecnicas de Analise de Risco","4. Medidas de controle","5. Equipamentos de protecao","6. Rotinas de trabalho","7. Primeiros socorros","8. Responsabilidades"]'),

('NR 10 SEP RECICL', 'NR - 10: RECICLAGEM SEP - SISTEMA ELETRICO DE POTENCIA', '20h', 24, 1, 1, 1,
'["1. Organizacao do SEP","2. Organizacao do trabalho","3. Riscos tipicos no SEP","4. Tecnicas de trabalho","5. Sistemas de protecao","6. Equipamentos de protecao individual","7. Procedimentos de trabalho","8. Acidentes tipicos"]'),

('NR 18 PLATAFORMA', 'NR - 18: PLATAFORMA ELEVATORIA', '8h', 12, 0, 0, 1,
'["1. Legislacao e normas","2. Tipos de plataformas elevatorias","3. Inspecao pre-operacional","4. Operacao segura","5. Limites de carga","6. Procedimentos de emergencia","7. EPI obrigatorios"]'),

('NR 12 RECICL', 'NR - 12: RECICLAGEM - SEGURANCA EM MAQUINAS E EQUIPAMENTOS', '4h', 24, 0, 0, 1,
'["1. Atualizacoes da NR-12","2. Revisao de dispositivos de seguranca","3. Sistemas de protecao","4. Procedimentos de trabalho","5. Manutencao e inspecao"]'),

('NR 34 TRABALHO QUENTE', 'NR - 34: TRABALHO A QUENTE (VIGIAS)', '4h', 12, 0, 0, 1,
'["1. Riscos do trabalho a quente","2. Funcoes do vigia","3. Monitoramento da area","4. Procedimentos de seguranca","5. Prevencao e combate a incendio","6. Comunicacao de emergencia"]');

-- ============================================
-- TIPOS DE DOCUMENTO (Controle Documental)
-- ============================================
INSERT INTO tipos_documento (nome, categoria, validade_meses, obrigatorio, descricao) VALUES
('ASO Admissional', 'aso', NULL, 1, 'Atestado de Saude Ocupacional - Admissao'),
('ASO Periodico', 'aso', 12, 1, 'Atestado de Saude Ocupacional - Periodico'),
('ASO Demissional', 'aso', NULL, 1, 'Atestado de Saude Ocupacional - Demissao'),
('ASO Retorno ao Trabalho', 'aso', NULL, 0, 'ASO de retorno ao trabalho apos afastamento'),
('ASO Mudanca de Risco', 'aso', NULL, 0, 'ASO por mudanca de risco ocupacional'),
('Ficha de EPI', 'epi', 12, 1, 'Ficha de controle de entrega de EPI'),
('Ordem de Servico', 'os', NULL, 1, 'Ordem de Servico de Seguranca do Trabalho'),
('Prontuario Medico', 'aso', NULL, 0, 'Prontuario medico ocupacional'),
('Declaracao de Treinamentos', 'treinamento', NULL, 0, 'Declaracao geral de treinamentos realizados'),
('Lista de Presenca', 'treinamento', NULL, 0, 'Lista de presenca de treinamento'),
('Anuencia NR-10', 'anuencia', 24, 1, 'Anuencia para trabalho com eletricidade'),
('Anuencia NR-33', 'anuencia', 12, 1, 'Anuencia para trabalho em espaco confinado'),
('Anuencia NR-35', 'anuencia', 24, 1, 'Anuencia para trabalho em altura'),
('Kit Admissional', 'outro', NULL, 1, 'Conjunto de documentos admissionais');
