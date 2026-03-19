<!-- Contadores -->
<div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-value"><?= $contadores['total'] ?? 0 ?></div>
        <div class="stat-label">Total de Documentos</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #00b279;">
        <div class="stat-value" style="color:#00b279;"><?= $contadores['vigente'] ?? 0 ?></div>
        <div class="stat-label">Vigentes</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #f39c12;">
        <div class="stat-value" style="color:#f39c12;"><?= $contadores['proximo_vencimento'] ?? 0 ?></div>
        <div class="stat-label">Vencendo em 30 dias</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #e74c3c;">
        <div class="stat-value" style="color:#e74c3c;"><?= $contadores['vencido'] ?? 0 ?></div>
        <div class="stat-label">Vencidos</div>
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <span class="table-title">Controle Documental</span>
        <a href="/exportar/documentos" class="btn btn-outline btn-sm" title="Exportar Excel">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Excel
        </a>
    </div>

    <!-- Search bar -->
    <div style="padding:12px 20px; border-bottom:1px solid #e5e7eb; background:#fafafa;">
        <form method="GET" action="/documentos" id="doc-search-form" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <div class="search-box" style="flex:1; min-width:220px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="q" class="form-control" placeholder="Buscar por colaborador ou tipo..." value="<?= htmlspecialchars($search ?? '') ?>" id="doc-search-input" autocomplete="off">
            </div>
            <select name="categoria" class="form-control" style="width:150px;" onchange="this.form.submit()">
                <option value="">Todas categorias</option>
                <option value="aso" <?= ($categoria ?? '') === 'aso' ? 'selected' : '' ?>>ASO</option>
                <option value="epi" <?= ($categoria ?? '') === 'epi' ? 'selected' : '' ?>>EPI</option>
                <option value="os" <?= ($categoria ?? '') === 'os' ? 'selected' : '' ?>>Ordem de Servico</option>
                <option value="treinamento" <?= ($categoria ?? '') === 'treinamento' ? 'selected' : '' ?>>Treinamento</option>
                <option value="anuencia" <?= ($categoria ?? '') === 'anuencia' ? 'selected' : '' ?>>Anuencia</option>
                <option value="outro" <?= ($categoria ?? '') === 'outro' ? 'selected' : '' ?>>Outros</option>
            </select>
            <select name="status" class="form-control" style="width:130px;" onchange="this.form.submit()">
                <option value="">Todos status</option>
                <option value="vigente" <?= ($status ?? '') === 'vigente' ? 'selected' : '' ?>>Vigentes</option>
                <option value="proximo_vencimento" <?= ($status ?? '') === 'proximo_vencimento' ? 'selected' : '' ?>>Vencendo</option>
                <option value="vencido" <?= ($status ?? '') === 'vencido' ? 'selected' : '' ?>>Vencidos</option>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">Buscar</button>
            <?php if ($search || $status || $categoria): ?>
            <a href="/documentos" class="btn btn-outline btn-sm">Limpar</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Live search results -->
    <div id="doc-live-results" style="display:none; position:relative;">
        <div id="doc-live-list" style="position:absolute; top:0; left:0; right:0; background:white; border:1px solid #e5e7eb; border-top:none; box-shadow:0 4px 12px rgba(0,0,0,0.1); max-height:400px; overflow-y:auto; z-index:100;"></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Colaborador</th>
                <th>Tipo</th>
                <th>Categoria</th>
                <th>Emissao</th>
                <th>Validade</th>
                <th>Status</th>
                <th>Acoes</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($documentos)): ?>
            <tr><td colspan="7" style="text-align:center;color:#6b7280;padding:32px;">
                <?php if (!empty($search) || !empty($status) || !empty($categoria)): ?>
                    Nenhum documento encontrado com os filtros selecionados.
                <?php else: ?>
                    Nenhum documento cadastrado. Acesse a ficha de um colaborador para enviar documentos.
                <?php endif; ?>
            </td></tr>
        <?php else: ?>
            <?php foreach ($documentos as $d): ?>
            <tr>
                <td>
                    <a href="/colaboradores/<?= $d['colaborador_id'] ?>" style="color:var(--c-primary);font-weight:600;">
                        <?= htmlspecialchars($d['nome_completo']) ?>
                    </a>
                </td>
                <td style="font-size:13px;"><?= htmlspecialchars($d['tipo_nome']) ?></td>
                <td>
                    <span class="badge" style="background:<?= match($d['categoria']) {
                        'aso' => '#e8f5e9; color:#2e7d32',
                        'epi' => '#e3f2fd; color:#1565c0',
                        'os' => '#fff3e0; color:#e65100',
                        'treinamento' => '#f3e5f5; color:#7b1fa2',
                        'anuencia' => '#e0f7fa; color:#00838f',
                        default => '#f5f5f5; color:#616161'
                    } ?>;">
                        <?= htmlspecialchars(strtoupper($d['categoria'])) ?>
                    </span>
                </td>
                <td style="font-size:13px;"><?= date('d/m/Y', strtotime($d['data_emissao'])) ?></td>
                <td style="font-size:13px;"><?= $d['data_validade'] ? date('d/m/Y', strtotime($d['data_validade'])) : '—' ?></td>
                <td>
                    <?php
                    $badgeClass = match($d['status']) {
                        'vigente' => 'badge-vigente',
                        'proximo_vencimento' => 'badge-proximo_vencimento',
                        'vencido' => 'badge-vencido',
                        default => ''
                    };
                    $statusLabel = match($d['status']) {
                        'vigente' => 'Vigente',
                        'proximo_vencimento' => 'Vencendo',
                        'vencido' => 'Vencido',
                        default => ucfirst($d['status'])
                    };
                    ?>
                    <span class="badge <?= $badgeClass ?>"><?= $statusLabel ?></span>
                </td>
                <td style="white-space:nowrap;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="viewDocPdf(<?= $d['id'] ?>, '<?= htmlspecialchars($d['arquivo_nome'] ?? 'documento.pdf', ENT_QUOTES) ?>')" title="Visualizar">Ver</button>
                    <a href="/documentos/download/<?= $d['id'] ?>" class="btn btn-outline btn-sm" title="Download">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <?php if (($totalPages ?? 1) > 1): ?>
    <div style="padding:12px 20px; display:flex; justify-content:center; align-items:center; gap:6px;">
        <?php if ($page > 1): ?>
        <a href="/documentos?page=1&q=<?= urlencode($search ?? '') ?>&status=<?= urlencode($status ?? '') ?>&categoria=<?= urlencode($categoria ?? '') ?>" class="btn btn-outline btn-sm">&laquo;</a>
        <a href="/documentos?page=<?= $page - 1 ?>&q=<?= urlencode($search ?? '') ?>&status=<?= urlencode($status ?? '') ?>&categoria=<?= urlencode($categoria ?? '') ?>" class="btn btn-outline btn-sm">&lsaquo;</a>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 3);
        $end = min($totalPages, $page + 3);
        if ($start > 1) echo '<span style="color:#999;padding:0 4px;">...</span>';
        for ($p = $start; $p <= $end; $p++):
        ?>
        <a href="/documentos?page=<?= $p ?>&q=<?= urlencode($search ?? '') ?>&status=<?= urlencode($status ?? '') ?>&categoria=<?= urlencode($categoria ?? '') ?>"
           class="btn btn-sm <?= $p == $page ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($end < $totalPages) echo '<span style="color:#999;padding:0 4px;">...</span>'; ?>

        <?php if ($page < $totalPages): ?>
        <a href="/documentos?page=<?= $page + 1 ?>&q=<?= urlencode($search ?? '') ?>&status=<?= urlencode($status ?? '') ?>&categoria=<?= urlencode($categoria ?? '') ?>" class="btn btn-outline btn-sm">&rsaquo;</a>
        <a href="/documentos?page=<?= $totalPages ?>&q=<?= urlencode($search ?? '') ?>&status=<?= urlencode($status ?? '') ?>&categoria=<?= urlencode($categoria ?? '') ?>" class="btn btn-outline btn-sm">&raquo;</a>
        <?php endif; ?>

        <span style="color:#999; font-size:12px; margin-left:12px;">Pagina <?= $page ?> de <?= $totalPages ?></span>
    </div>
    <?php endif; ?>
</div>

<!-- PDF Viewer Modal -->
<div id="doc-pdf-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:1000; justify-content:center; align-items:center;">
    <div style="background:white; border-radius:8px; width:90%; max-width:900px; height:85vh; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 20px; border-bottom:1px solid #e5e7eb;">
            <span id="doc-pdf-title" style="font-weight:600; font-size:14px; color:#001e21;"></span>
            <div style="display:flex; gap:8px;">
                <a id="doc-pdf-download" href="#" class="btn btn-outline btn-sm">Baixar</a>
                <button type="button" class="btn btn-outline btn-sm" onclick="closeDocPdfModal()" style="font-size:18px; line-height:1; padding:4px 10px;">&times;</button>
            </div>
        </div>
        <div style="flex:1; overflow:hidden;">
            <iframe id="doc-pdf-iframe" style="width:100%; height:100%; border:none;"></iframe>
        </div>
    </div>
</div>

<?php if (empty($documentos) && empty($search) && empty($status) && empty($categoria)): ?>
<div style="background:#fff; border-radius:10px; padding:40px; text-align:center; margin-top:16px; box-shadow:0 1px 4px rgba(0,30,32,0.08);">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#00b279" stroke-width="1.5" style="margin-bottom:16px;">
        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
        <line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
    </svg>
    <h3 style="color:#001e21; margin-bottom:8px;">Como funciona o Controle Documental</h3>
    <p style="color:#6b7280; max-width:500px; margin:0 auto 20px; line-height:1.6;">
        Os documentos (ASO, Ficha EPI, Ordem de Servico, etc.) sao vinculados a cada <strong>colaborador</strong>.<br>
        Para enviar um documento, acesse a ficha do colaborador desejado.
    </p>
    <a href="/colaboradores" class="btn btn-primary">Ir para Colaboradores</a>
</div>
<?php endif; ?>

<script>
(function() {
    var input = document.getElementById('doc-search-input');
    var liveResults = document.getElementById('doc-live-results');
    var liveList = document.getElementById('doc-live-list');
    var debounceTimer = null;

    input.addEventListener('input', function() {
        var q = this.value.trim();
        clearTimeout(debounceTimer);

        if (q.length < 2) {
            liveResults.style.display = 'none';
            return;
        }

        debounceTimer = setTimeout(function() {
            fetch('/colaboradores?q=' + encodeURIComponent(q) + '&format=json')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.length === 0) {
                        liveList.innerHTML = '<div style="padding:12px 16px;color:#999;font-size:13px;">Nenhum colaborador encontrado</div>';
                    } else {
                        liveList.innerHTML = data.map(function(c) {
                            return '<a href="/colaboradores/' + c.id + '" style="display:flex;justify-content:space-between;padding:10px 16px;border-bottom:1px solid #f0f0f0;color:#001e21;text-decoration:none;font-size:13px;" onmouseover="this.style.background=\'#f5f5f0\'" onmouseout="this.style.background=\'white\'">' +
                                '<span style="font-weight:600;">' + c.nome_completo + '</span>' +
                                '<span style="color:#999;">' + (c.cargo || '-') + '</span>' +
                                '</a>';
                        }).join('');
                    }
                    liveResults.style.display = 'block';
                })
                .catch(function() { liveResults.style.display = 'none'; });
        }, 300);
    });

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') liveResults.style.display = 'none';
    });

    document.addEventListener('click', function(e) {
        if (!liveResults.contains(e.target) && e.target !== input) {
            liveResults.style.display = 'none';
        }
    });
})();

function viewDocPdf(docId, filename) {
    document.getElementById('doc-pdf-title').textContent = filename;
    document.getElementById('doc-pdf-download').href = '/documentos/download/' + docId;
    document.getElementById('doc-pdf-iframe').src = '/documentos/visualizar/' + docId;
    document.getElementById('doc-pdf-modal').style.display = 'flex';
}

function closeDocPdfModal() {
    document.getElementById('doc-pdf-modal').style.display = 'none';
    document.getElementById('doc-pdf-iframe').src = '';
}

document.getElementById('doc-pdf-modal').addEventListener('click', function(e) {
    if (e.target === this) closeDocPdfModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDocPdfModal();
});
</script>
