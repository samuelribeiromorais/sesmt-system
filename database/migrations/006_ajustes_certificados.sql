-- Migration 006: Ajustes nos tipos de certificado (Grupo 3)
-- Compatibilidade retroativa: apenas UPDATE/ADD COLUMN NULL/DEFAULT e INSERT

-- 1) Adicionar flag tem_diego_responsavel (Diego aparece como Resp. Técnico, Mariana como Instrutora)
ALTER TABLE tipos_certificado
    ADD COLUMN tem_diego_responsavel TINYINT(1) NOT NULL DEFAULT 0 AFTER tem_diego;

-- 2) Corrigir carga horária
UPDATE tipos_certificado SET duracao = '4h'  WHERE codigo = 'LOTO';
UPDATE tipos_certificado SET duracao = '16h' WHERE codigo = 'NR 18 ANDAIME';
UPDATE tipos_certificado SET duracao = '4h'  WHERE codigo = 'NR 18 GERAL';
UPDATE tipos_certificado SET duracao = '4h'  WHERE codigo = 'NR 18 PLATAFORMA';
UPDATE tipos_certificado SET duracao = '8h'  WHERE codigo = 'NR 34 OBSERVADOR';
UPDATE tipos_certificado SET duracao = '16h' WHERE codigo = 'NR 34 SOLDADOR';

-- 3) Corrigir título de NR 10 BÁSICO RECICL 20H
UPDATE tipos_certificado
SET titulo = 'NR - 10: RECICLAGEM - SEGURANÇA EM INSTALAÇÕES E SERVIÇOS EM ELETRICIDADE'
WHERE codigo = 'NR 10 BÁSICO RECICL 20H';

-- 4) NR-35: somente Mariana (sem Diego)
UPDATE tipos_certificado SET tem_diego = 0, tem_diego_responsavel = 0, ministrante_id = 1
WHERE codigo = 'NR 35';

-- 5) NR-33 TRABALHADOR: igual NR-12 (Mariana como Instrutora/Responsável, sem Diego)
UPDATE tipos_certificado SET tem_diego = 0, tem_diego_responsavel = 0, ministrante_id = 1
WHERE codigo = 'NR 33 TRABALHADOR';

-- 6) NR-10: Mariana como Instrutora, Diego como Responsável Técnico
UPDATE tipos_certificado SET tem_diego = 0, tem_diego_responsavel = 1, ministrante_id = 1
WHERE codigo IN ('NR 10 BÁSICO', 'NR 10 BÁSICO RECICL 20H', 'NR 10 SEP');

-- 7) Desativar tipos que não são realizados
UPDATE tipos_certificado SET ativo = 0
WHERE codigo IN (
    'NR 10 RECICLAGEM',
    'NR 10 SEP RECICL',
    'NR 11 PONTE ROLANTE',
    'NR 12 RECICL',
    'NR 33 SUPERVISOR',
    'NR 34 TRABALHO QUENTE'
);

-- 8) Renomear NR 11 SINALEIRO → NR 18 SINALEIRO
UPDATE tipos_certificado
SET codigo = 'NR 18 SINALEIRO',
    titulo = 'NR - 18: SINALEIRO E AMARRADOR DE CARGAS'
WHERE codigo = 'NR 11 SINALEIRO';

-- 9) Atualizar conteúdos programáticos (baseado no documento oficial)

UPDATE tipos_certificado SET conteudo_programatico = '["Conceito de direção defensiva","Conduzindo em condições adversas","Conduzindo em situações de risco: ultrapassagens, derrapagem, ondulações e buracos, cruzamentos e curvas, frenagem normal e de emergência","Como evitar acidentes em veículos de duas ou mais rodas","Abordagem teórica da condução de motocicletas com passageiro e cargas","Cuidados com os demais usuários da via","Respeito mútuo entre condutores","Equipamentos de segurança do condutor motociclista","Estado físico e mental do condutor - consequências da ingestão de bebida alcoólica e substâncias psicoativas"]'
WHERE codigo = 'DIREÇÃO DEFENSIVA';

UPDATE tipos_certificado SET conteudo_programatico = '["Bloqueio de Energia: Identificação das fontes de energia envolvidas na atividade","Procedimento para parar máquinas e equipamentos","Válvulas e dispositivos para isolar o fluxo de energia e energia armazenada (energia residual)","Travas, bloqueio e sinalização de válvulas ou dispositivos de isolamento de energia","Teste de Energia Zero, Responsabilidade e Normalização","Desbloqueio de Energia: Verificação do cenário (exposição de colaboradores no local)","Remoção de ferramentas e reinstalação de proteções","Comunicação e remoção de dispositivos de bloqueio e identificação","Acionamento de válvulas e dispositivos de isolamento"]'
WHERE codigo = 'LOTO';

UPDATE tipos_certificado SET conteudo_programatico = '["Introdução da NR-06","O que é o EPI - Equipamento de Proteção Individual","Responsabilidades do Empregador e do Trabalhador","Responsabilidades dos Fabricantes e Importadores","Da Competência do Ministério do Trabalho e Emprego (MTE)","Riscos por não usar os EPIs","EPI Para Proteção da Cabeça, dos Olhos e da Face","EPI Para Proteção Auditiva","EPI Para Proteção Respiratória","EPI Para Proteção de Tronco","EPI Para Proteção dos Membros Superiores e Inferiores","EPI Para Proteção do Corpo Inteiro","EPI Para Proteção Contra Quedas Com Diferença de Nível","Ficha de Controle do EPI","CA - Certificado de Aprovação","EPC - Equipamento de Proteção Coletiva"]'
WHERE codigo = 'NR 06';

UPDATE tipos_certificado SET conteudo_programatico = '["Introdução à segurança com eletricidade","Riscos em instalações e serviços com eletricidade: choque elétrico, arcos elétricos, queimaduras e quedas, campos eletromagnéticos","Técnicas de Análise de Risco","Medidas de Controle do Risco Elétrico: desenergização, aterramento, equipotencialização, dispositivos a corrente de fuga, barreiras, bloqueios, isolamento das partes vivas, separação elétrica","Normas Técnicas Brasileiras - NBR da ABNT: NBR-5410, NBR 14039 e outras","Regulamentações do MTE: NRs, NR-10, qualificação, habilitação, capacitação e autorização","Equipamentos de proteção coletiva","Equipamentos de proteção individual","Rotinas de trabalho e procedimentos: instalações desenergizadas, liberação para serviços, sinalização, inspeções","Documentação de instalações elétricas","Riscos adicionais: altura, ambientes confinados, áreas classificadas, umidade, condições atmosféricas","Proteção e combate a incêndios: noções básicas, medidas preventivas, métodos de extinção","Acidentes de origem elétrica: causas diretas e indiretas, discussão de casos","Primeiros socorros: noções sobre lesões, respiração artificial, massagem cardíaca, remoção e transporte de acidentados","Responsabilidades"]'
WHERE codigo = 'NR 10 BÁSICO';

UPDATE tipos_certificado SET conteudo_programatico = '["Introdução à segurança com eletricidade","Riscos em instalações e serviços com eletricidade: choque elétrico, arcos elétricos, queimaduras e quedas, campos eletromagnéticos","Técnicas de Análise de Risco","Medidas de Controle do Risco Elétrico: desenergização, aterramento, equipotencialização, dispositivos a corrente de fuga, barreiras, bloqueios, isolamento das partes vivas, separação elétrica","Normas Técnicas Brasileiras - NBR da ABNT: NBR-5410, NBR 14039 e outras","Regulamentações do MTE: NRs, NR-10, qualificação, habilitação, capacitação e autorização","Equipamentos de proteção coletiva","Equipamentos de proteção individual","Rotinas de trabalho e procedimentos: instalações desenergizadas, liberação para serviços, sinalização, inspeções","Documentação de instalações elétricas","Riscos adicionais: altura, ambientes confinados, áreas classificadas, umidade, condições atmosféricas","Proteção e combate a incêndios: noções básicas, medidas preventivas, métodos de extinção","Acidentes de origem elétrica: causas diretas e indiretas, discussão de casos","Primeiros socorros: noções sobre lesões, respiração artificial, massagem cardíaca, remoção e transporte de acidentados","Responsabilidades"]'
WHERE codigo = 'NR 10 BÁSICO RECICL 20H';

UPDATE tipos_certificado SET conteudo_programatico = '["Organização do Sistema Elétrico de Potência - SEP","Organização do trabalho: programação e planejamento dos serviços, trabalho em equipe, prontuário e cadastro das instalações","Aspectos comportamentais","Condições impeditivas para serviços","Riscos típicos no SEP e sua prevenção: proximidade com partes energizadas, indução, descargas atmosféricas, estática, campos elétricos e magnéticos","Técnicas de análise de risco no SEP","Procedimentos de trabalho - análise e discussão","Técnicas de trabalho sob tensão: em linha viva, ao potencial, em áreas internas, trabalho à distância, trabalho noturno, ambientes subterrâneos","Equipamentos e ferramentas de trabalho: escolha, uso, conservação, verificação, ensaios","Sistemas de proteção coletiva","Equipamentos de proteção individual","Posturas e vestuários de trabalho","Segurança com veículos e transporte de pessoas, materiais e equipamentos","Sinalização e isolamento de áreas de trabalho","Liberação de instalação para serviço e para operação e uso","Treinamento em técnicas de remoção, atendimento e transporte de acidentados","Acidentes típicos - análise, discussão e medidas de proteção","Responsabilidades"]'
WHERE codigo = 'NR 10 SEP';

UPDATE tipos_certificado SET conteudo_programatico = '["Introdução","Acidentes do trabalho - causas e consequências","Conceito e aspectos operacionais do guindauto","Componentes básicos do equipamento guindauto: motor, transmissão, embreagem, sistema hidráulico, painel de instrumentos","Inspeção diária no equipamento (check list) - tabela de observação diária","NR-11: transporte, movimentação, armazenagem e manuseio de materiais","Operação do equipamento (prática) com exercícios de dificuldade crescente","Circulação e sinalização para operação de guindauto","Medidas de controle, riscos associados e seus controles","Plano de içamento e movimentação de cargas","Carga x capacidade do equipamento","Movimentação de carga de geometria complexa","Operação simultânea com dois ou mais equipamentos","Manobras diversas"]'
WHERE codigo = 'NR 11 MUNK';

UPDATE tipos_certificado SET conteudo_programatico = '["Conceito e aspectos operacionais do equipamento","Componentes básicos e inspeção diária (check list)","NR-11: transporte, movimentação, armazenagem e manuseio de materiais","Operação do equipamento (prática) com exercícios de dificuldade crescente","Medidas de controle, riscos associados e seus controles","Plano de içamento e movimentação de cargas","Carga x capacidade do equipamento","Movimentação de carga de geometria complexa","Manobras diversas e operação simultânea","Responsabilidades do operador"]'
WHERE codigo = 'NR 11 RIGGER';

UPDATE tipos_certificado SET conteudo_programatico = '["Técnicas operacionais no uso de lixadeira, esmerilhadeira e furadeira","Funcionamento das proteções: como e porque devem ser usadas","Sistema de bloqueio e etiquetagem de máquinas e equipamentos","Sistema operacional da máquina","Segurança no uso de Lixadeira, Esmerilhadeira, Furadeira, Rosqueadeira, Máquina de Solda, Furadeira de Bancada e Soprador Térmico","Descrição e identificação dos riscos associados com cada máquina e seus controles","Como e em que circunstâncias uma proteção pode ser removida, e por quem","Princípios de segurança na utilização da máquina ou equipamento","Segurança para riscos mecânicos, elétricos e outros relevantes","Método de trabalho seguro","Permissão de trabalho","Sistema de bloqueio de funcionamento durante operações de inspeção, limpeza, lubrificação e manutenção"]'
WHERE codigo = 'NR 12';

UPDATE tipos_certificado SET conteudo_programatico = '["Tipos de Andaime: Suspenso e Mecânicos","Andaimes suspensos, mecânicos-pesados e mecânicos-leves","Andaimes de Balanço e simplesmente acoplados","Segurança e Proteção nos andaimes","Materiais Utilizados na Montagem e Desmontagem dos andaimes","Elementos e Acessórios dos andaimes","Plataforma de Trabalho e Peso Máximo permitido","Medidas Preventivas","Segurança, cuidados e procedimentos de montagem e desmontagem dos andaimes","Acesso aos pontos de ancoragem para andaime","EPIs utilizados na Montagem e Desmontagem de Andaime","Fases da Atividade e Análises de Riscos","Checklist - inspeção diária do andaime - Tabela de observação diária","Prevenção de acidentes e Primeiros Socorros","Referências Normativas: NR-18, NR-12 e NR-06"]'
WHERE codigo = 'NR 18 ANDAIME';

UPDATE tipos_certificado SET conteudo_programatico = '["As condições e meio ambiente de trabalho","Os riscos inerentes às atividades desenvolvidas","Os equipamentos e proteção coletiva existentes no canteiro de obras","Uso adequado dos equipamentos de proteção individual","O PGR do canteiro de obras","EPI Para Proteção Auditiva"]'
WHERE codigo = 'NR 18 GERAL';

UPDATE tipos_certificado SET conteudo_programatico = '["Seleção das PEMT apropriadas das várias classificações","A finalidade e a utilização de manuais de operação, plaqueta de identificação e regras de segurança","Validação de que a inspeção anual está em dia","Conhecimento de como realizar uma inspeção de pré-uso","Responsabilidades associadas a problemas ou avarias na PEMT","Conhecimento e compreensão dos fatores que afetam a estabilidade","Reconhecimento e prevenção contra perigos associados à operação","Inspeções no local de trabalho antes de cada utilização (análise de risco)","Compreensão dos perigos dos ventos e das condições climáticas","Compreensão dos controles da PEMT: controles de solo, de cesto e descida de emergência","Regulamentos, normas e regras de segurança aplicáveis","Utilização de EPI adequado à tarefa, ao local de trabalho e ao ambiente","Práticas seguras de deslocamento","Entendimento de que a autorização pelo usuário é necessária para operar a PEMT","PRÁTICA: Inspeção visual e familiarização da PEMT","Identificação dos componentes principais e suas funções","Realização da inspeção de pré-uso - verificações e inspeções diárias","Planejamento da rota de deslocamento e posicionamento no local de trabalho","Operação e função de todos os controles","Estacionamento e proteção da PEMT"]'
WHERE codigo = 'NR 18 PLATAFORMA';

UPDATE tipos_certificado SET conteudo_programatico = '["Conscientização sobre a importância da sintonia entre Operador x Sinaleiro/Amarrador de cargas","Definição e Funcionamento","Montagem, Instalação e Operação e Sinalização","Amarração de cargas para o içamento (eslingas e acessórios)","Sistemas de Segurança e Bola peso","Cabo do guindaste e Cabos de aço","Carga líquida, carga bruta e Centro de gravidade","Classificação dos guindastes","Como inspecionar e amarrar de cargas","Componentes básicos, carga bruta e comprimento da lança","Comunicação via rádio em frequência exclusiva entre sinaleiro e Operador de Grua","Escolha correta dos materiais de amarração de acordo com as características das cargas","Medidas e dispositivos de segurança para evitar acidentes","Sinalização manual e por comunicação via rádio","Isolamentos seguros de áreas sob cargas suspensas","Inspeções visuais das condições de uso de ganchos, cabos de aço, cintas sintéticas e acessórios"]'
WHERE codigo = 'NR 18 SINALEIRO';

UPDATE tipos_certificado SET conteudo_programatico = '["Inflamáveis: características, propriedades, perigos e riscos","Controles coletivo e individual para trabalhos com inflamáveis","Fontes de ignição e seu controle","Proteção contra incêndio com inflamáveis","Procedimentos em emergências com inflamáveis","Estudo da Norma Regulamentadora No. 20","Análise Preliminar de Perigos/Riscos: conceitos e exercícios práticos","Permissão para Trabalho com Inflamáveis"]'
WHERE codigo IN ('NR 20 CARGILL', 'NR 20 UNILEVER');

UPDATE tipos_certificado SET conteudo_programatico = '["Definições","Reconhecimento, avaliação e controle de riscos","Funcionamento de equipamentos utilizados","Procedimentos e utilização da Permissão de Entrada e Trabalho (PET)","Noções de resgate e primeiros socorros","Tipo de trabalho: montagem de infraestrutura elétrica, lançamento de cabos, ligações elétricas","Espaços confinados: tanques de óleo, túnel de graneleiro, fosso de elevador, forro, silos e caixa de passagem"]'
WHERE codigo = 'NR 33 TRABALHADOR';

UPDATE tipos_certificado SET conteudo_programatico = '["Classes de Fogo","Métodos de extinção","Tipos de equipamentos de combate a incêndio","Sistemas de alarme e comunicação","Rotas de Fuga","Equipamento de Proteção Individual e Coletiva","Práticas de prevenção e combate a incêndio"]'
WHERE codigo = 'NR 34 OBSERVADOR';

UPDATE tipos_certificado SET conteudo_programatico = '["Módulo Geral: aplicável a todas as especialidades de trabalho a quente","Estudo da NR-34, Item 34.5","Identificação de Perigos e Análise de Riscos: Conceitos e Técnicas (APP e APR)","Permissão para Trabalho - PT","Limite inferior e superior de explosividade","Medidas de Controle: Inspeção Preliminar, Controle de combustíveis e inflamáveis, Proteção Física, Sinalização e Isolamento","Renovação de Ar no Local de Trabalho (Ventilação/Exaustão)","Rede de Gases (Válvulas e Engates)","Ergonomia","Doenças ocupacionais","FISPQ"]'
WHERE codigo IN ('NR 34 GERAL', 'NR 34 SOLDADOR');

UPDATE tipos_certificado SET conteudo_programatico = '["Normas e regulamentos aplicáveis ao trabalho em altura","Análise de Risco e condições impeditivas","Riscos potenciais inerentes ao trabalho em altura e medidas de prevenção e controle","Sistemas, equipamentos e procedimentos de proteção coletiva","Equipamentos de Proteção Individual para trabalho em altura: seleção, inspeção, conservação e limitação de uso","Acidentes típicos em trabalhos em altura","Condutas em situações de emergência, incluindo noções de técnicas de resgate e primeiros socorros"]'
WHERE codigo = 'NR 35';
