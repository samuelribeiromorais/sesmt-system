<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <h2 style="margin:0;">Aprovações Pendentes <span style="font-size:16px; color:var(--c-gray); font-weight:400;">(<?= number_format($total, 0, ',', '.') ?> documentos)</span></h2>
    <a href="/dashboard" class="btn btn-outline btn-sm">← Dashboard</a>
</div>

<!-- Busca -->
<form method="GET" action="/aprovacoes" style="margin-bottom:20px; display:flex; gap:8px; max-width:480px;">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar por colaborador ou tipo..." class="form-control" style="flex:1;">
    <button type="submit" class="btn btn-primary btn-sm">Buscar</button>
    <?php if ($search): ?>
    <a href="/aprovacoes" class="btn btn-outline btn-sm">Limpar</a>
    <?php endif; ?>
</form>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Colaborador</th>
                <th>Documento</th>
                <th>Emissão</th>
                <th>Enviado em</th>
                <th style="text-align:center;">Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($aprovacoes)): ?>
            <tr><td colspan="5" style="text-align:center; color:var(--c-gray); padding:32px;">
                <?= $search ? 'Nenhuma aprovação encontrada para esta busca.' : 'Nenhum documento aguardando aprovação.' ?>
            </td></tr>
        <?php else: ?>
            <?php foreach ($aprovacoes as $ap): ?>
            <tr>
                <td><a href="/colaboradores/<?= $ap['colaborador_id'] ?>" style="color:var(--c-primary); font-weight:600;"><?= htmlspecialchars($ap['nome_completo']) ?></a></td>
                <td style="font-size:13px;"><?= htmlspecialchars($ap['tipo_nome']) ?></td>
                <td style="font-size:13px;"><?= $ap['data_emissao'] ? date('d/m/Y', strtotime($ap['data_emissao'])) : '—' ?></td>
                <td style="font-size:13px;"><?= date('d/m/Y H:i', strtotime($ap['criado_em'])) ?></td>
                <td style="text-align:center; white-space:nowrap;">
                    <form method="POST" action="/documentos/<?= $ap['id'] ?>/aprovar" style="display:inline-flex; gap:4px; align-items:center;">
                        <?= \App\Core\View::csrfField() ?>
                        <input type="hidden" name="decisao" value="aprovado">
                        <button type="submit" class="btn btn-sm" style="padding:4px 12px; font-size:12px; background:#059669; color:white;">Aprovar</button>
                    </form>
                    <button type="button" class="btn btn-sm" style="padding:4px 12px; font-size:12px; background:#dc2626; color:white;"
                            onclick="rejeitarDoc(<?= $ap['id'] ?>)">Rejeitar</button>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Paginação -->
<?php if ($totalPages > 1): ?>
<div style="display:flex; gap:4px; justify-content:center; margin-top:16px; flex-wrap:wrap;">
    <?php if ($page > 1): ?>
    <a href="/aprovacoes?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>" class="btn btn-outline btn-sm">‹</a>
    <?php endif; ?>
    <?php for ($p = max(1,$page-3); $p <= min($totalPages,$page+3); $p++): ?>
    <a href="/aprovacoes?page=<?= $p ?>&q=<?= urlencode($search) ?>" class="btn btn-sm <?= $p==$page?'btn-primary':'btn-outline' ?>"><?= $p ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
    <a href="/aprovacoes?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>" class="btn btn-outline btn-sm">›</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Modal de rejeição -->
<div id="rejeitar-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
    <div style="background:white; border-radius:8px; padding:24px; width:400px; max-width:90vw;">
        <h3 style="margin-bottom:12px; color:#dc2626;">Rejeitar Documento</h3>
        <form method="POST" id="rejeitar-form">
            <?= \App\Core\View::csrfField() ?>
            <input type="hidden" name="decisao" value="rejeitado">
            <label style="font-size:13px; font-weight:600;">Motivo da rejeição:</label>
            <textarea name="aprovacao_obs" rows="3" style="width:100%; margin-top:6px; padding:8px; border:1px solid #ddd; border-radius:4px; font-size:13px;" placeholder="Ex: Documento ilegível, data incorreta..." required></textarea>
            <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:16px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('rejeitar-modal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn btn-sm" style="background:#dc2626; color:white;">Confirmar Rejeição</button>
            </div>
        </form>
    </div>
</div>
<script>
function rejeitarDoc(docId) {
    const modal = document.getElementById('rejeitar-modal');
    const form  = document.getElementById('rejeitar-form');
    form.action = '/documentos/' + docId + '/aprovar';
    modal.style.display = 'flex';
}
</script>
