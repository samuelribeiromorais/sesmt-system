<?php
$totalAtivos   = ($colaboradores_status['ativo'] ?? 0);
$totalInativos = ($colaboradores_status['inativo'] ?? 0) + ($colaboradores_status['afastado'] ?? 0);

$docsVigentes = ($documentos_status['vigente'] ?? 0);
$docsProximos = ($documentos_status['proximo_vencimento'] ?? 0);
$docsVencidos = ($documentos_status['vencido'] ?? 0);

$certsVigentes = ($certificados_status['vigente'] ?? 0);
$certsProximos = ($certificados_status['proximo_vencimento'] ?? 0);
$certsVencidos = ($certificados_status['vencido'] ?? 0);

// Prepare chart data from PHP variables
$chartStatusLabels = json_encode(['Vigente', 'Proximo Vencimento', 'Vencido']);
$chartStatusData   = json_encode([$docsVigentes + $certsVigentes, $docsProximos + $certsProximos, $docsVencidos + $certsVencidos]);
$chartStatusColors = json_encode(['#afd85a', '#f39c12', '#e74c3c']);

$clientLabels  = [];
$clientTotals  = [];
$clientVencidos = [];
if (!empty($docs_by_client)) {
    foreach ($docs_by_client as $row) {
        $clientLabels[]  = $row['nome_fantasia'];
        $clientTotals[]  = (int)$row['total'];
        $clientVencidos[] = (int)$row['vencidos'];
    }
}
$chartClientLabels  = json_encode($clientLabels);
$chartClientTotals  = json_encode($clientTotals);
$chartClientVencidos = json_encode($clientVencidos);

// Organize docs by category for display
$categorias = [];
if (!empty($docs_by_category)) {
    foreach ($docs_by_category as $row) {
        $cat = $row['categoria'];
        if (!isset($categorias[$cat])) {
            $categorias[$cat] = ['vigente' => 0, 'proximo_vencimento' => 0, 'vencido' => 0];
        }
        $categorias[$cat][$row['status']] = (int)$row['total'];
    }
}
$categoriaNomes = [
    'aso'          => 'ASO',
    'epi'          => 'EPI',
    'os'           => 'Ordem de Servico',
    'treinamento'  => 'Treinamento',
];
?>

<!-- ============ STAT CARDS ============ -->
<div class="cards-row">
    <div class="card-stat info">
        <div class="card-stat-value"><?= $totalAtivos ?></div>
        <div class="card-stat-label">Colaboradores Ativos</div>
    </div>
    <div class="card-stat danger">
        <div class="card-stat-value"><?= $docsVencidos + $certsVencidos ?></div>
        <div class="card-stat-label">Documentos/Certs Vencidos</div>
    </div>
    <div class="card-stat warning">
        <div class="card-stat-value"><?= $docsProximos + $certsProximos ?></div>
        <div class="card-stat-label">Vencendo em 30 dias</div>
    </div>
    <div class="card-stat ok">
        <div class="card-stat-value"><?= $docsVigentes + $certsVigentes ?></div>
        <div class="card-stat-label">Em dia</div>
    </div>
</div>

<!-- ============ CATEGORY CARDS + MISSING DOCS ============ -->
<div class="cards-row" style="margin-top: 16px;">
    <?php foreach ($categoriaNomes as $key => $label): ?>
    <?php $cat = $categorias[$key] ?? ['vigente' => 0, 'proximo_vencimento' => 0, 'vencido' => 0]; ?>
    <div class="card-stat" style="border-left: 4px solid var(--c-primary); background: #fff;">
        <div class="card-stat-value" style="font-size: 1.1rem; color: var(--c-primary);"><?= $label ?></div>
        <div class="card-stat-label" style="margin-top: 8px;">
            <span class="badge badge-vigente"><?= $cat['vigente'] ?> vigentes</span>
            <span class="badge badge-proximo"><?= $cat['proximo_vencimento'] ?> vencendo</span>
            <span class="badge badge-vencido"><?= $cat['vencido'] ?> vencidos</span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (!empty($missing_docs_count) && $missing_docs_count > 0): ?>
<div class="cards-row" style="margin-top: 16px;">
    <div class="card-stat warning" style="flex: 0 0 auto; padding: 16px 32px;">
        <div class="card-stat-value"><?= (int)$missing_docs_count ?></div>
        <div class="card-stat-label">Colaboradores ativos sem categorias obrigatorias</div>
    </div>
</div>
<?php endif; ?>

<!-- ============ CHARTS ============ -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 24px;">
    <!-- Doughnut: Document status distribution -->
    <div class="table-container">
        <div class="table-header">
            <span class="table-title">Distribuicao por Status</span>
        </div>
        <div style="padding: 24px; display: flex; justify-content: center;">
            <canvas id="chartStatus" style="max-width: 320px; max-height: 320px;"></canvas>
        </div>
    </div>

    <!-- Bar: Documents by client -->
    <div class="table-container">
        <div class="table-header">
            <span class="table-title">Documentos por Cliente (Top 10)</span>
        </div>
        <div style="padding: 24px;">
            <canvas id="chartClients" style="max-height: 320px;"></canvas>
        </div>
    </div>
</div>

<!-- ============ EXPIRING TABLES (side by side) ============ -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 24px;">
    <!-- Documentos vencendo -->
    <div class="table-container">
        <div class="table-header">
            <span class="table-title">Documentos Vencendo</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Colaborador</th>
                    <th>Documento</th>
                    <th>Vence em</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($docs_expiring)): ?>
                <tr><td colspan="3" style="text-align:center;color:#6b7280;">Nenhum documento proximo do vencimento</td></tr>
                <?php else: ?>
                <?php foreach ($docs_expiring as $doc): ?>
                <tr>
                    <td><a href="/colaboradores/<?= $doc['colaborador_id'] ?>"><?= htmlspecialchars($doc['nome_completo']) ?></a></td>
                    <td><?= htmlspecialchars($doc['tipo_nome']) ?></td>
                    <td>
                        <?php $dias = $doc['dias_restantes']; ?>
                        <span class="badge <?= $dias <= 7 ? 'badge-vencido' : 'badge-proximo' ?>">
                            <?= $dias ?> dia<?= $dias != 1 ? 's' : '' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Certificados vencendo -->
    <div class="table-container">
        <div class="table-header">
            <span class="table-title">Certificados Vencendo</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Colaborador</th>
                    <th>Certificado</th>
                    <th>Vence em</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($certs_expiring)): ?>
                <tr><td colspan="3" style="text-align:center;color:#6b7280;">Nenhum certificado proximo do vencimento</td></tr>
                <?php else: ?>
                <?php foreach ($certs_expiring as $cert): ?>
                <tr>
                    <td><a href="/colaboradores/<?= $cert['colaborador_id'] ?>"><?= htmlspecialchars($cert['nome_completo']) ?></a></td>
                    <td><?= htmlspecialchars($cert['codigo']) ?></td>
                    <td>
                        <?php $dias = $cert['dias_restantes']; ?>
                        <span class="badge <?= $dias <= 7 ? 'badge-vencido' : 'badge-proximo' ?>">
                            <?= $dias ?> dia<?= $dias != 1 ? 's' : '' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============ TOP PENDENTES TABLE ============ -->
<?php if (!empty($top_pendentes)): ?>
<div class="table-container" style="margin-top: 24px;">
    <div class="table-header">
        <span class="table-title" style="color: var(--c-warning);">Colaboradores com Mais Pendencias</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Colaborador</th>
                <th>Docs Pendentes</th>
                <th>Certs Pendentes</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($top_pendentes as $row): ?>
            <tr>
                <td><a href="/colaboradores/<?= $row['colaborador_id'] ?>"><?= htmlspecialchars($row['nome_completo']) ?></a></td>
                <td><span class="badge badge-vencido"><?= (int)$row['docs_pendentes'] ?></span></td>
                <td><span class="badge badge-proximo"><?= (int)$row['certs_pendentes'] ?></span></td>
                <td><strong><?= (int)$row['total_pendentes'] ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ============ EXPIRED DOCS TABLE ============ -->
<?php if (!empty($docs_expired)): ?>
<div class="table-container" style="margin-top: 24px;">
    <div class="table-header">
        <span class="table-title" style="color: var(--c-danger);">Documentos Vencidos</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Colaborador</th>
                <th>Documento</th>
                <th>Vencido ha</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($docs_expired as $doc): ?>
            <tr>
                <td><a href="/colaboradores/<?= $doc['colaborador_id'] ?>"><?= htmlspecialchars($doc['nome_completo']) ?></a></td>
                <td><?= htmlspecialchars($doc['tipo_nome']) ?></td>
                <td><span class="badge badge-vencido"><?= $doc['dias_vencido'] ?> dias</span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ============ CHART.JS ============ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- Doughnut: status distribution ---
    new Chart(document.getElementById('chartStatus'), {
        type: 'doughnut',
        data: {
            labels: <?= $chartStatusLabels ?>,
            datasets: [{
                data: <?= $chartStatusData ?>,
                backgroundColor: <?= $chartStatusColors ?>,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 16, usePointStyle: true }
                }
            }
        }
    });

    // --- Bar: documents by client ---
    new Chart(document.getElementById('chartClients'), {
        type: 'bar',
        data: {
            labels: <?= $chartClientLabels ?>,
            datasets: [
                {
                    label: 'Total',
                    data: <?= $chartClientTotals ?>,
                    backgroundColor: 'rgba(0, 178, 121, 0.7)',
                    borderColor: '#00b279',
                    borderWidth: 1
                },
                {
                    label: 'Vencidos',
                    data: <?= $chartClientVencidos ?>,
                    backgroundColor: 'rgba(231, 76, 60, 0.7)',
                    borderColor: '#e74c3c',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                x: {
                    ticks: {
                        maxRotation: 45,
                        minRotation: 25,
                        font: { size: 11 }
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 16, usePointStyle: true }
                }
            }
        }
    });
});
</script>
