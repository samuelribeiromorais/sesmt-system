<div class="table-container">
    <div class="table-header">
        <span class="table-title">Upload de Documento - <?= htmlspecialchars($colab['nome_completo']) ?></span>
    </div>
    <div style="padding:24px;">
        <form method="POST" action="/documentos/upload" enctype="multipart/form-data">
            <?= \App\Core\View::csrfField() ?>
            <input type="hidden" name="colaborador_id" value="<?= $colab['id'] ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label for="tipo_documento_id">Tipo de Documento *</label>
                    <select id="tipo_documento_id" name="tipo_documento_id" class="form-control" required>
                        <option value="">-- Selecionar --</option>
                        <?php
                        $lastCat = '';
                        foreach ($tipos as $t):
                            if ($t['categoria'] !== $lastCat):
                                if ($lastCat) echo '</optgroup>';
                                $catLabels = ['aso'=>'ASO','epi'=>'EPI','os'=>'Ordem de Servico','treinamento'=>'Treinamento','anuencia'=>'Anuencia','outro'=>'Outros'];
                                echo '<optgroup label="' . ($catLabels[$t['categoria']] ?? ucfirst($t['categoria'])) . '">';
                                $lastCat = $t['categoria'];
                            endif;
                        ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nome']) ?><?= $t['validade_meses'] ? " ({$t['validade_meses']}m)" : '' ?></option>
                        <?php endforeach; ?>
                        <?php if ($lastCat) echo '</optgroup>'; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="data_emissao">Data de Emissao *</label>
                    <input type="date" id="data_emissao" name="data_emissao" class="form-control" required
                           value="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="arquivos">Arquivo(s) PDF *</label>
                    <input type="file" id="arquivos" name="arquivos[]" class="form-control" accept=".pdf" multiple required>
                    <small style="color:var(--c-gray);">Tamanho maximo: 10MB por arquivo. Apenas PDF. Voce pode selecionar multiplos arquivos PDF.</small>
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="observacoes">Observacoes</label>
                    <textarea id="observacoes" name="observacoes" class="form-control" rows="3"></textarea>
                </div>
            </div>

            <div style="margin-top:24px; display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary">Enviar Documento</button>
                <a href="/colaboradores/<?= $colab['id'] ?>" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>
