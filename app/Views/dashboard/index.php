<?php
$totalAtivos = ($colaboradores_status['ativo'] ?? 0);
$totalInativos = ($colaboradores_status['inativo'] ?? 0) + ($colaboradores_status['afastado'] ?? 0);

$docsVigentes = ($documentos_status['vigente'] ?? 0);
$docsProximos = ($documentos_status['proximo_vencimento'] ?? 0);
$docsVencidos = ($documentos_status['vencido'] ?? 0);

$certsVigentes = ($certificados_status['vigente'] ?? 0);
$certsProximos = ($certificados_status['proximo_vencimento'] ?? 0);
$certsVencidos = ($certificados_status['vencido'] ?? 0);
?>

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

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
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

<!-- Documentos vencidos -->
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
