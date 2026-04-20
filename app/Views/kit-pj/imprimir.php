<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Kit PJ - <?= htmlspecialchars($kit['nome_completo']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 9.5pt; color: #333; }
        .print-bar { position: fixed; top: 0; left: 0; right: 0; background: #005e4e; color: white; padding: 10px 20px; display: flex; gap: 12px; align-items: center; z-index: 100; }
        .print-bar button { background: white; color: #005e4e; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 600; }
        .page { width: 210mm; min-height: 297mm; margin: 60px auto 20px; padding: 10mm 14mm; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .doc-title { text-align: center; margin-bottom: 4px; }
        .doc-title h1 { font-size: 13pt; text-decoration: underline; letter-spacing: 2px; }
        .doc-title h2 { font-size: 11pt; font-weight: normal; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #555; padding: 3px 5px; font-size: 8.5pt; vertical-align: top; }
        .sec { background: #222; color: white; font-weight: bold; font-size: 9pt; padding: 4px 8px; }
        .cb { font-family: 'Segoe UI Symbol', sans-serif; }
        /* 2-col question rows */
        .q2 td { width: 50%; border: 1px solid #555; padding: 3px 5px; font-size: 8pt; }
        .blank { border-bottom: 1px solid #555; display: inline-block; min-width: 120px; }
        .blank-lg { border-bottom: 1px solid #555; display: inline-block; min-width: 200px; }
        /* Linha de escrita: altura real de 22px para caber letra manuscrita */
        .blank-full { border-bottom: 1px solid #777; display: block; width: 100%; height: 22px; margin-top: 2px; margin-bottom: 2px; }
        /* Ficha Clínica */
        .q2 td { font-size: 9pt !important; line-height: 1.55; padding: 5px 6px !important; vertical-align: top; }
        /* signatures */
        .sig-area { margin-top: 20px; display: flex; justify-content: space-around; align-items: flex-end; }
        .sig-block { text-align: center; width: 220px; }
        .sig-block .sig-space { height: 70px; }
        .sig-block .line { border-top: 1px solid #333; margin: 2px 0 3px 0; }
        @media print {
            .print-bar { display: none !important; }
            .page { margin: 0; box-shadow: none; padding: 6mm 10mm; }
            @page { size: A4 portrait; margin: 4mm; }
            table { page-break-inside: avoid; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="print-bar">
        <button onclick="window.print()">Imprimir</button>
        <button onclick="window.close()">Fechar</button>
        <span>Kit PJ — <?= htmlspecialchars($kit['nome_completo']) ?> — <?= htmlspecialchars($kit['razao_social']) ?></span>
    </div>

    <!-- ===================== PAGINA 1: ASO ===================== -->
    <div class="page">
        <div class="doc-title">
            <h1>A S O — A T E S T A D O &nbsp; D E &nbsp; S A U D E &nbsp; O C U P A C I O N A L</h1>
            <h2><?= htmlspecialchars($kit['razao_social']) ?></h2>
        </div>

        <!-- Empresa -->
        <table>
            <tr><td class="sec" colspan="4">Empresa</td></tr>
            <tr>
                <td><strong>Razao Social:</strong> <?= htmlspecialchars($kit['razao_social']) ?></td>
                <td colspan="3"><strong>CNPJ:</strong> <?= htmlspecialchars($kit['cnpj']) ?></td>
            </tr>
            <tr>
                <td colspan="4" style="word-break:break-word; white-space:normal;"><strong>Endereço:</strong> <?= htmlspecialchars($kit['endereco'] ?? '') ?></td>
            </tr>
        </table>

        <!-- Funcionário -->
        <table>
            <tr><td class="sec" colspan="3">Funcionario</td></tr>
            <tr>
                <td colspan="2"><strong>NOME:</strong> <?= htmlspecialchars($kit['nome_completo']) ?></td>
                <td><strong>CPF:</strong> <?= htmlspecialchars($kit['cpf_formatado']) ?></td>
            </tr>
            <tr>
                <td><strong>CARGO:</strong> <?= htmlspecialchars($kit['cargo'] ?? $kit['funcao'] ?? '') ?></td>
                <td><strong>Nascimento:</strong> <?= $kit['data_nascimento'] ? date('d/m/Y', strtotime($kit['data_nascimento'])) : '____/____/________' ?></td>
                <td><strong>Idade:</strong> <?= $kit['idade'] ?></td>
            </tr>
            <tr>
                <td colspan="3"><strong>SETOR:</strong> <?= htmlspecialchars($kit['setor'] ?? '') ?></td>
            </tr>
        </table>

        <!-- Médico PCMSO -->
        <table>
            <tr><td class="sec" colspan="3">Médico responsavel pelo PCMSO</td></tr>
            <tr>
                <td><?= htmlspecialchars($kit['medico_nome']) ?></td>
                <td><?= htmlspecialchars($kit['medico_crm']) ?></td>
                <td>UF: <?= htmlspecialchars($kit['medico_uf']) ?> &nbsp; RQE: <span class="blank"></span></td>
            </tr>
        </table>

        <!-- Riscos -->
        <table>
            <tr><td class="sec" colspan="2">Perigos / Fatores de Risco</td></tr>
            <tr><td style="width:100px;"><strong>Fisicos</strong></td><td><?= htmlspecialchars($kit['riscos_fisicos'] ?? '') ?></td></tr>
            <tr><td><strong>Quimicos</strong></td><td><?= htmlspecialchars($kit['riscos_quimicos'] ?? '') ?></td></tr>
            <tr><td><strong>Biologicos</strong></td><td><?= htmlspecialchars($kit['riscos_biologicos'] ?? '') ?></td></tr>
            <tr><td><strong>Ergonômicos</strong></td><td><?= htmlspecialchars($kit['riscos_ergonomicos'] ?? '') ?></td></tr>
            <tr><td><strong>Acidentes</strong></td><td><?= htmlspecialchars($kit['riscos_acidentes'] ?? '') ?></td></tr>
        </table>

        <!-- Portarias -->
        <table>
            <tr>
                <td style="font-size:7.5pt; font-weight:bold; padding:4px;">
                    EM CUMPRIMENTO AS PORTARIAS N&ordm;S 3214/78, 3164/82, 12/83, 24/94 E 08/96 NR7 DO MINISTERIO DO TRABALHO E EMPREGO PARA FINS DE EXAME:
                </td>
            </tr>
            <tr><td style="font-size:8.5pt; padding:4px;"><?= ucfirst($kit['tipo_aso']) ?></td></tr>
        </table>

        <!-- Exames -->
        <table>
            <tr><td class="sec" colspan="2">Avaliacao Clínica e Exames Realizados</td></tr>
            <?php
            $exames = $kit['exames_arr'];
            for ($i = 0; $i < count($exames); $i += 2):
            ?>
            <tr>
                <td style="width:50%;">____/____/________ &nbsp; <?= htmlspecialchars($exames[$i]) ?></td>
                <td style="width:50%;"><?= isset($exames[$i+1]) ? '____/____/________ &nbsp; ' . htmlspecialchars($exames[$i+1]) : '&nbsp;' ?></td>
            </tr>
            <?php endfor; ?>
        </table>

        <!-- Parecer -->
        <table>
            <tr><td class="sec" colspan="2">Parecer</td></tr>
            <tr><td colspan="2" style="padding:6px;">
                <span class="cb">[ &nbsp; ]</span> Apto para a função &nbsp;&nbsp;
                <span class="cb">[ &nbsp; ]</span> Inapto para a função
                <?php foreach (array_filter($kit['aptidoes_arr'], fn($a) => $a !== 'Apto para a função') as $apt): ?>
                &nbsp;&nbsp; <span class="cb">[ &nbsp; ]</span> <?= htmlspecialchars($apt) ?>
                <?php endforeach; ?>
            </td></tr>
        </table>

        <!-- Observações -->
        <table>
            <tr><td class="sec">Observacoes</td></tr>
            <tr><td style="height:60px;"></td></tr>
        </table>

        <p style="text-align:right; font-size:8pt; margin: 8px 0;">DECLARO TER RECEBIDO COPIA DESTE ATESTADO</p>
        <p style="font-size:8pt; margin-bottom:4px;">____/____/________</p>

        <!-- Assinaturas: somente médico + colaborador -->
        <div class="sig-area">
            <div class="sig-block">
                <div class="sig-space"></div>
                <div class="line"></div>
                <div style="font-size:7.5pt;">Carimbo e Assinatura<br>Médico(a) Examinador(a) com CRM</div>
            </div>
            <div class="sig-block">
                <div class="sig-space"></div>
                <div class="line"></div>
                <div style="font-weight:bold; font-size:8.5pt;"><?= htmlspecialchars($kit['nome_completo']) ?></div>
            </div>
        </div>
    </div>

    <!-- ===================== PAGINA 2: PEDIDO DE EXAMES ===================== -->
    <div class="page">
        <div class="doc-title">
            <h1>P E D I D O &nbsp; D E &nbsp; E X A M E S</h1>
            <h2><?= htmlspecialchars($kit['razao_social']) ?></h2>
        </div>

        <table>
            <tr><td class="sec" colspan="6">Sequencia</td></tr>
            <tr>
                <td colspan="2"><strong>Funcionario</strong><br><?= htmlspecialchars($kit['nome_completo']) ?></td>
                <td><strong>Matricula</strong><br>&nbsp;</td>
                <td colspan="2"><strong>RG</strong><br>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td colspan="2"><strong>Empresa</strong><br><?= htmlspecialchars($kit['razao_social']) ?></td>
                <td><strong>Unidade</strong><br>&nbsp;</td>
                <td colspan="3"><strong>CNPJ</strong><br><?= htmlspecialchars($kit['cnpj']) ?></td>
            </tr>
            <tr>
                <td colspan="2"><strong>Data de Nascimento</strong><br><?= $kit['data_nascimento'] ? date('d/m/Y', strtotime($kit['data_nascimento'])) : '' ?></td>
                <td><strong>Data de admissao</strong><br><?= $kit['data_admissao'] ? date('d/m/Y', strtotime($kit['data_admissao'])) : '' ?></td>
                <td><strong>Idade</strong><br><?= $kit['idade'] ?></td>
                <td colspan="2"><strong>Data Ficha</strong><br>____/____/________</td>
            </tr>
            <tr>
                <td colspan="3"><strong>Nome do Setor</strong><br><?= htmlspecialchars($kit['setor'] ?? '') ?></td>
                <td colspan="3"><strong>Nome do Cargo</strong><br><?= htmlspecialchars($kit['cargo'] ?? $kit['funcao'] ?? '') ?></td>
            </tr>
        </table>

        <table>
            <tr><td class="sec" colspan="5">Exames</td></tr>
            <tr>
                <th>Código Exame</th>
                <th>Nome do Exame</th>
                <th>Recomendacao</th>
                <th>Data</th>
                <th>Hora</th>
            </tr>
            <?php foreach ($kit['exames_arr'] as $i => $exame): ?>
            <tr>
                <td style="text-align:center; width:50px;"><?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?></td>
                <td><?= htmlspecialchars($exame) ?></td>
                <td>&nbsp;</td>
                <td>____/____/________</td>
                <td>____:____</td>
            </tr>
            <?php endforeach; ?>
        </table>

        <!-- Assinaturas: somente médico + colaborador -->
        <div class="sig-area" style="margin-top:60px;">
            <div class="sig-block">
                <div class="sig-space"></div>
                <div class="line"></div>
                <div style="font-size:7.5pt;">Carimbo e Assinatura<br>Médico Examinador com CRM</div>
            </div>
            <div class="sig-block">
                <div class="sig-space"></div>
                <div class="line"></div>
                <div style="font-weight:bold; font-size:8.5pt;"><?= htmlspecialchars($kit['nome_completo']) ?></div>
            </div>
        </div>
    </div>

    <!-- ===================== PAGINAS 3+: FICHA CLINICA ===================== -->
    <div class="page">
        <div class="doc-title">
            <h1>F I C H A &nbsp; C L I N I C A</h1>
            <h2><?= htmlspecialchars($kit['razao_social']) ?></h2>
        </div>

        <!-- Dados do colaborador -->
        <table>
            <tr>
                <td colspan="3"><strong>Funcionario (Código / Nome)</strong><br><?= htmlspecialchars($kit['nome_completo']) ?></td>
                <td><strong>RG</strong><br>&nbsp;</td>
            </tr>
            <tr>
                <td colspan="2"><strong>Empresa</strong><br><?= htmlspecialchars($kit['razao_social']) ?></td>
                <td colspan="2"><strong>CNPJ</strong><br><?= htmlspecialchars($kit['cnpj']) ?></td>
            </tr>
            <tr>
                <td colspan="2"><strong>Unidade</strong><br>&nbsp;</td>
                <td><strong>Setor</strong><br><?= htmlspecialchars($kit['setor'] ?? '') ?></td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td colspan="2"><strong>Cargo</strong><br><?= htmlspecialchars($kit['cargo'] ?? $kit['funcao'] ?? '') ?></td>
                <td><strong>Sexo</strong><br><span class="cb">[ &nbsp; ]</span> Masculino &nbsp; <span class="cb">[ &nbsp; ]</span> Feminino</td>
                <td><strong>Idade</strong><br><?= $kit['idade'] ?></td>
            </tr>
            <tr>
                <td><strong>Nascimento</strong><br><?= $kit['data_nascimento'] ? date('d/m/Y', strtotime($kit['data_nascimento'])) : '' ?></td>
                <td><strong>Admissao</strong><br><?= $kit['data_admissao'] ? date('d/m/Y', strtotime($kit['data_admissao'])) : '' ?></td>
                <td><strong>Entrada</strong><br>____:____</td>
                <td><strong>Saida</strong><br>____:____</td>
            </tr>
            <tr>
                <td colspan="2"><strong>Tipo de Exame</strong><br><?= ucfirst($kit['tipo_aso']) ?></td>
                <td colspan="2"><strong>Data Ficha</strong><br>____/____/________</td>
            </tr>
            <tr>
                <td colspan="4"><strong>Médico(a)</strong><br><?= htmlspecialchars($kit['medico_nome']) ?> — <?= htmlspecialchars($kit['medico_crm']) ?> UF: <?= htmlspecialchars($kit['medico_uf']) ?></td>
            </tr>
            <tr>
                <td colspan="3"><strong>Exames</strong><br><?= htmlspecialchars(implode('; ', $kit['exames_arr'])) ?></td>
                <td><strong>Parecer do ASO</strong><br><span class="cb">[ &nbsp; ]</span> Apto &nbsp; <span class="cb">[ &nbsp; ]</span> Inapto</td>
            </tr>
        </table>

        <!-- Sinais Vitais -->
        <table>
            <tr><td class="sec" colspan="4">SINAIS VITAIS</td></tr>
            <tr>
                <th>Temperatura</th>
                <th>Frequencia Respiratoria (IPM)</th>
                <th>Pressao Arterial (mmHg)</th>
                <th>Frequencia de Pulso (BPM)</th>
            </tr>
            <tr>
                <td style="height:22px;">&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <th>Altura</th>
                <th>Biotipo</th>
                <th>Peso (Kg)</th>
                <th>Indice de Massa Corporea</th>
            </tr>
            <tr>
                <td style="height:22px;">&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td colspan="2"><strong>Perimetro Cintura (cm)</strong><br>&nbsp;</td>
                <td colspan="2"><strong>Perimetro Quadril (cm)</strong><br>&nbsp;</td>
            </tr>
        </table>

        <!-- Medicamentos -->
        <table>
            <tr><td class="sec" colspan="2">Medicamentos</td></tr>
            <tr><td colspan="2" style="height:50px;">&nbsp;</td></tr>
        </table>

        <!-- CID -->
        <table>
            <tr><td class="sec">CID</td></tr>
            <tr><td style="height:36px;">&nbsp;</td></tr>
        </table>

        <!-- Historia Patologica -->
        <table>
            <tr><td class="sec" colspan="2">HISTORIA PATOLOGICA</td></tr>
            <tr class="q2">
                <td>Voce tem ou teve alguma doenca?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Qual? <span class="blank-full"></span></td>
                <td>Voce tem febre constante?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Qual? <span class="blank-full"></span></td>
            </tr>
            <tr class="q2">
                <td>Portador de deficiencia?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>&nbsp;</td>
            </tr>
            <tr class="q2">
                <td>Voce tem alergia?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Qual? <span class="blank-full"></span></td>
                <td>Adquirida (acidente) <span class="cb">[ &nbsp; ]</span> &nbsp; Congenita (nascimento) <span class="cb">[ &nbsp; ]</span><br>Histórico de Cirurgia?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Qual? <span class="blank-full"></span></td>
            </tr>
            <tr class="q2">
                <td>Voce tem emagrecimento constante?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Perdeu peso recentemente?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Quantos quilos? <span class="blank-full"></span></td>
                <td>Ja esteve internado?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Alguma cirurgia prevista?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Qual? <span class="blank-full"></span></td>
            </tr>
            <tr class="q2">
                <td>Voce sente cheiro normalmente?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Voce faz algum tratamento?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Qual? <span class="blank-full"></span></td>
            </tr>
            <tr class="q2">
                <td>Voce tem rinite?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Faz uso de algum medicamento?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Qual? <span class="blank-full"></span></td>
            </tr>
            <tr class="q2">
                <td>Voce tem sinusite?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>O que faz em seu tempo livre (hobby)?<br><span class="blank-full"></span><span class="blank-full"></span></td>
            </tr>
            <tr class="q2">
                <td>Voce tem diabetes?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Tabagista? <span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Quantos cigarros por dia? <span class="blank-full"></span></td>
                <td>Voce pratica atividade fisica?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Qual? <span class="blank-full"></span><br>Etilista? <span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Qual a frequencia? <span class="blank-full"></span></td>
            </tr>
        </table>

        <!-- Segmento Cranioencefalico -->
        <table>
            <tr><td class="sec" colspan="2">SEGMENTO CRANIOENCEFALICO — OLHOS, BOCA, OUVIDO</td></tr>
            <tr class="q2">
                <td>Dificuldade para enxergar?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não &nbsp; Correcao de grau? <span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Lentes de contato <span class="cb">[ &nbsp; ]</span> &nbsp; Oculos <span class="cb">[ &nbsp; ]</span> &nbsp; Perto <span class="cb">[ &nbsp; ]</span> &nbsp; Longe <span class="cb">[ &nbsp; ]</span><br>Voce tem ou teve doenca oftalmologica?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Qual? <span class="blank-full"></span></td>
                <td>Voce ja realizou audiometria?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Qual o resultado?<br><span class="cb">[ &nbsp; ]</span> Normal &nbsp; <span class="cb">[ &nbsp; ]</span> Alterado &nbsp; <span class="cb">[ &nbsp; ]</span> Não soube o resultado</td>
            </tr>
            <tr class="q2">
                <td>Fez tratamento odontologico recente?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Voce tem dor de garganta frequente?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Voce tem ou teve otite recente?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Voce tem ou teve outro tipo de doenca de ouvido?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Voce sente zumbido ou ouvido tapado?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Na sua opiniao, voce ouve bem? <span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
        </table>

        <!-- Sistema Neurológico -->
        <table>
            <tr><td class="sec" colspan="2">SISTEMA NEUROLOGICO / PSIQUICO</td></tr>
            <tr class="q2">
                <td>Voce tem dor de cabeca frequente?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Voce tem insonia (dificuldade para dormir)?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
            <tr class="q2">
                <td>Voce tem ou teve desmaios?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Voce tem ou teve quadro de tontura?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
            <tr class="q2">
                <td>Voce ja fez tratamentos contra o stress?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Voce tem ou teve convulsao?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
            <tr class="q2">
                <td>Voce tem depressao?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Voce fica irritado(a) ou nervoso(a) facilmente?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
        </table>

        <!-- Sistema Geniturinario -->
        <table>
            <tr><td class="sec" colspan="2">SISTEMA GENITURINARIO</td></tr>
            <tr class="q2">
                <td>Voce bebe muita agua?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Voce acorda varias vezes a noite para urinar?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
            <tr class="q2">
                <td>Voce tem ou teve infeccoes de urina?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não &nbsp; Quantas vezes? <span class="blank"></span></td>
                <td>Voce urina varias vezes ao dia?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
            <tr class="q2">
                <td>Voce sente dor ou ardencia ao urinar?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Voce tem ou teve colica de rins ou calculos?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Voce tem doenca de rins?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
        </table>

        <!-- Aparelho Digestivo -->
        <table>
            <tr><td class="sec" colspan="2">APARELHO DIGESTIVO (GASTROINTESTINAL)</td></tr>
            <tr class="q2">
                <td>Voce tem diarreia frequente?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Voce tem intestino preso?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
            <tr class="q2">
                <td>Voce apresenta sangue nas fezes?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Voce tem ou teve hemorroidas?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
            <tr class="q2">
                <td>Voce tem ou teve ulcera?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Voce tem gastrite?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
            <tr class="q2">
                <td>Voce tem azia?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Voce tem ou teve hernias?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
        </table>

        <!-- Aparelho Respiratorio -->
        <table>
            <tr><td class="sec" colspan="2">APARELHO RESPIRATORIO</td></tr>
            <tr class="q2">
                <td>Voce tem ou teve falta de ar?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Voce ja teve pneumonia?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
            <tr class="q2">
                <td>Voce tem tosse constante?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Voce sente falta de ar quando faz algum esforco?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
            <tr class="q2">
                <td>Voce tem asma?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Voce tem ou teve bronquite?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
            <tr class="q2">
                <td>Voce fica resfriado com frequencia?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Voce ja apresentou escarros de sangue?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
        </table>

        <!-- Aparelho Cardiovascular -->
        <table>
            <tr><td class="sec" colspan="2">APARELHO CARDIOVASCULAR</td></tr>
            <tr class="q2">
                <td>Voce tem ou teve doenca do coracao?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Voce tem ou teve dores no peito?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
            <tr class="q2">
                <td>Voce tem ou teve pressao alta?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Voce tem dores nas pernas?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
            <tr class="q2">
                <td>Voce tem taquicardia?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Voce tem varizes?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
            <tr class="q2">
                <td>Voce tem sopro cardíaco?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Ja operou de varizes?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
        </table>

        <!-- Pele -->
        <table>
            <tr><td class="sec" colspan="2">PELE</td></tr>
            <tr class="q2">
                <td>Voce tem doenca de pele e unha?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Qual? <span class="blank-full"></span></td>
                <td>Voce tem carocos pelo corpo?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Onde? <span class="blank-full"></span></td>
            </tr>
            <tr class="q2">
                <td>Voce ja fez tratamento de pele?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Sua pele se irrita por qualquer coisa?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
            <tr class="q2">
                <td colspan="2">Voce tem ou teve alguma mancha na pele que tenha crescido ou mudado de cor recentemente?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
        </table>

        <!-- Aparelho Osteomuscular -->
        <table>
            <tr><td class="sec" colspan="2">APARELHO OSTEOMUSCULAR</td></tr>
            <tr class="q2">
                <td>Voce tem ou teve tendinite?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Voce sente dor na coluna?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Onde? <span class="blank-full"></span></td>
            </tr>
            <tr class="q2">
                <td>Voce sente dores musculares?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Qual? <span class="blank-full"></span></td>
                <td>Voce tem ou teve hernia de disco?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
            <tr class="q2">
                <td>Voce tem reumatismo?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
                <td>Voce sente dores nas articulacoes (juntas)?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td>
            </tr>
        </table>

        <!-- Antecedentes Familiares -->
        <table>
            <tr><td class="sec" colspan="2">ANTECEDENTES FAMILIARES — Alguem na sua familia tem ou teve:</td></tr>
            <tr class="q2">
                <td>Cancer?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Quem? <span class="blank-full"></span></td>
                <td>Doencas do coracao?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Quem? <span class="blank-full"></span></td>
            </tr>
            <tr class="q2">
                <td>Pressao alta?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Quem? <span class="blank-full"></span></td>
                <td>Tuberculose?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Quem? <span class="blank-full"></span></td>
            </tr>
            <tr class="q2">
                <td>Diabetes?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Quem? <span class="blank-full"></span></td>
                <td>Doenca mental?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Quem? <span class="blank-full"></span></td>
            </tr>
            <tr class="q2">
                <td colspan="2">Alcoolismo?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Quem? <span class="blank-full"></span></td>
            </tr>
        </table>

        <!-- Perguntas Homens -->
        <table>
            <tr><td class="sec">PERGUNTAS SOMENTE PARA HOMENS</td></tr>
            <tr><td>Voce faz ou fez tratamento para problemas na prostata?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não</td></tr>
        </table>

        <!-- Anamnese Ocupacional -->
        <table>
            <tr><td class="sec" colspan="2">ANAMNESE OCUPACIONAL</td></tr>
            <tr class="q2">
                <td>Voce ja sofreu algum acidente de trabalho?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Precisou se afastar pelo acidente?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não &nbsp; Por quanto tempo? <span class="blank"></span></td>
                <td>Voce utiliza computador na sua casa?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Quantas horas por dia? <span class="blank"></span></td>
            </tr>
            <tr class="q2">
                <td>Ja trabalhou com produtos quimicos?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Quais? <span class="blank-full"></span><span class="blank-full"></span><span class="blank-full"></span></td>
                <td>No seu ultimo trabalho, voce acha que sua função poderia causar algum tipo de doenca?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Qual doenca? <span class="blank-full"></span><span class="blank-full"></span><span class="blank-full"></span></td>
            </tr>
            <tr class="q2">
                <td>Ja trabalhou em local barulhento?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Utilizava protetor auricular?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Ja trabalhou em local com poeira?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Ja se afastou por motivo de doenca?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Qual? <span class="blank-full"></span><br>Por quanto tempo? <span class="blank-full"></span></td>
                <td>Voce acha que o trabalho que faz atualmente poderia causar algum tipo de doenca?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Qual doenca? <span class="blank-full"></span><span class="blank-full"></span><br>Voce faz uso de algum EPI?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não<br>Qual(ais)? <span class="blank-full"></span><span class="blank-full"></span></td>
            </tr>
            <tr class="q2">
                <td colspan="2">Voce trabalha utilizando computador?<br><span class="cb">[ &nbsp; ]</span> Sim &nbsp; <span class="cb">[ &nbsp; ]</span> Não &nbsp; Quantas horas por dia? <span class="blank"></span></td>
            </tr>
        </table>

        <!-- Declaracao -->
        <table style="margin-top:8px;">
            <tr>
                <td style="font-size:7.5pt; font-weight:bold; padding:5px;">
                    DECLARO QUE AS INFORMACOES PRESTADAS ACIMA SAO VERDADEIRAS E COMPLETAS, PODENDO SER PENALIZADO(A)<br>
                    De acordo com o Art. 299 do código Penal Brasileiro (Falsidade Ideologica)
                </td>
            </tr>
            <tr>
                <td style="font-size:8pt; padding:8px;">
                    Local: <span class="blank-lg"></span> &nbsp;&nbsp; Data: ____/____/________<br><br>
                    Assinatura: <span class="blank-full"></span>
                </td>
            </tr>
        </table>

        <!-- Assinatura: somente médico + colaborador -->
        <div class="sig-area" style="margin-top:16px;">
            <div class="sig-block">
                <div class="sig-space"></div>
                <div class="line"></div>
                <div style="font-size:7.5pt;">Carimbo e Assinatura<br>Médico(a) Examinador(a) com CRM</div>
            </div>
            <div class="sig-block">
                <div class="sig-space"></div>
                <div class="line"></div>
                <div style="font-weight:bold; font-size:8.5pt;"><?= htmlspecialchars($kit['nome_completo']) ?></div>
            </div>
        </div>
    </div>
</body>
</html>
