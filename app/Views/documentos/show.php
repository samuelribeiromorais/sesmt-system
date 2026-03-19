<div class="table-container">
    <div class="table-header">
        <span class="table-title">Detalhes do Documento</span>
        <a href="/documentos/download/<?= $doc['id'] ?>" class="btn btn-primary btn-sm">Baixar PDF</a>
    </div>
    <div style="padding:20px; display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
        <div><strong>Arquivo:</strong> <?= htmlspecialchars($doc['arquivo_nome']) ?></div>
        <div><strong>Emissao:</strong> <?= date('d/m/Y', strtotime($doc['data_emissao'])) ?></div>
        <div><strong>Validade:</strong> <?= $doc['data_validade'] ? date('d/m/Y', strtotime($doc['data_validade'])) : 'N/A' ?></div>
        <div><strong>Status:</strong> <span class="badge badge-<?= $doc['status'] ?>"><?= ucfirst(str_replace('_',' ',$doc['status'])) ?></span></div>
        <div><strong>Hash:</strong> <code style="font-size:11px;"><?= substr($doc['arquivo_hash'], 0, 16) ?>...</code></div>
        <?php if ($doc['observacoes']): ?>
        <div style="grid-column:1/-1;"><strong>Observacoes:</strong> <?= htmlspecialchars($doc['observacoes']) ?></div>
        <?php endif; ?>
    </div>
</div>
