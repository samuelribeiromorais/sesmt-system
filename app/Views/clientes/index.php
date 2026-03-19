<div class="table-container">
    <div class="table-header">
        <span class="table-title">Clientes</span>
        <a href="/clientes/novo" class="btn btn-primary btn-sm">+ Novo Cliente</a>
    </div>
    <table>
        <thead><tr><th>Nome Fantasia</th><th>Razao Social</th><th>CNPJ</th><th>Contato</th><th>Status</th><th>Acoes</th></tr></thead>
        <tbody>
        <?php if (empty($clientes)): ?>
        <tr><td colspan="6" style="text-align:center;color:#6b7280;">Nenhum cliente cadastrado.</td></tr>
        <?php else: ?>
        <?php foreach ($clientes as $c): ?>
        <tr>
            <td><a href="/clientes/<?= $c['id'] ?>" style="color:var(--c-primary);font-weight:600;"><?= htmlspecialchars($c['nome_fantasia'] ?? $c['razao_social']) ?></a></td>
            <td><?= htmlspecialchars($c['razao_social']) ?></td>
            <td><?= htmlspecialchars($c['cnpj'] ?? '-') ?></td>
            <td><?= htmlspecialchars($c['contato_nome'] ?? '-') ?></td>
            <td><span class="badge badge-<?= $c['ativo'] ? 'ativo' : 'inativo' ?>"><?= $c['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
            <td>
                <a href="/clientes/<?= $c['id'] ?>/editar" class="btn btn-outline btn-sm">Editar</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
