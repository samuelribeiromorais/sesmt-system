<?php $csrfToken = $_SESSION['csrf_token'] ?? ''; ?>

<!-- ============ UPLOAD DIRETO ============ -->
<div class="table-container" style="margin-bottom: 24px;">
    <div class="table-header">
        <span class="table-title">Upload Direto de Dados</span>
    </div>
    <div style="padding: 20px;">
        <p style="font-size:13px; color:#6b7280; margin-bottom:16px;">
            Envie uma planilha CSV ou Excel diretamente. Colaboradores existentes serao atualizados (por CPF) e novos serao cadastrados.
        </p>
        <form method="POST" action="/upload-links/upload-direto" enctype="multipart/form-data" id="uploadDiretoForm" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
            <div style="flex:1; min-width:250px;">
                <label style="display:block; font-size:12px; font-weight:600; color:var(--c-text); margin-bottom:4px;">Arquivo (.xlsx, .xls, .csv)</label>
                <input type="file" name="arquivo" accept=".xlsx,.xls,.csv" required
                       style="width:100%; padding:8px; border:1px solid var(--c-border); border-radius:6px; font-size:13px; background:var(--c-bg); color:var(--c-text);">
            </div>
            <button type="submit" class="btn btn-primary btn-sm" id="btnUploadDireto">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                Processar
            </button>
        </form>
        <div style="margin-top:12px; font-size:11px; color:#9ca3af;">
            <strong>Colunas aceitas:</strong> nome_completo (ou PE_NOME), cpf (ou PE_CPF), matricula (ou CODIGO), cargo, funcao, setor, unidade, data_admissao, data_nascimento, telefone, email, PE_CIDADE, PE_UF
        </div>
    </div>
</div>

<?php if (!empty($_SESSION['ultimo_resultado_upload'])):
    $res = $_SESSION['ultimo_resultado_upload'];
    unset($_SESSION['ultimo_resultado_upload']);
?>
<div class="table-container" style="margin-bottom:24px; border-left:4px solid #00b279;">
    <div style="padding:16px 20px;">
        <h4 style="margin:0 0 12px; font-size:14px; color:var(--c-text);">Resultado do Upload</h4>
        <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:12px;">
            <div style="background:#f0fdf4; border-radius:6px; padding:10px 16px; text-align:center;">
                <div style="font-size:22px; font-weight:bold; color:#00b279;"><?= $res['atualizados'] ?? 0 ?></div>
                <div style="font-size:11px; color:#166534;">Atualizados</div>
            </div>
            <div style="background:#eff6ff; border-radius:6px; padding:10px 16px; text-align:center;">
                <div style="font-size:22px; font-weight:bold; color:#2563eb;"><?= $res['novos'] ?? 0 ?></div>
                <div style="font-size:11px; color:#1e40af;">Novos</div>
            </div>
            <div style="background:#fef3c7; border-radius:6px; padding:10px 16px; text-align:center;">
                <div style="font-size:22px; font-weight:bold; color:#d97706;"><?= $res['ignorados'] ?? 0 ?></div>
                <div style="font-size:11px; color:#92400e;">Ignorados</div>
            </div>
        </div>
        <?php if (!empty($res['detalhes'])): ?>
        <details>
            <summary style="cursor:pointer; font-size:12px; color:var(--c-link); font-weight:600;">Ver detalhes (<?= count($res['detalhes']) ?> registros)</summary>
            <div style="max-height:250px; overflow-y:auto; margin-top:8px; font-size:12px; background:var(--c-bg); border-radius:6px; padding:12px;">
                <?php foreach (array_slice($res['detalhes'], 0, 100) as $d): ?>
                <?php
                    $cor = ($d['acao'] ?? '') === 'atualizado' ? '#00b279' : (($d['acao'] ?? '') === 'criado' ? '#2563eb' : '#d97706');
                ?>
                <div style="padding:2px 0;">[<span style="color:<?= $cor ?>; font-weight:600;"><?= strtoupper($d['acao'] ?? '?') ?></span>] <?= htmlspecialchars($d['nome'] ?? '') ?><?= !empty($d['campos']) ? ' — ' . htmlspecialchars($d['campos']) : '' ?></div>
                <?php endforeach; ?>
                <?php if (count($res['detalhes']) > 100): ?>
                <div style="color:#6b7280; margin-top:4px;">...e mais <?= count($res['detalhes']) - 100 ?> registro(s)</div>
                <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ============ LINKS EXTERNOS ============ -->
<div class="table-container" style="margin-bottom: 24px;">
    <div class="table-header" style="flex-wrap: wrap; gap: 12px;">
        <span class="table-title">Links para Upload Externo</span>
        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('modal-gerar').style.display='flex'">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
            Gerar Link para Colega
        </button>
    </div>
    <div style="padding: 12px 16px; font-size: 13px; color: #6b7280;">
        Gere links seguros para colegas externos (RH, DP) enviarem planilhas sem precisar de login no sistema.
    </div>
</div>

<?php if (!empty($_SESSION['ultimo_link_gerado'])): ?>
<div class="alert alert-success" style="margin-bottom:24px;">
    <strong>Link gerado! Copie e envie ao colega:</strong>
    <div style="display:flex; align-items:center; gap:8px; margin-top:8px;">
        <input type="text" id="linkCopiar" value="<?= htmlspecialchars($_SESSION['ultimo_link_gerado']) ?>" readonly
               style="flex:1; padding:10px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; background:#f9fafb; font-family:monospace;">
        <button type="button" class="btn btn-primary btn-sm" onclick="copiarLink()" id="btnCopiar">Copiar</button>
    </div>
</div>
<?php unset($_SESSION['ultimo_link_gerado']); endif; ?>

<!-- Tabela de links -->
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Descricao</th>
                <th>Criado por</th>
                <th>Criado em</th>
                <th>Expira em</th>
                <th>Status</th>
                <th>Resultado</th>
                <th>Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($links)): ?>
            <tr><td colspan="7" style="text-align:center; color:#6b7280; padding:24px;">Nenhum link gerado ainda.</td></tr>
            <?php else: ?>
            <?php foreach ($links as $link): ?>
            <?php
                $expirado = strtotime($link['expira_em']) < time();
                $usado = !empty($link['usado_em']);
                $ativo = $link['ativo'] && !$expirado;

                if ($usado) {
                    $statusClass = 'badge-vigente';
                    $statusText = 'Usado';
                } elseif (!$link['ativo']) {
                    $statusClass = 'badge-vencido';
                    $statusText = 'Revogado';
                } elseif ($expirado) {
                    $statusClass = 'badge-vencido';
                    $statusText = 'Expirado';
                } else {
                    $statusClass = 'badge-proximo';
                    $statusText = 'Ativo';
                }
            ?>
            <tr>
                <td><?= htmlspecialchars($link['descricao']) ?></td>
                <td style="font-size:12px;"><?= htmlspecialchars($link['criado_por_nome']) ?></td>
                <td style="font-size:12px; white-space:nowrap;"><?= date('d/m/Y H:i', strtotime($link['criado_em'])) ?></td>
                <td style="font-size:12px; white-space:nowrap;"><?= date('d/m/Y H:i', strtotime($link['expira_em'])) ?></td>
                <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                <td style="font-size:12px;">
                    <?php if ($usado): ?>
                        <?= htmlspecialchars($link['arquivo_nome'] ?? '-') ?>
                        <?php $res = json_decode($link['resultado'] ?? '{}', true); if ($res): ?>
                        <div style="font-size:11px; color:#6b7280; margin-top:2px;">
                            <?= ($res['atualizados'] ?? 0) ?> atualiz., <?= ($res['novos'] ?? 0) ?> novos, <?= ($res['ignorados'] ?? 0) ?> ignor.
                        </div>
                        <?php endif; ?>
                    <?php else: ?>-<?php endif; ?>
                </td>
                <td style="white-space:nowrap;">
                    <?php if ($ativo && !$usado): ?>
                    <button type="button" class="btn btn-outline btn-sm" onclick="copiarLinkToken('<?= htmlspecialchars($link['token']) ?>')" title="Copiar link">Copiar</button>
                    <form method="POST" action="/upload-links/<?= $link['id'] ?>/revogar" style="display:inline;">
                        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
                        <button type="submit" class="btn btn-danger btn-sm" data-confirm="Revogar este link?">Revogar</button>
                    </form>
                    <?php elseif ($usado): ?>
                    <span style="font-size:11px; color:#6b7280;"><?= date('d/m/Y H:i', strtotime($link['usado_em'])) ?></span>
                    <?php else: ?>-<?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Gerar Link -->
<div id="modal-gerar" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
    <div style="background:var(--c-white); border-radius:12px; width:90%; max-width:500px; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div style="padding:20px 24px; border-bottom:1px solid var(--c-border);">
            <h3 style="font-size:16px; color:var(--c-text);">Gerar Link para Upload Externo</h3>
        </div>
        <form method="POST" action="/upload-links/gerar">
            <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
            <div style="padding:24px;">
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:13px; font-weight:600; color:var(--c-text); margin-bottom:6px;">Descricao</label>
                    <input type="text" name="descricao" value="Upload de dados de colaboradores"
                           style="width:100%; padding:10px 12px; border:1px solid var(--c-border); border-radius:6px; font-size:14px; background:var(--c-bg); color:var(--c-text);">
                    <span style="font-size:11px; color:#6b7280;">Visivel para quem receber o link</span>
                </div>
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; color:var(--c-text); margin-bottom:6px;">Validade</label>
                    <select name="dias_validade" style="padding:10px 12px; border:1px solid var(--c-border); border-radius:6px; font-size:14px; background:var(--c-bg); color:var(--c-text);">
                        <option value="1">1 dia</option>
                        <option value="3">3 dias</option>
                        <option value="7" selected>7 dias</option>
                        <option value="14">14 dias</option>
                        <option value="30">30 dias</option>
                    </select>
                </div>
            </div>
            <div style="padding:16px 24px; border-top:1px solid var(--c-border); display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('modal-gerar').style.display='none'">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">Gerar Link</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('uploadDiretoForm').addEventListener('submit', function() {
    var btn = document.getElementById('btnUploadDireto');
    btn.disabled = true;
    btn.textContent = 'Processando...';
});

function copiarLink() {
    var input = document.getElementById('linkCopiar');
    input.select();
    document.execCommand('copy');
    var btn = document.getElementById('btnCopiar');
    btn.textContent = 'Copiado!';
    setTimeout(function() { btn.textContent = 'Copiar'; }, 2000);
}

function copiarLinkToken(token) {
    var url = window.location.origin + '/upload-externo/' + token;
    navigator.clipboard.writeText(url).then(function() {
        alert('Link copiado para a area de transferencia!');
    });
}

document.getElementById('modal-gerar').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>
