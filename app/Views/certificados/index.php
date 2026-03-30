<!-- Contadores (clicaveis para filtrar) -->
<div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin-bottom:24px;">
    <a href="/certificados" class="stat-card stat-card-clickable <?= empty($status ?? '') ? 'stat-card-active' : '' ?>" style="text-decoration:none; color:inherit;">
        <div class="stat-value"><?= $contadores['total'] ?? 0 ?></div>
        <div class="stat-label">Total de Certificados</div>
    </a>
    <a href="/certificados?status=vigente" class="stat-card stat-card-clickable <?= ($status ?? '') === 'vigente' ? 'stat-card-active' : '' ?>" style="border-left:4px solid #00b279; text-decoration:none; color:inherit;">
        <div class="stat-value" style="color:#00b279;"><?= $contadores['vigente'] ?? 0 ?></div>
        <div class="stat-label">Vigentes</div>
    </a>
    <a href="/certificados?status=proximo_vencimento" class="stat-card stat-card-clickable <?= ($status ?? '') === 'proximo_vencimento' ? 'stat-card-active' : '' ?>" style="border-left:4px solid #f39c12; text-decoration:none; color:inherit;">
        <div class="stat-value" style="color:#f39c12;"><?= $contadores['proximo_vencimento'] ?? 0 ?></div>
        <div class="stat-label">Vencendo em 30 dias</div>
    </a>
    <a href="/certificados?status=vencido" class="stat-card stat-card-clickable <?= ($status ?? '') === 'vencido' ? 'stat-card-active' : '' ?>" style="border-left:4px solid #e74c3c; text-decoration:none; color:inherit;">
        <div class="stat-value" style="color:#e74c3c;"><?= $contadores['vencido'] ?? 0 ?></div>
        <div class="stat-label">Vencidos</div>
    </a>
</div>

<!-- Emissão de Certificados -->
<div class="table-container">
    <div class="table-header">
        <span class="table-title">Emissão de Certificados</span>
    </div>
    <div style="padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <p style="color:var(--c-gray);font-size:14px;margin:0;">Selecione um colaborador para emitir ou visualizar certificados.</p>
            <label style="font-size:13px;color:var(--c-gray);cursor:pointer;display:flex;align-items:center;gap:4px;">
                <input type="checkbox" onchange="window.location.href='/certificados?mostrar_inativos='+(this.checked?'1':'0')" <?= ($mostrarInativos ?? false) ? 'checked' : '' ?>>
                Incluir inativos
            </label>
        </div>

        <div class="form-group">
            <label>Colaborador</label>
            <select class="form-control" onchange="if(this.value) window.location='/certificados/emitir/'+this.value">
                <option value="">-- Selecionar Colaborador --</option>
                <?php foreach ($colaboradores as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome_completo']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<!-- Certificados Emitidos -->
<div class="table-container" style="margin-top:24px;">
    <div class="table-header">
        <span class="table-title">Certificados Emitidos<?= !empty($status) ? ' — ' . match($status) { 'vigente' => 'Vigentes', 'proximo_vencimento' => 'Vencendo', 'vencido' => 'Vencidos', default => '' } : '' ?></span>
        <div style="display:flex; gap:8px; align-items:center;">
            <?php if (!empty($status)): ?>
            <a href="/certificados" class="btn btn-outline btn-sm">Limpar filtro</a>
            <?php endif; ?>
            <a href="/exportar/certificados<?= !empty($status) ? '?status=' . urlencode($status) : '' ?>" class="btn btn-outline btn-sm" title="Exportar Excel">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Excel
            </a>
        </div>
    </div>

    <!-- Search bar -->
    <div style="padding:12px 20px; border-bottom:1px solid var(--c-border); background:var(--c-bg);">
        <form method="GET" action="/certificados" style="display:flex; gap:10px; align-items:center;">
            <div class="search-box" style="flex:1; min-width:220px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="q" class="form-control" placeholder="Buscar por colaborador ou certificado..." value="<?= htmlspecialchars($search ?? '') ?>" autocomplete="off">
            </div>
            <input type="hidden" name="status" value="<?= htmlspecialchars($status ?? '') ?>">
            <button type="submit" class="btn btn-secondary btn-sm">Buscar</button>
            <?php if ($search): ?>
            <a href="/certificados<?= $status ? '?status='.urlencode($status) : '' ?>" class="btn btn-outline btn-sm">Limpar</a>
            <?php endif; ?>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>Colaborador</th>
                <th>Certificado</th>
                <th>Duracao</th>
                <th>Emissão</th>
                <th>Validade</th>
                <th>Status</th>
                <th>Acoes</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($certificadosList)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--c-gray);padding:32px;">
                <?php if (!empty($status) || !empty($search)): ?>
                    Nenhum certificado encontrado com os filtros selecionados.
                <?php else: ?>
                    Nenhum certificado emitido ainda.
                <?php endif; ?>
            </td></tr>
        <?php else: ?>
            <?php foreach ($certificadosList as $cert): ?>
            <tr>
                <td>
                    <a href="/colaboradores/<?= $cert['colaborador_id'] ?>" style="color:var(--c-primary);font-weight:600;">
                        <?= htmlspecialchars($cert['nome_completo']) ?>
                    </a>
                </td>
                <td style="font-size:13px;">
                    <strong><?= htmlspecialchars($cert['tipo_codigo']) ?></strong>
                    <span style="color:var(--c-gray);"> — <?= htmlspecialchars($cert['tipo_titulo']) ?></span>
                </td>
                <td style="font-size:13px;"><?= htmlspecialchars($cert['duracao'] ?? '') ?></td>
                <td style="font-size:13px;"><?= date('d/m/Y', strtotime($cert['data_emissao'])) ?></td>
                <td style="font-size:13px;"><?= $cert['data_validade'] ? date('d/m/Y', strtotime($cert['data_validade'])) : '—' ?></td>
                <td>
                    <?php
                    $badgeClass = match($cert['status']) {
                        'vigente' => 'badge-vigente',
                        'proximo_vencimento' => 'badge-proximo_vencimento',
                        'vencido' => 'badge-vencido',
                        default => ''
                    };
                    $statusLabel = match($cert['status']) {
                        'vigente' => 'Vigente',
                        'proximo_vencimento' => 'Vencendo',
                        'vencido' => 'Vencido',
                        default => ucfirst($cert['status'])
                    };
                    ?>
                    <span class="badge <?= $badgeClass ?>"><?= $statusLabel ?></span>
                </td>
                <td style="white-space:nowrap;">
                    <a href="/certificados/preview/<?= $cert['id'] ?>" class="btn btn-outline btn-sm" target="_blank" title="Visualizar">Ver</a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if (($totalPages ?? 1) > 1): ?>
    <div style="padding:12px 20px; display:flex; justify-content:center; align-items:center; gap:6px;">
        <?php if ($page > 1): ?>
        <a href="/certificados?page=1&status=<?= urlencode($status ?? '') ?>&q=<?= urlencode($search ?? '') ?>" class="btn btn-outline btn-sm">&laquo;</a>
        <a href="/certificados?page=<?= $page - 1 ?>&status=<?= urlencode($status ?? '') ?>&q=<?= urlencode($search ?? '') ?>" class="btn btn-outline btn-sm">&lsaquo;</a>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 3);
        $end = min($totalPages, $page + 3);
        if ($start > 1) echo '<span style="color:var(--c-gray);padding:0 4px;">...</span>';
        for ($p = $start; $p <= $end; $p++):
        ?>
        <a href="/certificados?page=<?= $p ?>&status=<?= urlencode($status ?? '') ?>&q=<?= urlencode($search ?? '') ?>"
           class="btn btn-sm <?= $p == $page ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($end < $totalPages) echo '<span style="color:var(--c-gray);padding:0 4px;">...</span>'; ?>

        <?php if ($page < $totalPages): ?>
        <a href="/certificados?page=<?= $page + 1 ?>&status=<?= urlencode($status ?? '') ?>&q=<?= urlencode($search ?? '') ?>" class="btn btn-outline btn-sm">&rsaquo;</a>
        <a href="/certificados?page=<?= $totalPages ?>&status=<?= urlencode($status ?? '') ?>&q=<?= urlencode($search ?? '') ?>" class="btn btn-outline btn-sm">&raquo;</a>
        <?php endif; ?>

        <span style="color:var(--c-gray); font-size:12px; margin-left:12px;">Pagina <?= $page ?> de <?= $totalPages ?></span>
    </div>
    <?php endif; ?>
</div>

<!-- Tipos de Certificado -->
<div class="table-container" style="margin-top:24px;">
    <div class="table-header"><span class="table-title">Tipos de Certificado Disponiveis (<?= count($tipos) ?>)</span></div>
    <table>
        <thead><tr><th>Código</th><th>Título</th><th>Duracao</th><th>Validade</th><th>Anuencia</th></tr></thead>
        <tbody>
        <?php foreach ($tipos as $t): ?>
        <tr>
            <td style="font-weight:600;"><?= htmlspecialchars($t['codigo']) ?></td>
            <td style="font-size:13px;"><?= htmlspecialchars($t['titulo']) ?></td>
            <td><?= htmlspecialchars($t['duracao']) ?></td>
            <td><?= $t['validade_meses'] ?> meses</td>
            <td><?= $t['tem_anuencia'] ? '<span class="badge badge-vigente">Sim</span>' : '-' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
