<?php
$nomesMes = ['1'=>'Janeiro','2'=>'Fevereiro','3'=>'Março','4'=>'Abril','5'=>'Maio','6'=>'Junho',
             '7'=>'Julho','8'=>'Agosto','9'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
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
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <h2 style="margin:0;">Relatório por Tipo de Documento</h2>
    <a href="/relatorios" class="btn btn-outline btn-sm">Voltar</a>
</div>

<!-- Filtros -->
<div class="table-container" style="margin-bottom:24px; padding:20px;">
    <form method="GET" action="/relatorios/tipo-documento" style="display:flex; flex-wrap:wrap; gap:16px; align-items:flex-end;">
        <div style="flex:2; min-width:200px;">
            <label class="form-label">Tipo de Documento *</label>
            <select name="tipo_documento_id" class="form-input" required>
                <option value="">-- Selecionar --</option>
                <?php foreach ($tiposDocumento as $t): ?>
                <option value="<?= $t['id'] ?>" <?= $tipoId == $t['id'] ? 'selected' : '' ?>>
                    [<?= htmlspecialchars(ucfirst($t['categoria'])) ?>] <?= htmlspecialchars($t['nome']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1; min-width:130px;">
            <label class="form-label">Status</label>
            <select name="status" class="form-input">
                <option value="">Todos</option>
                <option value="vigente" <?= $status==='vigente'?'selected':'' ?>>Vigente</option>
                <option value="proximo_vencimento" <?= $status==='proximo_vencimento'?'selected':'' ?>>Próximo Vencimento</option>
                <option value="vencido" <?= $status==='vencido'?'selected':'' ?>>Vencido</option>
            </select>
        </div>
        <div style="flex:1; min-width:120px;">
            <label class="form-label">Mes (emissão)</label>
            <select name="mes" class="form-input">
                <option value="">Todos</option>
                <?php foreach ($nomesMes as $n => $nome): ?>
                <option value="<?= $n ?>" <?= $mes==$n?'selected':'' ?>><?= $nome ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1; min-width:100px;">
            <label class="form-label">Ano (emissão)</label>
            <select name="ano" class="form-input">
                <option value="">Todos</option>
                <?php for ($a = date('Y'); $a >= 2022; $a--): ?>
                <option value="<?= $a ?>" <?= $ano==$a?'selected':'' ?>><?= $a ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <?php if (!empty($documentos)): ?>
            <button type="button" onclick="window.print()" class="btn btn-outline" style="margin-left:8px;">Imprimir</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if ($tipoSelecionado): ?>
<!-- Resumo -->
<?php
$counts = ['vigente'=>0,'proximo_vencimento'=>0,'vencido'=>0,'obsoleto'=>0];
foreach ($documentos as $d) { if (isset($counts[$d['status']])) $counts[$d['status']]++; }
?>
<div style="display:flex; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:16px 24px; text-align:center;">
        <div style="font-size:1.8rem; font-weight:700; color:var(--c-primary);"><?= count($documentos) ?></div>
        <div style="font-size:13px; color:var(--c-gray);">Total</div>
    </div>
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:16px 24px; text-align:center;">
        <div style="font-size:1.8rem; font-weight:700; color:#00b279;"><?= $counts['vigente'] ?></div>
        <div style="font-size:13px; color:var(--c-gray);">Vigentes</div>
    </div>
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:16px 24px; text-align:center;">
        <div style="font-size:1.8rem; font-weight:700; color:#f39c12;"><?= $counts['proximo_vencimento'] ?></div>
        <div style="font-size:13px; color:var(--c-gray);">Próximo Venc.</div>
    </div>
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:16px 24px; text-align:center;">
        <div style="font-size:1.8rem; font-weight:700; color:#e74c3c;"><?= $counts['vencido'] ?></div>
        <div style="font-size:13px; color:var(--c-gray);">Vencidos</div>
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <span class="table-title">
            <?= htmlspecialchars($tipoSelecionado['nome']) ?>
            <?php if ($mes && $ano): ?> — <?= $nomesMes[(string)$mes] ?>/<?= $ano ?><?php endif; ?>
            (<?= count($documentos) ?> registros)
        </span>
    </div>
    <?php if (empty($documentos)): ?>
    <div style="padding:32px; text-align:center; color:var(--c-gray);">Nenhum documento encontrado com os filtros selecionados.</div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Colaborador</th>
                <th>Cargo</th>
                <th>Setor</th>
                <th>Emissão</th>
                <th>Validade</th>
                <th>Status</th>
                <th>Inserido em</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($documentos as $i => $d): ?>
            <tr>
                <td style="color:#999; font-size:12px;"><?= $i+1 ?></td>
                <td style="font-weight:600; color:var(--c-primary);"><?= htmlspecialchars($d['nome_completo']) ?></td>
                <td style="font-size:13px;"><?= htmlspecialchars($d['cargo'] ?? '—') ?></td>
                <td style="font-size:13px;"><?= htmlspecialchars($d['setor'] ?? '—') ?></td>
                <td><?= $d['data_emissao'] ? date('d/m/Y', strtotime($d['data_emissao'])) : '—' ?></td>
                <td><?= $d['data_validade'] ? date('d/m/Y', strtotime($d['data_validade'])) : '—' ?></td>
                <td><span class="badge <?= $badgeStatus[$d['status']] ?? '' ?>"><?= $labelStatus[$d['status']] ?? $d['status'] ?></span></td>
                <td style="font-size:12px; color:#999;"><?= date('d/m/Y', strtotime($d['criado_em'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<style>
@media print {
    nav, aside, .sidebar, header, form { display: none !important; }
    .table-container { box-shadow: none; border: 1px solid #ddd; }
    @page { size: A4 landscape; margin: 10mm; }
}
</style>
