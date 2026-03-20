<div style="margin-bottom:24px; display:flex; justify-content:space-between; align-items:center;">
    <div>
        <h2>Relatorios Mensais de Vencimento</h2>
        <span style="color:var(--c-gray);">Relatorios gerados automaticamente no dia 1 de cada mes.</span>
    </div>
    <a href="/relatorios" class="btn btn-outline btn-sm">Voltar</a>
</div>

<div class="table-container">
    <div class="table-header">
        <span class="table-title">Relatorios Gerados</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Arquivo</th>
                <th>Tamanho</th>
                <th>Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($relatorios)): ?>
            <tr><td colspan="4" style="text-align:center;color:#6b7280;">Nenhum relatorio mensal gerado ainda.</td></tr>
            <?php else: ?>
            <?php foreach ($relatorios as $r): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($r['data'])) ?></td>
                <td><?= htmlspecialchars($r['arquivo']) ?></td>
                <td><?= number_format($r['tamanho'] / 1024, 1) ?> KB</td>
                <td>
                    <a href="/storage/relatorios/<?= htmlspecialchars($r['arquivo']) ?>" target="_blank" class="btn btn-primary btn-sm">Visualizar</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
