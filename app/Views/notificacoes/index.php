<div class="table-container">
    <div class="table-header">
        <span class="table-title">Notificacoes (<?= $total ?>)</span>
        <?php if ($total > 0): ?>
        <form method="POST" action="/notificacoes/marcar-todas" style="display:inline;">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
            <button type="submit" class="btn btn-outline btn-sm">Marcar todas como lidas</button>
        </form>
        <?php endif; ?>
    </div>

    <div style="padding:0;">
        <?php if (empty($notificacoes)): ?>
        <div style="text-align:center; color:#6b7280; padding:40px;">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px; opacity:0.5;">
                <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>
            </svg>
            <p>Nenhuma notificacao encontrada.</p>
        </div>
        <?php else: ?>
        <?php foreach ($notificacoes as $n): ?>
        <div style="display:flex; align-items:flex-start; gap:12px; padding:14px 20px; border-bottom:1px solid var(--c-border); background:<?= $n['lida'] ? 'transparent' : 'rgba(0,178,121,0.04)' ?>;">
            <div style="flex-shrink:0; margin-top:2px;">
                <?php
                $iconColor = match($n['tipo'] ?? 'info') {
                    'alerta' => 'var(--c-warning)',
                    'erro', 'vencimento' => 'var(--c-danger)',
                    'sucesso' => 'var(--c-accent)',
                    default => 'var(--c-link)',
                };
                ?>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="<?= $iconColor ?>" stroke-width="2">
                    <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>
                </svg>
            </div>
            <div style="flex:1; min-width:0;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                    <strong style="font-size:14px; color:var(--c-text);">
                        <?php if (!$n['lida']): ?><span style="display:inline-block; width:8px; height:8px; background:var(--c-accent); border-radius:50%; margin-right:6px;"></span><?php endif; ?>
                        <?= htmlspecialchars($n['titulo']) ?>
                    </strong>
                    <span style="font-size:12px; color:var(--c-gray); white-space:nowrap; margin-left:12px;">
                        <?= date('d/m/Y H:i', strtotime($n['criado_em'])) ?>
                    </span>
                </div>
                <p style="font-size:13px; color:var(--c-gray); margin:0 0 6px;"><?= htmlspecialchars($n['mensagem']) ?></p>
                <div style="display:flex; gap:8px; align-items:center;">
                    <?php if (!empty($n['link'])): ?>
                    <a href="<?= htmlspecialchars($n['link']) ?>" class="btn btn-outline btn-sm" style="font-size:12px; padding:4px 10px;">Ver detalhes</a>
                    <?php endif; ?>
                    <?php if (!$n['lida']): ?>
                    <form method="POST" action="/notificacoes/<?= $n['id'] ?>/lida" style="display:inline;">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                        <button type="submit" class="btn btn-sm" style="font-size:12px; padding:4px 10px; background:transparent; color:var(--c-gray); border:1px solid var(--c-border);">Marcar como lida</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div style="padding:12px 20px; display:flex; justify-content:center; align-items:center; gap:6px;">
        <?php if ($page > 1): ?>
        <a href="/notificacoes?page=<?= $page - 1 ?>" class="btn btn-outline btn-sm">&lsaquo;</a>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 3);
        $end = min($totalPages, $page + 3);
        for ($p = $start; $p <= $end; $p++):
        ?>
        <a href="/notificacoes?page=<?= $p ?>" class="btn btn-sm <?= $p == $page ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
        <a href="/notificacoes?page=<?= $page + 1 ?>" class="btn btn-outline btn-sm">&rsaquo;</a>
        <?php endif; ?>

        <span style="color:#999; font-size:12px; margin-left:12px;">Pagina <?= $page ?> de <?= $totalPages ?></span>
    </div>
    <?php endif; ?>
</div>
