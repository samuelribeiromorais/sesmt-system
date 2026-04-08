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
                <div style="position:relative;">
                    <input type="password" id="senha_atual" name="senha_atual" required
                           autocomplete="current-password" class="form-control" style="padding-right:40px;">
                    <button type="button" onclick="toggleSenha('senha_atual', this)" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; padding:4px; color:var(--c-gray);" title="Mostrar senha">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="nova_senha">Nova Senha</label>
                <div style="position:relative;">
                    <input type="password" id="nova_senha" name="nova_senha" required
                           autocomplete="new-password" minlength="12" class="form-control" style="padding-right:40px;">
                    <button type="button" onclick="toggleSenha('nova_senha', this)" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; padding:4px; color:var(--c-gray);" title="Mostrar senha">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="confirmar_senha">Confirmar Nova Senha</label>
                <div style="position:relative;">
                    <input type="password" id="confirmar_senha" name="confirmar_senha" required
                           autocomplete="new-password" minlength="12" class="form-control" style="padding-right:40px;">
                    <button type="button" onclick="toggleSenha('confirmar_senha', this)" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; padding:4px; color:var(--c-gray);" title="Mostrar senha">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Alterar Senha</button>
        </form>
    </div>
</div>

<script>
function toggleSenha(inputId, btn) {
    var input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
        btn.title = 'Ocultar senha';
    } else {
        input.type = 'password';
        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
        btn.title = 'Mostrar senha';
    }
}
</script>
