-- ============================================
-- SESMT System - Dados Iniciais (Docker)
-- Senha padrão: TseAdmin@2026
-- Hash bcrypt gerado com cost 12
-- ============================================

USE sesmt_tse;

-- USUÁRIOS (senha: TseAdmin@2026)
INSERT INTO usuarios (nome, email, senha_hash, perfil, ativo, criado_em) VALUES
('Mariana Toscano Rios', 'mariana.rios@tsea.com.br', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, NOW()),
('Samuel Morais', 'samuel.morais@tsea.com.br', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, NOW()),
('Allyff Sousa', 'allyff.sousa@tsea.com.br', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, NOW());

-- MINISTRANTES (instrutores cadastrados)
INSERT INTO ministrantes (nome, cargo_titulo, registro) VALUES
('Mariana Toscano Rios', 'Eng. de Segurança do Trabalho', 'CREA - 5071365203/SP'),
('Diego Costa Rodrigues', 'Engenheiro Eletricista', 'CREA - 1018746617D/GO');

-- TIPOS DE CERTIFICADO (26 tipos)
INSERT INTO tipos_certificado (codigo, titulo, duracao, validade_meses, tem_anuencia, tem_diego, conteudo_no_verso, conteudo_programatico) VALUES
('DIREÇÃO DEFENSIVA', 'DIREÇÃO DEFENSIVA', '16h', 60, 0, 0, 1, '["1. Legislação de trânsito","2. Direção defensiva","3. Noções de primeiros socorros","4. Meio ambiente e cidadania","5. Prevenção de acidentes"]'),
('LOTO', 'LOTO - BLOQUEIO E ETIQUETAGEM DE ENERGIAS PERIGOSAS', '8h', 12, 0, 1, 1, '["1. Conceitos de energias perigosas","2. Procedimentos de bloqueio","3. Tipos de dispositivos de bloqueio","4. Etiquetagem","5. Procedimentos de liberação","6. Responsabilidades"]'),
('NR 06', 'NR - 06: EQUIPAMENTO DE PROTEÇÃO INDIVIDUAL - EPI', '4h', 12, 0, 0, 0, '["1. Definição de EPI","2. Obrigações do empregador e empregado","3. Certificado de Aprovação (CA)","4. Tipos de EPI","5. Uso correto e conservação","6. Higienização e guarda"]'),
('NR 10 BÁSICO', 'NR - 10: BÁSICO - SEGURANÇA EM INSTALAÇÕES E SERVIÇOS EM ELETRICIDADE', '40h', 24, 1, 1, 1, '["1. Introdução à segurança com eletricidade","2. Riscos em instalações e serviços com eletricidade","3. Técnicas de Análise de Risco","4. Medidas de Controle do Risco Elétrico","5. Normas Técnicas Brasileiras","6. Regulamentações do MTE","7. Equipamentos de proteção coletiva","8. Equipamentos de proteção individual","9. Rotinas de trabalho e procedimentos","10. Documentação de instalações elétricas","11. Riscos adicionais","12. Proteção e combate a incêndios","13. Acidentes de origem elétrica","14. Primeiros socorros","15. Responsabilidades"]'),
('NR 10 RECICLAGEM', 'NR - 10: RECICLAGEM - SEGURANÇA EM INSTALAÇÕES E SERVIÇOS EM ELETRICIDADE', '20h', 24, 1, 1, 1, '["1. Introdução à segurança com eletricidade","2. Riscos em instalações","3. Técnicas de Análise de Risco","4. Medidas de Controle","5. Normas Técnicas","6. Regulamentações do MTE","7. Equipamentos de proteção coletiva","8. Equipamentos de proteção individual","9. Rotinas de trabalho","10. Documentação","11. Riscos adicionais","12. Proteção e combate a incêndios","13. Acidentes de origem elétrica","14. Primeiros socorros","15. Responsabilidades"]'),
('NR 10 SEP', 'NR - 10: SEP - SISTEMA ELÉTRICO DE POTÊNCIA', '40h', 24, 1, 1, 1, '["1. Organização do SEP","2. Organização do trabalho","3. Aspectos comportamentais","4. Condições impeditivas","5. Riscos típicos no SEP","6. Técnicas de trabalho sob tensão","7. Equipamentos e ferramentas","8. Sistemas de proteção coletiva","9. Equipamentos de proteção individual","10. Posturas e vestuários","11. Segurança com veículos","12. Sinalização e isolamento","13. Liberação de instalação","14. Procedimentos de trabalho","15. Acidentes típicos"]'),
('NR 11 MUNK', 'NR - 11: OPERADOR DE MUNCK', '16h', 12, 0, 0, 1, '["1. Legislação e normas","2. Tipos de guindastes","3. Capacidade de carga","4. Inspeção pré-operacional","5. Operação segura","6. Sinalização","7. Manutenção preventiva","8. Situações de emergência"]'),
('NR 11 PONTE ROLANTE', 'NR - 11: OPERADOR DE PONTE ROLANTE', '16h', 12, 0, 0, 1, '["1. Legislação e normas","2. Tipos de pontes rolantes","3. Capacidade de carga","4. Inspeção pré-operacional","5. Operação segura","6. Acessórios de içamento","7. Sinalização convencional","8. Manutenção preventiva"]'),
('NR 11 RIGGER', 'NR - 11: RIGGER - MOVIMENTAÇÃO DE CARGAS', '40h', 12, 0, 0, 1, '["1. Legislação e normas","2. Tipos de cargas","3. Acessórios de içamento","4. Plano de Rigging","5. Tabelas de capacidade","6. Sinalização convencional","7. Procedimentos de segurança","8. Inspeção de equipamentos"]'),
('NR 11 SINALEIRO', 'NR - 11: SINALEIRO DE GUINDASTES', '8h', 12, 0, 0, 1, '["1. Legislação e normas","2. Funções do sinaleiro","3. Sinais manuais","4. Comunicação por rádio","5. Procedimentos de segurança","6. Situações de emergência"]'),
('NR 12', 'NR - 12: SEGURANÇA NO TRABALHO EM MÁQUINAS E EQUIPAMENTOS', '8h', 24, 0, 0, 1, '["1. Princípios gerais da NR-12","2. Arranjo físico e instalações","3. Dispositivos de segurança","4. Sistemas de proteção","5. Dispositivos de parada de emergência","6. Meios de acesso permanentes","7. Manutenção e inspeção","8. Sinalização","9. Procedimentos de trabalho","10. Capacitação"]'),
('NR 18 ANDAIME', 'NR - 18: ANDAIMES - MONTAGEM E DESMONTAGEM', '8h', 12, 0, 0, 1, '["1. Legislação e normas","2. Tipos de andaimes","3. Montagem e desmontagem","4. Inspeção","5. Uso correto","6. EPI obrigatórios","7. Procedimentos de segurança"]'),
('NR 18 GERAL', 'NR - 18: CONDIÇÕES DE SEGURANÇA NA INDÚSTRIA DA CONSTRUÇÃO', '8h', 12, 0, 0, 1, '["1. Condições e meio ambiente de trabalho","2. Áreas de vivência","3. Ordem e limpeza","4. Sinalização","5. EPI e EPC","6. Armazenamento de materiais","7. Proteção contra incêndio","8. Primeiros socorros"]'),
('NR 20 UNILEVER', 'NR - 20: SEGURANÇA COM INFLAMÁVEIS E COMBUSTÍVEIS - UNILEVER', '4h', 12, 0, 0, 1, '["1. Inflamáveis: conceito e classificação","2. Combustíveis: conceito e classificação","3. Controle de fontes de ignição","4. Proteção contra incêndio","5. Procedimentos de emergência","6. Requisitos Unilever"]'),
('NR 20 CARGILL', 'NR - 20: SEGURANÇA COM INFLAMÁVEIS E COMBUSTÍVEIS - CARGILL', '16h', 12, 0, 0, 1, '["1. Inflamáveis: conceito e classificação","2. Combustíveis: conceito e classificação","3. Controle de fontes de ignição","4. Proteção contra incêndio","5. Procedimentos de emergência","6. Zona de atmosfera explosiva","7. Plano de resposta à emergência","8. Requisitos Cargill"]'),
('NR 33 SUPERVISOR', 'NR - 33: SUPERVISOR DE ENTRADA EM ESPAÇOS CONFINADOS', '40h', 12, 1, 1, 1, '["1. Definição de espaço confinado","2. Reconhecimento e avaliação de riscos","3. Monitoramento atmosférico","4. Ventilação e resgate","5. Permissão de Entrada (PET)","6. Procedimentos de emergência","7. Primeiros socorros","8. Funções do supervisor","9. Responsabilidades legais","10. Práticas simuladas"]'),
('NR 33 TRABALHADOR', 'NR - 33: TRABALHADOR AUTORIZADO - ESPAÇOS CONFINADOS', '16h', 12, 1, 1, 1, '["1. Definição de espaço confinado","2. Riscos em espaços confinados","3. Monitoramento atmosférico","4. Medidas de controle","5. Permissão de Entrada (PET)","6. Noções de resgate","7. Primeiros socorros","8. EPI obrigatórios"]'),
('NR 34 GERAL', 'NR - 34: CONDIÇÕES E MEIO AMBIENTE DE TRABALHO NA CONSTRUÇÃO NAVAL', '8h', 12, 0, 0, 1, '["1. Condições e meio ambiente de trabalho","2. Trabalho a quente","3. Ensaios não destrutivos","4. Pintura","5. Movimentação de cargas","6. Jateamento","7. Montagem de andaimes","8. Exposição à radiação"]'),
('NR 34 SOLDADOR', 'NR - 34: SOLDADOR - TRABALHO A QUENTE', '8h', 24, 0, 0, 1, '["1. Riscos do trabalho a quente","2. Procedimentos de segurança","3. Permissão de trabalho","4. Inspeção da área","5. EPI obrigatórios","6. Prevenção e combate a incêndio","7. Procedimentos de emergência"]'),
('NR 34 OBSERVADOR', 'NR - 34: OBSERVADOR DE TRABALHO A QUENTE', '4h', 24, 0, 0, 1, '["1. Funções do observador","2. Riscos do trabalho a quente","3. Monitoramento da área","4. Uso de extintores","5. Comunicação de emergência","6. Procedimentos pós-trabalho"]'),
('NR 35', 'NR - 35: TRABALHO EM ALTURA', '8h', 24, 1, 1, 1, '["1. Normas e regulamentos","2. Análise de risco e condições impeditivas","3. Riscos potenciais em trabalho em altura","4. Medidas de prevenção e controle","5. Sistemas de proteção coletiva","6. EPI para trabalho em altura","7. Acidentes típicos","8. Condutas em situações de emergência","9. Noções de resgate e primeiros socorros"]'),
('NR 10 BÁSICO RECICL 20H', 'NR - 10: RECICLAGEM 20H', '20h', 24, 1, 1, 1, '["1. Introdução à segurança com eletricidade","2. Riscos em instalações","3. Técnicas de Análise de Risco","4. Medidas de controle","5. Equipamentos de proteção","6. Rotinas de trabalho","7. Primeiros socorros","8. Responsabilidades"]'),
('NR 10 SEP RECICL', 'NR - 10: RECICLAGEM SEP', '20h', 24, 1, 1, 1, '["1. Organização do SEP","2. Organização do trabalho","3. Riscos típicos no SEP","4. Técnicas de trabalho","5. Sistemas de proteção","6. Equipamentos de proteção individual","7. Procedimentos de trabalho","8. Acidentes típicos"]'),
('NR 18 PLATAFORMA', 'NR - 18: PLATAFORMA ELEVATÓRIA', '8h', 12, 0, 0, 1, '["1. Legislação e normas","2. Tipos de plataformas","3. Inspeção pré-operacional","4. Operação segura","5. Limites de carga","6. Procedimentos de emergência","7. EPI obrigatórios"]'),
('NR 12 RECICL', 'NR - 12: RECICLAGEM - SEGURANÇA EM MÁQUINAS', '4h', 24, 0, 0, 1, '["1. Atualizações da NR-12","2. Revisão de dispositivos de segurança","3. Sistemas de proteção","4. Procedimentos de trabalho","5. Manutenção e inspeção"]'),
('NR 34 TRABALHO QUENTE', 'NR - 34: TRABALHO A QUENTE (VIGIAS)', '4h', 12, 0, 0, 1, '["1. Riscos do trabalho a quente","2. Funções do vigia","3. Monitoramento da área","4. Procedimentos de segurança","5. Prevenção e combate a incêndio","6. Comunicação de emergência"]');

-- TIPOS DE DOCUMENTO
INSERT INTO tipos_documento (nome, categoria, validade_meses, obrigatorio, descricao) VALUES
('ASO Admissional', 'aso', NULL, 1, 'Atestado de Saúde Ocupacional - Admissão'),
('ASO Periódico', 'aso', 12, 1, 'Atestado de Saúde Ocupacional - Periódico'),
('ASO Demissional', 'aso', NULL, 1, 'Atestado de Saúde Ocupacional - Demissão'),
('ASO Retorno ao Trabalho', 'aso', NULL, 0, 'ASO de retorno ao trabalho após afastamento'),
('ASO Mudança de Risco', 'aso', NULL, 0, 'ASO por mudança de risco ocupacional'),
('Ficha de EPI', 'epi', 12, 1, 'Ficha de controle de entrega de EPI'),
('Ordem de Serviço', 'os', NULL, 1, 'Ordem de Serviço de Segurança do Trabalho'),
('Prontuário Médico', 'aso', NULL, 0, 'Prontuário médico ocupacional'),
('Declaração de Treinamentos', 'treinamento', NULL, 0, 'Declaração geral de treinamentos realizados'),
('Lista de Presença', 'treinamento', NULL, 0, 'Lista de presença de treinamento'),
('Anuência NR-10', 'anuencia', 24, 1, 'Anuência para trabalho com eletricidade'),
('Anuência NR-33', 'anuencia', 12, 1, 'Anuência para trabalho em espaço confinado'),
('Anuência NR-35', 'anuencia', 24, 1, 'Anuência para trabalho em altura');

-- DADOS DE TESTE
INSERT INTO clientes (razao_social, nome_fantasia, cnpj, contato_nome, contato_email) VALUES
('Unilever Brasil Industrial Ltda', 'Unilever', '01.615.814/0001-35', 'João Gerente', 'joao@unilever.com'),
('Cargill Agrícola S.A.', 'Cargill', '60.498.706/0001-60', 'Maria Coord', 'maria@cargill.com');

INSERT INTO obras (cliente_id, nome, local_obra, data_inicio, status) VALUES
(1, 'Parada de Manutenção 2026', 'Goiânia - GO', '2026-03-01', 'ativa'),
(2, 'Expansão Planta Cargill', 'Uberlândia - MG', '2026-02-15', 'ativa');

INSERT INTO colaboradores (nome_completo, cargo, funcao, setor, cliente_id, obra_id, data_admissao, status, unidade) VALUES
('João Carlos da Silva', 'Montador Industrial', 'Montador', 'Produção', 1, 1, '2024-03-15', 'ativo', 'Goiânia'),
('Maria Fernanda Santos', 'Eletricista', 'Eletricista Industrial', 'Elétrica', 1, 1, '2023-08-10', 'ativo', 'Goiânia'),
('Pedro Henrique Oliveira', 'Soldador', 'Soldador', 'Caldeiraria', 2, 2, '2024-01-20', 'ativo', 'Uberlândia'),
('Ana Paula Rodrigues', 'Aux. Serviços Gerais', 'Aux. Serviços Gerais', 'Administrativo', NULL, NULL, '2025-06-01', 'ativo', 'Goiânia'),
('Carlos Eduardo Lima', 'Técnico de Segurança', 'TST', 'SESMT', NULL, NULL, '2022-11-10', 'ativo', 'Goiânia');

-- Certificados de teste (vencendo em breve e vencidos para testar alertas)
INSERT INTO certificados (colaborador_id, tipo_certificado_id, data_realizacao, data_emissao, data_validade, status) VALUES
(1, 4, '2024-04-10', '2024-04-10', '2026-04-10', 'proximo_vencimento'),
(1, 21, '2024-04-10', '2024-04-10', '2026-04-10', 'proximo_vencimento'),
(1, 11, '2025-03-15', '2025-03-15', '2027-03-15', 'vigente'),
(2, 4, '2024-06-20', '2024-06-20', '2026-06-20', 'vigente'),
(2, 6, '2024-06-20', '2024-06-20', '2026-06-20', 'vigente'),
(2, 21, '2025-01-10', '2025-01-10', '2027-01-10', 'vigente'),
(3, 19, '2024-02-01', '2024-02-01', '2026-02-01', 'vencido'),
(3, 18, '2025-06-01', '2025-06-01', '2026-06-01', 'vigente'),
(3, 11, '2025-06-01', '2025-06-01', '2027-06-01', 'vigente');
