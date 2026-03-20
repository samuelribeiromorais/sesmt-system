<?php
function statusSemaforo($status) {
    return match($status) {
        'vigente' => 'verde',
        'proximo_vencimento' => 'amarelo',
        'vencido' => 'vermelho',
        default => 'cinza',
    };
}
?>

<!-- Resumo de Conformidade -->
<div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-value"><?= ($docsVigentes + $certsVigentes) ?></div>
        <div class="stat-label">Em dia</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #f39c12;">
        <div class="stat-value" style="color:#f39c12;"><?= ($docsVencendo + $certsVencendo) ?></div>
        <div class="stat-label">Vencendo em 30 dias</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #e74c3c;">
        <div class="stat-value" style="color:#e74c3c;"><?= ($docsVencidos + $certsVencidos) ?></div>
        <div class="stat-label">Vencidos</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #00b279;">
        <div class="stat-value" style="color:#00b279;"><?= round($taxaConformidade) ?>%</div>
        <div class="stat-label">Conformidade</div>
    </div>
</div>

<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px;">
    <div>
        <h2 style="font-size:24px; margin-bottom:4px;"><?= htmlspecialchars($colab['nome_completo']) ?></h2>
        <div style="display:flex; gap:16px; color:var(--c-gray); font-size:14px;">
            <span>CPF: <?= htmlspecialchars($cpfDisplay) ?></span>
            <span>Cargo: <?= htmlspecialchars($colab['cargo'] ?? $colab['funcao'] ?? '-') ?></span>
            <?php if ($colab['cliente_nome']): ?>
            <span>Cliente: <?= htmlspecialchars($colab['cliente_nome']) ?></span>
            <?php endif; ?>
            <?php if ($colab['obra_nome']): ?>
            <span>Obra: <?= htmlspecialchars($colab['obra_nome']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div style="display:flex; gap:8px; align-items:center;">
        <span class="badge badge-<?= $colab['status'] ?>"><?= ucfirst($colab['status']) ?></span>
        <?php if (!empty($documentos)): ?>
        <a href="/colaboradores/<?= $colab['id'] ?>/download-zip" class="btn btn-outline btn-sm" title="Baixar todos os documentos em ZIP">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            ZIP
        </a>
        <?php endif; ?>
        <?php if (!$isReadOnly): ?>
        <a href="/colaboradores/<?= $colab['id'] ?>/editar" class="btn btn-outline btn-sm">Editar</a>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
    <!-- Certificados -->
    <div class="table-container">
        <div class="table-header">
            <span class="table-title">Certificados</span>
            <?php if (!$isReadOnly): ?>
            <a href="/certificados/emitir/<?= $colab['id'] ?>" class="btn btn-secondary btn-sm">Emitir</a>
            <?php endif; ?>
        </div>
        <table>
            <thead><tr><th>Tipo</th><th>Validade</th><th>Status</th></tr></thead>
            <tbody>
                <?php if (empty($certificados)): ?>
                <tr><td colspan="3" style="text-align:center;color:var(--c-gray);">Nenhum certificado</td></tr>
                <?php else: ?>
                <?php foreach ($certificados as $cert): ?>
                <tr>
                    <td>
                        <span class="semaforo semaforo-<?= statusSemaforo($cert['status']) ?>"></span>
                        <?= htmlspecialchars($cert['codigo']) ?>
                    </td>
                    <td><?= $cert['data_validade'] ? date('d/m/Y', strtotime($cert['data_validade'])) : '-' ?></td>
                    <td><span class="badge badge-<?= $cert['status'] ?>"><?= ucfirst(str_replace('_', ' ', $cert['status'])) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Documentos -->
    <div class="table-container">
        <div class="table-header">
            <span class="table-title">Documentos (<?= count($documentos) ?>)</span>
            <?php if (!$isReadOnly): ?>
            <a href="/documentos/upload/<?= $colab['id'] ?>" class="btn btn-primary btn-sm">Upload</a>
            <?php endif; ?>
        </div>
        <table>
            <thead><tr><th>Tipo</th><th>Emissao</th><th>Validade</th><th>Acoes</th></tr></thead>
            <tbody>
                <?php if (empty($documentos)): ?>
                <tr><td colspan="4" style="text-align:center;color:var(--c-gray);">Nenhum documento</td></tr>
                <?php else: ?>
                <?php foreach ($documentos as $doc): ?>
                <tr>
                    <td>
                        <span class="semaforo semaforo-<?= statusSemaforo($doc['status']) ?>"></span>
                        <?= htmlspecialchars($doc['tipo_nome']) ?>
                        <?php if (!empty($doc['assinatura_digital'])): ?>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" style="vertical-align:middle; margin-left:4px;" title="Documento assinado por <?= htmlspecialchars($doc['assinado_por'] ?? '') ?>"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:13px;"><?= $doc['data_emissao'] ? date('d/m/Y', strtotime($doc['data_emissao'])) : '-' ?></td>
                    <td style="font-size:13px;">
                        <?php if ($doc['data_validade']): ?>
                            <?= date('d/m/Y', strtotime($doc['data_validade'])) ?>
                        <?php elseif (($doc['categoria'] ?? '') === 'epi' || ($doc['categoria'] ?? '') === 'os'): ?>
                            <span style="color:#6b7280; font-size:11px;" title="Atualizado em <?= date('d/m/Y', strtotime($doc['data_emissao'])) ?>">N/A</span>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="viewPdf(<?= $doc['id'] ?>, '<?= htmlspecialchars($doc['arquivo_nome'], ENT_QUOTES) ?>')" title="Visualizar">Ver</button>
                        <a href="/documentos/download/<?= $doc['id'] ?>" class="btn btn-outline btn-sm" title="Baixar">PDF</a>
                        <?php if (!$isReadOnly): ?>
                        <form method="POST" action="/documentos/<?= $doc['id'] ?>/excluir" style="display:inline;">
                            <?= \App\Core\View::csrfField() ?>
                            <button type="submit" class="btn btn-danger btn-sm" data-confirm="Excluir este documento?">X</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- PDF Viewer Modal -->
<div id="pdf-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:1000; justify-content:center; align-items:center;">
    <div style="background:var(--c-white); border-radius:8px; width:90%; max-width:900px; height:85vh; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 20px; border-bottom:1px solid var(--c-border);">
            <span id="pdf-title" style="font-weight:600; font-size:14px; color:var(--c-text);"></span>
            <div style="display:flex; gap:8px;">
                <a id="pdf-download" href="#" class="btn btn-outline btn-sm">Baixar</a>
                <button type="button" class="btn btn-outline btn-sm" onclick="closePdfModal()" style="font-size:18px; line-height:1; padding:4px 10px;">&times;</button>
            </div>
        </div>
        <div style="flex:1; overflow:hidden;">
            <iframe id="pdf-iframe" style="width:100%; height:100%; border:none;"></iframe>
        </div>
    </div>
</div>

<!-- Info adicional -->
<div class="table-container" style="margin-top:24px;">
    <div class="table-header">
        <span class="table-title">Dados do Colaborador</span>
    </div>
    <div style="padding:20px; display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
        <div><strong>Matricula:</strong> <?= htmlspecialchars($colab['matricula'] ?? '-') ?></div>
        <div><strong>Funcao:</strong> <?= htmlspecialchars($colab['funcao'] ?? '-') ?></div>
        <div><strong>Setor:</strong> <?= htmlspecialchars($colab['setor'] ?? '-') ?></div>
        <div><strong>Unidade:</strong> <?= htmlspecialchars($colab['unidade'] ?? '-') ?></div>
        <div><strong>Admissao:</strong> <?= $colab['data_admissao'] ? date('d/m/Y', strtotime($colab['data_admissao'])) : '-' ?></div>
        <?php if ($colab['data_demissao']): ?>
        <div><strong>Demissao:</strong> <?= date('d/m/Y', strtotime($colab['data_demissao'])) ?></div>
        <?php endif; ?>
        <div><strong>Data Nascimento:</strong> <?= !empty($colab['data_nascimento']) ? date('d/m/Y', strtotime($colab['data_nascimento'])) : '-' ?></div>
        <div><strong>Telefone:</strong> <?= htmlspecialchars($colab['telefone'] ?? '-') ?></div>
        <div><strong>Email:</strong> <?= htmlspecialchars($colab['email'] ?? '-') ?></div>
    </div>
</div>

<!-- Timeline de Atividades -->
<?php if (!empty($historico)): ?>
<div class="table-container" style="margin-top:24px;">
    <div class="table-header">
        <span class="table-title">Historico de Atividades</span>
    </div>
    <div style="padding:20px;">
        <?php foreach ($historico as $h): ?>
        <div style="display:flex; gap:12px; padding:10px 0; border-bottom:1px solid var(--c-border);">
            <div style="min-width:80px; font-size:12px; color:var(--c-gray);">
                <?= date('d/m/Y', strtotime($h['criado_em'])) ?><br>
                <span style="font-size:11px;"><?= date('H:i', strtotime($h['criado_em'])) ?></span>
            </div>
            <div style="flex:1;">
                <span style="font-size:13px; color:var(--c-text);"><?= htmlspecialchars($h['descricao']) ?></span>
                <span style="font-size:12px; color:var(--c-gray); margin-left:8px;">por <?= htmlspecialchars($h['usuario_nome'] ?? 'Sistema') ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script>
function viewPdf(docId, filename) {
    document.getElementById('pdf-title').textContent = filename;
    document.getElementById('pdf-download').href = '/documentos/download/' + docId;
    document.getElementById('pdf-iframe').src = '/documentos/visualizar/' + docId;
    var modal = document.getElementById('pdf-modal');
    modal.style.display = 'flex';
}

function closePdfModal() {
    var modal = document.getElementById('pdf-modal');
    modal.style.display = 'none';
    document.getElementById('pdf-iframe').src = '';
}

document.getElementById('pdf-modal').addEventListener('click', function(e) {
    if (e.target === this) closePdfModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePdfModal();
});
</script>
