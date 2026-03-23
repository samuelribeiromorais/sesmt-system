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
    'treinamento'  => 'Treinamento',
];
?>

<!-- ============ STAT CARDS ============ -->
<div class="cards-row">
    <a href="/colaboradores?status=ativo" class="card-stat info stat-card-clickable" style="text-decoration:none; color:inherit;">
        <div class="card-stat-value"><?= $totalAtivos ?></div>
        <div class="card-stat-label">Colaboradores Ativos</div>
    </a>
    <a href="/documentos?status=vencido" class="card-stat danger stat-card-clickable" style="text-decoration:none; color:inherit;">
        <div class="card-stat-value"><?= $docsVencidos + $certsVencidos ?></div>
        <div class="card-stat-label">Documentos/Certs Vencidos</div>
    </a>
    <a href="/documentos?status=proximo_vencimento" class="card-stat warning stat-card-clickable" style="text-decoration:none; color:inherit;">
        <div class="card-stat-value"><?= $docsProximos + $certsProximos ?></div>
        <div class="card-stat-label">Vencendo em 30 dias</div>
    </a>
    <a href="/documentos?status=vigente" class="card-stat ok stat-card-clickable" style="text-decoration:none; color:inherit;">
        <div class="card-stat-value"><?= $docsVigentes + $certsVigentes ?></div>
        <div class="card-stat-label">Em dia</div>
    </a>
</div>

<!-- ============ CATEGORY CARDS + MISSING DOCS ============ -->
<div class="cards-row" style="margin-top: 16px;">
    <?php foreach ($categoriaNomes as $key => $label): ?>
    <?php $cat = $categorias[$key] ?? ['vigente' => 0, 'proximo_vencimento' => 0, 'vencido' => 0]; ?>
    <a href="/documentos?categoria=<?= $key ?>" class="card-stat stat-card-clickable" style="border-left: 4px solid var(--c-primary); background: #fff; text-decoration:none; color:inherit;">
        <div class="card-stat-value" style="font-size: 1.1rem; color: var(--c-primary);"><?= $label ?></div>
        <div class="card-stat-label" style="margin-top: 8px;">
            <span class="badge badge-vigente"><?= $cat['vigente'] ?> vigentes</span>
            <span class="badge badge-proximo"><?= $cat['proximo_vencimento'] ?> vencendo</span>
            <span class="badge badge-vencido"><?= $cat['vencido'] ?> vencidos</span>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<div class="cards-row" style="margin-top: 16px;">
    <?php if (!empty($colab_sem_docs_count) && $colab_sem_docs_count > 0): ?>
    <a href="#colab-sem-docs" class="card-stat danger stat-card-clickable" style="flex: 0 0 auto; padding: 16px 32px; text-decoration:none; color:inherit;" onclick="document.getElementById('colab-sem-docs').scrollIntoView({behavior:'smooth'}); return false;">
        <div class="card-stat-value"><?= (int)$colab_sem_docs_count ?></div>
        <div class="card-stat-label">Colaboradores ativos sem nenhum documento</div>
    </a>
    <?php endif; ?>
    <?php if (!empty($missing_docs_count) && $missing_docs_count > 0): ?>
    <a href="/alertas" class="card-stat warning stat-card-clickable" style="flex: 0 0 auto; padding: 16px 32px; text-decoration:none; color:inherit;">
        <div class="card-stat-value"><?= (int)$missing_docs_count ?></div>
        <div class="card-stat-label">Colaboradores ativos sem categorias obrigatorias</div>
    </a>
    <?php endif; ?>
    <?php $totalPendentes = $total_aprovacoes_pendentes ?? 0; if ($totalPendentes > 0): ?>
    <a href="#aprovacoes-pendentes" class="card-stat stat-card-clickable" style="flex: 0 0 auto; padding: 16px 32px; text-decoration:none; color:inherit; border-left: 4px solid #8b5cf6;" onclick="document.getElementById('aprovacoes-pendentes').scrollIntoView({behavior:'smooth'}); return false;">
        <div class="card-stat-value" style="color:#8b5cf6;"><?= number_format($totalPendentes, 0, ',', '.') ?></div>
        <div class="card-stat-label">Aprovacoes pendentes</div>
    </a>
    <?php endif; ?>
</div>

<!-- ============ CHARTS (Pizza + Barras) ============ -->
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

<!-- ============ KPI INDICATORS ============ -->
<?php
$kpiConformidade = $kpi_conformidade_atual ?? 0;
$kpiTempoRenov = $kpi_tempo_renovacao ?? 'N/A';
$kpiDocsVencAtivos = $kpi_docs_vencidos_ativos ?? 0;

$confColor = $kpiConformidade >= 80 ? '#00b279' : ($kpiConformidade >= 50 ? '#f39c12' : '#e74c3c');
$tempoIsNum = is_numeric($kpiTempoRenov);
$tempoColor = !$tempoIsNum ? '#6b7280' : ($kpiTempoRenov <= 15 ? '#00b279' : ($kpiTempoRenov <= 30 ? '#f39c12' : '#e74c3c'));

$vencLabels = [];
$vencData = [];
if (!empty($kpi_tendencia_vencimentos)) {
    foreach ($kpi_tendencia_vencimentos as $row) {
        $vencLabels[] = $row['mes_label'];
        $vencData[] = (int)$row['total'];
    }
}
$jsonVencLabels = json_encode($vencLabels);
$jsonVencData = json_encode($vencData);
?>

<div style="margin-top:24px; margin-bottom:8px;">
    <h3 style="color:var(--c-primary); font-size:16px; font-weight:600; margin:0;">Indicadores KPI (Colaboradores Ativos)</h3>
</div>

<div class="cards-row" style="margin-top:8px;">
    <a href="/colaboradores?status=ativo" class="card-stat stat-card-clickable" style="flex:1; border-left:4px solid <?= $confColor ?>; background:#fff; text-align:center; text-decoration:none; color:inherit;">
        <div class="card-stat-value" style="font-size:2rem; color:<?= $confColor ?>;"><?= $kpiConformidade ?>%</div>
        <div class="card-stat-label">Taxa de Conformidade</div>
        <div style="margin-top:6px; font-size:12px; color:#6b7280;">Colaboradores sem docs vencidos</div>
    </a>

    <a href="/documentos?status=vencido" class="card-stat stat-card-clickable" style="flex:1; border-left:4px solid #e74c3c; background:#fff; text-align:center; text-decoration:none; color:inherit;">
        <div class="card-stat-value" style="font-size:2rem; color:#e74c3c;"><?= $kpiDocsVencAtivos ?></div>
        <div class="card-stat-label">Docs Vencidos (Ativos)</div>
        <div style="margin-top:6px; font-size:12px; color:#6b7280;">Requerem acao imediata</div>
    </a>

    <div class="card-stat" style="flex:1; border-left:4px solid <?= $tempoColor ?>; background:#fff; text-align:center;">
        <div class="card-stat-value" style="font-size:2rem; color:<?= $tempoColor ?>;"><?= $tempoIsNum ? $kpiTempoRenov . ' dias' : $kpiTempoRenov ?></div>
        <div class="card-stat-label">Tempo Medio Renovacao</div>
        <div style="margin-top:6px; font-size:12px; color:#6b7280;">Ultimos 12 meses</div>
    </div>
</div>

<!-- KPI Chart: Upcoming Expirations -->
<div style="margin-top: 16px;">
    <div class="table-container">
        <div class="table-header">
            <span class="table-title">Vencimentos Previstos - Proximos 6 Meses (Apenas Ativos)</span>
        </div>
        <div style="padding: 24px;">
            <canvas id="chartKpiVencimentos" style="max-height: 280px;"></canvas>
        </div>
    </div>
</div>

<!-- ============ COLABORADORES SEM DOCUMENTOS ============ -->
<?php if (!empty($colab_sem_docs) && count($colab_sem_docs) > 0): ?>
<div class="table-container" style="margin-top: 24px;" id="colab-sem-docs">
    <div class="table-header">
        <span class="table-title" style="color: var(--c-danger);">
            Colaboradores Ativos Sem Nenhum Documento (<?= count($colab_sem_docs) ?>)
        </span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Colaborador</th>
                <th>Cargo</th>
                <th>Cliente</th>
                <th width="100">Acao</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($colab_sem_docs as $cs): ?>
            <tr>
                <td><a href="/colaboradores/<?= $cs['id'] ?>"><?= htmlspecialchars($cs['nome_completo']) ?></a></td>
                <td><?= htmlspecialchars($cs['cargo'] ?? '—') ?></td>
                <td><?= htmlspecialchars($cs['cliente_nome'] ?? '—') ?></td>
                <td>
                    <a href="/colaboradores/<?= $cs['id'] ?>" class="btn btn-primary btn-sm">Enviar Docs</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ============ APROVACOES PENDENTES ============ -->
<?php if (!empty($aprovacoes_pendentes)): ?>
<div id="aprovacoes-pendentes" class="table-container" style="margin-top:24px; border-left: 4px solid #8b5cf6;">
    <div class="table-header">
        <span class="table-title" style="color:#8b5cf6;">Aprovacoes Pendentes (<?= number_format($total_aprovacoes_pendentes ?? 0, 0, ',', '.') ?> total - mostrando 20 mais recentes)</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Colaborador</th>
                <th>Documento</th>
                <th>Emissao</th>
                <th>Enviado em</th>
                <th style="text-align:center;">Acao</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($aprovacoes_pendentes as $ap): ?>
            <tr>
                <td><a href="/colaboradores/<?= $ap['colaborador_id'] ?>" style="color:var(--c-accent);"><?= htmlspecialchars($ap['nome_completo']) ?></a></td>
                <td style="font-size:13px;"><?= htmlspecialchars($ap['tipo_nome']) ?></td>
                <td style="font-size:13px;"><?= $ap['data_emissao'] ? date('d/m/Y', strtotime($ap['data_emissao'])) : '-' ?></td>
                <td style="font-size:13px;"><?= date('d/m/Y', strtotime($ap['criado_em'])) ?></td>
                <td style="text-align:center;">
                    <form method="POST" action="/documentos/<?= $ap['id'] ?>/aprovar" style="display:inline-flex; gap:4px;">
                        <?= \App\Core\View::csrfField() ?>
                        <input type="hidden" name="decisao" value="aprovado">
                        <button type="submit" class="btn btn-sm" style="padding:2px 10px; font-size:11px; background:#059669; color:white;">Aprovar</button>
                    </form>
                    <button type="button" class="btn btn-sm" style="padding:2px 10px; font-size:11px; background:#dc2626; color:white;"
                            onclick="rejeitarDoc(<?= $ap['id'] ?>)">Rejeitar</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal de rejeicao -->
<div id="rejeitar-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
    <div style="background:white; border-radius:8px; padding:24px; width:400px; max-width:90vw;">
        <h3 style="margin-bottom:12px; color:#dc2626;">Rejeitar Documento</h3>
        <form method="POST" id="rejeitar-form">
            <?= \App\Core\View::csrfField() ?>
            <input type="hidden" name="decisao" value="rejeitado">
            <label style="font-size:13px; font-weight:600;">Motivo da rejeicao:</label>
            <textarea name="aprovacao_obs" rows="3" style="width:100%; margin-top:6px; padding:8px; border:1px solid #ddd; border-radius:4px; font-size:13px;" placeholder="Ex: Documento ilegivel, data incorreta..." required></textarea>
            <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:16px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('rejeitar-modal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn btn-sm" style="background:#dc2626; color:white;">Confirmar Rejeicao</button>
            </div>
        </form>
    </div>
</div>
<script>
function rejeitarDoc(docId) {
    const modal = document.getElementById('rejeitar-modal');
    const form = document.getElementById('rejeitar-form');
    form.action = '/documentos/' + docId + '/aprovar';
    modal.style.display = 'flex';
}
</script>
<?php endif; ?>

<!-- ============ VENCENDO ESTA SEMANA ============ -->
<?php if (!empty($vencendo_esta_semana)): ?>
<div class="table-container" style="margin-top:24px; border-left: 4px solid #f39c12;">
    <div class="table-header">
        <span class="table-title" style="color:#f39c12;">Vencendo esta semana (<?= count($vencendo_esta_semana) ?>)</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Colaborador</th>
                <th>Tipo</th>
                <th>Item</th>
                <th style="text-align:center;">Vence em</th>
                <th style="text-align:center;">Dias</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($vencendo_esta_semana as $item): ?>
            <tr>
                <td><a href="/colaboradores/<?= $item['colaborador_id'] ?>" style="color:var(--c-accent);"><?= htmlspecialchars($item['nome_completo']) ?></a></td>
                <td><span class="badge badge-<?= $item['tipo'] === 'documento' ? 'vigente' : 'proximo_vencimento' ?>"><?= $item['tipo'] === 'documento' ? 'Doc' : 'Cert' ?></span></td>
                <td style="font-size:13px;"><?= htmlspecialchars($item['item_nome']) ?></td>
                <td style="text-align:center; font-size:13px;"><?= date('d/m/Y', strtotime($item['data_validade'])) ?></td>
                <td style="text-align:center;">
                    <span style="font-weight:600; color:<?= $item['dias_restantes'] <= 2 ? '#e74c3c' : '#f39c12' ?>;">
                        <?= $item['dias_restantes'] ?> dia<?= $item['dias_restantes'] != 1 ? 's' : '' ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

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

    // --- Bar: KPI Upcoming Expirations ---
    new Chart(document.getElementById('chartKpiVencimentos'), {
        type: 'bar',
        data: {
            labels: <?= $jsonVencLabels ?>,
            datasets: [{
                label: 'Vencimentos',
                data: <?= $jsonVencData ?>,
                backgroundColor: function(ctx) {
                    var v = ctx.parsed?.y || 0;
                    if (v > 20) return 'rgba(231, 76, 60, 0.7)';
                    if (v > 10) return 'rgba(243, 156, 18, 0.7)';
                    return 'rgba(0, 178, 121, 0.7)';
                },
                borderColor: function(ctx) {
                    var v = ctx.parsed?.y || 0;
                    if (v > 20) return '#e74c3c';
                    if (v > 10) return '#f39c12';
                    return '#00b279';
                },
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            },
            plugins: {
                legend: { display: false }
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
