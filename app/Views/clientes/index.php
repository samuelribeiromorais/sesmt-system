<div class="table-container">
    <div class="table-header">
        <span class="table-title">Clientes</span>
        <a href="/clientes/novo" class="btn btn-primary btn-sm">+ Novo Cliente</a>
    </div>
    <table>
        <thead><tr><th>Nome Fantasia</th><th>Razao Social</th><th>CNPJ</th><th>Colaboradores</th><th>Compliance</th><th>Contato</th><th>Status</th><th>Acoes</th></tr></thead>
        <tbody>
        <?php if (empty($clientes)): ?>
        <tr><td colspan="8" style="text-align:center;color:#6b7280;">Nenhum cliente cadastrado.</td></tr>
        <?php else: ?>
        <?php foreach ($clientes as $c):
            $cid = (int)$c['id'];
            $numColabs = $colabCounts[$cid] ?? 0;
            $numExpired = $docsExpired[$cid] ?? 0;
            $numExpiring = $docsExpiring[$cid] ?? 0;

            if ($numExpired > 0) {
                $complianceColor = '#ef4444';
                $complianceLabel = $numExpired . ' vencido(s)';
            } elseif ($numExpiring > 0) {
                $complianceColor = '#f59e0b';
                $complianceLabel = $numExpiring . ' vencendo';
            } else {
                $complianceColor = '#22c55e';
                $complianceLabel = 'OK';
            }
        ?>
        <tr>
            <td><a href="/clientes/<?= $c['id'] ?>" style="color:var(--c-primary);font-weight:600;"><?= htmlspecialchars($c['nome_fantasia'] ?? $c['razao_social']) ?></a></td>
            <td><?= htmlspecialchars($c['razao_social']) ?></td>
            <td><?= htmlspecialchars($c['cnpj'] ?? '-') ?></td>
            <td><a href="/colaboradores?cliente=<?= $cid ?>" style="color:var(--c-primary);font-weight:600;"><?= $numColabs ?></a></td>
            <td>
                <span style="display:inline-flex;align-items:center;gap:0.4rem;">
                    <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $complianceColor ?>;"></span>
                    <span style="font-size:0.85rem;"><?= $complianceLabel ?></span>
                </span>
            </td>
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
