<?php
$mesAtual = date('m');
$anoAtual = date('Y');
$nomesMes = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho',
             '07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
$nomeMesAtual = $nomesMes[$mesAtual];
?>

<!-- Destaque: Documentos Vencidos -->
<div class="table-container" style="margin-bottom:24px; border-left:4px solid #dc2626;">
    <div style="padding:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <div style="font-size:16px; font-weight:700; color:#dc2626; margin-bottom:4px;">Documentos e Certificados Vencidos</div>
            <p style="color:var(--c-gray); font-size:14px; margin:0;">
                Visão completa de tudo que está com validade expirada, agrupado por tipo de documento.
            </p>
        </div>
        <a href="/relatorios/vencidos" class="btn btn-primary" style="background:#dc2626; border-color:#dc2626; white-space:nowrap;">
            Ver Vencidos
        </a>
    </div>
</div>

<!-- Linha 1: Colaborador + Cliente -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:24px;">
    <div class="table-container">
        <div class="table-header"><span class="table-title">Relatório por Colaborador</span></div>
        <div style="padding:24px;">
            <p style="color:var(--c-gray);font-size:14px;margin-bottom:16px;">Todos os documentos e certificados do colaborador selecionado.</p>
            <div class="form-group">
                <label class="form-label">Selecione o Colaborador</label>
                <select class="form-input" onchange="if(this.value) window.location='/relatorios/colaborador/'+this.value">
                    <option value="">-- Selecionar --</option>
                    <?php foreach ($colaboradores as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="table-container">
        <div class="table-header"><span class="table-title">Relatório por Cliente</span></div>
        <div style="padding:24px;">
            <p style="color:var(--c-gray);font-size:14px;margin-bottom:16px;">Conformidade de todos os colaboradores do cliente (exporta Excel).</p>
            <div class="form-group">
                <label class="form-label">Selecione o Cliente</label>
                <select class="form-input" onchange="if(this.value) window.location='/relatorios/cliente/'+this.value">
                    <option value="">-- Selecionar --</option>
                    <?php foreach ($clientes as $cl): ?>
                    <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['nome_fantasia'] ?? $cl['razao_social']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Linha 2: Obra + Tipo de Documento -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:24px;">
    <div class="table-container">
        <div class="table-header"><span class="table-title">Relatório por Obra</span></div>
        <div style="padding:24px;">
            <p style="color:var(--c-gray);font-size:14px;margin-bottom:16px;">Conformidade dos colaboradores alocados na obra, com requisitos do cliente.</p>
            <div class="form-group">
                <label class="form-label">Selecione a Obra</label>
                <select class="form-input" onchange="if(this.value) window.location='/relatorios/obra/'+this.value">
                    <option value="">-- Selecionar --</option>
                    <?php foreach ($obras as $ob): ?>
                    <option value="<?= $ob['id'] ?>"><?= htmlspecialchars($ob['nome']) ?><?= !empty($ob['local_obra']) ? ' — ' . htmlspecialchars($ob['local_obra']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="table-container">
        <div class="table-header"><span class="table-title">Relatório por Tipo de Documento</span></div>
        <div style="padding:24px;">
            <p style="color:var(--c-gray);font-size:14px;margin-bottom:16px;">Todos os documentos de um tipo específico, com filtro de status e período.</p>
            <div class="form-group">
                <label class="form-label">Tipo de Documento</label>
                <select class="form-input" id="selectTipoDoc">
                    <option value="">-- Selecionar --</option>
                    <?php foreach ($tiposDocumento as $t): ?>
                    <option value="<?= $t['id'] ?>">[<?= htmlspecialchars(ucfirst($t['categoria'])) ?>] <?= htmlspecialchars($t['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                <div>
                    <label class="form-label">Mes (opcional)</label>
                    <select class="form-input" id="selectMesDoc">
                        <option value="">Todos</option>
                        <?php foreach ($nomesMes as $num => $nome): ?>
                        <option value="<?= (int)$num ?>"><?= $nome ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Ano (opcional)</label>
                    <select class="form-input" id="selectAnoDoc">
                        <option value="">Todos</option>
                        <?php for ($a = date('Y'); $a >= 2022; $a--): ?>
                        <option value="<?= $a ?>"><?= $a ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <button class="btn btn-primary" onclick="irTipoDoc()">Gerar Relatório</button>
        </div>
    </div>
</div>

<!-- Linha 3: Mensal + Mês Corrente -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
    <div class="table-container">
        <div class="table-header"><span class="table-title">Relatório Mensal</span></div>
        <div style="padding:24px;">
            <p style="color:var(--c-gray);font-size:14px;margin-bottom:16px;">Todos os documentos e certificados inseridos no sistema em um determinado mes.</p>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div>
                    <label class="form-label">Mes</label>
                    <select class="form-input" id="selectMes">
                        <?php foreach ($nomesMes as $num => $nome): ?>
                        <option value="<?= (int)$num ?>" <?= $num === $mesAtual ? 'selected' : '' ?>><?= $nome ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Ano</label>
                    <select class="form-input" id="selectAno">
                        <?php for ($a = date('Y'); $a >= 2022; $a--): ?>
                        <option value="<?= $a ?>"><?= $a ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <button class="btn btn-primary" onclick="irMensal()">Gerar Relatório</button>
        </div>
    </div>

    <div class="table-container" style="border-left: 4px solid var(--c-primary);">
        <div class="table-header"><span class="table-title">Documentos do Mes Corrente</span></div>
        <div style="padding:24px;">
            <p style="color:var(--c-gray);font-size:14px;margin-bottom:12px;">
                Documentos inseridos em <strong><?= $nomeMesAtual ?>/<?= $anoAtual ?></strong>.
            </p>
            <div style="font-size:2rem; font-weight:700; color:var(--c-primary); margin-bottom:16px;">
                <?= $docsMesCount ?>
                <span style="font-size:1rem; font-weight:400; color:var(--c-gray);">documentos</span>
            </div>
            <a href="/relatorios/mensal?mes=<?= (int)$mesAtual ?>&ano=<?= $anoAtual ?>" class="btn btn-primary">Ver Detalhes</a>
        </div>
    </div>
</div>

<script>
function irMensal() {
    const mes = document.getElementById('selectMes').value;
    const ano = document.getElementById('selectAno').value;
    window.location = '/relatorios/mensal?mes=' + mes + '&ano=' + ano;
}
function irTipoDoc() {
    const tipo = document.getElementById('selectTipoDoc').value;
    if (!tipo) { alert('Selecione um tipo de documento.'); return; }
    const mes = document.getElementById('selectMesDoc').value;
    const ano = document.getElementById('selectAnoDoc').value;
    let url = '/relatorios/tipo-documento?tipo_documento_id=' + tipo;
    if (mes && ano) url += '&mes=' + mes + '&ano=' + ano;
    window.location = url;
}
</script>
