<?= \App\Core\View::csrfField() ?>
<?php
$csrfToken = \App\Core\Session::get('csrf_token', '');

// Status labels & cores
$statusLabel = [
    'pendente_envio' => ['label' => 'Pendente',   'color' => '#f39c12', 'bg' => '#fff8ec'],
    'enviado'        => ['label' => 'Enviado',     'color' => '#3498db', 'bg' => '#eef6fb'],
    'confirmado'     => ['label' => 'Confirmado',  'color' => '#00b279', 'bg' => '#e6f9f3'],
    'rejeitado'      => ['label' => 'Rejeitado',   'color' => '#e74c3c', 'bg' => '#fdf0ef'],
];
function statusBadge(string $s): string {
    global $statusLabel;
    $info = $statusLabel[$s] ?? ['label' => $s, 'color' => '#6b7280', 'bg' => '#f3f4f6'];
    return "<span style=\"display:inline-block; padding:2px 10px; border-radius:12px; font-size:11px; font-weight:600;
                           background:{$info['bg']}; color:{$info['color']}; border:1px solid {$info['color']}40;\">{$info['label']}</span>";
}
?>

<!-- KPI Cards -->
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px;">
    <a href="/rh?status=pendente" style="text-decoration:none;">
        <div class="stat-card" style="border-left:4px solid #f39c12; cursor:pointer; <?= $statusFiltro==='pendente'?'box-shadow:0 0 0 2px #f39c12;':'' ?>">
            <div class="stat-value" style="color:#f39c12;"><?= $contadores['pendentes'] ?></div>
            <div class="stat-label">Pendentes de envio</div>
        </div>
    </a>
    <a href="/rh?status=enviado" style="text-decoration:none;">
        <div class="stat-card" style="border-left:4px solid #3498db; cursor:pointer; <?= $statusFiltro==='enviado'?'box-shadow:0 0 0 2px #3498db;':'' ?>">
            <div class="stat-value" style="color:#3498db;"><?= $contadores['enviados'] ?></div>
            <div class="stat-label">Aguardando confirmação</div>
        </div>
    </a>
    <a href="/rh?status=confirmado" style="text-decoration:none;">
        <div class="stat-card" style="border-left:4px solid #00b279; cursor:pointer; <?= $statusFiltro==='confirmado'?'box-shadow:0 0 0 2px #00b279;':'' ?>">
            <div class="stat-value" style="color:#00b279;"><?= $contadores['confirmados'] ?></div>
            <div class="stat-label">Confirmados</div>
        </div>
    </a>
    <a href="/rh?status=todos" style="text-decoration:none;">
        <div class="stat-card" style="border-left:4px solid #6b7280; cursor:pointer; <?= $statusFiltro==='todos'?'box-shadow:0 0 0 2px #6b7280;':'' ?>">
            <div class="stat-value" style="color:#6b7280;"><?= $contadores['pendentes'] + $contadores['enviados'] + $contadores['confirmados'] + $contadores['rejeitados'] ?></div>
            <div class="stat-label">Total geral</div>
        </div>
    </a>
</div>

<!-- Filtros -->
<div class="table-container">
    <div class="table-header" style="padding:16px 20px; border-bottom:1px solid var(--c-border);">
        <form method="GET" action="/rh" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; width:100%;">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFiltro) ?>">
            <input type="text" name="q" class="form-control" placeholder="Buscar colaborador..."
                   value="<?= htmlspecialchars($q) ?>" style="flex:1; min-width:180px;">
            <select name="cliente_id" class="form-control" style="width:190px;">
                <option value="">Todos os clientes</option>
                <?php foreach ($clientes as $cl): ?>
                <option value="<?= $cl['id'] ?>" <?= $clienteId == $cl['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cl['nome_fantasia']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select name="tipo_id" class="form-control" style="width:200px;">
                <option value="">Todos os tipos</option>
                <?php foreach ($tipos as $t): ?>
                <option value="<?= $t['id'] ?>" <?= $tipoId == $t['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['nome']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">Filtrar</button>
            <?php if ($q || $clienteId || $tipoId): ?>
            <a href="/rh?status=<?= htmlspecialchars($statusFiltro) ?>" class="btn btn-secondary btn-sm">Limpar</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Abas de status -->
    <div style="display:flex; gap:0; border-bottom:2px solid var(--c-border); padding:0 20px; background:var(--c-bg);">
        <?php foreach (['pendente'=>'Pendentes','enviado'=>'Enviados','confirmado'=>'Confirmados','rejeitado'=>'Rejeitados','todos'=>'Todos'] as $s => $l): ?>
        <a href="/rh?status=<?= $s ?>&cliente_id=<?= $clienteId ?>&tipo_id=<?= $tipoId ?>&q=<?= urlencode($q) ?>"
           style="padding:10px 18px; font-size:13px; font-weight:<?= $statusFiltro===$s?'700':'500' ?>;
                  color:<?= $statusFiltro===$s?'var(--c-primary)':'var(--c-gray)' ?>;
                  border-bottom:<?= $statusFiltro===$s?'2px solid var(--c-primary)':'2px solid transparent' ?>;
                  text-decoration:none; margin-bottom:-2px; white-space:nowrap;">
            <?= $l ?>
            <?php if ($s === 'pendente' && $contadores['pendentes'] > 0): ?>
            <span style="background:#f39c12; color:#fff; border-radius:10px; padding:1px 7px; font-size:10px; margin-left:4px;"><?= $contadores['pendentes'] ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Tabela -->
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Colaborador</th>
                    <th>Cliente</th>
                    <th>Tipo de Documento</th>
                    <th>Emissão</th>
                    <th>Validade</th>
                    <th>Status</th>
                    <th>Protocolo</th>
                    <th style="text-align:center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($linhas)): ?>
                <tr><td colspan="8" style="text-align:center; color:#6b7280; padding:40px;">
                    <?php
                    $msgs = ['pendente'=>'Nenhuma pendência de protocolo.','enviado'=>'Nenhum protocolo aguardando confirmação.',
                             'confirmado'=>'Nenhum protocolo confirmado.','rejeitado'=>'Nenhum protocolo rejeitado.','todos'=>'Nenhum registro encontrado.'];
                    echo $msgs[$statusFiltro] ?? 'Nenhum registro.';
                    ?>
                </td></tr>
                <?php else: ?>
                <?php foreach ($linhas as $l):
                    $pStatus    = $l['protocolo_status'] ?? 'pendente_envio';
                    $isNovo     = ($l['protocolo_id'] === null);  // nenhum registro ainda
                    $isPendente = $isNovo || $pStatus === 'pendente_envio';
                    $isEnviado  = ($pStatus === 'enviado');

                    // Cor da linha de validade
                    $valStyle = '';
                    if ($l['data_validade']) {
                        $dias = (strtotime($l['data_validade']) - time()) / 86400;
                        if ($dias < 0)  $valStyle = 'color:#e74c3c; font-weight:600;';
                        elseif ($dias <= 30) $valStyle = 'color:#f39c12; font-weight:600;';
                    }
                ?>
                <tr>
                    <td>
                        <a href="/colaboradores/<?= $l['colaborador_id'] ?>" style="color:var(--c-primary); font-weight:600;">
                            <?= htmlspecialchars($l['nome_completo']) ?>
                        </a>
                        <?php if ($l['matricula']): ?>
                        <div style="font-size:11px; color:#6b7280;">Mat. <?= htmlspecialchars($l['matricula']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:13px;"><?= htmlspecialchars($l['cliente_nome']) ?></td>
                    <td style="font-size:13px;"><?= htmlspecialchars($l['tipo_nome']) ?></td>
                    <td style="font-size:13px;"><?= $l['data_emissao'] ? date('d/m/Y', strtotime($l['data_emissao'])) : '-' ?></td>
                    <td style="font-size:13px; <?= $valStyle ?>">
                        <?= $l['data_validade'] ? date('d/m/Y', strtotime($l['data_validade'])) : 'N/A' ?>
                    </td>
                    <td><?= statusBadge($isNovo ? 'pendente_envio' : $pStatus) ?></td>
                    <td style="font-size:12px; color:#6b7280;">
                        <?php if ($l['numero_protocolo']): ?>
                        <strong style="color:var(--c-text);">#<?= htmlspecialchars($l['numero_protocolo']) ?></strong><br>
                        <?php endif; ?>
                        <?php if ($l['protocolado_em']): ?>
                        <?= date('d/m/Y', strtotime($l['protocolado_em'])) ?>
                        <?php endif; ?>
                        <?php if ($l['enviado_por_nome']): ?>
                        <div style="font-size:11px;"><?= htmlspecialchars($l['enviado_por_nome']) ?></div>
                        <?php endif; ?>
                        <?php if ($l['n_comprovantes'] > 0): ?>
                        <a href="/rh/protocolo/<?= $l['protocolo_id'] ?>/comprovantes" style="font-size:11px; color:var(--c-primary);">
                            📎 <?= $l['n_comprovantes'] ?> comprovante(s)
                        </a>
                        <?php elseif ($isEnviado && $l['sem_comprovante']): ?>
                        <span style="font-size:11px; color:#f39c12;" title="Enviado sem comprovante">⚠ Sem comprovante</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center; white-space:nowrap;">
                        <?php if ($isPendente): ?>
                        <button class="btn btn-primary btn-sm"
                                onclick="abrirModal(<?= $l['doc_id'] ?>, '<?= addslashes($l['nome_completo']) ?>', '<?= addslashes($l['tipo_nome']) ?>', '<?= addslashes($l['cliente_nome']) ?>', '<?= $l['data_validade'] ? date('d/m/Y', strtotime($l['data_validade'])) : 'N/A' ?>')">
                            Marcar enviado
                        </button>
                        <?php elseif ($isEnviado): ?>
                        <button class="btn btn-sm" style="background:#00b279; color:#fff; border:none;"
                                onclick="confirmarProtocolo(<?= $l['protocolo_id'] ?>)"
                                title="Marcar como confirmado pelo cliente">✓ Confirmar</button>
                        <button class="btn btn-sm" style="background:#e74c3c; color:#fff; border:none; margin-left:4px;"
                                onclick="abrirRejeitar(<?= $l['protocolo_id'] ?>)"
                                title="Cliente rejeitou">✗ Rejeitar</button>
                        <?php elseif ($pStatus === 'confirmado'): ?>
                        <span style="color:#00b279; font-size:12px;">✓ Concluído</span>
                        <?php elseif ($pStatus === 'rejeitado'): ?>
                        <span style="color:#e74c3c; font-size:12px;" title="<?= htmlspecialchars($l['motivo_rejeicao'] ?? '') ?>">✗ Rejeitado</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($linhas) >= 500): ?>
    <div style="padding:10px 20px; font-size:12px; color:#6b7280; border-top:1px solid var(--c-border);">
        Resultado limitado a 500 registros. Use os filtros para refinar.
    </div>
    <?php endif; ?>
</div>

<!-- ================================================
     MODAL: Marcar como Enviado
     ================================================ -->
<div id="modalEnviado" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:var(--c-white); border-radius:12px; width:520px; max-width:95vw; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div style="padding:20px 24px; border-bottom:1px solid var(--c-border); display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:16px;">Registrar Protocolo</h3>
            <button onclick="fecharModal()" style="background:none; border:none; font-size:20px; cursor:pointer; color:#6b7280;">&times;</button>
        </div>

        <!-- Info do documento (readonly) -->
        <div id="modalDocInfo" style="padding:16px 24px; background:var(--c-bg); font-size:13px; border-bottom:1px solid var(--c-border);">
        </div>

        <form id="formEnviado" enctype="multipart/form-data" style="padding:20px 24px;">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" id="modalDocId" name="doc_id" value="">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label style="font-size:12px; font-weight:600; display:block; margin-bottom:4px;">Nº Protocolo <span style="color:#6b7280; font-weight:400;">(opcional)</span></label>
                    <input type="text" name="numero_protocolo" class="form-control" placeholder="Ex.: SOC-2026-00123">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:600; display:block; margin-bottom:4px;">Data do Protocolo</label>
                    <input type="date" name="data_protocolo" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <div style="margin-bottom:14px;">
                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:4px;">
                    Comprovante <span style="color:#6b7280; font-weight:400;">(PDF, JPEG, PNG — máx. 10 MB)</span>
                </label>
                <input type="file" name="comprovante" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp"
                       style="padding:6px;">
                <div style="font-size:11px; color:#f39c12; margin-top:4px;">
                    ⚠ Recomendado: sem comprovante, o registro ficará marcado como "sem evidência".
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:4px;">Observações</label>
                <textarea name="observacoes" class="form-control" rows="3" placeholder="Observações adicionais sobre este protocolo..."></textarea>
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" onclick="fecharModal()" class="btn btn-secondary">Cancelar</button>
                <button type="submit" id="btnEnviar" class="btn btn-primary">Confirmar envio</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Rejeitar -->
<div id="modalRejeitar" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:var(--c-white); border-radius:12px; width:440px; max-width:95vw; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div style="padding:20px 24px; border-bottom:1px solid var(--c-border);">
            <h3 style="margin:0; font-size:16px; color:#e74c3c;">Rejeitar Protocolo</h3>
        </div>
        <form id="formRejeitar" method="POST" style="padding:20px 24px;">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div style="margin-bottom:16px;">
                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:4px;">Motivo da Rejeição <span style="color:#e74c3c;">*</span></label>
                <textarea name="motivo" class="form-control" rows="3" required placeholder="Ex.: Documento ilegível, assinatura inválida..."></textarea>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modalRejeitar').style.display='none'" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn" style="background:#e74c3c; color:#fff; border:none;">Confirmar rejeição</button>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF = '<?= addslashes($csrfToken) ?>';

function abrirModal(docId, colab, tipo, cliente, validade) {
    document.getElementById('modalDocId').value = docId;
    document.getElementById('modalDocInfo').innerHTML =
        '<div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">' +
        '<div><span style="font-size:11px; color:#6b7280; text-transform:uppercase;">Colaborador</span><div style="font-weight:600;">' + colab + '</div></div>' +
        '<div><span style="font-size:11px; color:#6b7280; text-transform:uppercase;">Cliente</span><div style="font-weight:600;">' + cliente + '</div></div>' +
        '<div><span style="font-size:11px; color:#6b7280; text-transform:uppercase;">Tipo</span><div>' + tipo + '</div></div>' +
        '<div><span style="font-size:11px; color:#6b7280; text-transform:uppercase;">Validade</span><div>' + validade + '</div></div>' +
        '</div>';
    // Reset form
    document.getElementById('formEnviado').reset();
    document.querySelector('[name="data_protocolo"]').value = new Date().toISOString().split('T')[0];
    document.getElementById('modalEnviado').style.display = 'flex';
}

function fecharModal() {
    document.getElementById('modalEnviado').style.display = 'none';
}

document.getElementById('formEnviado').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnEnviar');
    btn.disabled = true;
    btn.textContent = 'Enviando...';

    const docId = document.getElementById('modalDocId').value;
    const fd = new FormData(this);

    try {
        const res = await fetch('/rh/' + docId + '/marcar-enviado', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        const data = await res.json();
        if (data.success) {
            fecharModal();
            location.reload();
        } else {
            alert(data.error || 'Erro ao registrar protocolo.');
        }
    } catch (ex) {
        alert('Erro de comunicação.');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Confirmar envio';
    }
});

async function confirmarProtocolo(protId) {
    if (!confirm('Confirmar que o cliente aceitou este protocolo?')) return;
    const fd = new FormData();
    fd.append('_csrf_token', CSRF);
    const res = await fetch('/rh/protocolo/' + protId + '/confirmar', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    });
    const d = await res.json();
    if (d.success) location.reload();
    else alert('Não foi possível confirmar.');
}

function abrirRejeitar(protId) {
    document.getElementById('formRejeitar').action = '/rh/protocolo/' + protId + '/rejeitar';
    document.getElementById('modalRejeitar').style.display = 'flex';
}

// Fechar modais clicando fora
['modalEnviado', 'modalRejeitar'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
});
</script>
