<?php
$clienteNome = htmlspecialchars($obra['nome_fantasia'] ?? $obra['razao_social']);
$obraNome = htmlspecialchars($obra['nome']);
$obraLocal = htmlspecialchars($obra['local_obra'] ?? '-');
$obraStatus = ucfirst($obra['status'] ?? '-');

$corConf = $conformidadeGeral >= 80 ? '#00b279' : ($conformidadeGeral >= 50 ? '#f39c12' : '#e74c3c');
?>

<div style="margin-bottom:24px; display:flex; justify-content:space-between; align-items:center;">
    <div>
        <h2><?= $obraNome ?></h2>
        <span style="color:var(--c-gray);">Cliente: <?= $clienteNome ?> | Local: <?= $obraLocal ?> | Status: <?= $obraStatus ?></span>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="/relatorios/obra/<?= $obra['id'] ?>?format=excel" class="btn btn-primary btn-sm">Exportar Excel</a>
        <a href="/relatorios" class="btn btn-outline btn-sm">Voltar</a>
    </div>
</div>

<!-- Overall Compliance -->
<div class="cards-row" style="margin-bottom:24px;">
    <div class="card-stat" style="flex:0 0 280px; text-align:center; border-left:4px solid <?= $corConf ?>; background:#fff;">
        <div class="card-stat-value" style="font-size:2.5rem; color:<?= $corConf ?>;"><?= $conformidadeGeral ?>%</div>
        <div class="card-stat-label">Conformidade Geral</div>
        <div style="margin-top:12px; background:#e5e7eb; border-radius:6px; height:12px; overflow:hidden;">
            <div style="background:<?= $corConf ?>; height:100%; width:<?= min($conformidadeGeral, 100) ?>%; border-radius:6px; transition:width 0.5s;"></div>
        </div>
    </div>
    <div class="card-stat info" style="flex:0 0 180px;">
        <div class="card-stat-value"><?= count($colaboradores) ?></div>
        <div class="card-stat-label">Colaboradores na Obra</div>
    </div>
    <div class="card-stat" style="flex:0 0 180px; border-left:4px solid var(--c-primary); background:#fff;">
        <div class="card-stat-value" style="font-size:1.1rem; color:var(--c-primary);"><?= $totalReqDocs ?> docs / <?= $totalReqCerts ?> certs</div>
        <div class="card-stat-label">Requisitos do Cliente</div>
    </div>
</div>

<!-- Collaborator Compliance Table -->
<div class="table-container">
    <div class="table-header">
        <span class="table-title">Conformidade por Colaborador</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>Docs Em Dia</th>
                <th>Docs Vencidos</th>
                <th>Docs Faltantes</th>
                <th>Certs Em Dia</th>
                <th>Certs Vencidos</th>
                <th>% Conformidade</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($colaboradores)): ?>
            <tr><td colspan="7" style="text-align:center;color:#6b7280;">Nenhum colaborador alocado nesta obra</td></tr>
            <?php else: ?>
            <?php foreach ($colaboradores as $c):
                $pct = $c['conformidade'];
                if ($pct >= 80) {
                    $rowColor = 'background:rgba(174, 240, 133, 0.15);';
                    $badgeClass = 'badge-vigente';
                } elseif ($pct >= 50) {
                    $rowColor = 'background:rgba(243, 156, 18, 0.1);';
                    $badgeClass = 'badge-proximo';
                } else {
                    $rowColor = 'background:rgba(231, 76, 60, 0.1);';
                    $badgeClass = 'badge-vencido';
                }
            ?>
            <tr style="<?= $rowColor ?>">
                <td><a href="/colaboradores/<?= $c['id'] ?>"><?= htmlspecialchars($c['nome_completo']) ?></a></td>
                <td><span class="badge badge-vigente"><?= $c['docs_em_dia'] ?></span></td>
                <td><?php if ($c['docs_vencidos'] > 0): ?><span class="badge badge-vencido"><?= $c['docs_vencidos'] ?></span><?php else: ?>0<?php endif; ?></td>
                <td><?php if ($c['docs_faltantes'] > 0): ?><span class="badge badge-proximo"><?= $c['docs_faltantes'] ?></span><?php else: ?>0<?php endif; ?></td>
                <td><span class="badge badge-vigente"><?= $c['certs_em_dia'] ?></span></td>
                <td><?php if ($c['certs_vencidos'] > 0): ?><span class="badge badge-vencido"><?= $c['certs_vencidos'] ?></span><?php else: ?>0<?php endif; ?></td>
                <td><span class="badge <?= $badgeClass ?>"><?= $pct ?>%</span></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
