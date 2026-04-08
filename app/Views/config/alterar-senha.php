<div class="card">
    <div class="card-header">
        <h2>Alterar Senha</h2>
    </div>
    <div class="card-body">

        <?php if (!empty($senha_expirada)): ?>
            <div class="alert alert-warning" style="margin-bottom:20px;">
                Sua senha expirou (mais de 90 dias sem alteracao). Por favor, crie uma nova senha para continuar.
            </div>
        <?php endif; ?>

        <p style="margin-bottom:16px; font-size:13px; color:#666;">
            A nova senha deve atender aos seguintes requisitos:
        </p>
        <ul style="margin-bottom:20px; font-size:13px; color:#666; padding-left:20px; line-height:2;">
            <li>Minimo de <strong>12 caracteres</strong></li>
            <li>Pelo menos uma letra <strong>maiuscula</strong> (A-Z)</li>
            <li>Pelo menos uma letra <strong>minuscula</strong> (a-z)</li>
            <li>Pelo menos um <strong>numero</strong> (0-9)</li>
            <li>Pelo menos um <strong>caractere especial</strong> (!@#$%^&* etc.)</li>
            <li>Sem sequencias obvias (1234, abcd, qwer, aaaa)</li>
            <li>Sem palavras comuns (senha, password, admin)</li>
            <li>Nao pode ser igual as 5 ultimas senhas utilizadas</li>
        </ul>

        <form method="POST" action="/usuarios/alterar-senha" style="max-width:480px;">
            <?= \App\Core\View::csrfField() ?>

            <div class="form-group">
                <label for="senha_atual">Senha Atual</label>
                <input type="password" id="senha_atual" name="senha_atual" required
                       autocomplete="current-password" class="form-control">
            </div>

            <div class="form-group">
                <label for="nova_senha">Nova Senha</label>
                <input type="password" id="nova_senha" name="nova_senha" required
                       autocomplete="new-password" minlength="12" class="form-control">
            </div>

            <div class="form-group">
                <label for="confirmar_senha">Confirmar Nova Senha</label>
                <input type="password" id="confirmar_senha" name="confirmar_senha" required
                       autocomplete="new-password" minlength="12" class="form-control">
            </div>

            <button type="submit" class="btn btn-primary">Alterar Senha</button>
        </form>
    </div>
</div>
