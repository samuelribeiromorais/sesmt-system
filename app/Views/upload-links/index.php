<?php $csrfToken = $_SESSION['csrf_token'] ?? ''; ?>

<div class="table-container" style="margin-bottom: 24px;">
    <div class="table-header" style="flex-wrap: wrap; gap: 12px;">
        <span class="table-title">Links de Upload Externo</span>
        <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('modal-gerar').style.display='flex'">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Gerar Novo Link
        </button>
    </div>
    <div style="padding: 16px; font-size: 13px; color: #6b7280;">
        <p>Gere links seguros para receber dados de colaboradores de colegas externos (RH, DP, etc). O link nao exige login no sistema.</p>
        <p style="margin-top:4px;">O arquivo enviado (CSV/Excel) sera processado automaticamente: colaboradores existentes serao atualizados e novos serao cadastrados.</p>
    </div>
</div>

<?php if (!empty($_SESSION['ultimo_link_gerado'])): ?>
<div class="alert alert-success" style="margin-bottom:24px;">
    <strong>Link gerado:</strong>
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
                <th>Arquivo</th>
                <th>Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($links)): ?>
            <tr><td colspan="7" style="text-align:center; color:#6b7280; padding:32px;">Nenhum link gerado ainda.</td></tr>
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
                        <?php
                        $res = json_decode($link['resultado'] ?? '{}', true);
                        if ($res): ?>
                        <div style="font-size:11px; color:#6b7280; margin-top:2px;">
                            <?= ($res['atualizados'] ?? 0) ?> atualizados,
                            <?= ($res['novos'] ?? 0) ?> novos,
                            <?= ($res['ignorados'] ?? 0) ?> ignorados
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap;">
                    <?php if ($ativo && !$usado): ?>
                    <button type="button" class="btn btn-outline btn-sm" onclick="copiarLinkToken('<?= htmlspecialchars($link['token']) ?>')" title="Copiar link">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                    </button>
                    <form method="POST" action="/upload-links/<?= $link['id'] ?>/revogar" style="display:inline;">
                        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
                        <button type="submit" class="btn btn-danger btn-sm" data-confirm="Revogar este link?">Revogar</button>
                    </form>
                    <?php elseif ($usado): ?>
                    <span style="font-size:11px; color:#6b7280;"><?= date('d/m/Y H:i', strtotime($link['usado_em'])) ?></span>
                    <?php else: ?>
                    <span style="font-size:11px; color:#6b7280;">-</span>
                    <?php endif; ?>
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
            <h3 style="font-size:16px; color:var(--c-text);">Gerar Link de Upload</h3>
        </div>
        <form method="POST" action="/upload-links/gerar">
            <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
            <div style="padding:24px;">
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:13px; font-weight:600; color:var(--c-text); margin-bottom:6px;">Descricao</label>
                    <input type="text" name="descricao" value="Upload de dados de colaboradores"
                           style="width:100%; padding:10px 12px; border:1px solid var(--c-border); border-radius:6px; font-size:14px; background:var(--c-bg); color:var(--c-text);">
                    <span style="font-size:11px; color:#6b7280;">Identifica o proposito do link (visivel para quem receber)</span>
                </div>
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; color:var(--c-text); margin-bottom:6px;">Validade (dias)</label>
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
        alert('Link copiado!');
    });
}

document.getElementById('modal-gerar').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>
