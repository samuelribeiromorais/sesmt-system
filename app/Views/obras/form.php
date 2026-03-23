<?php $editing = !empty($obra); ?>
<div class="table-container">
    <div class="table-header">
        <span class="table-title"><?= $editing ? 'Editar' : 'Nova' ?> Obra - <?= htmlspecialchars($cliente['nome_fantasia'] ?? $cliente['razao_social']) ?></span>
    </div>
    <div style="padding:24px;">
        <form method="POST" action="<?= $editing ? "/obras/{$obra['id']}/atualizar" : '/obras/salvar' ?>">
            <?= \App\Core\View::csrfField() ?>
            <input type="hidden" name="cliente_id" value="<?= $cliente['id'] ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>Nome da Obra *</label>
                    <input type="text" name="nome" class="form-control" required value="<?= htmlspecialchars($obra['nome'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Local</label>
                    <input type="text" name="local_obra" class="form-control" value="<?= htmlspecialchars($obra['local_obra'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Data Inicio</label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= $obra['data_inicio'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Data Fim</label>
                    <input type="date" name="data_fim" class="form-control" value="<?= $obra['data_fim'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="ativa" <?= ($obra['status'] ?? 'ativa') === 'ativa' ? 'selected' : '' ?>>Ativa</option>
                        <option value="concluida" <?= ($obra['status'] ?? '') === 'concluida' ? 'selected' : '' ?>>Concluida</option>
                        <option value="suspensa" <?= ($obra['status'] ?? '') === 'suspensa' ? 'selected' : '' ?>>Suspensa</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Validade EPI (meses)</label>
                    <input type="number" name="epi_validade_meses" class="form-control" min="1" max="24"
                           value="<?= htmlspecialchars($obra['epi_validade_meses'] ?? '') ?>"
                           placeholder="Padrao: 6 meses">
                    <small style="color:var(--c-gray);">Deixe vazio para usar o padrao (6 meses). Preencha se esta obra exige validade diferente.</small>
                </div>
            </div>
            <div style="margin-top:24px;display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary"><?= $editing ? 'Salvar' : 'Cadastrar' ?></button>
                <a href="/clientes/<?= $cliente['id'] ?>" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>
