<?= \App\Core\View::csrfField() ?>
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div>
        <h2 style="margin:0 0 4px;">Painel RH — Envio ao Cliente</h2>
        <p style="color:var(--c-gray); font-size:13px; margin:0;">Marque cada documento que já foi encaminhado ao cliente.</p>
    </div>
</div>

<!-- KPIs -->
<div style="display:flex; gap:16px; margin-bottom:24px;">
    <div class="stat-card" style="border-left:4px solid #f39c12; flex:1;">
        <div class="stat-value" style="color:#f39c12;"><?= $totalPendentes ?></div>
        <div class="stat-label">Pendentes de envio ao cliente</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #00b279; flex:1;">
        <div class="stat-value" style="color:#00b279;"><?= $totalEnviados ?></div>
        <div class="stat-label">Já enviados ao cliente</div>
    </div>
</div>

<!-- Filtros + tabela -->
<div class="table-container">
    <div class="table-header">
        <span class="table-title">Documentos do SESMT</span>
    </div>
    <div style="padding:12px 20px; border-bottom:1px solid var(--c-border); background:var(--c-bg);">
        <form method="GET" action="/rh" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <input type="text" name="q" class="form-control" placeholder="Buscar colaborador..." value="<?= htmlspecialchars($search) ?>" style="flex:1; min-width:240px;">
            <select name="filtro" class="form-control" style="width:200px;" onchange="this.form.submit()">
                <option value="pendentes" <?= $somentePendentes ? 'selected' : '' ?>>Apenas pendentes</option>
                <option value="todos" <?= !$somentePendentes ? 'selected' : '' ?>>Todos os documentos</option>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">Buscar</button>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>Colaborador</th>
                <th>Cliente</th>
                <th>Tipo</th>
                <th>Emissão</th>
                <th>Validade</th>
                <th>Enviado por (SESMT)</th>
                <th style="text-align:center;">Enviado ao cliente</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($documentos)): ?>
            <tr><td colspan="7" style="text-align:center; color:#6b7280; padding:32px;">
                <?= $somentePendentes ? 'Nenhum documento pendente de envio.' : 'Nenhum documento encontrado.' ?>
            </td></tr>
            <?php else: ?>
            <?php foreach ($documentos as $d): ?>
            <tr id="row-doc-<?= $d['id'] ?>">
                <td>
                    <a href="/colaboradores/<?= $d['colaborador_id'] ?>" style="color:var(--c-primary); font-weight:600;">
                        <?= htmlspecialchars($d['nome_completo']) ?>
                    </a>
                    <?php if ($d['matricula']): ?>
                    <div style="font-size:11px; color:#6b7280;">Mat.: <?= htmlspecialchars($d['matricula']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="font-size:13px;"><?= htmlspecialchars($d['cliente_nome'] ?? '-') ?></td>
                <td style="font-size:13px;"><?= htmlspecialchars($d['tipo_nome']) ?></td>
                <td style="font-size:13px;"><?= date('d/m/Y', strtotime($d['data_emissao'])) ?></td>
                <td style="font-size:13px;"><?= $d['data_validade'] ? date('d/m/Y', strtotime($d['data_validade'])) : 'N/A' ?></td>
                <td style="font-size:13px;"><?= htmlspecialchars($d['enviado_por_nome'] ?? '-') ?></td>
                <td style="text-align:center;">
                    <label style="display:inline-flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" <?= $d['enviado_cliente'] ? 'checked' : '' ?>
                               onchange="marcarEnviado(<?= $d['id'] ?>, this)"
                               style="width:18px; height:18px; cursor:pointer;">
                        <?php if ($d['enviado_cliente']): ?>
                        <span style="font-size:11px; color:#00b279;" title="Enviado em <?= $d['enviado_cliente_em'] ?> por <?= htmlspecialchars($d['enviado_cliente_por_nome'] ?? '?') ?>">
                            ✓ <?= date('d/m/Y', strtotime($d['enviado_cliente_em'])) ?>
                        </span>
                        <?php endif; ?>
                    </label>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
async function marcarEnviado(docId, checkbox) {
    const fd = new FormData();
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfInput = document.querySelector('[name=_csrf_token]');
    fd.append('_csrf_token', (csrfMeta && csrfMeta.content) || (csrfInput && csrfInput.value) || '');
    fd.append('marcar', checkbox.checked ? '1' : '0');
    checkbox.disabled = true;
    try {
        const res = await fetch('/documentos/' + docId + '/enviado-cliente', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.success) {
            checkbox.checked = !checkbox.checked;
            alert(data.error || 'Falha ao atualizar.');
        } else {
            setTimeout(() => location.reload(), 300);
        }
    } catch (e) {
        checkbox.checked = !checkbox.checked;
        alert('Erro de comunicação.');
    } finally {
        checkbox.disabled = false;
    }
}
</script>
