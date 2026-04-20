<div class="table-container">
    <div class="table-header">
        <span class="table-title">Editar Treinamento</span>
        <a href="/treinamentos/<?= $treinamento['id'] ?>" class="btn btn-outline">Voltar</a>
    </div>
    <div style="padding:24px;">
        <form method="POST" action="/treinamentos/<?= $treinamento['id'] ?>/atualizar">
            <?= \App\Core\View::csrfField() ?>

            <div class="form-grid">
                <div class="form-group">
                    <label>Tipo de Certificado</label>
                    <input type="text" class="form-control" disabled
                           value="<?= htmlspecialchars($treinamento['tipo_codigo'] . ' — ' . $treinamento['tipo_titulo']) ?>">
                </div>

                <div class="form-group">
                    <label>Ministrante</label>
                    <select name="ministrante_id" class="form-control">
                        <option value="">-- Padrão do tipo --</option>
                        <?php foreach ($ministrantes as $m): ?>
                            <option value="<?= $m['id'] ?>"
                                <?= ((int)($treinamento['ministrante_id'] ?? 0) === (int)$m['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Data de Início *</label>
                    <input type="date" name="data_realizacao" class="form-control" required
                           value="<?= htmlspecialchars($treinamento['data_realizacao']) ?>">
                </div>

                <div class="form-group">
                    <label>Data de Término</label>
                    <input type="date" name="data_realizacao_fim" class="form-control"
                           value="<?= htmlspecialchars($treinamento['data_realizacao_fim'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Data de Emissão *</label>
                    <input type="date" name="data_emissao" class="form-control" required
                           value="<?= htmlspecialchars($treinamento['data_emissao']) ?>">
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Observações</label>
                    <textarea name="observacoes" class="form-control" rows="3"><?= htmlspecialchars($treinamento['observacoes'] ?? '') ?></textarea>
                </div>
            </div>

            <div style="margin-top:24px; display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                <a href="/treinamentos/<?= $treinamento['id'] ?>" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>
