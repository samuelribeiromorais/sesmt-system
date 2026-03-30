<div class="page-header">
    <h1>Gerar Evento eSocial</h1>
    <a href="/esocial" class="btn btn-outline btn-sm">Voltar</a>
</div>

<div style="max-width:720px;">
    <!-- Info do Colaborador -->
    <div style="padding:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:24px;">
        <h3 style="margin:0 0 8px;"><?= htmlspecialchars($colab['nome_completo']) ?></h3>
        <div style="display:flex;gap:24px;color:#475569;font-size:14px;">
            <span>Cargo: <strong><?= htmlspecialchars($colab['cargo'] ?? '-') ?></strong></span>
            <span>Função: <strong><?= htmlspecialchars($colab['funcao'] ?? '-') ?></strong></span>
            <?php if (!empty($colab['cliente_nome'])): ?>
                <span>Cliente: <strong><?= htmlspecialchars($colab['cliente_nome']) ?></strong></span>
            <?php endif; ?>
        </div>
    </div>

    <form method="POST" action="/esocial/criar" id="formEsocial">
        <?= \App\Core\View::csrfField() ?>
        <input type="hidden" name="colaborador_id" value="<?= $colab['id'] ?>">

        <div class="form-group">
            <label>Tipo de Evento</label>
            <select name="tipo_evento" id="tipoEvento" class="form-control" required>
                <option value="">Selecione o tipo...</option>
                <option value="S-2210">S-2210 - CAT (Comunicação de Acidente de Trabalho)</option>
                <option value="S-2220">S-2220 - ASO (Monitoramento da Saude do Trabalhador)</option>
                <option value="S-2240">S-2240 - Condicoes Ambientais (Exposicao a Agentes Nocivos)</option>
            </select>
        </div>

        <!-- Campos S-2210 (CAT) -->
        <div id="campos-S-2210" class="campos-evento" style="display:none;">
            <h3 style="color:#dc2626;border-bottom:2px solid #dc2626;padding-bottom:8px;">
                S-2210 - Comunicação de Acidente de Trabalho
            </h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label>Data do Acidente</label>
                    <input type="date" name="data_acidente" class="form-control">
                </div>
                <div class="form-group">
                    <label>Hora do Acidente</label>
                    <input type="time" name="hora_acidente" class="form-control">
                </div>
                <div class="form-group">
                    <label>Tipo de Acidente</label>
                    <select name="tipo_acidente" class="form-control">
                        <option value="">Selecione...</option>
                        <option value="tipico">Tipico</option>
                        <option value="trajeto">De Trajeto</option>
                        <option value="doenca">Doenca Ocupacional</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Houve Obito?</label>
                    <select name="houve_obito" class="form-control">
                        <option value="não">Não</option>
                        <option value="sim">Sim</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Local do Acidente</label>
                <input type="text" name="local_acidente" class="form-control" placeholder="Local onde ocorreu o acidente">
            </div>
            <div class="form-group">
                <label>Parte do Corpo Atingida</label>
                <input type="text" name="parte_atingida" class="form-control" placeholder="Ex: mao esquerda, coluna lombar...">
            </div>
            <div class="form-group">
                <label>Agente Causador</label>
                <input type="text" name="agente_causador" class="form-control" placeholder="Ex: maquina, queda, produto quimico...">
            </div>
            <div class="form-group">
                <label>Descrição do Acidente</label>
                <textarea name="descricao" class="form-control" rows="4" placeholder="Descreva como ocorreu o acidente..."></textarea>
            </div>
        </div>

        <!-- Campos S-2220 (ASO) -->
        <div id="campos-S-2220" class="campos-evento" style="display:none;">
            <h3 style="color:#2563eb;border-bottom:2px solid #2563eb;padding-bottom:8px;">
                S-2220 - Monitoramento da Saude do Trabalhador
            </h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label>Data do ASO</label>
                    <input type="date" name="data_aso" class="form-control">
                </div>
                <div class="form-group">
                    <label>Tipo do ASO</label>
                    <select name="tipo_aso" class="form-control">
                        <option value="">Selecione...</option>
                        <option value="admissional">Admissional</option>
                        <option value="periodico">Periodico</option>
                        <option value="retorno">Retorno ao Trabalho</option>
                        <option value="mudanca">Mudanca de Função</option>
                        <option value="demissional">Demissional</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Resultado</label>
                    <select name="resultado" class="form-control">
                        <option value="">Selecione...</option>
                        <option value="apto">Apto</option>
                        <option value="inapto">Inapto</option>
                        <option value="apto_restricao">Apto com Restricao</option>
                    </select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label>Nome do Médico</label>
                    <input type="text" name="medico_nome" class="form-control">
                </div>
                <div class="form-group">
                    <label>CRM do Médico</label>
                    <input type="text" name="medico_crm" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>Exames Realizados</label>
                <textarea name="exames" class="form-control" rows="3" placeholder="Liste os exames realizados..."></textarea>
            </div>
            <div class="form-group">
                <label>Observacoes</label>
                <textarea name="observacoes" class="form-control" rows="3"></textarea>
            </div>
        </div>

        <!-- Campos S-2240 (Exposicao) -->
        <div id="campos-S-2240" class="campos-evento" style="display:none;">
            <h3 style="color:#d97706;border-bottom:2px solid #d97706;padding-bottom:8px;">
                S-2240 - Condicoes Ambientais do Trabalho
            </h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label>Data de Inicio da Exposicao</label>
                    <input type="date" name="data_inicio" class="form-control">
                </div>
                <div class="form-group">
                    <label>Fator de Risco</label>
                    <input type="text" name="fator_risco" class="form-control" placeholder="Ex: ruido, poeira, calor...">
                </div>
                <div class="form-group">
                    <label>Intensidade / Concentracao</label>
                    <input type="text" name="intensidade" class="form-control" placeholder="Ex: 85 dB(A), 2 mg/m3...">
                </div>
                <div class="form-group">
                    <label>Tecnica Utilizada</label>
                    <input type="text" name="tecnica_utilizada" class="form-control" placeholder="Tecnica/metodologia de medicao">
                </div>
                <div class="form-group">
                    <label>EPC Eficaz?</label>
                    <select name="epc_eficaz" class="form-control">
                        <option value="sim">Sim</option>
                        <option value="não">Não</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>EPI Eficaz?</label>
                    <select name="epi_eficaz" class="form-control">
                        <option value="sim">Sim</option>
                        <option value="não">Não</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Descrição da Atividade</label>
                <textarea name="descricao" class="form-control" rows="4" placeholder="Descreva a atividade exercida e a exposicao..."></textarea>
            </div>
        </div>

        <div style="margin-top:24px;display:flex;gap:12px;">
            <button type="submit" class="btn btn-primary">Criar Evento</button>
            <a href="/esocial" class="btn btn-outline">Cancelar</a>
        </div>
    </form>
</div>

<script>
document.getElementById('tipoEvento').addEventListener('change', function() {
    document.querySelectorAll('.campos-evento').forEach(el => el.style.display = 'none');
    if (this.value) {
        const campos = document.getElementById('campos-' + this.value);
        if (campos) campos.style.display = 'block';
    }
});
</script>
