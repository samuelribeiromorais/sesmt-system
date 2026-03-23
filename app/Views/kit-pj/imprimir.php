<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Kit PJ - <?= htmlspecialchars($kit['nome_completo']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10pt; color: #333; }
        .print-bar { position: fixed; top: 0; left: 0; right: 0; background: #005e4e; color: white; padding: 10px 20px; display: flex; gap: 12px; align-items: center; z-index: 100; }
        .print-bar button { background: white; color: #005e4e; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 600; }
        .page { width: 210mm; min-height: 297mm; margin: 60px auto 20px; padding: 12mm 15mm; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        h2 { font-size: 12pt; color: #005e4e; text-align: center; margin-bottom: 12px; border-bottom: 2px solid #005e4e; padding-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #666; padding: 4px 6px; font-size: 9pt; text-align: left; vertical-align: top; }
        th { background: #e8f5e9; font-size: 8.5pt; }
        .section-title { background: #005e4e; color: white; font-weight: bold; font-size: 9pt; padding: 4px 8px; text-align: center; }
        .checkbox { font-family: 'Segoe UI Symbol', sans-serif; }
        .sig-area { margin-top: 30px; display: flex; justify-content: space-around; align-items: flex-end; }
        .sig-block { text-align: center; width: 220px; }
        .sig-block .sig-img-space { height: 90px; display: flex; align-items: flex-end; justify-content: center; }
        .sig-block .sig-img-space img { max-height: 80px; max-width: 200px; object-fit: contain; }
        .sig-block .line { border-top: 1px solid #333; margin: 2px 0 4px 0; }
        .header-info { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .header-info .logo { font-size: 14pt; font-weight: bold; color: #005e4e; }
        @media print {
            .print-bar { display: none !important; }
            .page { margin: 0; box-shadow: none; padding: 8mm 12mm; }
            @page { size: A4 portrait; margin: 5mm; }
        }
    </style>
</head>
<body>
    <div class="print-bar">
        <button onclick="window.print()">Imprimir</button>
        <button onclick="window.close()">Fechar</button>
        <span>Kit PJ - <?= htmlspecialchars($kit['nome_completo']) ?> - <?= htmlspecialchars($kit['razao_social']) ?></span>
    </div>

    <!-- ===================== PAGINA 1: ASO ===================== -->
    <div class="page">
        <div class="header-info">
            <div class="logo">TSE SESMT</div>
        </div>

        <h2>ATESTADO DE SAUDE OCUPACIONAL - ASO</h2>

        <!-- Empresa -->
        <table>
            <tr><td class="section-title" colspan="3">Empresa</td></tr>
            <tr>
                <td><strong>Razao Social:</strong> <?= htmlspecialchars($kit['razao_social']) ?></td>
                <td colspan="2"><strong>CNPJ:</strong> <?= htmlspecialchars($kit['cnpj']) ?></td>
            </tr>
            <tr>
                <td colspan="3"><strong>Endereco:</strong> <?= htmlspecialchars($kit['endereco'] ?? '') ?></td>
            </tr>
        </table>

        <!-- Funcionário -->
        <table>
            <tr><td class="section-title" colspan="3">Funcionario</td></tr>
            <tr>
                <td><strong>NOME:</strong> <?= htmlspecialchars($kit['nome_completo']) ?></td>
                <td colspan="2"><strong>CPF:</strong> <?= htmlspecialchars($kit['cpf_formatado']) ?></td>
            </tr>
            <tr>
                <td><strong>CARGO:</strong> <?= htmlspecialchars($kit['cargo'] ?? $kit['funcao'] ?? '') ?></td>
                <td><strong>Nascimento:</strong> <?= $kit['data_nascimento'] ? date('d/m/Y', strtotime($kit['data_nascimento'])) : '' ?></td>
                <td><strong>Idade:</strong> <?= $kit['idade'] ?></td>
            </tr>
            <tr>
                <td colspan="3"><strong>SETOR:</strong> <?= htmlspecialchars($kit['setor'] ?? '') ?></td>
            </tr>
        </table>

        <!-- Médico PCMSO -->
        <table>
            <tr><td class="section-title" colspan="2">Medico responsavel pelo PCMSO</td></tr>
            <tr>
                <td><strong><?= htmlspecialchars($kit['medico_nome']) ?></strong></td>
                <td><strong><?= htmlspecialchars($kit['medico_crm']) ?></strong> UF: <?= htmlspecialchars($kit['medico_uf']) ?></td>
            </tr>
        </table>

        <!-- Riscos -->
        <table>
            <tr><td class="section-title" colspan="2">Perigos / Fatores de Risco</td></tr>
            <tr><td style="width:100px;"><strong>Fisicos</strong></td><td><?= htmlspecialchars($kit['riscos_fisicos'] ?? '') ?></td></tr>
            <tr><td><strong>Quimicos</strong></td><td><?= htmlspecialchars($kit['riscos_quimicos'] ?? '') ?></td></tr>
            <tr><td><strong>Biologicos</strong></td><td><?= htmlspecialchars($kit['riscos_biologicos'] ?? '') ?></td></tr>
            <tr><td><strong>Ergonomicos</strong></td><td><?= htmlspecialchars($kit['riscos_ergonomicos'] ?? '') ?></td></tr>
            <tr><td><strong>Acidentes</strong></td><td><?= htmlspecialchars($kit['riscos_acidentes'] ?? '') ?></td></tr>
        </table>

        <!-- Tipo de Exame -->
        <table>
            <tr><td class="section-title" colspan="2">Tipo de Exame</td></tr>
            <tr><td colspan="2" style="font-weight:bold; font-size:10pt;"><?= ucfirst($kit['tipo_aso']) ?></td></tr>
        </table>

        <!-- Exames -->
        <table>
            <tr><td class="section-title" colspan="3">Avaliacao Clinica e Exames Realizados</td></tr>
            <?php foreach ($kit['exames_arr'] as $i => $exame): ?>
            <tr>
                <td style="width:40%;">____/____/________ <?= htmlspecialchars($exame) ?></td>
                <?php if ($i + 1 < count($kit['exames_arr'])): $i++; ?>
                <td style="width:40%;">____/____/________ <?= htmlspecialchars($kit['exames_arr'][$i]) ?></td>
                <?php else: ?>
                <td></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </table>

        <!-- Parecer -->
        <table>
            <tr><td class="section-title" colspan="2">Parecer</td></tr>
            <tr><td colspan="2" style="font-size:10pt; padding:8px;">
                <?php foreach ($kit['aptidoes_arr'] as $apt): ?>
                <span class="checkbox">[ &nbsp; ]</span> Apto - <?= htmlspecialchars($apt) ?> &nbsp;&nbsp;
                <span class="checkbox">[ &nbsp; ]</span> Inapto - <?= htmlspecialchars($apt) ?><br>
                <?php endforeach; ?>
            </td></tr>
        </table>

        <!-- Observações -->
        <table>
            <tr><td class="section-title">Observacoes</td></tr>
            <tr><td style="height:40px;"></td></tr>
        </table>

        <!-- Assinaturas -->
        <div class="sig-area">
            <div class="sig-block">
                <div class="sig-img-space"></div>
                <div class="line"></div>
                <div style="font-size:8pt;">Carimbo e Assinatura<br>Medico(a) Examinador(a) com CRM</div>
            </div>
            <div class="sig-block">
                <div class="sig-img-space"></div>
                <div class="line"></div>
                <div style="font-weight:bold; font-size:9pt;"><?= htmlspecialchars($kit['nome_completo']) ?></div>
                <div style="font-size:8pt;">Funcionario</div>
            </div>
            <div class="sig-block">
                <div class="sig-img-space"><img src="/assets/images/assinatura_mariana.png?v=2"></div>
                <div class="line"></div>
                <div style="font-weight:bold; font-size:9pt;">Mariana Toscano Rios</div>
                <div style="font-size:8pt;">Eng. de Seguranca do Trabalho<br>CREA - 5071365203/SP</div>
            </div>
            <div class="sig-block">
                <div class="sig-img-space"><img src="/assets/images/carimbo_tse.png?v=2"></div>
                <div class="line"></div>
                <div style="font-weight:bold; font-size:9pt;">TSE Automacao Industrial Ltda</div>
                <div style="font-size:8pt;">CNPJ 05.149.152/0002-55</div>
            </div>
        </div>

        <p style="margin-top:15px; font-size:7pt; text-align:center; color:#999;">
            DECLARO TER RECEBIDO COPIA DESTE ATESTADO &nbsp;&nbsp; ___/___/____ &nbsp;&nbsp; Assinatura: _______________________
        </p>
    </div>

    <!-- ===================== PAGINA 2: PEDIDO DE EXAMES ===================== -->
    <div class="page">
        <div class="header-info">
            <div class="logo">TSE SESMT</div>
        </div>

        <h2>PEDIDO DE EXAMES</h2>

        <table>
            <tr>
                <td><strong>Funcionario:</strong> <?= htmlspecialchars($kit['nome_completo']) ?></td>
                <td><strong>CPF:</strong> <?= htmlspecialchars($kit['cpf_formatado']) ?></td>
            </tr>
            <tr>
                <td><strong>Empresa:</strong> <?= htmlspecialchars($kit['razao_social']) ?></td>
                <td><strong>CNPJ:</strong> <?= htmlspecialchars($kit['cnpj']) ?></td>
            </tr>
            <tr>
                <td><strong>Nascimento:</strong> <?= $kit['data_nascimento'] ? date('d/m/Y', strtotime($kit['data_nascimento'])) : '' ?></td>
                <td><strong>Idade:</strong> <?= $kit['idade'] ?></td>
            </tr>
            <tr>
                <td><strong>Setor:</strong> <?= htmlspecialchars($kit['setor'] ?? '') ?></td>
                <td><strong>Cargo:</strong> <?= htmlspecialchars($kit['cargo'] ?? $kit['funcao'] ?? '') ?></td>
            </tr>
        </table>

        <table>
            <tr><td class="section-title" colspan="4">Exames Solicitados</td></tr>
            <tr><th>Codigo</th><th>Nome do Exame</th><th>Data</th><th>Hora</th></tr>
            <?php foreach ($kit['exames_arr'] as $i => $exame): ?>
            <tr>
                <td style="text-align:center;"><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($exame) ?></td>
                <td>____/____/________</td>
                <td>____:____</td>
            </tr>
            <?php endforeach; ?>
        </table>

        <div class="sig-area" style="margin-top:60px;">
            <div class="sig-block">
                <div class="sig-img-space"><img src="/assets/images/assinatura_mariana.png?v=2"></div>
                <div class="line"></div>
                <div style="font-weight:bold; font-size:9pt;">Mariana Toscano Rios</div>
                <div style="font-size:8pt;">Eng. de Seguranca do Trabalho<br>CREA - 5071365203/SP</div>
            </div>
            <div class="sig-block">
                <div class="sig-img-space"><img src="/assets/images/carimbo_tse.png?v=2"></div>
                <div class="line"></div>
                <div style="font-weight:bold; font-size:9pt;">TSE Automacao Industrial Ltda</div>
                <div style="font-size:8pt;">CNPJ 05.149.152/0002-55</div>
            </div>
        </div>
    </div>

    <!-- ===================== PAGINA 3: FICHA CLINICA ===================== -->
    <div class="page">
        <div class="header-info">
            <div class="logo">TSE SESMT</div>
        </div>

        <h2>FICHA CLINICA - ANAMNESE OCUPACIONAL</h2>

        <table>
            <tr>
                <td><strong>Nome:</strong> <?= htmlspecialchars($kit['nome_completo']) ?></td>
                <td><strong>Empresa:</strong> <?= htmlspecialchars($kit['razao_social']) ?></td>
            </tr>
            <tr>
                <td><strong>Cargo:</strong> <?= htmlspecialchars($kit['cargo'] ?? $kit['funcao'] ?? '') ?></td>
                <td><strong>Setor:</strong> <?= htmlspecialchars($kit['setor'] ?? '') ?></td>
            </tr>
            <tr>
                <td><strong>Nascimento:</strong> <?= $kit['data_nascimento'] ? date('d/m/Y', strtotime($kit['data_nascimento'])) : '' ?></td>
                <td><strong>Sexo:</strong> __________ &nbsp;&nbsp; <strong>Tipo:</strong> <?= ucfirst($kit['tipo_aso']) ?></td>
            </tr>
        </table>

        <table>
            <tr><td class="section-title" colspan="2">HISTORIA PATOLOGICA</td></tr>
            <?php
            $perguntas = [
                'Voce tem ou teve alguma doenca?',
                'Voce tem alergia? Qual?',
                'Voce tem emagrecimento constante?',
                'Voce sente cheiro normalmente?',
                'Voce tem rinite/sinusite?',
                'Voce tem diabetes?',
                'Tabagista? Quantos cigarros/dia?',
                'Etilista? Qual frequencia?',
                'Portador de deficiencia?',
                'Historico de cirurgia?',
                'Ja esteve internado?',
                'Faz algum tratamento?',
                'Faz uso de algum medicamento?',
                'Pratica atividade fisica?',
            ];
            foreach ($perguntas as $p): ?>
            <tr>
                <td style="width:70%;"><?= $p ?></td>
                <td style="text-align:center;"><span class="checkbox">[ &nbsp; ]</span> Sim &nbsp; <span class="checkbox">[ &nbsp; ]</span> Nao</td>
            </tr>
            <?php endforeach; ?>
        </table>

        <table>
            <tr><td class="section-title" colspan="2">SEGMENTO CRANIOENCEFALICO</td></tr>
            <tr><td>Dificuldade para enxergar?</td><td><span class="checkbox">[ &nbsp; ]</span> Sim <span class="checkbox">[ &nbsp; ]</span> Nao &nbsp; Correcao: <span class="checkbox">[ &nbsp; ]</span> Lentes <span class="checkbox">[ &nbsp; ]</span> Oculos</td></tr>
            <tr><td>Otite recente?</td><td><span class="checkbox">[ &nbsp; ]</span> Sim <span class="checkbox">[ &nbsp; ]</span> Nao</td></tr>
            <tr><td>Sente zumbido ou ouvido tapado?</td><td><span class="checkbox">[ &nbsp; ]</span> Sim <span class="checkbox">[ &nbsp; ]</span> Nao</td></tr>
        </table>

        <table>
            <tr><td class="section-title" colspan="2">ANAMNESE OCUPACIONAL</td></tr>
            <tr><td>Ja sofreu acidente de trabalho?</td><td><span class="checkbox">[ &nbsp; ]</span> Sim <span class="checkbox">[ &nbsp; ]</span> Nao</td></tr>
            <tr><td>Ja trabalhou com produtos quimicos?</td><td><span class="checkbox">[ &nbsp; ]</span> Sim <span class="checkbox">[ &nbsp; ]</span> Nao</td></tr>
            <tr><td>Ja trabalhou em local barulhento?</td><td><span class="checkbox">[ &nbsp; ]</span> Sim <span class="checkbox">[ &nbsp; ]</span> Nao</td></tr>
            <tr><td>Ja se afastou por motivo de doenca?</td><td><span class="checkbox">[ &nbsp; ]</span> Sim <span class="checkbox">[ &nbsp; ]</span> Nao</td></tr>
            <tr><td>Utiliza computador? Quantas horas/dia?</td><td><span class="checkbox">[ &nbsp; ]</span> Sim <span class="checkbox">[ &nbsp; ]</span> Nao &nbsp; ____h</td></tr>
            <tr><td>Usa EPI?</td><td><span class="checkbox">[ &nbsp; ]</span> Sim <span class="checkbox">[ &nbsp; ]</span> Nao &nbsp; Qual: _______________</td></tr>
        </table>

        <p style="margin-top:12px; font-size:7.5pt; text-align:center; border:1px solid #666; padding:6px;">
            DECLARO QUE AS INFORMACOES PRESTADAS ACIMA SAO VERDADEIRAS E COMPLETAS.<br>
            De acordo com o Art. 299 do Codigo Penal Brasileiro (Falsidade Ideologica)<br>
            Local: ________________ Data: ____/____/________ Assinatura: _______________________________
        </p>
    </div>
</body>
</html>
