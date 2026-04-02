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
            <?php
            $pendentesCount = count(array_filter($documentos, fn($d) => ($d['aprovacao_status'] ?? '') === 'pendente'));
            if ($pendentesCount > 0): ?>
            <form method="POST" action="/documentos/aprovar-todos/<?= $colab['id'] ?>" style="display:inline;">
                <?= \App\Core\View::csrfField() ?>
                <button type="submit" class="btn btn-sm" style="background:#059669; color:white;" data-confirm="Aprovar todos os <?= $pendentesCount ?> documentos pendentes?">&#10003; Aprovar Todos (<?= $pendentesCount ?>)</button>
            </form>
            <?php endif; ?>
            <a href="/documentos/upload/<?= $colab['id'] ?>" class="btn btn-primary btn-sm">Upload</a>
            <?php endif; ?>
        </div>
        <table>
            <thead><tr><th>Tipo</th><th>Emissão</th><th>Validade</th><th style="text-align:center;">Aprovação</th><th>Acoes</th></tr></thead>
            <tbody>
                <?php if (empty($documentos)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--c-gray);">Nenhum documento</td></tr>
                <?php else: ?>
                <?php foreach ($documentos as $doc): ?>
                <tr>
                    <td>
                        <span class="semaforo semaforo-<?= statusSemaforo($doc['status']) ?>"></span>
                        <a href="/documentos/<?= $doc['id'] ?>" style="color:inherit; text-decoration:none;" title="Ver detalhes"><?= htmlspecialchars($doc['tipo_nome']) ?></a>
                        <?php if (!empty($doc['assinatura_digital'])): ?>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" style="vertical-align:middle; margin-left:4px;" title="Documento assinado por <?= htmlspecialchars($doc['assinado_por'] ?? '') ?>"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:13px;">
                        <span id="emissão-show-<?= $doc['id'] ?>">
                            <?= $doc['data_emissao'] ? date('d/m/Y', strtotime($doc['data_emissao'])) : '-' ?>
                            <?php if (!$isReadOnly): ?>
                            <button type="button" onclick="editEmissaoInline(<?= $doc['id'] ?>, '<?= $doc['data_emissao'] ?>')" class="btn btn-outline btn-sm" style="padding:1px 5px; font-size:10px; margin-left:4px;" title="Editar emissao">✎</button>
                            <?php endif; ?>
                        </span>
                        <span id="emissão-form-<?= $doc['id'] ?>" style="display:none;">
                            <form method="POST" action="/documentos/<?= $doc['id'] ?>/atualizar-emissao" style="display:inline-flex; align-items:center; gap:4px;">
                                <?= \App\Core\View::csrfField() ?>
                                <input type="date" name="data_emissao" id="emissão-input-<?= $doc['id'] ?>" value="<?= $doc['data_emissao'] ?>" class="form-input" style="padding:2px 6px; font-size:12px; width:140px;" required>
                                <button type="submit" class="btn btn-primary btn-sm" style="padding:1px 6px; font-size:10px;">✓</button>
                                <button type="button" onclick="cancelEmissaoInline(<?= $doc['id'] ?>)" class="btn btn-outline btn-sm" style="padding:1px 6px; font-size:10px;">✗</button>
                            </form>
                        </span>
                    </td>
                    <td style="font-size:13px;">
                        <?php if ($doc['data_validade']): ?>
                            <?= date('d/m/Y', strtotime($doc['data_validade'])) ?>
                        <?php elseif (($doc['categoria'] ?? '') === 'epi' || ($doc['categoria'] ?? '') === 'os'): ?>
                            <span style="color:#6b7280; font-size:11px;" title="Atualizado em <?= date('d/m/Y', strtotime($doc['data_emissao'])) ?>">N/A</span>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center; font-size:12px;">
                        <?php
                        $apStatus = $doc['aprovacao_status'] ?? null;
                        if ($apStatus === 'aprovado'): ?>
                            <span class="badge badge-vigente" title="Aprovado em <?= !empty($doc['aprovado_em']) ? date('d/m/Y', strtotime($doc['aprovado_em'])) : '' ?>">Aprovado</span>
                        <?php elseif ($apStatus === 'rejeitado'): ?>
                            <span class="badge badge-vencido" title="<?= htmlspecialchars($doc['aprovacao_obs'] ?? '') ?>">Rejeitado</span>
                        <?php elseif ($apStatus === 'pendente'): ?>
                            <?php if (!$isReadOnly): ?>
                            <div style="display:flex; gap:3px; justify-content:center; align-items:center;">
                                <form method="POST" action="/documentos/<?= $doc['id'] ?>/aprovar" style="display:inline;">
                                    <?= \App\Core\View::csrfField() ?>
                                    <input type="hidden" name="decisao" value="aprovado">
                                    <button type="submit" class="btn btn-sm" style="padding:2px 8px; font-size:11px; background:#059669; color:white;" title="Aprovar">&#10003; Aprovar</button>
                                </form>
                                <form method="POST" action="/documentos/<?= $doc['id'] ?>/aprovar" style="display:inline;">
                                    <?= \App\Core\View::csrfField() ?>
                                    <input type="hidden" name="decisao" value="rejeitado">
                                    <button type="submit" class="btn btn-sm" style="padding:2px 8px; font-size:11px; background:#dc2626; color:white;" title="Rejeitar">&#10007;</button>
                                </form>
                            </div>
                            <?php else: ?>
                            <span class="badge badge-proximo_vencimento">Pendente</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color:#999;">-</span>
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
        <div><strong>CPF:</strong> <?= htmlspecialchars($cpfDisplay) ?></div>
        <div><strong>Matricula:</strong> <?= htmlspecialchars($colab['matricula'] ?? '-') ?></div>
        <div><strong>Função:</strong> <?= htmlspecialchars($colab['funcao'] ?? '-') ?></div>
        <div><strong>Setor:</strong> <?= htmlspecialchars($colab['setor'] ?? '-') ?></div>
        <div><strong>Unidade:</strong> <?= htmlspecialchars($colab['unidade'] ?? '-') ?></div>
        <div><strong>Admissao:</strong> <?= $colab['data_admissao'] ? date('d/m/Y', strtotime($colab['data_admissao'])) : '-' ?></div>
        <?php if ($colab['data_demissao']): ?>
        <div><strong>Demissao:</strong> <?= date('d/m/Y', strtotime($colab['data_demissao'])) ?></div>
        <?php endif; ?>
        <div><strong>Data Nascimento:</strong> <?= !empty($colab['data_nascimento']) ? date('d/m/Y', strtotime($colab['data_nascimento'])) : '-' ?></div>
        <div><strong>Telefone:</strong> <?= htmlspecialchars($colab['telefone'] ?? '-') ?></div>
        <div><strong>Email:</strong> <?= htmlspecialchars($colab['email'] ?? '-') ?></div>

        <!-- WhatsApp -->
        <div style="grid-column:1/-1; border-top:1px solid var(--c-border); padding-top:16px; margin-top:4px;">
        <?php
            $celularWa = !empty($colab['celular']) ? $colab['celular'] : ($colab['celular_manual'] ?? null);
            $origemWa  = !empty($colab['celular']) ? 'gco' : (!empty($colab['celular_manual']) ? 'manual' : null);
            $waNumero  = $celularWa ? preg_replace('/\D/', '', $celularWa) : null;
            // Garante código do país
            if ($waNumero && !str_starts_with($waNumero, '55')) {
                $waNumero = '55' . $waNumero;
            }
        ?>
        <strong>WhatsApp:</strong>&nbsp;
        <?php if ($waNumero): ?>
            <a href="https://wa.me/<?= $waNumero ?>" target="_blank"
               style="display:inline-flex; align-items:center; gap:6px; background:#25d366; color:#fff; padding:5px 14px; border-radius:20px; text-decoration:none; font-size:13px; font-weight:600; vertical-align:middle;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.122.555 4.122 1.526 5.857L.057 23.882a.5.5 0 00.611.611l5.963-1.463A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.933 0-3.74-.519-5.29-1.424l-.376-.221-3.905.958.977-3.819-.243-.389A9.96 9.96 0 012 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>
                <?= htmlspecialchars($celularWa) ?>
            </a>
            <?php if ($origemWa === 'gco'): ?>
            <span style="font-size:11px; color:#16a34a; margin-left:6px; vertical-align:middle;">via GCO</span>
            <?php elseif ($origemWa === 'manual'): ?>
            <span style="font-size:11px; color:#2563eb; margin-left:6px; vertical-align:middle;">inserido manualmente</span>
            <?php endif; ?>
            <?php if (!$isReadOnly && $origemWa === 'manual'): ?>
            &nbsp;<button type="button" onclick="editarCelular()" class="btn btn-outline btn-sm" style="font-size:11px; padding:2px 8px; vertical-align:middle;">✎ Editar</button>
            <?php endif; ?>

        <?php elseif (!$isReadOnly): ?>
            <span style="color:var(--c-gray); font-size:13px;">Não informado</span>
            &nbsp;<button type="button" onclick="editarCelular()" class="btn btn-outline btn-sm" style="font-size:12px; padding:3px 10px;">+ Adicionar</button>
        <?php else: ?>
            <span style="color:var(--c-gray);">Não informado</span>
        <?php endif; ?>

        <?php if (!$isReadOnly): ?>
        <div id="form-celular" style="display:none; margin-top:10px; max-width:320px;">
            <form method="POST" action="/colaboradores/<?= $colab['id'] ?>/celular">
                <?= \App\Core\View::csrfField() ?>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="tel" name="celular_manual" placeholder="(11) 99999-9999"
                           value="<?= htmlspecialchars($colab['celular_manual'] ?? '') ?>"
                           class="form-input" style="flex:1;" maxlength="20">
                    <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
                    <button type="button" onclick="document.getElementById('form-celular').style.display='none'" class="btn btn-outline btn-sm">✗</button>
                </div>
                <p style="font-size:11px; color:var(--c-gray); margin-top:4px;">
                    Este número é salvo manualmente e não afeta o GCO.
                    <?php if (!empty($colab['celular'])): ?>
                    O GCO já fornece o celular acima.
                    <?php endif; ?>
                </p>
            </form>
        </div>
        <?php endif; ?>
        </div>
    </div>
</div>

<!-- Timeline de Atividades -->
<?php if (!empty($historico)): ?>
<div class="table-container" style="margin-top:24px;">
    <div class="table-header">
        <span class="table-title">Histórico de Atividades</span>
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
function editarCelular() {
    const f = document.getElementById('form-celular');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
}

function editEmissaoInline(docId, currentDate) {
    document.getElementById('emissão-show-' + docId).style.display = 'none';
    document.getElementById('emissão-form-' + docId).style.display = 'inline';
    document.getElementById('emissão-input-' + docId).value = currentDate;
    document.getElementById('emissão-input-' + docId).focus();
}
function cancelEmissaoInline(docId) {
    document.getElementById('emissão-form-' + docId).style.display = 'none';
    document.getElementById('emissão-show-' + docId).style.display = 'inline';
}

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
