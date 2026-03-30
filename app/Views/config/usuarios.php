<div style="display:flex; gap:24px;">
    <!-- Lista de usuarios -->
    <div class="table-container" style="flex:2;">
        <div class="table-header">
            <span class="table-title">Usuarios do Sistema</span>
            <button class="btn btn-primary btn-sm" data-modal="modal-novo-usuario">+ Novo Usuario</button>
        </div>
        <table>
            <thead><tr><th>Nome</th><th>Email</th><th>Perfil</th><th>Ultimo Login</th><th>Acoes</th></tr></thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['nome']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="badge badge-ativo"><?= strtoupper($u['perfil']) ?></span></td>
                <td><?= $u['ultimo_login'] ? date('d/m/Y H:i', strtotime($u['ultimo_login'])) : 'Nunca' ?></td>
                <td style="display:flex;gap:4px;">
                    <button class="btn btn-outline btn-sm" data-modal="modal-reset-<?= $u['id'] ?>">Resetar Senha</button>
                    <form method="POST" action="/usuarios/<?= $u['id'] ?>/excluir" style="display:inline;">
                        <?= \App\Core\View::csrfField() ?>
                        <button type="submit" class="btn btn-danger btn-sm" data-confirm="Excluir usuario <?= htmlspecialchars($u['nome']) ?>?">Excluir</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Novo Usuario -->
<div class="modal-overlay" id="modal-novo-usuario">
    <div class="modal">
        <div class="modal-header">
            <h2>Novo Usuario</h2>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST" action="/usuarios/salvar">
            <?= \App\Core\View::csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>Nome Completo</label>
                    <input type="text" name="nome" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Senha</label>
                    <input type="password" name="senha" class="form-control" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Perfil</label>
                    <select name="perfil" class="form-control">
                        <option value="rh">RH (somente leitura)</option>
                        <option value="sesmt">SESMT (controle total)</option>
                        <option value="admin">Admin (configurações)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline modal-close">Cancelar</button>
                <button type="submit" class="btn btn-primary">Criar Usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- Modais Reset Senha -->
<?php foreach ($usuarios as $u): ?>
<div class="modal-overlay" id="modal-reset-<?= $u['id'] ?>">
    <div class="modal">
        <div class="modal-header">
            <h2>Resetar Senha - <?= htmlspecialchars($u['nome']) ?></h2>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST" action="/usuarios/<?= $u['id'] ?>/resetar">
            <?= \App\Core\View::csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>Nova Senha</label>
                    <input type="password" name="nova_senha" class="form-control" required minlength="6">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline modal-close">Cancelar</button>
                <button type="submit" class="btn btn-primary">Alterar Senha</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>
