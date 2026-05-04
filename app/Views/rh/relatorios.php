<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <p style="color:#6b7280; margin:0;">Exportações Excel para análise externa e auditoria.</p>
    <a href="/rh" class="btn btn-secondary btn-sm">← Voltar ao painel</a>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">

    <!-- Pendências por cliente -->
    <div class="table-container">
        <div class="table-header">
            <span class="table-title">Pendências em aberto por cliente</span>
        </div>
        <div style="padding:16px;">
            <p style="color:#6b7280; font-size:13px;">Todas as pendências de envio ainda não tratadas. Indica também o prazo SLA.</p>
            <form method="GET" action="/rh/relatorios/pendencias-cliente.xlsx" style="display:flex; gap:8px; align-items:center;">
                <select name="cliente_id" class="form-control" style="flex:1;">
                    <option value="0">Todos os clientes</option>
                    <?php foreach ($clientes as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome_fantasia']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">📥 Exportar XLSX</button>
            </form>
        </div>
    </div>

    <!-- Conformidade por obra -->
    <div class="table-container">
        <div class="table-header">
            <span class="table-title">Conformidade por obra</span>
        </div>
        <div style="padding:16px;">
            <p style="color:#6b7280; font-size:13px;">Percentual de protocolos confirmados em cada obra. Útil para reuniões com o cliente.</p>
            <a href="/rh/relatorios/conformidade-obra.xlsx" class="btn btn-primary btn-sm">📥 Exportar XLSX</a>
        </div>
    </div>

    <!-- Histórico por colaborador -->
    <div class="table-container" style="grid-column:1/-1;">
        <div class="table-header">
            <span class="table-title">Histórico de protocolos por colaborador</span>
        </div>
        <div style="padding:16px;">
            <p style="color:#6b7280; font-size:13px;">Linha do tempo dos protocolos de um colaborador específico (todos os clientes, todos os documentos).</p>
            <form method="GET" action="/rh/relatorios/historico-colab.xlsx" style="display:flex; gap:8px; align-items:center;">
                <input type="number" name="colab_id" class="form-control" placeholder="ID do colaborador" required style="max-width:200px;">
                <button type="submit" class="btn btn-primary btn-sm">📥 Exportar XLSX</button>
                <span style="color:#6b7280; font-size:12px;">Encontre o ID em /colaboradores</span>
            </form>
        </div>
    </div>

</div>
