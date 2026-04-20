<link rel="stylesheet" href="/assets/css/certificados.css">
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="/assets/js/images-data.js"></script>
<script src="/assets/js/certificados.js"></script>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <div>
        <h2>Novo Treinamento em Massa</h2>
        <span style="color:var(--c-gray);">Registrar treinamento para multiplos colaboradores</span>
    </div>
    <a href="/treinamentos" class="btn btn-outline btn-sm">Voltar</a>
</div>

<form method="POST" action="/treinamentos/salvar" id="form-treinamento">
    <?= \App\Core\View::csrfField() ?>

    <div style="display:grid; grid-template-columns: 380px 1fr; gap:24px;">
        <!-- Painel lateral -->
        <div>
            <!-- Dados do treinamento -->
            <div class="table-container">
                <div class="table-header"><span class="table-title">Dados do Treinamento</span></div>
                <div style="padding:16px;">
                    <div class="form-group">
                        <label>Tipo de Certificado</label>
                        <select name="tipo_certificado_id" id="tipo-cert-select" class="form-control" required>
                            <option value="">-- Selecionar --</option>
                            <?php foreach ($tipos as $t): ?>
                            <option value="<?= $t['id'] ?>"
                                data-titulo="<?= htmlspecialchars($t['titulo']) ?>"
                                data-codigo="<?= htmlspecialchars($t['codigo']) ?>"
                                data-duracao="<?= htmlspecialchars($t['duracao']) ?>"
                                data-validade="<?= $t['validade_meses'] ?>"
                                data-anuencia="<?= $t['tem_anuencia'] ?>"
                                data-diego="<?= $t['tem_diego'] ?>"
                                data-diego-responsavel="<?= $t['tem_diego_responsavel'] ?? 0 ?>"
                                data-verso="<?= $t['conteudo_no_verso'] ?>"
                                data-conteudo="<?= htmlspecialchars($t['conteudo_programatico'] ?? '[]') ?>"
                                data-ministrante-id="<?= $t['ministrante_id'] ?? '' ?>"
                            ><?= htmlspecialchars($t['codigo']) ?> (<?= $t['duracao'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Ministrante</label>
                        <select name="ministrante_id" id="ministrante-select" class="form-control" required>
                            <option value="">-- Selecionar --</option>
                            <?php foreach ($ministrantes as $m): ?>
                            <option value="<?= $m['id'] ?>"
                                data-nome="<?= htmlspecialchars($m['nome']) ?>"
                                data-cargo="<?= htmlspecialchars($m['cargo_titulo']) ?>"
                                data-registro="<?= htmlspecialchars($m['registro'] ?? '') ?>"
                            ><?= htmlspecialchars($m['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Data de Inicio</label>
                        <input type="date" name="data_realizacao" id="data-realizacao" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="form-group" id="data-fim-group" style="display:none;">
                        <label>Data de Termino</label>
                        <input type="date" name="data_realizacao_fim" id="data-realizacao-fim" class="form-control">
                        <small id="data-info" style="color:#6b7280; font-size:12px;"></small>
                    </div>

                    <div id="data-alertas" style="margin-bottom:8px;"></div>

                    <div class="form-group">
                        <label>Data de Emissão</label>
                        <input type="date" name="data_emissao" id="data-emissao" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="form-group">
                        <label>Observacoes</label>
                        <textarea name="observacoes" class="form-control" rows="2" style="font-size:13px;" placeholder="Opcional..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Seleção de colaboradores -->
            <div class="table-container" style="margin-top:16px;">
                <div class="table-header">
                    <span class="table-title">Participantes (<span id="count-selecionados">0</span>)</span>
                </div>
                <div style="padding:12px 16px;">
                    <input type="text" id="busca-colab" class="form-control" placeholder="Buscar colaborador..." style="font-size:13px; margin-bottom:8px;">
                    <div style="display:flex; gap:6px; margin-bottom:8px;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="selecionarTodosVisiveis()">Selecionar Visiveis</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="limparSelecao()">Limpar</button>
                    </div>
                    <div id="lista-colaboradores" style="max-height:350px; overflow-y:auto; border:1px solid var(--c-border); border-radius:6px;">
                        <?php foreach ($colaboradores as $c): ?>
                        <label class="colab-item" data-nome="<?= htmlspecialchars(mb_strtolower($c['nome_completo'])) ?>" style="display:flex; align-items:center; gap:8px; padding:6px 10px; cursor:pointer; border-bottom:1px solid var(--c-border); font-size:13px;">
                            <input type="checkbox" name="colaborador_ids[]" value="<?= $c['id'] ?>" class="colab-check">
                            <div>
                                <div><?= htmlspecialchars($c['nome_completo']) ?></div>
                                <small style="color:#6b7280;"><?= htmlspecialchars($c['funcao'] ?? $c['cargo'] ?? '') ?><?= $c['setor'] ? ' - ' . htmlspecialchars($c['setor']) : '' ?></small>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div style="margin-top:16px; display:flex; gap:8px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="atualizarPreview()">Visualizar</button>
                <button type="submit" class="btn btn-primary btn-sm" id="btn-registrar">Registrar Treinamento</button>
            </div>
        </div>

        <!-- Area de preview -->
        <div class="table-container" style="min-height:600px;">
            <div class="table-header">
                <span class="table-title">Preview do Certificado</span>
            </div>
            <div id="preview-area" style="padding:20px; overflow:auto; max-height:80vh;">
                <p style="text-align:center; color:#999; margin-top:200px;">Selecione o tipo, preencha as datas e clique em "Visualizar".</p>
            </div>
        </div>
    </div>
</form>

<script>
// Map tipos
const TIPOS_MAP = {};
document.querySelectorAll('#tipo-cert-select option[data-codigo]').forEach(opt => {
    TIPOS_MAP[opt.value] = {
        titulo: opt.dataset.titulo,
        codigo: opt.dataset.codigo,
        duracao: opt.dataset.duracao,
        validade_meses: parseInt(opt.dataset.validade),
        tem_anuencia: parseInt(opt.dataset.anuencia),
        tem_diego: parseInt(opt.dataset.diego),
        tem_diego_responsavel: parseInt(opt.dataset.diegoResponsavel || 0),
        conteudo_no_verso: parseInt(opt.dataset.verso),
        conteudo_programatico: opt.dataset.conteudo,
        ministrante_id: opt.dataset.ministranteId || null,
    };
});

// Map ministrantes
const MINISTRANTES_MAP = {};
document.querySelectorAll('#ministrante-select option[data-nome]').forEach(opt => {
    MINISTRANTES_MAP[opt.value] = {
        id: parseInt(opt.value),
        nome: opt.dataset.nome,
        cargo_titulo: opt.dataset.cargo,
        registro: opt.dataset.registro,
    };
});

// === DATE CALCULATION (reused from emitir.php) ===
function calcularHoras(duracao) { return parseInt(duracao) || 8; }
function calcularDias(duracao) { return Math.ceil(calcularHoras(duracao) / 8); }
function ehDomingo(date) { return date.getDay() === 0; }
function calcularDataFim(dataInicio, numDias) {
    const d = new Date(dataInicio + 'T00:00:00');
    let diasContados = 1;
    while (diasContados < numDias) { d.setDate(d.getDate() + 1); if (!ehDomingo(d)) diasContados++; }
    return d.toISOString().split('T')[0];
}

function atualizarDatas() {
    const tipoId = document.getElementById('tipo-cert-select').value;
    const dataInicio = document.getElementById('data-realizacao').value;
    const fimGroup = document.getElementById('data-fim-group');
    const fimInput = document.getElementById('data-realizacao-fim');
    const infoEl = document.getElementById('data-info');
    const alertasEl = document.getElementById('data-alertas');
    alertasEl.innerHTML = '';
    if (!tipoId || !TIPOS_MAP[tipoId]) { fimGroup.style.display = 'none'; return; }
    const numDias = calcularDias(TIPOS_MAP[tipoId].duracao);
    if (numDias <= 1) { fimGroup.style.display = 'none'; fimInput.value = ''; return; }
    fimGroup.style.display = 'block';
    infoEl.textContent = `${calcularHoras(TIPOS_MAP[tipoId].duracao)}h = ${numDias} dias (8h/dia)`;
    if (dataInicio) {
        const dataFim = calcularDataFim(dataInicio, numDias);
        fimInput.value = dataFim;
        const inicio = new Date(dataInicio + 'T00:00:00');
        if (ehDomingo(inicio)) {
            alertasEl.innerHTML = '<div style="background:#fff3cd;border:1px solid #f39c12;padding:6px 10px;border-radius:4px;font-size:12px;color:#856404;">A data de inicio cai em um domingo.</div>';
        }
        const emissaoEl = document.getElementById('data-emissao');
        if (emissaoEl.value < dataFim) emissaoEl.value = dataFim;
    }
}

document.getElementById('tipo-cert-select').addEventListener('change', function() {
    if (this.value && TIPOS_MAP[this.value] && TIPOS_MAP[this.value].ministrante_id) {
        document.getElementById('ministrante-select').value = TIPOS_MAP[this.value].ministrante_id;
    }
    atualizarDatas();
});
document.getElementById('data-realizacao').addEventListener('change', atualizarDatas);

function getMinistranteAtual() {
    const mId = document.getElementById('ministrante-select').value;
    return mId && MINISTRANTES_MAP[mId] ? MINISTRANTES_MAP[mId] : null;
}

// === COLABORADORES SELECTION ===
const buscaInput = document.getElementById('busca-colab');
const listaEl = document.getElementById('lista-colaboradores');

buscaInput.addEventListener('input', function() {
    const termo = this.value.toLowerCase().trim();
    listaEl.querySelectorAll('.colab-item').forEach(item => {
        item.style.display = item.dataset.nome.includes(termo) ? 'flex' : 'none';
    });
});

function atualizarContador() {
    document.getElementById('count-selecionados').textContent = document.querySelectorAll('.colab-check:checked').length;
}

document.querySelectorAll('.colab-check').forEach(cb => cb.addEventListener('change', atualizarContador));

function selecionarTodosVisiveis() {
    listaEl.querySelectorAll('.colab-item').forEach(item => {
        if (item.style.display !== 'none') {
            item.querySelector('.colab-check').checked = true;
        }
    });
    atualizarContador();
}

function limparSelecao() {
    document.querySelectorAll('.colab-check').forEach(cb => cb.checked = false);
    atualizarContador();
}

// === PREVIEW ===
function atualizarPreview() {
    const tipoId = document.getElementById('tipo-cert-select').value;
    if (!tipoId || !TIPOS_MAP[tipoId]) { alert('Selecione um tipo de certificado.'); return; }

    // Use first selected collaborator for preview, or a placeholder
    const firstChecked = document.querySelector('.colab-check:checked');
    let nome = 'NOME DO COLABORADOR';
    if (firstChecked) {
        nome = firstChecked.closest('.colab-item').querySelector('div > div').textContent;
    }

    const colabData = {
        nome: nome,
        cpf: '***.***.***-**',
        função: '',
        cargo: '',
        data_admissao: '',
        data_realizacao: document.getElementById('data-realizacao').value,
        data_realizacao_fim: document.getElementById('data-realizacao-fim').value || null,
        data_emissao: document.getElementById('data-emissao').value,
    };
    const ministrante = getMinistranteAtual();
    renderPreview(colabData, TIPOS_MAP[tipoId], ministrante);
}

// Validate form
document.getElementById('form-treinamento').addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('.colab-check:checked');
    if (checked.length === 0) {
        e.preventDefault();
        alert('Selecione ao menos um colaborador.');
        return;
    }
    document.getElementById('btn-registrar').disabled = true;
    document.getElementById('btn-registrar').textContent = 'Registrando...';
});
</script>
