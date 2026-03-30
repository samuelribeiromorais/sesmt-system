<div style="margin-bottom:16px;">
    <a href="/documentos/<?= $doc['id'] ?>" class="btn btn-outline btn-sm">&larr; Voltar ao Documento</a>
</div>

<div class="table-container">
    <div class="table-header">
        <span class="table-title">Assinar Documento</span>
    </div>
    <div style="padding:20px;">
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:24px; padding:16px; background:#f9fafb; border-radius:8px;">
            <div><strong>Arquivo:</strong> <?= htmlspecialchars($doc['arquivo_nome']) ?></div>
            <div><strong>Emissão:</strong> <?= date('d/m/Y', strtotime($doc['data_emissao'])) ?></div>
            <div><strong>Status:</strong> <span class="badge badge-<?= $doc['status'] ?>"><?= ucfirst(str_replace('_', ' ', $doc['status'])) ?></span></div>
            <div><strong>Hash do Arquivo:</strong> <code style="font-size:11px;"><?= substr($doc['arquivo_hash'], 0, 16) ?>...</code></div>
        </div>

        <form method="POST" action="/documentos/<?= $doc['id'] ?>/assinar">
            <?= \App\Core\View::csrfField() ?>
            <div style="margin-bottom:16px;">
                <label for="assinado_por" style="display:block; font-weight:600; margin-bottom:6px;">Nome do Assinante</label>
                <input type="text" name="assinado_por" id="assinado_por" required
                       placeholder="Nome completo do assinante"
                       style="width:100%; max-width:400px; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:14px;">
            </div>
            <p style="font-size:13px; color:#6b7280; margin-bottom:16px;">
                A assinatura digital sera gerada como um hash SHA-256 do conteudo do arquivo, nome do assinante e data/hora da assinatura.
            </p>
            <button type="submit" class="btn btn-primary" data-confirm="Confirma a assinatura deste documento? Esta acao não pode ser desfeita.">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:4px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                Assinar Documento
            </button>
        </form>
    </div>
</div>
