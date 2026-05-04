<?php
$corConformidade = $conformidade >= 95 ? '#00b279' : ($conformidade >= 80 ? '#f39c12' : '#e74c3c');
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <div></div>
    <a href="/rh" class="btn btn-secondary btn-sm">← Voltar ao painel operacional</a>
</div>

<!-- KPIs principais -->
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px;">
    <div class="stat-card" style="border-left:4px solid <?= $corConformidade ?>;">
        <div class="stat-value" style="color:<?= $corConformidade ?>;"><?= number_format($conformidade,1,',','.') ?>%</div>
        <div class="stat-label">Conformidade global</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #f39c12;">
        <div class="stat-value" style="color:#f39c12;"><?= $totalPend ?></div>
        <div class="stat-label">Pendentes de envio</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #e74c3c;">
        <div class="stat-value" style="color:#e74c3c;"><?= $atrasados ?></div>
        <div class="stat-label">Atrasados (SLA vencido)</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #8b5cf6;">
        <div class="stat-value" style="color:#8b5cf6;"><?= $proxVenc ?></div>
        <div class="stat-label">Doc vencendo em 30 dias</div>
    </div>
</div>

<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px;">
    <div class="stat-card"><div class="stat-value"><?= $totalGeral ?></div><div class="stat-label">Total de protocolos</div></div>
    <div class="stat-card"><div class="stat-value" style="color:#3498db;"><?= $totalEnv ?></div><div class="stat-label">Aguardando confirmação</div></div>
    <div class="stat-card"><div class="stat-value" style="color:#00b279;"><?= $totalConf ?></div><div class="stat-label">Confirmados</div></div>
    <div class="stat-card"><div class="stat-value" style="color:#e74c3c;"><?= $totalRej ?></div><div class="stat-label">Rejeitados</div></div>
</div>

<!-- Mapa de calor -->
<div class="table-container" style="margin-bottom:24px;">
    <div class="table-header">
        <span class="table-title">Mapa de calor — Cliente × Tipo de Documento</span>
        <span style="font-size:11px; color:#6b7280;">Verde = tudo confirmado · Amarelo = pendente · Vermelho = atrasado</span>
    </div>
    <div style="overflow-x:auto;">
        <table style="font-size:12px;">
            <thead>
                <tr>
                    <th style="position:sticky; left:0; background:var(--c-bg); z-index:1;">Cliente</th>
                    <?php foreach ($tiposLista as $t): ?>
                    <th style="text-align:center; min-width:80px;"><?= htmlspecialchars($t) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($mapa)): ?>
                <tr><td colspan="<?= count($tiposLista)+1 ?>" style="text-align:center; color:#6b7280; padding:30px;">
                    Sem dados. Crie pendências para ver o mapa de calor.
                </td></tr>
                <?php else: foreach ($mapa as $cliente => $tipos): ?>
                <tr>
                    <td style="position:sticky; left:0; background:var(--c-bg); font-weight:600;"><?= htmlspecialchars($cliente) ?></td>
                    <?php foreach ($tiposLista as $t):
                        $cell = $tipos[$t] ?? null;
                        if (!$cell) {
                            echo '<td style="text-align:center; color:#d1d5db;">—</td>';
                            continue;
                        }
                        if ($cell['pendentes'] > 0) {
                            $bg = '#fef3c7'; $fg = '#92400e'; $tag = $cell['pendentes'].'P';
                        } elseif ($cell['enviados'] > 0) {
                            $bg = '#dbeafe'; $fg = '#1e40af'; $tag = $cell['enviados'].'E';
                        } elseif ($cell['confirmados'] > 0) {
                            $bg = '#d1fae5'; $fg = '#065f46'; $tag = '✓';
                        } else {
                            $bg = '#f3f4f6'; $fg = '#6b7280'; $tag = '—';
                        }
                    ?>
                    <td style="text-align:center; background:<?= $bg ?>; color:<?= $fg ?>; font-weight:600;" title="<?= $cell['total'] ?> total">
                        <?= $tag ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Top 10 + Gráfico mensal -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px;">
    <div class="table-container">
        <div class="table-header">
            <span class="table-title">Top 10 colaboradores com mais pendências</span>
        </div>
        <table>
            <thead><tr><th>#</th><th>Colaborador</th><th style="text-align:right;">Pendências</th></tr></thead>
            <tbody>
                <?php if (empty($topColab)): ?>
                <tr><td colspan="3" style="text-align:center; color:#6b7280; padding:20px;">Nenhum colaborador com pendência.</td></tr>
                <?php else: foreach ($topColab as $i => $c): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><a href="/colaboradores/<?= $c['id'] ?>" style="color:var(--c-primary);"><?= htmlspecialchars($c['nome_completo']) ?></a></td>
                    <td style="text-align:right; font-weight:600;"><?= $c['pendencias'] ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-container">
        <div class="table-header">
            <span class="table-title">Protocolos confirmados — últimos 6 meses</span>
        </div>
        <div style="padding:16px;">
            <?php if (empty($confMes)): ?>
            <div style="text-align:center; color:#6b7280; padding:40px;">Sem confirmações no período.</div>
            <?php else:
                $maxV = max(array_map(fn($r) => (int)$r['total'], $confMes));
                $maxV = $maxV > 0 ? $maxV : 1;
            ?>
            <div style="display:flex; align-items:flex-end; gap:8px; height:180px; padding-bottom:24px; border-bottom:1px solid var(--c-border);">
                <?php foreach ($confMes as $r):
                    $h = round(160 * (int)$r['total'] / $maxV);
                ?>
                <div style="flex:1; display:flex; flex-direction:column; align-items:center;">
                    <div style="font-size:11px; color:#6b7280;"><?= $r['total'] ?></div>
                    <div style="background:#00b279; width:80%; height:<?= $h ?>px; border-radius:3px 3px 0 0; margin-top:4px;"></div>
                    <div style="font-size:10px; color:#9ca3af; margin-top:6px;"><?= htmlspecialchars(substr($r['mes'],5).'/'.substr($r['mes'],2,2)) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
