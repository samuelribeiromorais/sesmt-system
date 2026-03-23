<?php
$mesAnterior = $mes - 1; $anoAnterior = $ano;
if ($mesAnterior < 1) { $mesAnterior = 12; $anoAnterior--; }
$mesSeguinte = $mes + 1; $anoSeguinte = $ano;
if ($mesSeguinte > 12) { $mesSeguinte = 1; $anoSeguinte++; }
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <h2>Agenda de Exames Periodicos</h2>
    <div style="display:flex; gap:8px; align-items:center;">
        <a href="/agenda-exames?mes=<?= $mesAnterior ?>&ano=<?= $anoAnterior ?>" class="btn btn-outline btn-sm">&laquo;</a>
        <span style="font-size:18px; font-weight:600; min-width:180px; text-align:center;"><?= $meses[$mes] ?> <?= $ano ?></span>
        <a href="/agenda-exames?mes=<?= $mesSeguinte ?>&ano=<?= $anoSeguinte ?>" class="btn btn-outline btn-sm">&raquo;</a>
    </div>
</div>

<!-- Resumo próximos 12 meses -->
<div style="display:grid; grid-template-columns:repeat(6, 1fr); gap:8px; margin-bottom:24px;">
    <?php foreach ($resumoMeses as $rm): ?>
    <a href="/agenda-exames?mes=<?= $rm['mes'] ?>&ano=<?= $rm['ano'] ?>"
       class="table-container" style="text-decoration:none; color:inherit; padding:12px; text-align:center;
              <?= ($rm['mes'] == $mes && $rm['ano'] == $ano) ? 'border:2px solid var(--c-primary); background:rgba(0,94,78,0.05);' : '' ?>">
        <div style="font-size:12px; color:var(--c-gray);"><?= substr($meses[$rm['mes']], 0, 3) ?>/<?= substr($rm['ano'], 2) ?></div>
        <div style="font-size:24px; font-weight:700; color:<?= $rm['total'] > 0 ? ($rm['total'] > 20 ? '#e53935' : '#ff9800') : '#4caf50' ?>;">
            <?= $rm['total'] ?>
        </div>
        <div style="font-size:10px; color:var(--c-gray);">exames</div>
    </a>
    <?php endforeach; ?>
</div>

<!-- ASOs vencidos (urgente) -->
<?php if (!empty($vencidos)): ?>
<div class="table-container" style="margin-bottom:24px; border-left:5px solid #e53935;">
    <div class="table-header">
        <span class="table-title" style="color:#e53935;">ASOs Vencidos (<?= count($vencidos) ?>)</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Colaborador</th>
                <th>Cargo</th>
                <th>Setor</th>
                <th style="text-align:center;">Venceu em</th>
                <th style="text-align:center;">Dias vencido</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($vencidos as $v): ?>
            <tr>
                <td><a href="/colaboradores/<?= $v['id'] ?>" style="color:var(--c-primary); font-weight:600;"><?= htmlspecialchars($v['nome_completo']) ?></a></td>
                <td style="font-size:13px;"><?= htmlspecialchars($v['cargo'] ?? $v['funcao'] ?? '-') ?></td>
                <td style="font-size:13px;"><?= htmlspecialchars($v['setor'] ?? '-') ?></td>
                <td style="text-align:center; font-size:13px;"><?= date('d/m/Y', strtotime($v['data_validade'])) ?></td>
                <td style="text-align:center;"><span style="color:#e53935; font-weight:600;"><?= $v['dias_vencido'] ?> dias</span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Exames do mês selecionado -->
<div class="table-container">
    <div class="table-header">
        <span class="table-title">Exames vencendo em <?= $meses[$mes] ?> <?= $ano ?> (<?= count($examesMes) ?>)</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Colaborador</th>
                <th>Cargo</th>
                <th>Setor</th>
                <th>Tipo ASO</th>
                <th style="text-align:center;">Ultimo Exame</th>
                <th style="text-align:center;">Vence em</th>
                <th style="text-align:center;">Dias</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($examesMes)): ?>
            <tr><td colspan="7" style="text-align:center; color:var(--c-gray); padding:32px;">Nenhum exame vencendo em <?= $meses[$mes] ?> <?= $ano ?>.</td></tr>
            <?php else: ?>
            <?php foreach ($examesMes as $e): ?>
            <tr style="<?= $e['dias_restantes'] < 0 ? 'background:#fff5f5;' : ($e['dias_restantes'] <= 7 ? 'background:#fff8e1;' : '') ?>">
                <td><a href="/colaboradores/<?= $e['id'] ?>" style="color:var(--c-primary); font-weight:600;"><?= htmlspecialchars($e['nome_completo']) ?></a></td>
                <td style="font-size:13px;"><?= htmlspecialchars($e['cargo'] ?? $e['funcao'] ?? '-') ?></td>
                <td style="font-size:13px;"><?= htmlspecialchars($e['setor'] ?? '-') ?></td>
                <td style="font-size:13px;"><?= htmlspecialchars($e['tipo_aso']) ?></td>
                <td style="text-align:center; font-size:13px;"><?= date('d/m/Y', strtotime($e['data_emissao'])) ?></td>
                <td style="text-align:center; font-size:13px; font-weight:600;"><?= date('d/m/Y', strtotime($e['data_validade'])) ?></td>
                <td style="text-align:center;">
                    <?php if ($e['dias_restantes'] < 0): ?>
                        <span style="color:#e53935; font-weight:600;">Vencido</span>
                    <?php elseif ($e['dias_restantes'] <= 7): ?>
                        <span style="color:#ff9800; font-weight:600;"><?= $e['dias_restantes'] ?> dias</span>
                    <?php else: ?>
                        <span style="color:#4caf50;"><?= $e['dias_restantes'] ?> dias</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
