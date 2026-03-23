<?php $total = count($resultado); $pctOk = $total > 0 ? round($totalOk / $total * 100) : 0; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <div>
        <h2 style="margin:0;">Checklist Pre-Obra: <?= htmlspecialchars($obra['nome']) ?></h2>
        <p style="color:var(--c-gray); margin:4px 0 0; font-size:14px;"><?= htmlspecialchars($obra['nome_fantasia']) ?></p>
    </div>
    <div style="display:flex; gap:8px;">
        <button onclick="window.print()" class="btn btn-outline btn-sm">Imprimir</button>
        <a href="/checklist" class="btn btn-outline btn-sm">Voltar</a>
    </div>
</div>

<!-- Resumo -->
<div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:24px;">
    <div class="stat-card" style="border-left-color:#4caf50;">
        <div class="stat-number" style="color:#4caf50;"><?= $totalOk ?></div>
        <div class="stat-label">Conformes</div>
    </div>
    <div class="stat-card" style="border-left-color:#e53935;">
        <div class="stat-number" style="color:#e53935;"><?= $totalNok ?></div>
        <div class="stat-label">Nao Conformes</div>
    </div>
    <div class="stat-card" style="border-left-color:<?= $pctOk >= 80 ? '#4caf50' : ($pctOk >= 50 ? '#ff9800' : '#e53935') ?>;">
        <div class="stat-number"><?= $pctOk ?>%</div>
        <div class="stat-label">Conformidade Geral</div>
    </div>
</div>

<?php if ($totalNok > 0): ?>
<div style="background:#fce4ec; border-left:5px solid #e53935; padding:12px 16px; border-radius:6px; margin-bottom:24px; font-size:14px;">
    <strong style="color:#e53935;">Atencao:</strong> <?= $totalNok ?> colaborador(es) NAO estao com a documentacao completa para esta obra. Verifique os itens em vermelho abaixo.
</div>
<?php else: ?>
<div style="background:#e8f5e9; border-left:5px solid #4caf50; padding:12px 16px; border-radius:6px; margin-bottom:24px; font-size:14px;">
    <strong style="color:#4caf50;">Tudo certo!</strong> Todos os <?= $total ?> colaboradores estao com a documentacao em dia para esta obra.
</div>
<?php endif; ?>

<!-- Tabela de resultado -->
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Colaborador</th>
                <th style="text-align:center;">ASO</th>
                <th style="text-align:center;">EPI</th>
                <?php foreach ($reqDocs as $rd): ?>
                <th style="text-align:center; font-size:11px;"><?= htmlspecialchars($rd['doc_nome']) ?></th>
                <?php endforeach; ?>
                <?php foreach ($reqCerts as $rc): ?>
                <th style="text-align:center; font-size:11px;"><?= htmlspecialchars($rc['cert_codigo']) ?></th>
                <?php endforeach; ?>
                <th style="text-align:center;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($resultado as $r): ?>
            <tr style="<?= !$r['conforme'] ? 'background:#fff5f5;' : '' ?>">
                <td>
                    <a href="/colaboradores/<?= $r['colaborador']['id'] ?>" style="color:var(--c-primary); font-weight:600;">
                        <?= htmlspecialchars($r['colaborador']['nome_completo']) ?>
                    </a>
                    <br><span style="font-size:11px; color:var(--c-gray);"><?= htmlspecialchars($r['colaborador']['cargo'] ?? $r['colaborador']['funcao'] ?? '') ?></span>
                </td>
                <td style="text-align:center;">
                    <?php if ($r['aso']['ok']): ?>
                        <span title="Valido ate <?= $r['aso']['validade'] ? date('d/m/Y', strtotime($r['aso']['validade'])) : '' ?>" style="color:#4caf50; font-size:18px; cursor:help;">&#10003;</span>
                    <?php elseif ($r['aso']['status'] === 'ausente'): ?>
                        <span title="Sem ASO" style="color:#e53935; font-size:16px; cursor:help;">&#10007;</span>
                    <?php elseif ($r['aso']['status'] === 'proximo_vencimento'): ?>
                        <span title="Vence em <?= $r['aso']['validade'] ? date('d/m/Y', strtotime($r['aso']['validade'])) : '' ?>" style="color:#ff9800; font-size:18px; cursor:help;">&#9888;</span>
                    <?php else: ?>
                        <span title="Vencido em <?= $r['aso']['validade'] ? date('d/m/Y', strtotime($r['aso']['validade'])) : '' ?>" style="color:#e53935; font-size:16px; cursor:help;">&#10007;</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;">
                    <?php if ($r['epi']['ok']): ?>
                        <span style="color:#4caf50; font-size:18px;">&#10003;</span>
                    <?php elseif ($r['epi']['status'] === 'ausente'): ?>
                        <span title="Sem EPI" style="color:#e53935; font-size:16px;">&#10007;</span>
                    <?php elseif ($r['epi']['status'] === 'proximo_vencimento'): ?>
                        <span style="color:#ff9800; font-size:18px;">&#9888;</span>
                    <?php else: ?>
                        <span title="Vencido" style="color:#e53935; font-size:16px;">&#10007;</span>
                    <?php endif; ?>
                </td>
                <?php foreach ($r['itens'] as $item): ?>
                <td style="text-align:center;">
                    <?php if ($item['ok']): ?>
                        <span style="color:#4caf50; font-size:18px;">&#10003;</span>
                    <?php elseif ($item['status'] === 'ausente'): ?>
                        <span title="Ausente" style="color:#e53935; font-size:16px;">&#10007;</span>
                    <?php elseif ($item['status'] === 'proximo_vencimento'): ?>
                        <span title="Vencendo" style="color:#ff9800; font-size:18px;">&#9888;</span>
                    <?php else: ?>
                        <span title="Vencido" style="color:#e53935; font-size:16px;">&#10007;</span>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
                <td style="text-align:center;">
                    <?php if ($r['conforme']): ?>
                        <span class="badge badge-vigente">CONFORME</span>
                    <?php else: ?>
                        <span class="badge badge-vencido">NAO CONFORME</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div style="margin-top:16px; font-size:12px; color:var(--c-gray);">
    <strong>Legenda:</strong>
    <span style="color:#4caf50;">&#10003; Em dia</span> &nbsp;|&nbsp;
    <span style="color:#ff9800;">&#9888; Vencendo em breve</span> &nbsp;|&nbsp;
    <span style="color:#e53935;">&#10007; Vencido ou ausente</span>
    &nbsp;|&nbsp; Verificado em <?= date('d/m/Y H:i') ?>
</div>
