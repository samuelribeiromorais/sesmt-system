<?php
$nomesMes = ['1'=>'Janeiro','2'=>'Fevereiro','3'=>'Março','4'=>'Abril','5'=>'Maio','6'=>'Junho',
             '7'=>'Julho','8'=>'Agosto','9'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
$nomeMes = $nomesMes[(string)$mes] ?? $mes;

$badgeStatus = [
    'vigente'            => 'badge-vigente',
    'proximo_vencimento' => 'badge-proximo',
    'vencido'            => 'badge-vencido',
    'obsoleto'           => 'badge-obsoleto',
];
$labelStatus = [
    'vigente'            => 'Vigente',
    'proximo_vencimento' => 'Próximo Vencimento',
    'vencido'            => 'Vencido',
    'obsoleto'           => 'Obsoleto',
];
$labelCat = ['aso'=>'ASO','epi'=>'EPI','treinamento'=>'Treinamento'];
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="margin:0;">Relatório Mensal — <?= $nomeMes ?>/<?= $ano ?></h2>
        <span style="color:var(--c-gray); font-size:14px;">
            <?= count($documentos) ?> documentos &nbsp;|&nbsp; <?= count($certificados) ?> certificados inseridos no período
        </span>
    </div>
    <div style="display:flex; gap:8px; align-items:center;">
        <!-- Navegar mês -->
        <?php
        $mesAnterior = $mes - 1; $anoAnterior = $ano;
        if ($mesAnterior < 1) { $mesAnterior = 12; $anoAnterior--; }
        $mesSeguinte = $mes + 1; $anoSeguinte = $ano;
        if ($mesSeguinte > 12) { $mesSeguinte = 1; $anoSeguinte++; }
        ?>
        <a href="/relatorios/mensal?mes=<?= $mesAnterior ?>&ano=<?= $anoAnterior ?>" class="btn btn-outline btn-sm">&#8592; <?= $nomesMes[(string)$mesAnterior] ?></a>
        <?php if (!($mes == date('m') && $ano == date('Y'))): ?>
        <a href="/relatorios/mensal?mes=<?= $mesSeguinte ?>&ano=<?= $anoSeguinte ?>" class="btn btn-outline btn-sm"><?= $nomesMes[(string)$mesSeguinte] ?> &#8594;</a>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-primary btn-sm">Imprimir</button>
        <a href="/relatorios" class="btn btn-outline btn-sm">Voltar</a>
    </div>
</div>

<!-- Resumo cards -->
<div style="display:flex; gap:16px; margin-bottom:24px; flex-wrap:wrap;">
    <?php foreach (['aso'=>'ASO','epi'=>'EPI','treinamento'=>'Treinamento'] as $cat => $label): ?>
    <div class="card-stat" style="flex:1; min-width:120px; border-left:4px solid var(--c-primary);">
        <div class="card-stat-value" style="font-size:1.5rem;"><?= $resumoCategoria[$cat] ?? 0 ?></div>
        <div class="card-stat-label"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
    <div class="card-stat" style="flex:1; min-width:120px; border-left:4px solid #6366f1;">
        <div class="card-stat-value" style="font-size:1.5rem;"><?= count($certificados) ?></div>
        <div class="card-stat-label">Certificados</div>
    </div>
</div>

<!-- Documentos -->
<div class="table-container" style="margin-bottom:24px;">
    <div class="table-header" style="display:flex; justify-content:space-between; align-items:center;">
        <span class="table-title">Documentos Inseridos (<?= count($documentos) ?>)</span>
    </div>
    <?php if (empty($documentos)): ?>
    <div style="padding:32px; text-align:center; color:var(--c-gray);">Nenhum documento inserido neste período.</div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Colaborador</th>
                <th>Cargo / Setor</th>
                <th>Tipo</th>
                <th>Categoria</th>
                <th>Emissão</th>
                <th>Validade</th>
                <th>Status</th>
                <th>Inserido em</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($documentos as $d): ?>
            <tr>
                <td><a href="/colaboradores/<?= /* would need colabid */ '' ?>" style="color:var(--c-primary);"><?= htmlspecialchars($d['nome_completo']) ?></a></td>
                <td style="font-size:12px;"><?= htmlspecialchars($d['cargo'] ?? '-') ?><br><span style="color:#999;"><?= htmlspecialchars($d['setor'] ?? '') ?></span></td>
                <td><?= htmlspecialchars($d['tipo_nome'] ?? ucfirst($d['categoria'])) ?></td>
                <td><span class="badge badge-vigente"><?= htmlspecialchars($labelCat[$d['categoria']] ?? $d['categoria']) ?></span></td>
                <td><?= $d['data_emissao'] ? date('d/m/Y', strtotime($d['data_emissao'])) : '—' ?></td>
                <td><?= $d['data_validade'] ? date('d/m/Y', strtotime($d['data_validade'])) : '—' ?></td>
                <td><span class="badge <?= $badgeStatus[$d['status']] ?? '' ?>"><?= $labelStatus[$d['status']] ?? $d['status'] ?></span></td>
                <td style="font-size:12px;"><?= date('d/m/Y H:i', strtotime($d['criado_em'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Certificados -->
<div class="table-container">
    <div class="table-header">
        <span class="table-title">Certificados Inseridos (<?= count($certificados) ?>)</span>
    </div>
    <?php if (empty($certificados)): ?>
    <div style="padding:32px; text-align:center; color:var(--c-gray);">Nenhum certificado inserido neste período.</div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Colaborador</th>
                <th>Cargo / Setor</th>
                <th>Treinamento</th>
                <th>Realizacao</th>
                <th>Validade</th>
                <th>Status</th>
                <th>Inserido em</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($certificados as $c): ?>
            <tr>
                <td style="color:var(--c-primary);"><?= htmlspecialchars($c['nome_completo']) ?></td>
                <td style="font-size:12px;"><?= htmlspecialchars($c['cargo'] ?? '-') ?><br><span style="color:#999;"><?= htmlspecialchars($c['setor'] ?? '') ?></span></td>
                <td><?= htmlspecialchars($c['tipo_codigo'] ?? '') ?> <?= htmlspecialchars($c['tipo_titulo'] ?? '') ?></td>
                <td><?= $c['data_realizacao'] ? date('d/m/Y', strtotime($c['data_realizacao'])) : '—' ?></td>
                <td><?= $c['data_validade'] ? date('d/m/Y', strtotime($c['data_validade'])) : '—' ?></td>
                <td><span class="badge <?= $badgeStatus[$c['status']] ?? '' ?>"><?= $labelStatus[$c['status']] ?? $c['status'] ?></span></td>
                <td style="font-size:12px;"><?= date('d/m/Y H:i', strtotime($c['criado_em'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<style>
@media print {
    .print-bar, nav, aside, .sidebar, header { display: none !important; }
    .table-container { box-shadow: none; border: 1px solid #ddd; }
    @page { size: A4 landscape; margin: 10mm; }
}
</style>
