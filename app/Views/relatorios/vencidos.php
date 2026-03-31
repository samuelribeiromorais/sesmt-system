<?php
$categorias = ['nr' => 'NR', 'admissional' => 'Admissional', 'periodico' => 'Periódico',
               'outros' => 'Outros', 'treinamento' => 'Treinamento'];
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <div>
        <h2 style="margin:0;">Documentos Vencidos</h2>
        <p style="color:var(--c-gray); font-size:14px; margin:4px 0 0;">
            Todos os documentos e certificados com validade expirada.
        </p>
    </div>
    <a href="/relatorios" class="btn btn-outline btn-sm">← Voltar</a>
</div>

<!-- Resumo por tipo -->
<?php if (!empty($resumoPorTipo)): ?>
<div class="table-container" style="margin-bottom:24px;">
    <div class="table-header"><span class="table-title">Resumo por Tipo de Documento</span></div>
    <div style="padding:16px; display:flex; flex-wrap:wrap; gap:12px;">
        <?php foreach ($resumoPorTipo as $r): ?>
        <a href="/relatorios/vencidos?tipo_documento_id=<?= htmlspecialchars(
            array_column(array_filter($tiposDocumento, fn($t) => $t['nome'] === $r['tipo_nome']), 'id')[0] ?? ''
        ) ?>" style="text-decoration:none;">
            <div style="background:#fff3f3; border:1px solid #fca5a5; border-radius:8px; padding:12px 18px; min-width:140px; text-align:center; cursor:pointer;">
                <div style="font-size:1.5rem; font-weight:700; color:#dc2626;"><?= $r['total'] ?></div>
                <div style="font-size:12px; color:#666; margin-top:2px;"><?= htmlspecialchars($r['tipo_nome'] ?? 'Sem tipo') ?></div>
                <div style="font-size:11px; color:#999;"><?= htmlspecialchars(ucfirst($r['categoria'] ?? '')) ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Filtros -->
<div class="table-container" style="margin-bottom:24px;">
    <div class="table-header"><span class="table-title">Filtrar</span></div>
    <div style="padding:16px; display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
        <form method="GET" action="/relatorios/vencidos" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; width:100%;">
            <div style="flex:1; min-width:220px;">
                <label class="form-label">Tipo de Documento</label>
                <select name="tipo_documento_id" class="form-input">
                    <option value="">Todos os tipos</option>
                    <?php foreach ($tiposDocumento as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $tipoId == $t['id'] ? 'selected' : '' ?>>
                        [<?= htmlspecialchars(ucfirst($t['categoria'])) ?>] <?= htmlspecialchars($t['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="min-width:160px;">
                <label class="form-label">Categoria</label>
                <select name="categoria" class="form-input">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $categoria === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="/relatorios/vencidos" class="btn btn-outline">Limpar</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabela: Documentos Vencidos -->
<div class="table-container" style="margin-bottom:24px;">
    <div class="table-header" style="background:#dc2626;">
        <span class="table-title" style="color:#fff;">
            Documentos Vencidos
            <span style="font-weight:400; font-size:13px; margin-left:8px;">(<?= count($documentosVencidos) ?> registro<?= count($documentosVencidos) !== 1 ? 's' : '' ?>)</span>
        </span>
    </div>

    <?php if (empty($documentosVencidos)): ?>
    <div style="padding:32px; text-align:center; color:var(--c-gray);">
        Nenhum documento vencido encontrado com os filtros aplicados.
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Colaborador</th>
                    <th>Cargo / Setor</th>
                    <th>Tipo de Documento</th>
                    <th>Categoria</th>
                    <th>Emissão</th>
                    <th>Vencimento</th>
                    <th>Dias Vencido</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $tipoAtual = null;
                foreach ($documentosVencidos as $doc):
                    if ($doc['tipo_nome'] !== $tipoAtual):
                        $tipoAtual = $doc['tipo_nome'];
                        if (!$tipoId): // só mostra separador quando não filtrado por tipo
                ?>
                <tr style="background:#fef2f2;">
                    <td colspan="8" style="font-weight:600; color:#dc2626; font-size:13px; padding:6px 16px;">
                        <?= htmlspecialchars($tipoAtual ?? 'Sem tipo') ?>
                        <span style="color:#999; font-weight:400;">&nbsp;— <?= htmlspecialchars(ucfirst($doc['categoria'] ?? '')) ?></span>
                    </td>
                </tr>
                <?php endif; endif; ?>
                <tr>
                    <td><strong><?= htmlspecialchars($doc['nome_completo']) ?></strong></td>
                    <td style="font-size:12px; color:var(--c-gray);">
                        <?= htmlspecialchars($doc['cargo'] ?? '') ?>
                        <?php if (!empty($doc['setor'])): ?><br><?= htmlspecialchars($doc['setor']) ?><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($doc['tipo_nome'] ?? '—') ?></td>
                    <td><span class="badge badge-info"><?= htmlspecialchars(ucfirst($doc['categoria'] ?? '—')) ?></span></td>
                    <td><?= $doc['data_emissao'] ? date('d/m/Y', strtotime($doc['data_emissao'])) : '—' ?></td>
                    <td style="color:#dc2626; font-weight:600;">
                        <?= $doc['data_validade'] ? date('d/m/Y', strtotime($doc['data_validade'])) : '—' ?>
                    </td>
                    <td>
                        <?php $dias = (int)$doc['dias_vencido']; ?>
                        <span style="background:#fca5a5; color:#7f1d1d; padding:2px 8px; border-radius:12px; font-size:12px; font-weight:600; white-space:nowrap;">
                            <?= $dias ?> dia<?= $dias !== 1 ? 's' : '' ?>
                        </span>
                    </td>
                    <td>
                        <a href="/documentos/<?= $doc['id'] ?>" class="btn btn-outline btn-sm">Ver</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Tabela: Certificados Vencidos -->
<?php if (!$tipoId): // certificados só aparecem quando não filtrado por tipo de documento ?>
<div class="table-container">
    <div class="table-header" style="background:#b45309;">
        <span class="table-title" style="color:#fff;">
            Certificados Vencidos
            <span style="font-weight:400; font-size:13px; margin-left:8px;">(<?= count($certificadosVencidos) ?> registro<?= count($certificadosVencidos) !== 1 ? 's' : '' ?>)</span>
        </span>
    </div>

    <?php if (empty($certificadosVencidos)): ?>
    <div style="padding:32px; text-align:center; color:var(--c-gray);">
        Nenhum certificado vencido encontrado.
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Colaborador</th>
                    <th>Cargo / Setor</th>
                    <th>Tipo de Certificado</th>
                    <th>Realização</th>
                    <th>Vencimento</th>
                    <th>Dias Vencido</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $tipoAtual = null;
                foreach ($certificadosVencidos as $cert):
                    if ($cert['tipo_titulo'] !== $tipoAtual):
                        $tipoAtual = $cert['tipo_titulo'];
                ?>
                <tr style="background:#fffbeb;">
                    <td colspan="7" style="font-weight:600; color:#b45309; font-size:13px; padding:6px 16px;">
                        <?= htmlspecialchars($tipoAtual ?? 'Sem tipo') ?>
                        <?php if (!empty($cert['tipo_codigo'])): ?>
                        <span style="color:#999; font-weight:400;">&nbsp;— <?= htmlspecialchars($cert['tipo_codigo']) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><strong><?= htmlspecialchars($cert['nome_completo']) ?></strong></td>
                    <td style="font-size:12px; color:var(--c-gray);">
                        <?= htmlspecialchars($cert['cargo'] ?? '') ?>
                        <?php if (!empty($cert['setor'])): ?><br><?= htmlspecialchars($cert['setor']) ?><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($cert['tipo_titulo'] ?? '—') ?></td>
                    <td><?= $cert['data_realizacao'] ? date('d/m/Y', strtotime($cert['data_realizacao'])) : '—' ?></td>
                    <td style="color:#b45309; font-weight:600;">
                        <?= $cert['data_validade'] ? date('d/m/Y', strtotime($cert['data_validade'])) : '—' ?>
                    </td>
                    <td>
                        <?php $dias = (int)$cert['dias_vencido']; ?>
                        <span style="background:#fde68a; color:#78350f; padding:2px 8px; border-radius:12px; font-size:12px; font-weight:600; white-space:nowrap;">
                            <?= $dias ?> dia<?= $dias !== 1 ? 's' : '' ?>
                        </span>
                    </td>
                    <td>
                        <a href="/certificados/<?= $cert['id'] ?>" class="btn btn-outline btn-sm">Ver</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
