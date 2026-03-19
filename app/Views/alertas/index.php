<div class="cards-row">
    <div class="card-stat danger">
        <div class="card-stat-value"><?= count($docs_vencidos) ?></div>
        <div class="card-stat-label">Documentos Vencidos</div>
    </div>
    <div class="card-stat warning">
        <div class="card-stat-value"><?= count($docs_expiring) ?></div>
        <div class="card-stat-label">Documentos Vencendo (30 dias)</div>
    </div>
    <div class="card-stat warning">
        <div class="card-stat-value"><?= count($certs_expiring) ?></div>
        <div class="card-stat-label">Certificados Vencendo (30 dias)</div>
    </div>
</div>

<?php if (!empty($docs_vencidos)): ?>
<div class="table-container">
    <div class="table-header">
        <span class="table-title" style="color:var(--c-danger);">Documentos Vencidos</span>
    </div>
    <table>
        <thead><tr><th>Colaborador</th><th>Documento</th><th>Vencido ha</th></tr></thead>
        <tbody>
        <?php foreach ($docs_vencidos as $d): ?>
        <tr>
            <td><a href="/colaboradores/<?= $d['colaborador_id'] ?>"><?= htmlspecialchars($d['nome_completo']) ?></a></td>
            <td><?= htmlspecialchars($d['tipo_nome']) ?></td>
            <td><span class="badge badge-vencido"><?= $d['dias_vencido'] ?> dias</span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($docs_expiring)): ?>
<div class="table-container">
    <div class="table-header">
        <span class="table-title" style="color:var(--c-warning);">Documentos Vencendo em 30 dias</span>
    </div>
    <table>
        <thead><tr><th>Colaborador</th><th>Documento</th><th>Vence em</th></tr></thead>
        <tbody>
        <?php foreach ($docs_expiring as $d): ?>
        <tr>
            <td><a href="/colaboradores/<?= $d['colaborador_id'] ?>"><?= htmlspecialchars($d['nome_completo']) ?></a></td>
            <td><?= htmlspecialchars($d['tipo_nome']) ?></td>
            <td><span class="badge badge-proximo"><?= $d['dias_restantes'] ?> dias</span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($certs_expiring)): ?>
<div class="table-container">
    <div class="table-header">
        <span class="table-title" style="color:var(--c-warning);">Certificados Vencendo em 30 dias</span>
    </div>
    <table>
        <thead><tr><th>Colaborador</th><th>Certificado</th><th>Vence em</th></tr></thead>
        <tbody>
        <?php foreach ($certs_expiring as $c): ?>
        <tr>
            <td><a href="/colaboradores/<?= $c['colaborador_id'] ?>"><?= htmlspecialchars($c['nome_completo']) ?></a></td>
            <td><?= htmlspecialchars($c['codigo']) ?></td>
            <td><span class="badge badge-proximo"><?= $c['dias_restantes'] ?> dias</span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
