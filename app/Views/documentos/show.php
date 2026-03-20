<div class="table-container">
    <div class="table-header">
        <span class="table-title">Detalhes do Documento</span>
        <div style="display:flex; gap:8px;">
            <?php if (empty($doc['assinatura_digital'])): ?>
            <a href="/documentos/<?= $doc['id'] ?>/assinar" class="btn btn-outline btn-sm" title="Assinar documento">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:2px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                Assinar
            </a>
            <?php endif; ?>
            <a href="/documentos/<?= $doc['id'] ?>/versoes" class="btn btn-outline btn-sm">
                Versoes<?php if (!empty($versionCount) && $versionCount > 1): ?> (<?= $versionCount ?>)<?php endif; ?>
            </a>
            <a href="/documentos/download/<?= $doc['id'] ?>" class="btn btn-primary btn-sm">Baixar PDF</a>
        </div>
    </div>
    <div style="padding:20px; display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
        <div><strong>Arquivo:</strong> <?= htmlspecialchars($doc['arquivo_nome']) ?></div>
        <div><strong>Emissao:</strong> <?= date('d/m/Y', strtotime($doc['data_emissao'])) ?></div>
        <div><strong>Validade:</strong> <?= $doc['data_validade'] ? date('d/m/Y', strtotime($doc['data_validade'])) : 'N/A' ?></div>
        <div><strong>Status:</strong> <span class="badge badge-<?= $doc['status'] ?>"><?= ucfirst(str_replace('_',' ',$doc['status'])) ?></span></div>
        <div><strong>Hash:</strong> <code style="font-size:11px;"><?= substr($doc['arquivo_hash'], 0, 16) ?>...</code></div>
        <div>
            <strong>Versao:</strong> v<?= (int) ($doc['versao'] ?? 1) ?>
            <?php if (!empty($doc['documento_pai_id'])): ?>
            <span style="font-size:12px; color:#6b7280;">(atualizado)</span>
            <?php endif; ?>
        </div>
        <div>
            <strong>Assinatura:</strong>
            <?php if (!empty($doc['assinatura_digital'])): ?>
            <span style="display:inline-flex; align-items:center; gap:4px; color:#059669;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                Assinado
            </span>
            <?php else: ?>
            <span style="color:#9ca3af;">Nao assinado</span>
            <?php endif; ?>
        </div>
        <?php if (!empty($doc['assinatura_digital'])): ?>
        <div><strong>Assinado por:</strong> <?= htmlspecialchars($doc['assinado_por']) ?></div>
        <div><strong>Assinado em:</strong> <?= date('d/m/Y H:i', strtotime($doc['assinado_em'])) ?></div>
        <div style="grid-column:1/-1;"><strong>Hash da Assinatura:</strong> <code style="font-size:11px;"><?= $doc['assinatura_digital'] ?></code></div>
        <?php endif; ?>
        <?php if ($doc['observacoes']): ?>
        <div style="grid-column:1/-1;"><strong>Observacoes:</strong> <?= htmlspecialchars($doc['observacoes']) ?></div>
        <?php endif; ?>
    </div>
</div>
