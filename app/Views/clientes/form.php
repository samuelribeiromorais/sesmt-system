<?php $editing = !empty($cliente); ?>
<div class="table-container">
    <div class="table-header">
        <span class="table-title"><?= $editing ? 'Editar' : 'Novo' ?> Cliente</span>
    </div>
    <div style="padding:24px;">
        <form method="POST" action="<?= $editing ? "/clientes/{$cliente['id']}/atualizar" : '/clientes/salvar' ?>">
            <?= \App\Core\View::csrfField() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label>Razao Social *</label>
                    <input type="text" name="razao_social" class="form-control" required value="<?= htmlspecialchars($cliente['razao_social'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Nome Fantasia</label>
                    <input type="text" name="nome_fantasia" class="form-control" value="<?= htmlspecialchars($cliente['nome_fantasia'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>CNPJ</label>
                    <input type="text" name="cnpj" class="form-control" value="<?= htmlspecialchars($cliente['cnpj'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Contato - Nome</label>
                    <input type="text" name="contato_nome" class="form-control" value="<?= htmlspecialchars($cliente['contato_nome'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Contato - Email</label>
                    <input type="email" name="contato_email" class="form-control" value="<?= htmlspecialchars($cliente['contato_email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Contato - Telefone</label>
                    <input type="text" name="contato_telefone" class="form-control" value="<?= htmlspecialchars($cliente['contato_telefone'] ?? '') ?>">
                </div>
                <?php if ($editing): ?>
                <div class="form-group">
                    <label>Status</label>
                    <select name="ativo" class="form-control">
                        <option value="1" <?= $cliente['ativo'] ? 'selected' : '' ?>>Ativo</option>
                        <option value="0" <?= !$cliente['ativo'] ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <div style="margin-top:24px;display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary"><?= $editing ? 'Salvar' : 'Cadastrar' ?></button>
                <a href="/clientes" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>
