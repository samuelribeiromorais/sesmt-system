<link rel="stylesheet" href="/assets/css/certificados.css">
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="/assets/js/images-data.js"></script>
<script src="/assets/js/certificados.js"></script>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <div>
        <h2><?= htmlspecialchars($colab['nome_completo']) ?></h2>
        <span style="color:var(--c-gray);">Emissao de Certificados</span>
    </div>
    <a href="/colaboradores/<?= $colab['id'] ?>" class="btn btn-outline btn-sm">Voltar</a>
</div>

<div style="display:grid; grid-template-columns: 380px 1fr; gap:24px;">
    <!-- Painel lateral -->
    <div>
        <!-- Cadastrar certificado -->
        <div class="table-container">
            <div class="table-header"><span class="table-title">Cadastrar Certificado</span></div>
            <div style="padding:16px;">
                <form method="POST" action="/certificados/salvar">
                    <?= \App\Core\View::csrfField() ?>
                    <input type="hidden" name="colaborador_id" value="<?= $colab['id'] ?>">

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
                        <label>Data de Inicio do Treinamento</label>
                        <input type="date" name="data_realizacao" id="data-realizacao" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="form-group" id="data-fim-group" style="display:none;">
                        <label>Data de Termino do Treinamento</label>
                        <input type="date" name="data_realizacao_fim" id="data-realizacao-fim" class="form-control">
                        <small id="data-info" style="color:#6b7280; font-size:12px;"></small>
                    </div>

                    <div id="data-alertas" style="margin-bottom:8px;"></div>

                    <div class="form-group">
                        <label>Data de Emissao</label>
                        <input type="date" name="data_emissao" id="data-emissao" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>

                    <div style="display:flex; gap:8px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="atualizarPreview()">Visualizar</button>
                        <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Certificados existentes -->
        <div class="table-container" style="margin-top:16px;">
            <div class="table-header">
                <span class="table-title">Certificados (<?= count($certs) ?>)</span>
                <?php if (!empty($certs)): ?>
                <button class="btn btn-outline btn-sm" onclick="baixarTodosDoColaborador()">ZIP</button>
                <?php endif; ?>
            </div>
            <table>
                <thead><tr><th>Tipo</th><th>Validade</th><th>Acao</th></tr></thead>
                <tbody>
                <?php if (empty($certs)): ?>
                <tr><td colspan="3" style="text-align:center;color:#6b7280;font-size:13px;">Nenhum</td></tr>
                <?php else: ?>
                <?php foreach ($certs as $cert): ?>
                <tr>
                    <td style="font-size:13px;"><?= htmlspecialchars($cert['codigo']) ?></td>
                    <td style="font-size:13px;">
                        <span class="badge badge-<?= $cert['status'] ?>"><?= date('d/m/Y', strtotime($cert['data_validade'])) ?></span>
                    </td>
                    <td>
                        <button class="btn btn-outline btn-sm" onclick="visualizarCert(<?= $cert['tipo_certificado_id'] ?>, '<?= $cert['data_realizacao'] ?>', '<?= $cert['data_realizacao_fim'] ?? '' ?>', '<?= $cert['data_emissao'] ?>', <?= $cert['ministrante_id'] ?? $cert['tipo_ministrante_id'] ?? 'null' ?>)">Ver</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Area de preview -->
    <div class="table-container" style="min-height:600px;">
        <div class="table-header">
            <span class="table-title">Preview do Certificado</span>
            <div style="display:flex;gap:8px;">
                <button class="btn btn-outline btn-sm" onclick="imprimirCertificado()">Imprimir</button>
                <button class="btn btn-primary btn-sm" onclick="baixarPdfAtual()">Baixar PDF</button>
            </div>
        </div>
        <div id="preview-area" style="padding:20px; overflow:auto; max-height:80vh;">
            <p style="text-align:center; color:#999; margin-top:200px;">Selecione um certificado e clique em "Visualizar".</p>
        </div>
    </div>
</div>

<script>
const COLAB_DATA = {
    nome: <?= json_encode($colab['nome_completo']) ?>,
    cpf: <?= json_encode($cpfFormatado ?? '***.***.***-**') ?>,
    funcao: <?= json_encode($colab['funcao'] ?? $colab['cargo'] ?? '') ?>,
    cargo: <?= json_encode($colab['cargo'] ?? '') ?>,
    data_admissao: <?= json_encode($colab['data_admissao'] ?? '') ?>,
};

const CERTS_DATA = <?= json_encode($certs) ?>;

// Map tipo_certificado_id to option data
const TIPOS_MAP = {};
document.querySelectorAll('#tipo-cert-select option[data-codigo]').forEach(opt => {
    TIPOS_MAP[opt.value] = {
        titulo: opt.dataset.titulo,
        codigo: opt.dataset.codigo,
        duracao: opt.dataset.duracao,
        validade_meses: parseInt(opt.dataset.validade),
        tem_anuencia: parseInt(opt.dataset.anuencia),
        tem_diego: parseInt(opt.dataset.diego),
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

// === CALCULAR DATAS MULTI-DIA ===
function calcularHoras(duracao) {
    return parseInt(duracao) || 8;
}

function calcularDias(duracao) {
    return Math.ceil(calcularHoras(duracao) / 8);
}

function ehDomingo(date) {
    return date.getDay() === 0;
}

function calcularDataFim(dataInicio, numDias) {
    const d = new Date(dataInicio + 'T00:00:00');
    let diasContados = 1;
    while (diasContados < numDias) {
        d.setDate(d.getDate() + 1);
        if (!ehDomingo(d)) {
            diasContados++;
        }
    }
    return d.toISOString().split('T')[0];
}

function verificarDomingos(dataInicio, dataFim) {
    const alertas = [];
    const d = new Date(dataInicio + 'T00:00:00');
    const fim = new Date(dataFim + 'T00:00:00');
    while (d <= fim) {
        if (ehDomingo(d)) {
            alertas.push(d.toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: '2-digit' }));
        }
        d.setDate(d.getDate() + 1);
    }
    return alertas;
}

function atualizarDatas() {
    const tipoId = document.getElementById('tipo-cert-select').value;
    const dataInicio = document.getElementById('data-realizacao').value;
    const fimGroup = document.getElementById('data-fim-group');
    const fimInput = document.getElementById('data-realizacao-fim');
    const infoEl = document.getElementById('data-info');
    const alertasEl = document.getElementById('data-alertas');

    alertasEl.innerHTML = '';

    if (!tipoId || !TIPOS_MAP[tipoId]) {
        fimGroup.style.display = 'none';
        return;
    }

    const numDias = calcularDias(TIPOS_MAP[tipoId].duracao);

    if (numDias <= 1) {
        fimGroup.style.display = 'none';
        fimInput.value = '';
        return;
    }

    fimGroup.style.display = 'block';
    const horas = calcularHoras(TIPOS_MAP[tipoId].duracao);
    infoEl.textContent = `${horas}h de treinamento = ${numDias} dias (8h/dia)`;

    if (dataInicio) {
        const dataFim = calcularDataFim(dataInicio, numDias);
        fimInput.value = dataFim;

        // Verificar domingos no intervalo
        const inicio = new Date(dataInicio + 'T00:00:00');
        if (ehDomingo(inicio)) {
            alertasEl.innerHTML = '<div style="background:#fff3cd;border:1px solid #f39c12;padding:6px 10px;border-radius:4px;font-size:12px;color:#856404;">⚠ A data de inicio cai em um domingo. Considere ajustar.</div>';
        }

        // Verificar se data_emissao precisa ajustar
        const emissaoEl = document.getElementById('data-emissao');
        if (emissaoEl.value < dataFim) {
            emissaoEl.value = dataFim;
        }
    }
}

// Auto-selecionar ministrante quando tipo muda
document.getElementById('tipo-cert-select').addEventListener('change', function() {
    const tipoId = this.value;
    if (tipoId && TIPOS_MAP[tipoId] && TIPOS_MAP[tipoId].ministrante_id) {
        document.getElementById('ministrante-select').value = TIPOS_MAP[tipoId].ministrante_id;
    }
    atualizarDatas();
});

document.getElementById('data-realizacao').addEventListener('change', atualizarDatas);

function getMinistranteAtual() {
    const sel = document.getElementById('ministrante-select');
    const mId = sel.value;
    if (mId && MINISTRANTES_MAP[mId]) {
        return MINISTRANTES_MAP[mId];
    }
    return null;
}

function atualizarPreview() {
    const tipoId = document.getElementById('tipo-cert-select').value;
    if (!tipoId || !TIPOS_MAP[tipoId]) {
        alert('Selecione um tipo de certificado.');
        return;
    }
    const tipoCert = TIPOS_MAP[tipoId];
    const colabData = {
        ...COLAB_DATA,
        data_realizacao: document.getElementById('data-realizacao').value,
        data_realizacao_fim: document.getElementById('data-realizacao-fim').value || null,
        data_emissao: document.getElementById('data-emissao').value,
    };
    const ministrante = getMinistranteAtual();
    renderPreview(colabData, tipoCert, ministrante);
}

function visualizarCert(tipoId, dataRealizacao, dataRealizacaoFim, dataEmissao, ministranteId) {
    const sel = document.getElementById('tipo-cert-select');
    sel.value = tipoId;
    document.getElementById('data-realizacao').value = dataRealizacao;
    document.getElementById('data-emissao').value = dataEmissao;

    // Atualizar ministrante
    if (ministranteId) {
        document.getElementById('ministrante-select').value = ministranteId;
    } else if (TIPOS_MAP[tipoId] && TIPOS_MAP[tipoId].ministrante_id) {
        document.getElementById('ministrante-select').value = TIPOS_MAP[tipoId].ministrante_id;
    }

    atualizarDatas();
    if (dataRealizacaoFim) {
        document.getElementById('data-realizacao-fim').value = dataRealizacaoFim;
    }

    atualizarPreview();
}

function baixarPdfAtual() {
    const tipoId = document.getElementById('tipo-cert-select').value;
    if (!tipoId || !TIPOS_MAP[tipoId]) {
        alert('Visualize um certificado primeiro.');
        return;
    }
    const tipoCert = TIPOS_MAP[tipoId];
    const colabData = {
        ...COLAB_DATA,
        data_realizacao: document.getElementById('data-realizacao').value,
        data_realizacao_fim: document.getElementById('data-realizacao-fim').value || null,
        data_emissao: document.getElementById('data-emissao').value,
    };
    const ministrante = getMinistranteAtual();
    baixarPdfIndividual(colabData, tipoCert, ministrante);
}

function baixarTodosDoColaborador() {
    baixarTodosPDFs(COLAB_DATA, CERTS_DATA);
}
</script>
