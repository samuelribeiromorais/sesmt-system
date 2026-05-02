<link rel="stylesheet" href="/assets/css/certificados.css">
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="/assets/js/images-data.js"></script>
<script src="/assets/js/certificados.js"></script>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <div>
        <h2><?= htmlspecialchars($treinamento['tipo_codigo']) ?></h2>
        <span style="color:var(--c-gray);"><?= htmlspecialchars($treinamento['tipo_titulo']) ?> (<?= htmlspecialchars($treinamento['duracao']) ?>)</span>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <?php
            $stLabel = ['em_andamento'=>'Em Andamento','aguardando_assinaturas'=>'Aguard. Assinaturas','finalizada'=>'Finalizada'];
            $stClass = ['em_andamento'=>'badge-proximo_vencimento','aguardando_assinaturas'=>'badge-vigente','finalizada'=>'badge-vigente'];
            $trSt = $treinamento['status'] ?? 'em_andamento';
        ?>
        <span class="badge <?= $stClass[$trSt] ?>" style="align-self:center; font-size:12px;"><?= $stLabel[$trSt] ?></span>
        <a href="/treinamentos/<?= $treinamento['id'] ?>/lista-presenca" class="btn btn-outline btn-sm" target="_blank">Lista de Presenca</a>
        <a href="/treinamentos/<?= $treinamento['id'] ?>/certificados" class="btn btn-outline btn-sm" target="_blank">Imprimir Todos</a>
        <button class="btn btn-primary btn-sm" onclick="baixarTodosZip()">Baixar ZIP</button>
        <a href="/treinamentos/<?= $treinamento['id'] ?>/editar" class="btn btn-outline btn-sm">Editar</a>
        <button class="btn btn-outline btn-sm" onclick="abrirModalAddColab()" style="background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe;">+ Colaborador</button>
        <form method="POST" action="/treinamentos/<?= $treinamento['id'] ?>/excluir" style="display:inline;"
              onsubmit="return confirm('Excluir este treinamento e todos os certificados?')">
            <?= \App\Core\View::csrfField() ?>
            <button type="submit" class="btn btn-sm" style="background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;">Excluir</button>
        </form>
        <a href="/treinamentos" class="btn btn-outline btn-sm">Voltar</a>
    </div>
</div>

<!-- Info do treinamento -->
<div class="table-container" style="margin-bottom:24px;">
    <div style="padding:16px; display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
        <div>
            <small style="color:#6b7280;">Data de Realizacao</small>
            <div style="font-size:14px; font-weight:600;">
                <?= date('d/m/Y', strtotime($treinamento['data_realizacao'])) ?>
                <?php if ($treinamento['data_realizacao_fim'] && $treinamento['data_realizacao_fim'] !== $treinamento['data_realizacao']): ?>
                a <?= date('d/m/Y', strtotime($treinamento['data_realizacao_fim'])) ?>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <small style="color:#6b7280;">Ministrante</small>
            <div style="font-size:14px; font-weight:600;"><?= htmlspecialchars($treinamento['ministrante_nome'] ?? '-') ?></div>
        </div>
        <div>
            <small style="color:#6b7280;">Participantes</small>
            <div style="font-size:14px; font-weight:600;"><?= $treinamento['total_participantes'] ?></div>
        </div>
        <div>
            <small style="color:#6b7280;">Registrado por</small>
            <div style="font-size:14px; font-weight:600;"><?= htmlspecialchars($treinamento['criador_nome'] ?? '-') ?></div>
        </div>
        <div>
            <small style="color:#6b7280;">Data de Emissão</small>
            <div style="font-size:14px; font-weight:600;"><?= date('d/m/Y', strtotime($treinamento['data_emissao'])) ?></div>
        </div>
        <?php if ($treinamento['observacoes']): ?>
        <div style="grid-column: 1 / -1;">
            <small style="color:#6b7280;">Observacoes</small>
            <div style="font-size:13px;"><?= htmlspecialchars($treinamento['observacoes']) ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tabela de participantes -->
<div class="table-container">
    <div class="table-header">
        <span class="table-title">Participantes (<?= count($participantes) ?>)</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Nome</th>
                <th>Cargo / Função</th>
                <th>Setor</th>
                <th style="text-align:center;" title="Marcar presença no dia do treinamento">Presença</th>
                <th style="text-align:center;">Status</th>
                <th style="text-align:center;">Validade</th>
                <th style="text-align:center;">Assinado</th>
                <th style="text-align:center;">Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($participantes as $p): ?>
            <tr>
                <td style="font-size:13px; color:#6b7280;"><?= $i++ ?></td>
                <td>
                    <a href="/colaboradores/<?= $p['colaborador_id'] ?>" style="font-size:13px; font-weight:500; color:var(--c-accent);">
                        <?= htmlspecialchars($p['nome_completo']) ?>
                    </a>
                </td>
                <td style="font-size:13px;"><?= htmlspecialchars($p['funcao'] ?? $p['cargo'] ?? '-') ?></td>
                <td style="font-size:13px;"><?= htmlspecialchars($p['setor'] ?? '-') ?></td>
                <td style="text-align:center;">
                    <?php $pres = $p['presente']; $pres = ($pres === null) ? '' : (string)$pres; ?>
                    <select class="form-control" style="font-size:12px; padding:4px; width:100px;"
                            onchange="marcarPresenca(<?= $p['certificado_id'] ?>, this.value, this)">
                        <option value=""  <?= $pres === ''  ? 'selected' : '' ?>>—</option>
                        <option value="1" <?= $pres === '1' ? 'selected' : '' ?>>Presente</option>
                        <option value="0" <?= $pres === '0' ? 'selected' : '' ?>>Ausente</option>
                    </select>
                </td>
                <td style="text-align:center;">
                    <span class="badge badge-<?= $p['status'] ?>">
                        <?= $p['status'] === 'vigente' ? 'Vigente' : ($p['status'] === 'proximo_vencimento' ? 'Vencendo' : 'Vencido') ?>
                    </span>
                </td>
                <td style="text-align:center; font-size:13px;"><?= date('d/m/Y', strtotime($p['data_validade'])) ?></td>
                <td style="text-align:center;">
                    <?php if (!empty($p['arquivo_assinado'])): ?>
                        <span class="badge badge-vigente" title="Vinculado em <?= date('d/m/Y', strtotime($p['assinado_em'])) ?>">Assinado</span>
                    <?php else: ?>
                        <button class="btn btn-sm" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;font-size:11px;"
                                onclick="abrirUploadAssinado(<?= $p['certificado_id'] ?>)">Vincular</button>
                    <?php endif; ?>
                </td>
                <td style="text-align:center; white-space:nowrap;">
                    <button class="btn btn-outline btn-sm" onclick="previewParticipante(<?= $p['colaborador_id'] ?>)">Ver</button>
                    <button class="btn btn-outline btn-sm" onclick="baixarPdfParticipante(<?= $p['colaborador_id'] ?>)">PDF</button>
                    <button class="btn btn-sm" style="background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;font-size:11px;"
                            onclick="removerColaborador(<?= $p['certificado_id'] ?>, '<?= htmlspecialchars(addslashes($p['nome_completo'])) ?>')">Remover</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Fotos do treinamento -->
<div class="table-container" style="margin-top:24px;">
    <div class="table-header">
        <span class="table-title">Fotos do Treinamento</span>
    </div>
    <div style="padding:20px; display:grid; grid-template-columns:1fr 1fr; gap:24px;">
        <?php foreach ([1, 2] as $slot):
            $path = $treinamento["foto{$slot}_path"] ?? null;
            $obrig = $slot === 1 ? '*' : '(opcional)';
        ?>
        <div>
            <label style="font-weight:600; display:block; margin-bottom:8px;">Foto <?= $slot ?> <?= $obrig ?></label>
            <?php if ($path): ?>
                <img src="/treinamentos/<?= $treinamento['id'] ?>/foto/<?= $slot ?>"
                     style="max-width:100%; max-height:240px; border:1px solid #e5e7eb; border-radius:6px;" alt="Foto <?= $slot ?>">
                <div style="margin-top:8px;">
                    <a href="/treinamentos/<?= $treinamento['id'] ?>/foto/<?= $slot ?>" target="_blank" class="btn btn-outline btn-sm">Abrir</a>
                </div>
            <?php else: ?>
                <p style="color:#6b7280; font-size:13px; margin-bottom:8px;">Nenhuma foto anexada.</p>
            <?php endif; ?>
            <form method="POST" action="/treinamentos/<?= $treinamento['id'] ?>/upload-foto" enctype="multipart/form-data" style="margin-top:8px;">
                <?= \App\Core\View::csrfField() ?>
                <input type="hidden" name="slot" value="<?= $slot ?>">
                <input type="file" name="foto" accept=".jpg,.jpeg,.png" class="form-control" style="margin-bottom:8px;" required>
                <button type="submit" class="btn btn-primary btn-sm"><?= $path ? 'Substituir' : 'Enviar' ?></button>
                <small style="color:#6b7280; display:block; margin-top:4px;">JPG ou PNG, máx. 5 MB.</small>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Preview area -->
<div class="table-container" style="margin-top:24px; display:none;" id="preview-container">
    <div class="table-header">
        <span class="table-title">Preview</span>
        <button class="btn btn-outline btn-sm" onclick="document.getElementById('preview-container').style.display='none'">Fechar</button>
    </div>
    <div id="preview-area" style="padding:20px; overflow:auto; max-height:80vh;"></div>
</div>

<!-- Progress overlay for ZIP download -->
<div id="progress-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); z-index:9999; display:none; align-items:center; justify-content:center;">
    <div style="background:white; padding:32px; border-radius:12px; text-align:center; min-width:300px;">
        <h3 id="progress-title">Gerando certificados...</h3>
        <div style="width:100%; background:#e5e7eb; border-radius:4px; margin:16px 0; height:8px;">
            <div id="progress-bar" style="width:0%; background:var(--c-primary); height:100%; border-radius:4px; transition:width 0.3s;"></div>
        </div>
        <span id="progress-text">0 / 0</span>
    </div>
</div>

<script>
const PARTICIPANTES = <?= json_encode($participantesJson) ?>;
const TREINAMENTO = <?= json_encode([
    'data_realizacao' => $treinamento['data_realizacao'],
    'data_realizacao_fim' => $treinamento['data_realizacao_fim'],
    'data_emissao' => $treinamento['data_emissao'],
    'tipo_certificado_id' => $treinamento['tipo_certificado_id'],
    'ministrante_id' => $treinamento['ministrante_id'],
]) ?>;

// Build TIPOS_MAP and MINISTRANTES_MAP from PHP data
const TIPOS_MAP = {};
<?php foreach ($tipos as $t): ?>
TIPOS_MAP[<?= $t['id'] ?>] = {
    titulo: <?= json_encode($t['titulo']) ?>,
    codigo: <?= json_encode($t['codigo']) ?>,
    duracao: <?= json_encode($t['duracao']) ?>,
    validade_meses: <?= $t['validade_meses'] ?>,
    tem_anuencia: <?= $t['tem_anuencia'] ?>,
    tem_diego: <?= $t['tem_diego'] ?>,
    tem_diego_responsavel: <?= $t['tem_diego_responsavel'] ?? 0 ?>,
    conteudo_no_verso: <?= $t['conteudo_no_verso'] ?>,
    conteudo_programatico: <?= json_encode($t['conteudo_programatico'] ?? '[]') ?>,
    ministrante_id: <?= json_encode($t['ministrante_id']) ?>,
};
<?php endforeach; ?>

const MINISTRANTES_MAP = {};
<?php foreach ($ministrantes as $m): ?>
MINISTRANTES_MAP[<?= $m['id'] ?>] = {
    id: <?= $m['id'] ?>,
    nome: <?= json_encode($m['nome']) ?>,
    cargo_titulo: <?= json_encode($m['cargo_titulo']) ?>,
    registro: <?= json_encode($m['registro'] ?? '') ?>,
};
<?php endforeach; ?>

function getParticipanteData(colabId) {
    const p = PARTICIPANTES.find(x => x.id === colabId);
    if (!p) return null;
    return {
        nome: p.nome,
        cpf: p.cpf,
        função: p.função,
        cargo: p.cargo,
        data_admissao: p.data_admissao,
        data_realizacao: TREINAMENTO.data_realizacao,
        data_realizacao_fim: TREINAMENTO.data_realizacao_fim,
        data_emissao: TREINAMENTO.data_emissao,
    };
}

function getTipoCert() {
    return TIPOS_MAP[TREINAMENTO.tipo_certificado_id] || null;
}

function getMinistrante() {
    return TREINAMENTO.ministrante_id ? MINISTRANTES_MAP[TREINAMENTO.ministrante_id] : null;
}

function previewParticipante(colabId) {
    const colabData = getParticipanteData(colabId);
    const tipoCert = getTipoCert();
    const ministrante = getMinistrante();
    if (!colabData || !tipoCert) return;

    document.getElementById('preview-container').style.display = 'block';
    renderPreview(colabData, tipoCert, ministrante);
    document.getElementById('preview-container').scrollIntoView({ behavior: 'smooth' });
}

function baixarPdfParticipante(colabId) {
    const colabData = getParticipanteData(colabId);
    const tipoCert = getTipoCert();
    const ministrante = getMinistrante();
    if (!colabData || !tipoCert) return;
    baixarPdfIndividual(colabData, tipoCert, ministrante);
}

async function baixarTodosZip() {
    const tipoCert = getTipoCert();
    const ministrante = getMinistrante();
    if (!tipoCert) return;

    const overlay = document.getElementById('progress-overlay');
    overlay.style.display = 'flex';
    const bar = document.getElementById('progress-bar');
    const text = document.getElementById('progress-text');
    const total = PARTICIPANTES.length;

    const zip = new JSZip();
    const container = document.createElement('div');
    container.style.cssText = 'position:fixed; left:-9999px; top:0; width:1123px;';
    document.body.appendChild(container);

    for (let i = 0; i < total; i++) {
        const p = PARTICIPANTES[i];
        const colabData = {
            nome: p.nome, cpf: p.cpf, função: p.função, cargo: p.cargo,
            data_admissao: p.data_admissao,
            data_realizacao: TREINAMENTO.data_realizacao,
            data_realizacao_fim: TREINAMENTO.data_realizacao_fim,
            data_emissao: TREINAMENTO.data_emissao,
        };

        bar.style.width = ((i + 1) / total * 100) + '%';
        text.textContent = `${i + 1} / ${total} - ${p.nome}`;

        // Generate certificate HTML (returns a single string with multiple .cert-page divs)
        const certHtml = gerarCertificadoHtml(colabData, tipoCert, ministrante);

        // Parse into individual pages
        container.innerHTML = certHtml;
        const certPages = container.querySelectorAll('.cert-page');

        const pdf = new jspdf.jsPDF({ orientation: 'landscape', unit: 'px', format: [1123, 794] });
        let firstPage = true;

        for (const pageEl of certPages) {
            // Render each page individually
            const tempDiv = document.createElement('div');
            tempDiv.style.cssText = 'position:fixed; left:-9999px; top:0; width:1123px;';
            tempDiv.appendChild(pageEl.cloneNode(true));
            document.body.appendChild(tempDiv);
            await new Promise(r => setTimeout(r, 50));

            const canvas = await html2canvas(tempDiv, { scale: 1, width: 1123, height: 794, useCORS: true });
            const imgData = canvas.toDataURL('image/jpeg', 0.92);

            if (!firstPage) pdf.addPage([1123, 794], 'landscape');
            pdf.addImage(imgData, 'JPEG', 0, 0, 1123, 794);
            firstPage = false;
            document.body.removeChild(tempDiv);
        }

        const pdfBlob = pdf.output('blob');
        const safeName = p.nome.replace(/[^a-zA-Z0-9\s]/g, '').trim();
        zip.file(`${safeName} - ${tipoCert.codigo}.pdf`, pdfBlob);

        await new Promise(r => setTimeout(r, 30));
    }

    document.body.removeChild(container);

    text.textContent = 'Compactando ZIP...';
    const zipBlob = await zip.generateAsync({ type: 'blob' });

    const a = document.createElement('a');
    a.href = URL.createObjectURL(zipBlob);
    a.download = `Treinamento_${tipoCert.codigo}_${TREINAMENTO.data_realizacao}.zip`;
    a.click();
    URL.revokeObjectURL(a.href);

    overlay.style.display = 'none';
}

// ---- Marcar presença ----
async function marcarPresenca(certId, valor, sel) {
    const fd = new FormData();
    fd.append('_csrf_token', document.querySelector('[name=_csrf_token]')?.value || '');
    fd.append('certificado_id', certId);
    fd.append('presente', valor);
    sel.disabled = true;
    try {
        const res = await fetch('/treinamentos/<?= $treinamento['id'] ?>/marcar-presenca', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.success) alert(data.error || 'Falha ao marcar presença.');
    } catch (e) {
        alert('Erro de comunicação.');
    } finally {
        sel.disabled = false;
    }
}

// ---- Remover colaborador ----
async function removerColaborador(certId, nome) {
    if (!confirm(`Remover ${nome} desta turma?`)) return;
    const fd = new FormData();
    fd.append('_csrf_token', document.querySelector('[name=_csrf_token]')?.value || '');
    fd.append('certificado_id', certId);
    try {
        const res = await fetch('/treinamentos/<?= $treinamento['id'] ?>/remover-colaborador', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Falha ao remover colaborador.');
        }
    } catch (e) {
        alert('Erro de comunicação. Tente novamente.');
    }
}

// ---- Upload certificado assinado ----
function abrirUploadAssinado(certId) {
    document.getElementById('upload-cert-id').value = certId;
    document.getElementById('upload-modal-trein').style.display = 'flex';
}
function fecharUploadModal() {
    document.getElementById('upload-modal-trein').style.display = 'none';
}
</script>

<!-- Modal upload certificado assinado -->
<div id="upload-modal-trein" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:white; padding:32px; border-radius:12px; min-width:360px; max-width:500px; width:90%;">
        <h3 style="margin:0 0 16px;">Vincular Certificado Assinado</h3>
        <form method="POST" action="/treinamentos/<?= $treinamento['id'] ?>/upload-assinado" enctype="multipart/form-data">
            <?= \App\Core\View::csrfField() ?>
            <input type="hidden" name="certificado_id" id="upload-cert-id">
            <div class="form-group">
                <label>Arquivo PDF assinado *</label>
                <input type="file" name="arquivo_assinado" accept=".pdf" class="form-control" required>
            </div>
            <div style="display:flex; gap:8px; margin-top:16px;">
                <button type="submit" class="btn btn-primary">Vincular</button>
                <button type="button" class="btn btn-outline" onclick="fecharUploadModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal adicionar colaboradores -->
<div id="modal-add-colab" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:white; padding:32px; border-radius:12px; min-width:400px; max-width:600px; width:90%; max-height:80vh; display:flex; flex-direction:column;">
        <h3 style="margin:0 0 16px;">Adicionar Colaboradores</h3>
        <form id="form-add-colab" method="POST" action="/treinamentos/<?= $treinamento['id'] ?>/adicionar-colaboradores" onsubmit="enviarAddColab(event)" style="display:flex; flex-direction:column; flex:1; overflow:hidden;">
            <?= \App\Core\View::csrfField() ?>
            <div class="form-group">
                <label>Buscar colaborador</label>
                <input type="text" id="search-add-colab" class="form-control" placeholder="Digite o nome..." autocomplete="off" oninput="buscarColabAdd(this.value)">
            </div>
            <div id="add-colab-list" style="flex:1; overflow-y:auto; border:1px solid #e5e7eb; border-radius:6px; max-height:280px; padding:8px;">
                <p style="color:#6b7280; font-size:13px; text-align:center; padding:20px 0;">Digite para buscar colaboradores</p>
            </div>
            <div style="display:flex; gap:8px; margin-top:16px;">
                <button type="submit" class="btn btn-primary">Adicionar Selecionados</button>
                <button type="button" class="btn btn-outline" onclick="fecharModalAddColab()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalAddColab() {
    document.getElementById('modal-add-colab').style.display = 'flex';
    document.getElementById('search-add-colab').focus();
}
function fecharModalAddColab() {
    document.getElementById('modal-add-colab').style.display = 'none';
}

async function enviarAddColab(ev) {
    ev.preventDefault();
    const form = ev.target;
    const fd = new FormData(form);
    const checks = form.querySelectorAll('input[name="colaborador_ids[]"]:checked');
    if (!checks.length) { alert('Selecione ao menos um colaborador.'); return; }
    try {
        const res = await fetch(form.action, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Falha ao adicionar colaboradores.');
        }
    } catch (e) {
        alert('Erro de comunicação. Tente novamente.');
    }
}

let searchTimer;
function buscarColabAdd(q) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(async () => {
        if (q.trim().length < 2) return;
        const res = await fetch(`/treinamentos/colaboradores-json?q=${encodeURIComponent(q)}`);
        const list = await res.json();
        const el = document.getElementById('add-colab-list');
        if (!list.length) {
            el.innerHTML = '<p style="color:#6b7280; font-size:13px; text-align:center; padding:20px 0;">Nenhum resultado</p>';
            return;
        }
        el.innerHTML = list.map(c => `
            <label style="display:flex; align-items:center; gap:8px; padding:8px; cursor:pointer; border-radius:4px;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background=''">
                <input type="checkbox" name="colaborador_ids[]" value="${c.id}">
                <span style="font-size:13px;"><strong>${c.nome_completo}</strong> <span style="color:#6b7280;">${c.cargo || ''}</span></span>
            </label>
        `).join('');
    }, 300);
}
</script>
