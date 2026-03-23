<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <h2>Gerar Kit PJ</h2>
    <a href="/kit-pj" class="btn btn-outline btn-sm">Voltar</a>
</div>

<form method="POST" action="/kit-pj/salvar" class="table-container" style="padding:24px;">
    <?= \App\Core\View::csrfField() ?>

    <h3 style="margin-bottom:16px; color:var(--c-primary);">Dados da Empresa PJ</h3>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px;">
        <div>
            <label class="form-label">Razao Social *</label>
            <input type="text" name="razao_social" class="form-input" required placeholder="Ex: QUARKS AUTOMACAO">
        </div>
        <div>
            <label class="form-label">CNPJ *</label>
            <input type="text" name="cnpj" class="form-input" required placeholder="00.000.000/0000-00">
        </div>
        <div style="grid-column:1/-1;">
            <label class="form-label">Endereco</label>
            <input type="text" name="endereco" class="form-input" placeholder="Rua, Bairro, Cidade/UF, CEP">
        </div>
    </div>

    <h3 style="margin-bottom:16px; color:var(--c-primary);">Colaborador</h3>
    <div style="display:grid; grid-template-columns:2fr 1fr; gap:16px; margin-bottom:24px;">
        <div>
            <label class="form-label">Colaborador *</label>
            <select name="colaborador_id" class="form-input" required id="selectColab">
                <option value="">-- Selecione --</option>
                <?php foreach ($colaboradores as $c): ?>
                <option value="<?= $c['id'] ?>"
                    data-cargo="<?= htmlspecialchars($c['cargo'] ?? '') ?>"
                    data-funcao="<?= htmlspecialchars($c['funcao'] ?? '') ?>"
                    data-setor="<?= htmlspecialchars($c['setor'] ?? '') ?>"
                    data-nascimento="<?= $c['data_nascimento'] ?? '' ?>"
                    <?= ($colab && $colab['id'] == $c['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nome_completo']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label">Tipo de ASO *</label>
            <select name="tipo_aso" class="form-input" required>
                <option value="admissional">Admissional</option>
                <option value="periodico" selected>Periodico</option>
                <option value="demissional">Demissional</option>
                <option value="retorno">Retorno ao Trabalho</option>
                <option value="mudanca_risco">Mudanca de Risco</option>
            </select>
        </div>
    </div>

    <h3 style="margin-bottom:16px; color:var(--c-primary);">Riscos Ocupacionais</h3>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px;">
        <div>
            <label class="form-label">Fisicos</label>
            <input type="text" name="riscos_fisicos" class="form-input" value="Ruido Continuo ou Intermitente">
        </div>
        <div>
            <label class="form-label">Quimicos</label>
            <input type="text" name="riscos_quimicos" class="form-input" value="Ausencia Risco Quimico">
        </div>
        <div>
            <label class="form-label">Biologicos</label>
            <input type="text" name="riscos_biologicos" class="form-input" value="Ausencia Risco Biologicos">
        </div>
        <div>
            <label class="form-label">Ergonomicos</label>
            <input type="text" name="riscos_ergonomicos" class="form-input" value="Posturas em pe/sentado por longos periodos.">
        </div>
        <div style="grid-column:1/-1;">
            <label class="form-label">Acidentes</label>
            <input type="text" name="riscos_acidentes" class="form-input" value="Queda de mesmo nivel e/ou escada de acesso.">
        </div>
    </div>

    <h3 style="margin-bottom:16px; color:var(--c-primary);">Exames</h3>
    <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:8px; margin-bottom:24px;">
        <?php
        $examesList = [
            'Exame Clinico', 'Acuidade Visual', 'Audiometria Tonal Ocupacional',
            'Avaliacao Psicossocial', 'Eletrocardiograma (ECG)', 'Eletroencefalograma (EEG)',
            'Glicose no Sangue', 'Hemograma Completo', 'Espirometria', 'Raio-X de Torax (PA)'
        ];
        foreach ($examesList as $ex): ?>
        <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
            <input type="checkbox" name="exames[]" value="<?= htmlspecialchars($ex) ?>"
                   <?= in_array($ex, ['Exame Clinico', 'Audiometria Tonal Ocupacional']) ? 'checked' : '' ?>>
            <?= htmlspecialchars($ex) ?>
        </label>
        <?php endforeach; ?>
    </div>

    <h3 style="margin-bottom:16px; color:var(--c-primary);">Aptidoes</h3>
    <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:8px; margin-bottom:24px;">
        <?php
        $aptidoesList = [
            'Apto para a funcao', 'Trabalho em Altura', 'Trabalho com Eletricidade',
            'Trabalho em Espaco Confinado'
        ];
        foreach ($aptidoesList as $ap): ?>
        <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
            <input type="checkbox" name="aptidoes[]" value="<?= htmlspecialchars($ap) ?>"
                   <?= $ap === 'Apto para a funcao' ? 'checked' : '' ?>>
            <?= htmlspecialchars($ap) ?>
        </label>
        <?php endforeach; ?>
    </div>

    <h3 style="margin-bottom:16px; color:var(--c-primary);">Medico Responsavel (PCMSO)</h3>
    <div style="display:grid; grid-template-columns:2fr 1fr 1fr; gap:16px; margin-bottom:24px;">
        <div>
            <label class="form-label">Nome</label>
            <input type="text" name="medico_nome" class="form-input" value="Dr. Haroldo Aquino Noleto">
        </div>
        <div>
            <label class="form-label">CRM</label>
            <input type="text" name="medico_crm" class="form-input" value="CRM: 2678">
        </div>
        <div>
            <label class="form-label">UF</label>
            <input type="text" name="medico_uf" class="form-input" value="GO">
        </div>
    </div>

    <div style="text-align:right;">
        <button type="submit" class="btn btn-primary">Gerar Kit PJ</button>
    </div>
</form>

<script>
document.getElementById('selectColab').addEventListener('change', function() {
    const opt = this.selectedOptions[0];
    if (!opt) return;
    // Could auto-fill fields based on colaborador data if needed
});
</script>
