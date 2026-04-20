<div class="table-container">
    <div class="table-header">
        <span class="table-title">Editar Certificado</span>
    </div>
    <div style="padding:24px;">
        <form method="POST" action="/certificados/<?= $cert['id'] ?>/atualizar">
            <?= \App\Core\View::csrfField() ?>

            <div class="form-grid">
                <div class="form-group">
                    <label>Data de Início do Treinamento *</label>
                    <input type="date" name="data_realizacao" class="form-control" required
                           value="<?= htmlspecialchars($cert['data_realizacao']) ?>">
                </div>

                <div class="form-group">
                    <label>Data de Término do Treinamento</label>
                    <input type="date" name="data_realizacao_fim" class="form-control"
                           value="<?= htmlspecialchars($cert['data_realizacao_fim'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Data de Emissão *</label>
                    <input type="date" name="data_emissao" class="form-control" required
                           value="<?= htmlspecialchars($cert['data_emissao']) ?>">
                </div>

                <div class="form-group">
                    <label>Ministrante</label>
                    <select name="ministrante_id" class="form-control">
                        <option value="">-- Padrão do tipo --</option>
                        <?php foreach ($ministrantes as $m): ?>
                            <option value="<?= $m['id'] ?>"
                                <?= ((int)$cert['ministrante_id'] === (int)$m['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="margin-top:24px; display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                <a href="/colaboradores/<?= $cert['colaborador_id'] ?>" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>
