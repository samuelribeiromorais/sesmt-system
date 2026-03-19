<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <div>
        <h2 style="font-size:24px;"><?= htmlspecialchars($cliente['nome_fantasia'] ?? $cliente['razao_social']) ?></h2>
        <span style="color:var(--c-gray);"><?= htmlspecialchars($cliente['razao_social']) ?> | CNPJ: <?= htmlspecialchars($cliente['cnpj'] ?? '-') ?></span>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="/clientes/<?= $cliente['id'] ?>/editar" class="btn btn-outline btn-sm">Editar</a>
        <a href="/obras/novo/<?= $cliente['id'] ?>" class="btn btn-primary btn-sm">+ Nova Obra</a>
    </div>
</div>

<div class="table-container">
    <div class="table-header"><span class="table-title">Obras</span></div>
    <table>
        <thead><tr><th>Nome</th><th>Local</th><th>Inicio</th><th>Status</th><th>Acoes</th></tr></thead>
        <tbody>
        <?php if (empty($obras)): ?>
        <tr><td colspan="5" style="text-align:center;color:#6b7280;">Nenhuma obra cadastrada.</td></tr>
        <?php else: ?>
        <?php foreach ($obras as $o): ?>
        <tr>
            <td><?= htmlspecialchars($o['nome']) ?></td>
            <td><?= htmlspecialchars($o['local_obra'] ?? '-') ?></td>
            <td><?= $o['data_inicio'] ? date('d/m/Y', strtotime($o['data_inicio'])) : '-' ?></td>
            <td><span class="badge badge-<?= $o['status'] === 'ativa' ? 'ativo' : ($o['status'] === 'suspensa' ? 'afastado' : 'inativo') ?>"><?= ucfirst($o['status']) ?></span></td>
            <td><a href="/obras/<?= $o['id'] ?>/editar" class="btn btn-outline btn-sm">Editar</a></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($cliente['contato_nome'] || $cliente['contato_email']): ?>
<div class="table-container" style="margin-top:24px;">
    <div class="table-header"><span class="table-title">Contato</span></div>
    <div style="padding:20px;">
        <p><strong>Nome:</strong> <?= htmlspecialchars($cliente['contato_nome'] ?? '-') ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($cliente['contato_email'] ?? '-') ?></p>
        <p><strong>Telefone:</strong> <?= htmlspecialchars($cliente['contato_telefone'] ?? '-') ?></p>
    </div>
</div>
<?php endif; ?>
