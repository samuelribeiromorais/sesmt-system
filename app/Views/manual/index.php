<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual de Operacao - SESMT TSE</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 16px; color: #333; background: #f5f5f5; }

        /* SIDEBAR */
        .sidebar { position: fixed; top: 0; left: 0; width: 280px; height: 100vh; background: #005e4e; color: white; overflow-y: auto; z-index: 100; padding: 20px 0; }
        .sidebar .logo { text-align: center; padding: 10px 20px 20px; border-bottom: 1px solid rgba(255,255,255,0.15); margin-bottom: 10px; }
        .sidebar .logo h1 { font-size: 18px; color: #afd85a; }
        .sidebar .logo p { font-size: 12px; color: rgba(255,255,255,0.7); margin-top: 4px; }
        .sidebar a { display: block; padding: 10px 20px; color: rgba(255,255,255,0.85); text-decoration: none; font-size: 14px; border-left: 3px solid transparent; transition: all 0.2s; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.1); color: #afd85a; border-left-color: #afd85a; }
        .sidebar a .num { display: inline-block; width: 24px; height: 24px; background: rgba(255,255,255,0.15); border-radius: 50%; text-align: center; line-height: 24px; font-size: 11px; margin-right: 8px; }
        .sidebar .back-btn { display: block; margin: 15px 20px; padding: 10px; background: #afd85a; color: #005e4e; text-align: center; border-radius: 6px; font-weight: bold; text-decoration: none; font-size: 13px; }
        .sidebar .search-box { padding: 10px 20px; }
        .sidebar .search-box input { width: 100%; padding: 8px 12px; border: none; border-radius: 4px; font-size: 13px; background: rgba(255,255,255,0.15); color: white; }
        .sidebar .search-box input::placeholder { color: rgba(255,255,255,0.5); }

        /* CONTENT */
        .content { margin-left: 280px; padding: 30px 40px 60px; max-width: 900px; }
        .content h2 { font-size: 28px; color: #005e4e; margin: 40px 0 16px; padding-bottom: 8px; border-bottom: 3px solid #afd85a; }
        .content h2:first-child { margin-top: 0; }
        .content h3 { font-size: 20px; color: #005e4e; margin: 24px 0 12px; }
        .content p { line-height: 1.7; margin-bottom: 12px; }
        .content ul, .content ol { margin: 8px 0 16px 24px; line-height: 1.8; }

        /* BOXES */
        .box { padding: 16px 20px; border-radius: 8px; margin: 16px 0; border-left: 5px solid; }
        .box-tip { background: #e8f5e9; border-color: #4caf50; }
        .box-warn { background: #fff3e0; border-color: #ff9800; }
        .box-important { background: #fce4ec; border-color: #e53935; }
        .box-info { background: #e3f2fd; border-color: #1976d2; }
        .box strong { display: block; margin-bottom: 4px; }

        /* STEPS */
        .steps { counter-reset: step; margin: 16px 0; }
        .step { display: flex; gap: 16px; margin-bottom: 16px; padding: 16px; background: white; border-radius: 8px; border: 1px solid #e0e0e0; }
        .step::before { counter-increment: step; content: counter(step); display: flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; background: #005e4e; color: white; border-radius: 50%; font-weight: bold; font-size: 16px; }
        .step-content { flex: 1; }
        .step-content strong { color: #005e4e; }

        /* TABLE */
        .manual-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .manual-table th, .manual-table td { border: 1px solid #ddd; padding: 10px 12px; text-align: left; font-size: 14px; }
        .manual-table th { background: #005e4e; color: white; font-size: 13px; }
        .manual-table tr:nth-child(even) { background: #f9f9f9; }

        /* MOBILE */
        .menu-toggle { display: none; position: fixed; top: 10px; left: 10px; z-index: 200; background: #005e4e; color: white; border: none; padding: 10px 14px; border-radius: 6px; font-size: 18px; cursor: pointer; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.open { transform: translateX(0); }
            .content { margin-left: 0; padding: 20px; }
            .menu-toggle { display: block; }
        }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open')">&#9776; Menu</button>

    <!-- SIDEBAR -->
    <nav class="sidebar">
        <div class="logo">
            <img src="/assets/img/logo-tse.png?v=2" alt="TSE" style="max-width:160px; margin-bottom:8px;">
            <p>Manual de Operacao</p>
        </div>
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Buscar no manual..." oninput="filtrarManual(this.value)">
        </div>
        <a href="#visao-geral"><span class="num">1</span> Visao Geral</a>
        <a href="#login"><span class="num">2</span> Login e Seguranca</a>
        <a href="#dashboard"><span class="num">3</span> Dashboard</a>
        <a href="#colaboradores"><span class="num">4</span> Colaboradores</a>
        <a href="#documentos"><span class="num">5</span> Documentos</a>
        <a href="#certificados"><span class="num">6</span> Certificados</a>
        <a href="#treinamentos"><span class="num">7</span> Treinamentos</a>
        <a href="#kit-pj"><span class="num">8</span> Kit PJ</a>
        <a href="#clientes"><span class="num">9</span> Clientes e Obras</a>
        <a href="#checklist"><span class="num">10</span> Checklist Pre-Obra</a>
        <a href="#agenda-exames"><span class="num">11</span> Agenda de Exames</a>
        <a href="#relatorios"><span class="num">12</span> Relatorios</a>
        <a href="#alertas"><span class="num">13</span> Alertas</a>
        <a href="#exportacao"><span class="num">14</span> Exportacao</a>
        <a href="#lixeira"><span class="num">15</span> Lixeira</a>
        <a href="#usuarios"><span class="num">16</span> Usuarios e Perfis</a>
        <a href="#configuracoes"><span class="num">17</span> Configuracoes</a>
        <a href="#backup"><span class="num">18</span> Backup</a>
        <a href="#faq"><span class="num">19</span> Perguntas Frequentes</a>
        <a href="/login" class="back-btn">Ir para o Sistema</a>
    </nav>

    <!-- CONTENT -->
    <main class="content">

        <!-- 1. VISAO GERAL -->
        <section id="visao-geral" class="manual-section">
            <h2>1. Visao Geral do Sistema</h2>
            <p>O <strong>SESMT TSE</strong> e o sistema de gestao de Seguranca e Saude do Trabalho da TSE Energia e Automacao Industrial. Ele serve para controlar todos os documentos, certificados, treinamentos e informacoes dos colaboradores da empresa.</p>

            <h3>Para que serve?</h3>
            <ul>
                <li><strong>Guardar documentos</strong> de cada colaborador (ASO, EPI, Ordem de Servico, etc.)</li>
                <li><strong>Emitir certificados</strong> de treinamentos (NR-10, NR-35, NR-12, etc.)</li>
                <li><strong>Controlar validades</strong> - o sistema avisa quando documentos e certificados estao vencendo</li>
                <li><strong>Registrar treinamentos</strong> em massa para varios colaboradores de uma vez</li>
                <li><strong>Gerar relatorios</strong> por colaborador, cliente ou obra</li>
                <li><strong>Gerenciar clientes e obras</strong> com requisitos de seguranca</li>
            </ul>

            <h3>Como acessar?</h3>
            <p>Abra o navegador (Chrome, Edge ou Firefox) e digite o endereco do sistema. Voce precisa de um usuario e senha para entrar.</p>

            <div class="box box-tip">
                <strong>Dica:</strong> Use o Chrome ou Edge para melhor experiencia. O sistema funciona em computador, tablet e celular.
            </div>
        </section>

        <!-- 2. LOGIN -->
        <section id="login" class="manual-section">
            <h2>2. Login e Seguranca</h2>

            <h3>Como fazer login</h3>
            <div class="steps">
                <div class="step"><div class="step-content"><strong>Abra o sistema</strong><br>Digite o endereco do sistema no navegador.</div></div>
                <div class="step"><div class="step-content"><strong>Digite seu email</strong><br>No campo "Email", coloque o email que foi cadastrado para voce (exemplo: seunome@tsea.com.br).</div></div>
                <div class="step"><div class="step-content"><strong>Digite sua senha</strong><br>No campo "Senha", coloque a senha que voce recebeu.</div></div>
                <div class="step"><div class="step-content"><strong>Clique em "Entrar"</strong><br>Se os dados estiverem corretos, voce vai direto para o Dashboard.</div></div>
            </div>

            <div class="box box-warn">
                <strong>Atencao:</strong> Se voce errar a senha 5 vezes seguidas, o sistema vai bloquear por 15 minutos. Espere e tente novamente.
            </div>

            <h3>Verificacao em duas etapas (2FA)</h3>
            <p>O sistema permite ativar uma seguranca extra chamada "verificacao em duas etapas". Com ela, alem da senha, voce precisa digitar um codigo que aparece no seu celular.</p>
            <div class="steps">
                <div class="step"><div class="step-content"><strong>Acesse "Usuarios" no menu lateral</strong><br>Clique no seu nome ou em "Configurar 2FA".</div></div>
                <div class="step"><div class="step-content"><strong>Escaneie o QR Code</strong><br>Abra o app Google Authenticator no celular e escaneie o codigo que aparece na tela.</div></div>
                <div class="step"><div class="step-content"><strong>Digite o codigo de 6 digitos</strong><br>O app vai mostrar um numero. Digite ele no campo e clique em "Ativar".</div></div>
            </div>

            <h3>Trocar senha</h3>
            <p>Va em <strong>Usuarios > Alterar Senha</strong>. Digite a senha atual, depois a nova senha duas vezes, e clique em "Salvar".</p>
        </section>

        <!-- 3. DASHBOARD -->
        <section id="dashboard" class="manual-section">
            <h2>3. Dashboard (Tela Inicial)</h2>
            <p>O Dashboard e a primeira tela que voce ve ao entrar no sistema. Ele mostra um resumo de tudo que esta acontecendo.</p>

            <h3>O que cada numero significa</h3>
            <table class="manual-table">
                <tr><th>Card</th><th>O que mostra</th></tr>
                <tr><td><strong>Colaboradores Ativos</strong></td><td>Quantos colaboradores estao com status "Ativo" no sistema</td></tr>
                <tr><td><strong>Documentos/Certs Vencidos</strong></td><td>Quantos documentos e certificados ja passaram da data de validade</td></tr>
                <tr><td><strong>Vencendo em 30 dias</strong></td><td>Quantos vao vencer nos proximos 30 dias (precisam de atencao)</td></tr>
                <tr><td><strong>Em dia</strong></td><td>Total de documentos e certificados que estao dentro da validade</td></tr>
                <tr><td><strong>ASO / EPI / Treinamento</strong></td><td>Resumo por categoria: quantos vigentes, vencendo e vencidos</td></tr>
                <tr><td><strong>Colaboradores sem documento</strong></td><td>Colaboradores ativos que nao tem nenhum documento no sistema</td></tr>
                <tr><td><strong>Aprovacoes Pendentes</strong></td><td>Documentos que precisam ser revisados e aprovados pelo SESMT</td></tr>
            </table>

            <h3>Vencendo esta semana</h3>
            <p>Abaixo dos cards, aparece uma tabela amarela com todos os documentos e certificados que vencem nos proximos 7 dias. Cada item mostra quantos dias faltam para vencer.</p>

            <div class="box box-important">
                <strong>Importante:</strong> Se aparecer "0 dias" ou "1 dia", significa que voce precisa agir URGENTEMENTE para renovar o documento ou certificado.
            </div>

            <h3>Graficos</h3>
            <ul>
                <li><strong>Distribuicao por Status</strong> - Grafico de pizza mostrando quanto esta vigente, vencendo ou vencido</li>
                <li><strong>Documentos por Cliente</strong> - Grafico de barras mostrando quantos documentos cada cliente tem</li>
            </ul>
        </section>

        <!-- 4. COLABORADORES -->
        <section id="colaboradores" class="manual-section">
            <h2>4. Colaboradores</h2>
            <p>Aqui voce gerencia todos os funcionarios da empresa. Cada colaborador tem seus documentos, certificados e treinamentos vinculados.</p>

            <h3>Como cadastrar um novo colaborador</h3>
            <div class="steps">
                <div class="step"><div class="step-content"><strong>Clique em "Colaboradores" no menu lateral</strong><br>A lista de todos os colaboradores vai aparecer.</div></div>
                <div class="step"><div class="step-content"><strong>Clique no botao verde "Novo"</strong><br>Fica no canto superior direito da tela.</div></div>
                <div class="step"><div class="step-content"><strong>Preencha os dados</strong><br>Nome completo (obrigatorio), CPF, cargo, funcao, setor, cliente, obra, data de admissao, etc.</div></div>
                <div class="step"><div class="step-content"><strong>Clique em "Salvar"</strong><br>O colaborador sera cadastrado e voce vera a pagina dele.</div></div>
            </div>

            <h3>Como buscar um colaborador</h3>
            <p>Na pagina de colaboradores, use o campo de busca no topo. Voce pode digitar o nome ou parte do nome. A lista vai filtrar automaticamente.</p>

            <h3>Como editar</h3>
            <p>Clique no nome do colaborador para abrir a pagina dele. Depois clique em <strong>"Editar"</strong>. Faca as alteracoes e clique em "Salvar".</p>

            <h3>Alterar status (Ativo / Inativo)</h3>
            <p>Para mudar um colaborador de ativo para inativo (ou vice-versa), edite o colaborador e mude o campo "Status".</p>

            <h3>Atualizacao em massa (Bulk Update)</h3>
            <p>Se voce precisa mudar o setor, cargo ou status de varios colaboradores de uma vez:</p>
            <div class="steps">
                <div class="step"><div class="step-content"><strong>Marque os colaboradores</strong><br>Use as caixas de selecao (checkbox) na coluna da esquerda. Para selecionar todos, clique no checkbox do cabecalho.</div></div>
                <div class="step"><div class="step-content"><strong>Uma barra verde aparece embaixo</strong><br>Ela mostra quantos estao selecionados.</div></div>
                <div class="step"><div class="step-content"><strong>Escolha o campo e o valor</strong><br>Selecione "Cargo", "Setor", "Status", etc. e digite o novo valor.</div></div>
                <div class="step"><div class="step-content"><strong>Clique em "Aplicar"</strong><br>Confirme a acao. Todos os selecionados serao atualizados.</div></div>
            </div>
        </section>

        <!-- 5. DOCUMENTOS -->
        <section id="documentos" class="manual-section">
            <h2>5. Documentos</h2>
            <p>Documentos sao os arquivos PDF que ficam guardados para cada colaborador. Exemplos: ASO, Ficha de EPI, Ordem de Servico, certificados de treinamento, etc.</p>

            <h3>Tipos de documento</h3>
            <table class="manual-table">
                <tr><th>Tipo</th><th>O que e</th><th>Validade</th></tr>
                <tr><td><strong>ASO Admissional</strong></td><td>Exame feito quando o colaborador entra na empresa</td><td>12 meses</td></tr>
                <tr><td><strong>ASO Periodico</strong></td><td>Exame feito periodicamente para verificar a saude</td><td>12 meses</td></tr>
                <tr><td><strong>ASO Demissional</strong></td><td>Exame feito quando o colaborador sai da empresa</td><td>-</td></tr>
                <tr><td><strong>Ficha de EPI</strong></td><td>Registro de entrega de equipamentos de protecao</td><td>6 meses (padrao) ou 3 meses (Cargill: Uberlandia, Primavera do Leste, Silvania, Vicentinopolis)</td></tr>
                <tr><td><strong>Ordem de Servico</strong></td><td>Documento com as instrucoes de seguranca da funcao</td><td>-</td></tr>
                <tr><td><strong>Treinamentos</strong></td><td>Certificados de NR-10, NR-35, NR-12, etc.</td><td>12-60 meses</td></tr>
            </table>

            <h3>Como fazer upload de um documento</h3>
            <div class="steps">
                <div class="step"><div class="step-content"><strong>Va ate a pagina do colaborador</strong><br>Clique no nome dele na lista.</div></div>
                <div class="step"><div class="step-content"><strong>Clique em "Novo Documento"</strong><br>Fica na area de documentos da pagina do colaborador.</div></div>
                <div class="step"><div class="step-content"><strong>Selecione o tipo de documento</strong><br>Escolha se e ASO, EPI, Treinamento, etc.</div></div>
                <div class="step"><div class="step-content"><strong>Escolha o arquivo PDF</strong><br>Clique em "Escolher arquivo" e selecione o PDF do seu computador. Tamanho maximo: 10 MB.</div></div>
                <div class="step"><div class="step-content"><strong>Preencha a data de emissao</strong><br>Coloque a data em que o documento foi emitido.</div></div>
                <div class="step"><div class="step-content"><strong>Clique em "Enviar"</strong><br>O documento sera guardado e a validade calculada automaticamente.</div></div>
            </div>

            <h3>Status dos documentos</h3>
            <table class="manual-table">
                <tr><th>Status</th><th>Cor</th><th>Significado</th></tr>
                <tr><td>Vigente</td><td style="color:#4caf50; font-weight:bold;">Verde</td><td>Dentro da validade, tudo certo</td></tr>
                <tr><td>Proximo vencimento</td><td style="color:#ff9800; font-weight:bold;">Amarelo</td><td>Vai vencer em menos de 30 dias</td></tr>
                <tr><td>Vencido</td><td style="color:#e53935; font-weight:bold;">Vermelho</td><td>Ja passou da validade, precisa renovar</td></tr>
            </table>

            <h3>Aprovacao de documentos</h3>
            <p>Documentos importados precisam ser aprovados pelo SESMT. Na pagina do colaborador, voce vera botoes "Aprovar" e "Rejeitar" ao lado de cada documento pendente.</p>

            <h3>Editar data de emissao</h3>
            <p>Se o sistema detectou a data errada (OCR), voce pode corrigir. Na pagina do colaborador, clique no icone de edicao ao lado da data de emissao do documento.</p>
        </section>

        <!-- 6. CERTIFICADOS -->
        <section id="certificados" class="manual-section">
            <h2>6. Certificados</h2>
            <p>Certificados sao os documentos que comprovam que o colaborador fez um treinamento. O sistema gera automaticamente o certificado com layout profissional, assinatura e carimbo.</p>

            <h3>Como emitir um certificado individual</h3>
            <div class="steps">
                <div class="step"><div class="step-content"><strong>Clique em "Certificados" no menu</strong><br>A pagina de certificados vai abrir.</div></div>
                <div class="step"><div class="step-content"><strong>Clique em "Emitir Certificado"</strong><br>Escolha o colaborador.</div></div>
                <div class="step"><div class="step-content"><strong>Selecione o tipo de certificado</strong><br>NR-10, NR-35, NR-12, etc.</div></div>
                <div class="step"><div class="step-content"><strong>Preencha as datas</strong><br>Data de realizacao do treinamento.</div></div>
                <div class="step"><div class="step-content"><strong>Clique em "Gerar"</strong><br>O certificado sera gerado com layout, assinaturas e carimbo da TSE automaticamente.</div></div>
            </div>

            <div class="box box-tip">
                <strong>Dica:</strong> Para emitir certificados para VARIOS colaboradores de uma vez, use a funcao de <strong>Treinamentos</strong> (secao 7).
            </div>

            <h3>Visualizar e imprimir</h3>
            <p>Clique em "Preview" para ver como o certificado fica. Depois clique em "Imprimir" para enviar para a impressora ou salvar como PDF.</p>
        </section>

        <!-- 7. TREINAMENTOS -->
        <section id="treinamentos" class="manual-section">
            <h2>7. Treinamentos</h2>
            <p>A funcao de treinamentos permite registrar um treinamento com VARIOS participantes de uma vez. O sistema gera certificados para todos automaticamente.</p>

            <h3>Como registrar um treinamento</h3>
            <div class="steps">
                <div class="step"><div class="step-content"><strong>Clique em "Treinamentos" no menu</strong></div></div>
                <div class="step"><div class="step-content"><strong>Clique em "Novo Treinamento"</strong></div></div>
                <div class="step"><div class="step-content"><strong>Selecione o tipo</strong><br>Exemplo: NR-10 Basico, NR-35, NR-12, etc.</div></div>
                <div class="step"><div class="step-content"><strong>Selecione o ministrante</strong><br>Quem vai ministrar o treinamento.</div></div>
                <div class="step"><div class="step-content"><strong>Preencha as datas</strong><br>Data de inicio e fim do treinamento.</div></div>
                <div class="step"><div class="step-content"><strong>Marque os participantes</strong><br>Marque todos os colaboradores que participaram. Use a busca para encontrar rapidamente.</div></div>
                <div class="step"><div class="step-content"><strong>Clique em "Registrar Treinamento"</strong><br>O sistema gera certificados para TODOS os participantes de uma vez.</div></div>
            </div>

            <h3>Lista de Presenca</h3>
            <p>Na pagina do treinamento, clique em <strong>"Lista de Presenca"</strong>. Uma pagina formatada para impressao vai abrir com todos os nomes, cargos e campo de assinatura.</p>

            <h3>Calendario</h3>
            <p>Clique em <strong>"Calendario"</strong> na pagina de treinamentos para ver uma visao mensal de todos os treinamentos realizados. Voce pode navegar entre meses.</p>

            <h3>Certificados em lote</h3>
            <p>Na pagina do treinamento, clique em <strong>"Imprimir Todos"</strong> para gerar todos os certificados de uma vez, prontos para impressao.</p>
        </section>

        <!-- 8. KIT PJ -->
        <section id="kit-pj" class="manual-section">
            <h2>8. Kit PJ</h2>
            <p>O Kit PJ e um conjunto de documentos gerados automaticamente para colaboradores de empresas terceirizadas (PJ). Inclui ASO, Pedido de Exames e Ficha Clinica.</p>

            <h3>Como gerar um Kit PJ</h3>
            <div class="steps">
                <div class="step"><div class="step-content"><strong>Clique em "Kit PJ" no menu</strong></div></div>
                <div class="step"><div class="step-content"><strong>Clique em "Novo Kit PJ"</strong></div></div>
                <div class="step"><div class="step-content"><strong>Preencha os dados da empresa PJ</strong><br>Razao social, CNPJ e endereco.</div></div>
                <div class="step"><div class="step-content"><strong>Selecione o colaborador</strong></div></div>
                <div class="step"><div class="step-content"><strong>Escolha o tipo de ASO</strong><br>Admissional, Periodico, Demissional, etc.</div></div>
                <div class="step"><div class="step-content"><strong>Marque os exames necessarios</strong><br>Exame Clinico, Audiometria, Acuidade Visual, etc.</div></div>
                <div class="step"><div class="step-content"><strong>Marque as aptidoes</strong><br>Apto para funcao, Trabalho em Altura, etc.</div></div>
                <div class="step"><div class="step-content"><strong>Clique em "Gerar Kit PJ"</strong><br>O sistema gera 3 paginas prontas para impressao: ASO + Pedido de Exames + Ficha Clinica.</div></div>
            </div>
        </section>

        <!-- 9. CLIENTES E OBRAS -->
        <section id="clientes" class="manual-section">
            <h2>9. Clientes e Obras</h2>
            <p>Cada cliente pode ter uma ou mais obras. O sistema permite cadastrar requisitos de seguranca por cliente (quais documentos e certificados sao obrigatorios).</p>

            <h3>Cadastrar um cliente</h3>
            <div class="steps">
                <div class="step"><div class="step-content"><strong>Va em "Clientes e Obras" no menu</strong></div></div>
                <div class="step"><div class="step-content"><strong>Clique em "Novo Cliente"</strong></div></div>
                <div class="step"><div class="step-content"><strong>Preencha razao social, CNPJ, nome fantasia</strong></div></div>
                <div class="step"><div class="step-content"><strong>Salve</strong></div></div>
            </div>

            <h3>Cadastrar uma obra</h3>
            <p>Na pagina do cliente, clique em <strong>"Nova Obra"</strong>. Preencha o nome da obra, endereco e status.</p>

            <h3>Requisitos de seguranca</h3>
            <p>Na pagina do cliente, voce pode adicionar quais documentos e certificados sao obrigatorios. Exemplo: "NR-10 obrigatorio", "ASO obrigatorio". Isso permite gerar relatorios de conformidade.</p>
        </section>

        <!-- 10. CHECKLIST PRE-OBRA -->
        <section id="checklist" class="manual-section">
            <h2>10. Checklist Pre-Obra</h2>
            <p>Antes de enviar colaboradores para uma obra, voce pode verificar automaticamente se todos estao com a documentacao em dia.</p>

            <h3>Como usar</h3>
            <div class="steps">
                <div class="step"><div class="step-content"><strong>Clique em "Checklist Pre-Obra" no menu</strong><br>Uma lista de todas as obras ativas vai aparecer.</div></div>
                <div class="step"><div class="step-content"><strong>Clique na obra desejada</strong><br>"Verificar conformidade".</div></div>
                <div class="step"><div class="step-content"><strong>Analise o resultado</strong><br>O sistema verifica CADA colaborador da obra e mostra:<br>
                    <span style="color:#4caf50;">&#10003;</span> Em dia |
                    <span style="color:#ff9800;">&#9888;</span> Vencendo |
                    <span style="color:#e53935;">&#10007;</span> Vencido ou ausente</div></div>
            </div>

            <h3>O que e verificado</h3>
            <ul>
                <li><strong>ASO</strong> — obrigatorio para todos (validade 12 meses)</li>
                <li><strong>Ficha de EPI</strong> — obrigatorio para todos (validade 3 meses)</li>
                <li><strong>Requisitos do cliente</strong> — documentos e certificados extras que o cliente exige</li>
            </ul>

            <div class="box box-tip">
                <strong>Dica:</strong> Use o botao "Imprimir" para gerar o checklist em papel e levar para a obra.
            </div>
        </section>

        <!-- 11. AGENDA DE EXAMES -->
        <section id="agenda-exames" class="manual-section">
            <h2>11. Agenda de Exames Periodicos</h2>
            <p>A agenda mostra quando cada colaborador precisa fazer o proximo ASO (exame periodico). Ajuda a planejar com antecedencia.</p>

            <h3>Como funciona</h3>
            <div class="steps">
                <div class="step"><div class="step-content"><strong>Clique em "Agenda de Exames" no menu</strong></div></div>
                <div class="step"><div class="step-content"><strong>Veja o resumo dos proximos 12 meses</strong><br>Cards coloridos mostram quantos exames vencem em cada mes. Vermelho = muitos exames.</div></div>
                <div class="step"><div class="step-content"><strong>Navegue entre meses</strong><br>Use as setas para ver os exames de cada mes.</div></div>
            </div>

            <h3>Secoes da pagina</h3>
            <ul>
                <li><strong>Cards mensais</strong> — Resumo visual dos proximos 12 meses. Clique em um mes para ver os detalhes.</li>
                <li><strong>ASOs Vencidos</strong> — Lista em vermelho dos colaboradores com ASO ja vencido (acao urgente).</li>
                <li><strong>Exames do mes</strong> — Lista detalhada de quem precisa fazer exame no mes selecionado.</li>
            </ul>

            <div class="box box-warn">
                <strong>Atencao:</strong> Colaboradores com ASO vencido NAO podem ser enviados para obras. Agende o exame o mais rapido possivel.
            </div>
        </section>

        <!-- 12. RELATORIOS -->
        <section id="relatorios" class="manual-section">
            <h2>12. Relatorios</h2>
            <p>O sistema gera relatorios completos que podem ser visualizados na tela ou exportados.</p>

            <h3>Tipos de relatorio</h3>
            <table class="manual-table">
                <tr><th>Relatorio</th><th>O que mostra</th></tr>
                <tr><td><strong>Por Colaborador</strong></td><td>Todos os documentos e certificados de um colaborador especifico</td></tr>
                <tr><td><strong>Por Cliente</strong></td><td>Resumo de conformidade dos colaboradores vinculados ao cliente</td></tr>
                <tr><td><strong>Por Obra</strong></td><td>Checklist de cada colaborador: quais requisitos atende e quais faltam</td></tr>
                <tr><td><strong>Mensal</strong></td><td>Resumo do mes: vencimentos, treinamentos realizados, novos documentos</td></tr>
            </table>
        </section>

        <!-- 11. ALERTAS -->
        <section id="alertas" class="manual-section">
            <h2>13. Alertas</h2>
            <p>O sistema verifica automaticamente quais documentos e certificados estao vencendo e pode enviar emails de aviso.</p>

            <h3>Como funciona</h3>
            <div class="steps">
                <div class="step"><div class="step-content"><strong>Va em "Alertas" no menu</strong></div></div>
                <div class="step"><div class="step-content"><strong>Clique em "Executar Verificacao"</strong><br>O sistema vai checar todos os documentos e certificados.</div></div>
                <div class="step"><div class="step-content"><strong>Veja os resultados</strong><br>Uma lista mostra o que esta vencido e o que esta proximo do vencimento.</div></div>
                <div class="step"><div class="step-content"><strong>Enviar emails (opcional)</strong><br>Clique em "Enviar Emails" para notificar os responsaveis.</div></div>
            </div>
        </section>

        <!-- 12. EXPORTACAO -->
        <section id="exportacao" class="manual-section">
            <h2>14. Exportacao de Dados</h2>
            <p>Voce pode exportar dados do sistema em formato CSV (abre no Excel).</p>

            <h3>O que pode exportar</h3>
            <ul>
                <li><strong>Colaboradores</strong> - Lista completa com todos os dados</li>
                <li><strong>Documentos</strong> - Todos os documentos com status e validades</li>
                <li><strong>Certificados</strong> - Todos os certificados emitidos</li>
            </ul>

            <h3>Como exportar</h3>
            <p>Na pagina de cada modulo (Colaboradores, Documentos ou Certificados), procure o botao <strong>"Exportar CSV"</strong>. Clique e o arquivo sera baixado automaticamente.</p>
        </section>

        <!-- 13. LIXEIRA -->
        <section id="lixeira" class="manual-section">
            <h2>15. Lixeira</h2>
            <p>Quando voce exclui um colaborador ou documento, ele nao e apagado de verdade. Ele vai para a lixeira, onde pode ser restaurado.</p>

            <h3>Como restaurar</h3>
            <div class="steps">
                <div class="step"><div class="step-content"><strong>Va em "Lixeira" no menu</strong><br>(Apenas administradores veem esta opcao)</div></div>
                <div class="step"><div class="step-content"><strong>Encontre o item</strong><br>A lista mostra tudo que foi excluido, com data e quem excluiu.</div></div>
                <div class="step"><div class="step-content"><strong>Clique em "Restaurar"</strong><br>O item volta para o sistema normalmente.</div></div>
            </div>

            <div class="box box-warn">
                <strong>Atencao:</strong> A exclusao permanente so pode ser feita por administradores e nao pode ser desfeita.
            </div>
        </section>

        <!-- 14. USUARIOS E PERFIS -->
        <section id="usuarios" class="manual-section">
            <h2>16. Usuarios e Perfis</h2>
            <p>O sistema tem 3 tipos de usuario, cada um com permissoes diferentes:</p>

            <table class="manual-table">
                <tr><th>Perfil</th><th>O que pode fazer</th></tr>
                <tr><td><strong>Admin</strong></td><td>Acesso TOTAL ao sistema. Pode criar usuarios, acessar configuracoes, lixeira, logs e tudo mais.</td></tr>
                <tr><td><strong>SESMT</strong></td><td>Acesso operacional. Pode gerenciar colaboradores, documentos, certificados, treinamentos, clientes e relatorios. Nao acessa configuracoes do sistema.</td></tr>
                <tr><td><strong>RH</strong></td><td>Acesso somente leitura. Pode ver colaboradores e baixar documentos, mas nao pode editar ou excluir nada.</td></tr>
            </table>

            <h3>Sessoes ativas</h3>
            <p>Em <strong>Usuarios > Sessoes Ativas</strong>, voce pode ver em quais dispositivos sua conta esta logada e encerrar sessoes remotamente.</p>
        </section>

        <!-- 15. CONFIGURACOES -->
        <section id="configuracoes" class="manual-section">
            <h2>17. Configuracoes (Apenas Admin)</h2>
            <p>A area de configuracoes permite personalizar o sistema. Apenas administradores tem acesso.</p>

            <h3>Abas de configuracao</h3>
            <table class="manual-table">
                <tr><th>Aba</th><th>O que configura</th></tr>
                <tr><td><strong>Tipos de Documento</strong></td><td>Quais tipos de documento o sistema aceita (ASO, EPI, etc.), suas categorias e validades padrao</td></tr>
                <tr><td><strong>Tipos de Certificado</strong></td><td>Quais certificados podem ser emitidos (NR-10, NR-35, etc.), duracao, conteudo programatico e validade. Botao "Ver" mostra preview do certificado.</td></tr>
                <tr><td><strong>Ministrantes</strong></td><td>Instrutores que ministram os treinamentos, com cargo e registro profissional</td></tr>
                <tr><td><strong>Configuracao SMTP</strong></td><td>Servidor de email para envio de alertas e notificacoes</td></tr>
            </table>
        </section>

        <!-- 18. BACKUP -->
        <section id="backup" class="manual-section">
            <h2>18. Backup (Apenas Admin)</h2>
            <p>O sistema permite fazer backup do banco de dados de forma manual ou automatica.</p>

            <h3>Backup manual</h3>
            <div class="steps">
                <div class="step"><div class="step-content"><strong>Clique em "Backup" no menu</strong><br>(Apenas administradores)</div></div>
                <div class="step"><div class="step-content"><strong>Clique em "Executar Backup Agora"</strong><br>O sistema gera uma copia completa do banco de dados.</div></div>
                <div class="step"><div class="step-content"><strong>Baixe o arquivo</strong><br>Clique em "Baixar" ao lado do backup para salvar no seu computador.</div></div>
            </div>

            <h3>Backup automatico</h3>
            <p>Voce pode configurar para o sistema fazer backup automaticamente todos os dias:</p>
            <div class="steps">
                <div class="step"><div class="step-content"><strong>Escolha o horario</strong><br>Recomendamos as 02:00 (madrugada).</div></div>
                <div class="step"><div class="step-content"><strong>Clique em "Configurar"</strong><br>O sistema vai criar um backup automaticamente todos os dias no horario definido.</div></div>
            </div>

            <div class="box box-info">
                <strong>Informacao:</strong> Backups com mais de 30 dias sao removidos automaticamente. Os arquivos ficam salvos na pasta do OneDrive, entao ja tem uma copia na nuvem.
            </div>
        </section>

        <!-- 19. FAQ -->
        <section id="faq" class="manual-section">
            <h2>19. Perguntas Frequentes</h2>

            <h3>Esqueci minha senha. O que faco?</h3>
            <p>Peca para o administrador do sistema resetar sua senha em <strong>Usuarios</strong>. Ele vai gerar uma nova senha temporaria para voce.</p>

            <h3>O documento ficou com a data errada. Como corrijo?</h3>
            <p>Na pagina do colaborador, ao lado do documento, clique no icone de edicao da data de emissao. Coloque a data correta e salve. A validade sera recalculada automaticamente.</p>

            <h3>Como sei se um colaborador esta com tudo em dia?</h3>
            <p>Va na pagina do colaborador. Na area de documentos, se tudo estiver verde, esta em dia. Se tiver algo amarelo ou vermelho, precisa renovar. Voce tambem pode gerar um <strong>Relatorio por Colaborador</strong>.</p>

            <h3>Posso usar o sistema no celular?</h3>
            <p>Sim! O sistema funciona em celular e tablet. Abra o navegador do celular e acesse o mesmo endereco.</p>

            <h3>Como imprimo varios certificados de uma vez?</h3>
            <p>Use a funcao de <strong>Treinamentos</strong>. Registre o treinamento com todos os participantes, e depois clique em "Imprimir Todos" ou "Baixar ZIP".</p>

            <h3>O que acontece quando um documento vence?</h3>
            <p>O status muda para vermelho ("Vencido") automaticamente. O sistema vai mostrar o alerta no Dashboard e pode enviar email se o SMTP estiver configurado.</p>

            <h3>Excluir um colaborador apaga todos os documentos dele?</h3>
            <p>Nao! O colaborador vai para a <strong>Lixeira</strong> e pode ser restaurado. Os documentos continuam vinculados a ele.</p>

            <h3>Qual o tamanho maximo de arquivo que posso enviar?</h3>
            <p>O limite e de <strong>10 MB por arquivo</strong>. Apenas arquivos PDF sao aceitos.</p>

            <h3>O sistema salva o historico de alteracoes?</h3>
            <p>Sim! Toda alteracao e registrada com quem fez, quando fez e o que mudou. O administrador pode ver os <strong>Logs</strong> no menu lateral.</p>
        </section>

        <div style="margin-top:60px; padding:20px; background:#005e4e; color:white; border-radius:8px; text-align:center;">
            <p style="margin:0; font-size:14px;">Manual de Operacao - SESMT TSE Energia e Automacao Industrial</p>
            <p style="margin:4px 0 0; font-size:12px; opacity:0.7;">Versao 1.0 - Atualizado em <?= date('d/m/Y') ?></p>
        </div>
    </main>

    <script>
    // Busca no manual
    function filtrarManual(termo) {
        const sections = document.querySelectorAll('.manual-section');
        const links = document.querySelectorAll('.sidebar a[href^="#"]');
        const t = termo.toLowerCase().trim();

        sections.forEach((sec, i) => {
            if (!t || sec.textContent.toLowerCase().includes(t)) {
                sec.style.display = '';
                if (links[i]) links[i].style.display = '';
            } else {
                sec.style.display = 'none';
                if (links[i]) links[i].style.display = 'none';
            }
        });
    }

    // Highlight menu on scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                document.querySelectorAll('.sidebar a').forEach(a => a.classList.remove('active'));
                const link = document.querySelector('.sidebar a[href="#' + entry.target.id + '"]');
                if (link) link.classList.add('active');
            }
        });
    }, { rootMargin: '-20% 0px -70% 0px' });

    document.querySelectorAll('.manual-section').forEach(s => observer.observe(s));

    // Close mobile menu on link click
    document.querySelectorAll('.sidebar a').forEach(a => {
        a.addEventListener('click', () => {
            if (window.innerWidth <= 768) document.querySelector('.sidebar').classList.remove('open');
        });
    });
    </script>
</body>
</html>
