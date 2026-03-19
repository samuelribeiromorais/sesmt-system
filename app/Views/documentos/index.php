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
        <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            <form method="GET" action="/documentos" style="display:flex; gap:8px; flex-wrap:wrap;">
                <div class="search-box">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="q" class="form-control" placeholder="Buscar colaborador..." value="<?= htmlspecialchars($search ?? '') ?>">
                </div>
                <select name="categoria" class="form-control" style="width:auto;" onchange="this.form.submit()">
                    <option value="">Todas categorias</option>
                    <option value="aso" <?= ($categoria ?? '') === 'aso' ? 'selected' : '' ?>>ASO</option>
                    <option value="epi" <?= ($categoria ?? '') === 'epi' ? 'selected' : '' ?>>EPI</option>
                    <option value="os" <?= ($categoria ?? '') === 'os' ? 'selected' : '' ?>>Ordem de Servico</option>
                    <option value="treinamento" <?= ($categoria ?? '') === 'treinamento' ? 'selected' : '' ?>>Treinamento</option>
                    <option value="anuencia" <?= ($categoria ?? '') === 'anuencia' ? 'selected' : '' ?>>Anuencia</option>
                    <option value="outro" <?= ($categoria ?? '') === 'outro' ? 'selected' : '' ?>>Outros</option>
                </select>
                <select name="status" class="form-control" style="width:auto;" onchange="this.form.submit()">
                    <option value="">Todos status</option>
                    <option value="vigente" <?= ($status ?? '') === 'vigente' ? 'selected' : '' ?>>Vigentes</option>
                    <option value="proximo_vencimento" <?= ($status ?? '') === 'proximo_vencimento' ? 'selected' : '' ?>>Vencendo</option>
                    <option value="vencido" <?= ($status ?? '') === 'vencido' ? 'selected' : '' ?>>Vencidos</option>
                </select>
                <button type="submit" class="btn btn-outline btn-sm">Filtrar</button>
            </form>
        </div>
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
                <td><?= htmlspecialchars($d['tipo_nome']) ?></td>
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
                <td><?= date('d/m/Y', strtotime($d['data_emissao'])) ?></td>
                <td><?= $d['data_validade'] ? date('d/m/Y', strtotime($d['data_validade'])) : '—' ?></td>
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
                <td style="display:flex; gap:4px;">
                    <a href="/documentos/download/<?= $d['id'] ?>" class="btn btn-outline btn-sm" title="Download">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    </a>
                    <a href="/colaboradores/<?= $d['colaborador_id'] ?>" class="btn btn-outline btn-sm" title="Ver ficha">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <?php if (($totalPages ?? 1) > 1): ?>
    <div style="padding:16px 20px; display:flex; justify-content:center; gap:4px;">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="/documentos?page=<?= $p ?>&q=<?= urlencode($search ?? '') ?>&status=<?= urlencode($status ?? '') ?>&categoria=<?= urlencode($categoria ?? '') ?>"
           class="btn btn-sm <?= $p == ($page ?? 1) ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
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
<?php else: ?>
<p style="color:#6b7280; font-size:13px; margin-top:16px;">
    Para enviar um novo documento, acesse a <a href="/colaboradores" style="color:var(--c-primary);font-weight:600;">ficha do colaborador</a> e clique em <strong>"Upload Documento"</strong>.
</p>
<?php endif; ?>
