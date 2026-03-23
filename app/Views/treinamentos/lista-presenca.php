<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Lista de Presenca - <?= htmlspecialchars($treinamento['tipo_codigo']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11pt; color: #333; }
        .print-bar { position: fixed; top: 0; left: 0; right: 0; background: #005e4e; color: white; padding: 10px 20px; display: flex; gap: 12px; align-items: center; z-index: 100; }
        .print-bar button { background: white; color: #005e4e; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 600; }
        .page { width: 210mm; min-height: 297mm; margin: 60px auto 20px; padding: 15mm 20mm; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #005e4e; padding-bottom: 15px; }
        .header h1 { font-size: 16pt; color: #005e4e; margin-bottom: 5px; }
        .header h2 { font-size: 12pt; color: #333; font-weight: normal; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 20px; font-size: 10pt; }
        .info-grid .item { display: flex; gap: 5px; }
        .info-grid .label { font-weight: bold; min-width: 100px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #999; padding: 6px 8px; text-align: left; font-size: 9.5pt; }
        th { background: #005e4e; color: white; font-size: 9pt; text-transform: uppercase; }
        td.num { text-align: center; width: 30px; }
        td.assinatura { width: 180px; height: 35px; }
        .footer { margin-top: 30px; font-size: 9pt; color: #666; text-align: center; }
        .footer-line { margin-top: 60px; display: flex; justify-content: space-around; }
        .footer-line .sign { text-align: center; width: 200px; }
        .footer-line .sign .line { border-top: 1px solid #333; margin-bottom: 4px; }
        @media print {
            .print-bar { display: none !important; }
            .page { margin: 0; box-shadow: none; }
            @page { size: A4 portrait; margin: 10mm; }
        }
    </style>
</head>
<body>
    <div class="print-bar">
        <button onclick="window.print()">Imprimir</button>
        <button onclick="window.close()">Fechar</button>
        <span>Lista de Presenca - <?= htmlspecialchars($treinamento['tipo_codigo']) ?> - <?= count($participantes) ?> participantes</span>
    </div>

    <div class="page">
        <div class="header">
            <h1>LISTA DE PRESENCA</h1>
            <h2><?= htmlspecialchars($treinamento['tipo_titulo']) ?></h2>
        </div>

        <div class="info-grid">
            <div class="item"><span class="label">Treinamento:</span> <?= htmlspecialchars($treinamento['tipo_codigo']) ?> (<?= htmlspecialchars($treinamento['duracao']) ?>)</div>
            <div class="item"><span class="label">Ministrante:</span> <?= htmlspecialchars($treinamento['ministrante_nome'] ?? '-') ?></div>
            <div class="item"><span class="label">Data:</span>
                <?= date('d/m/Y', strtotime($treinamento['data_realizacao'])) ?>
                <?php if ($treinamento['data_realizacao_fim'] && $treinamento['data_realizacao_fim'] !== $treinamento['data_realizacao']): ?>
                a <?= date('d/m/Y', strtotime($treinamento['data_realizacao_fim'])) ?>
                <?php endif; ?>
            </div>
            <div class="item"><span class="label">Local:</span> TSE Energia e Automacao</div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:30px;">N</th>
                    <th>Nome Completo</th>
                    <th style="width:120px;">Cargo / Funcao</th>
                    <th style="width:80px;">Setor</th>
                    <th style="width:180px;">Assinatura</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($participantes as $i => $p): ?>
                <tr>
                    <td class="num"><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($p['nome_completo']) ?></td>
                    <td style="font-size:8.5pt;"><?= htmlspecialchars($p['funcao'] ?? $p['cargo'] ?? '-') ?></td>
                    <td style="font-size:8.5pt;"><?= htmlspecialchars($p['setor'] ?? '-') ?></td>
                    <td class="assinatura"></td>
                </tr>
                <?php endforeach; ?>
                <?php /* linhas em branco para adicionar nomes manualmente */ ?>
                <?php for ($j = count($participantes); $j < count($participantes) + 3; $j++): ?>
                <tr>
                    <td class="num"><?= $j + 1 ?></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="assinatura"></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <div class="footer-line" style="align-items: flex-end;">
            <div class="sign">
                <div style="height:70px;"></div>
                <div class="line"></div>
                <div><?= htmlspecialchars($treinamento['ministrante_nome'] ?? 'Instrutor') ?></div>
                <div style="font-size:8pt;">Instrutor</div>
            </div>
            <div class="sign">
                <div style="height:70px; display:flex; align-items:flex-end; justify-content:center;">
                    <img src="/assets/images/assinatura_mariana.png?v=3" style="max-height:60px; max-width:160px;">
                </div>
                <div class="line"></div>
                <div>Mariana Toscano Rios</div>
                <div style="font-size:8pt;">Eng. de Seguranca do Trabalho</div>
            </div>
        </div>

        <div class="footer">
            TSE Energia e Automacao Industrial Ltda - SESMT<br>
            Documento gerado em <?= date('d/m/Y H:i') ?>
        </div>
    </div>
</body>
</html>
