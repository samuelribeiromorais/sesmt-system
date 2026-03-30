<?php
function statusLabel($s) {
    return match($s) {
        'vigente' => '<span class="badge badge-vigente">Vigente</span>',
        'proximo_vencimento' => '<span class="badge badge-proximo">Próximo vencimento</span>',
        'vencido' => '<span class="badge badge-vencido">Vencido</span>',
        default => '<span class="badge badge-obsoleto">-</span>',
    };
}
?>

<div style="margin-bottom:24px; display:flex; justify-content:space-between; align-items:center;">
    <div>
        <h2><?= htmlspecialchars($colab['nome_completo']) ?></h2>
        <span style="color:var(--c-gray);">Cargo: <?= htmlspecialchars($colab['cargo'] ?? $colab['funcao'] ?? '-') ?> | Cliente: <?= htmlspecialchars($colab['cliente_nome'] ?? '-') ?></span>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="/relatorios/colaborador/<?= $colab['id'] ?>?format=excel" class="btn btn-primary btn-sm">Exportar Excel</a>
        <a href="/relatorios" class="btn btn-outline btn-sm">Voltar</a>
    </div>
</div>

<div class="table-container">
    <div class="table-header"><span class="table-title">Certificados</span></div>
    <table>
        <thead><tr><th>Tipo</th><th>Duracao</th><th>Realizacao</th><th>Emissão</th><th>Validade</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (empty($certificados)): ?>
        <tr><td colspan="6" style="text-align:center;color:#6b7280;">Nenhum certificado</td></tr>
        <?php else: ?>
        <?php foreach ($certificados as $c): ?>
        <tr>
            <td><?= htmlspecialchars($c['codigo']) ?></td>
            <td><?= htmlspecialchars($c['duracao']) ?></td>
            <td><?= date('d/m/Y', strtotime($c['data_realizacao'])) ?></td>
            <td><?= date('d/m/Y', strtotime($c['data_emissao'])) ?></td>
            <td><?= date('d/m/Y', strtotime($c['data_validade'])) ?></td>
            <td><?= statusLabel($c['status']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="table-container" style="margin-top:24px;">
    <div class="table-header"><span class="table-title">Documentos</span></div>
    <table>
        <thead><tr><th>Tipo</th><th>Categoria</th><th>Emissão</th><th>Validade</th><th>Status</th><th>Arquivo</th></tr></thead>
        <tbody>
        <?php if (empty($documentos)): ?>
        <tr><td colspan="6" style="text-align:center;color:#6b7280;">Nenhum documento</td></tr>
        <?php else: ?>
        <?php foreach ($documentos as $d): ?>
        <tr>
            <td><?= htmlspecialchars($d['tipo_nome']) ?></td>
            <td><?= htmlspecialchars(strtoupper($d['categoria'])) ?></td>
            <td><?= date('d/m/Y', strtotime($d['data_emissao'])) ?></td>
            <td><?= $d['data_validade'] ? date('d/m/Y', strtotime($d['data_validade'])) : 'N/A' ?></td>
            <td><?= statusLabel($d['status']) ?></td>
            <td><a href="/documentos/download/<?= $d['id'] ?>" class="btn btn-outline btn-sm">PDF</a></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
