<?php
$totalColabs = count($colaboradores);
$clienteNome = htmlspecialchars($cliente['nome_fantasia'] ?? $cliente['razao_social']);
$obraNome = htmlspecialchars($obra['nome']);
$statusClass = match($obra['status']) {
    'ativa' => 'ativo',
    'suspensa' => 'afastado',
    default => 'inativo'
};
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <div>
        <div style="display:flex; align-items:center; gap:12px;">
            <h2 style="font-size:24px; margin:0;"><?= $obraNome ?></h2>
            <span class="badge badge-<?= $statusClass ?>"><?= ucfirst($obra['status']) ?></span>
        </div>
        <p style="color:var(--c-gray); margin:4px 0 0;">
            Cliente: <a href="/clientes/<?= $cliente['id'] ?>" style="color:var(--c-accent);"><?= $clienteNome ?></a>
            <?php if ($obra['local_obra']): ?>
                | Local: <?= htmlspecialchars($obra['local_obra']) ?>
            <?php endif; ?>
            <?php if ($obra['data_inicio']): ?>
                | Inicio: <?= date('d/m/Y', strtotime($obra['data_inicio'])) ?>
            <?php endif; ?>
            <?php if ($obra['data_fim']): ?>
                - Fim: <?= date('d/m/Y', strtotime($obra['data_fim'])) ?>
            <?php endif; ?>
        </p>
    </div>
    <div style="display:flex; gap:8px;">
        <a href="/relatorios/obra/<?= $obra['id'] ?>" class="btn btn-outline btn-sm">Relatório</a>
        <a href="/obras/<?= $obra['id'] ?>/editar" class="btn btn-outline btn-sm">Editar</a>
        <?php if ($totalColabs > 0): ?>
        <a href="/obras/<?= $obra['id'] ?>/download-zip" class="btn btn-primary btn-sm" onclick="return confirm('Baixar documentos de <?= $totalColabs ?> colaborador(es) em ZIP?');">
            Baixar Docs ZIP
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Summary cards -->
<div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin-bottom:24px;">
    <div class="card" style="padding:20px; text-align:center;">
        <div style="font-size:32px; font-weight:700; color:var(--c-text);"><?= $totalColabs ?></div>
        <div style="color:var(--c-gray); font-size:13px;">Colaboradores</div>
    </div>
    <div class="card" style="padding:20px; text-align:center;">
        <div style="font-size:32px; font-weight:700; color:#2e7d32;"><?= $totalRegular ?></div>
        <div style="color:var(--c-gray); font-size:13px;">Regulares</div>
    </div>
    <div class="card" style="padding:20px; text-align:center;">
        <div style="font-size:32px; font-weight:700; color:#f39c12;"><?= $totalAtencao ?></div>
        <div style="color:var(--c-gray); font-size:13px;">Próximos do Vencimento</div>
    </div>
    <div class="card" style="padding:20px; text-align:center;">
        <div style="font-size:32px; font-weight:700; color:#e74c3c;"><?= $totalIrregular ?></div>
        <div style="color:var(--c-gray); font-size:13px;">Irregulares</div>
    </div>
</div>

<!-- Collaborators table -->
<div class="table-container">
    <div class="table-header">
        <span class="table-title">Colaboradores na Obra (<?= $totalColabs ?>)</span>
        <div style="display:flex; gap:8px; align-items:center;">
            <input type="text" id="filtro-colab" placeholder="Filtrar por nome..." class="form-control" style="width:220px; height:34px;">
            <select id="filtro-status" class="form-control" style="width:160px; height:34px;">
                <option value="">Todos</option>
                <option value="regular">Regulares</option>
                <option value="atenção">Próximos Venc.</option>
                <option value="irregular">Irregulares</option>
            </select>
        </div>
    </div>
    <?php if (empty($colaboradores)): ?>
    <div style="padding:40px; text-align:center; color:#6b7280;">
        Nenhum colaborador ativo alocado nesta obra.
    </div>
    <?php else: ?>
    <table id="tabela-colabs">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Cargo/Funcao</th>
                <th>Matricula</th>
                <th style="text-align:center;">Docs Vigentes</th>
                <th style="text-align:center;">Prox. Venc.</th>
                <th style="text-align:center;">Vencidos</th>
                <th style="text-align:center;">Certs Vigentes</th>
                <th style="text-align:center;">Certs Venc.</th>
                <th style="text-align:center;">Conformidade</th>
                <th>Acoes</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($colaboradores as $c): ?>
        <tr data-nome="<?= mb_strtolower(htmlspecialchars($c['nome_completo'])) ?>" data-conformidade="<?= $c['conformidade'] ?>">
            <td>
                <a href="/colaboradores/<?= $c['id'] ?>" style="color:var(--c-accent); font-weight:500;">
                    <?= htmlspecialchars($c['nome_completo']) ?>
                </a>
            </td>
            <td style="font-size:13px;"><?= htmlspecialchars($c['cargo'] ?? $c['funcao'] ?? '-') ?></td>
            <td style="font-size:13px;"><?= htmlspecialchars($c['matricula'] ?? '-') ?></td>
            <td style="text-align:center;">
                <?php if ($c['docs']['vigente'] > 0): ?>
                <span class="badge badge-ativo"><?= $c['docs']['vigente'] ?></span>
                <?php else: ?>
                <span style="color:#ccc;">0</span>
                <?php endif; ?>
            </td>
            <td style="text-align:center;">
                <?php if ($c['docs']['proximo_vencimento'] > 0): ?>
                <span class="badge badge-afastado"><?= $c['docs']['proximo_vencimento'] ?></span>
                <?php else: ?>
                <span style="color:#ccc;">0</span>
                <?php endif; ?>
            </td>
            <td style="text-align:center;">
                <?php if ($c['docs']['vencido'] > 0): ?>
                <span class="badge badge-inativo"><?= $c['docs']['vencido'] ?></span>
                <?php else: ?>
                <span style="color:#ccc;">0</span>
                <?php endif; ?>
            </td>
            <td style="text-align:center;">
                <?php if ($c['certs']['vigente'] > 0): ?>
                <span class="badge badge-ativo"><?= $c['certs']['vigente'] ?></span>
                <?php else: ?>
                <span style="color:#ccc;">0</span>
                <?php endif; ?>
            </td>
            <td style="text-align:center;">
                <?php if (($c['certs']['vencido'] + $c['certs']['proximo_vencimento']) > 0): ?>
                <span class="badge badge-inativo"><?= $c['certs']['vencido'] + $c['certs']['proximo_vencimento'] ?></span>
                <?php else: ?>
                <span style="color:#ccc;">0</span>
                <?php endif; ?>
            </td>
            <td style="text-align:center;">
                <?php
                $confColor = match($c['conformidade']) {
                    'regular' => 'ativo',
                    'atenção' => 'afastado',
                    'irregular' => 'inativo',
                };
                $confLabel = match($c['conformidade']) {
                    'regular' => 'Regular',
                    'atenção' => 'Atenção',
                    'irregular' => 'Irregular',
                };
                ?>
                <span class="badge badge-<?= $confColor ?>"><?= $confLabel ?></span>
            </td>
            <td>
                <div style="display:flex; gap:4px;">
                    <a href="/colaboradores/<?= $c['id'] ?>" class="btn btn-outline btn-sm">Ver</a>
                    <a href="/colaboradores/<?= $c['id'] ?>/download-zip" class="btn btn-outline btn-sm" title="Baixar docs deste colaborador">ZIP</a>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filtroNome = document.getElementById('filtro-colab');
    const filtroStatus = document.getElementById('filtro-status');
    const tabela = document.getElementById('tabela-colabs');
    if (!tabela) return;
    const rows = tabela.querySelectorAll('tbody tr');

    function filtrar() {
        const nome = filtroNome.value.toLowerCase();
        const status = filtroStatus.value;
        rows.forEach(row => {
            const matchNome = !nome || row.dataset.nome.includes(nome);
            const matchStatus = !status || row.dataset.conformidade === status;
            row.style.display = (matchNome && matchStatus) ? '' : 'none';
        });
    }

    filtroNome.addEventListener('input', filtrar);
    filtroStatus.addEventListener('change', filtrar);
});
</script>
