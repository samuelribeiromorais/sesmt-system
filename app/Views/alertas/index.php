<div class="filter-bar" style="display:flex; gap:1rem; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap;">
    <form method="GET" action="/alertas" style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
        <div>
            <label for="filter-cliente" style="font-size:0.85rem; font-weight:600; margin-right:0.25rem;">Cliente:</label>
            <select name="cliente" id="filter-cliente" onchange="this.form.submit()" style="padding:0.4rem 0.6rem; border:1px solid #d1d5db; border-radius:6px; font-size:0.9rem;">
                <option value="">Todos</option>
                <?php foreach ($clientes as $cl): ?>
                <option value="<?= $cl['id'] ?>" <?= ($clienteFilter == $cl['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cl['nome_fantasia'] ?? $cl['razao_social']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="filter-tipo" style="font-size:0.85rem; font-weight:600; margin-right:0.25rem;">Tipo:</label>
            <select name="tipo" id="filter-tipo" onchange="this.form.submit()" style="padding:0.4rem 0.6rem; border:1px solid #d1d5db; border-radius:6px; font-size:0.9rem;">
                <option value="">Todos</option>
                <option value="docs_vencidos" <?= ($tipoFilter === 'docs_vencidos') ? 'selected' : '' ?>>Docs Vencidos</option>
                <option value="docs_vencendo" <?= ($tipoFilter === 'docs_vencendo') ? 'selected' : '' ?>>Docs Vencendo</option>
                <option value="certs_vencendo" <?= ($tipoFilter === 'certs_vencendo') ? 'selected' : '' ?>>Certs Vencendo</option>
            </select>
        </div>
        <?php if ($clienteFilter !== '' || $tipoFilter !== ''): ?>
        <a href="/alertas" class="btn btn-outline btn-sm" style="font-size:0.85rem;">Limpar</a>
        <?php endif; ?>
    </form>
</div>

<div class="cards-row">
    <a href="#docs-vencidos" class="card-stat danger stat-card-clickable" style="text-decoration:none; color:inherit;">
        <div class="card-stat-value"><?= count($docs_vencidos) ?></div>
        <div class="card-stat-label">Documentos Vencidos</div>
    </a>
    <a href="#docs-vencendo" class="card-stat warning stat-card-clickable" style="text-decoration:none; color:inherit;">
        <div class="card-stat-value"><?= count($docs_expiring) ?></div>
        <div class="card-stat-label">Documentos Vencendo (30 dias)</div>
    </a>
    <a href="#certs-vencendo" class="card-stat warning stat-card-clickable" style="text-decoration:none; color:inherit;">
        <div class="card-stat-value"><?= count($certs_expiring) ?></div>
        <div class="card-stat-label">Certificados Vencendo (30 dias)</div>
    </a>
</div>

<?php if (!empty($docs_vencidos)): ?>
<div id="docs-vencidos" class="table-container">
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
<div id="docs-vencendo" class="table-container">
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
<div id="certs-vencendo" class="table-container">
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
