<div class="login-container">
    <div class="login-logo">
        <img src="/assets/img/logo-tse.png" alt="TSE Engenharia">
        <div class="login-title">SISTEMA SESMT</div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error">
            <?php
            $msgs = [
                'invalid'  => 'Email ou senha incorretos.',
                'empty'    => 'Preencha todos os campos.',
                'inactive' => 'Sua conta esta desativada. Contacte o administrador.',
                'locked'   => 'Conta bloqueada. Tente novamente em ' . ($_GET['min'] ?? '15') . ' minutos.',
                'csrf'     => 'Token de seguranca inválido. Tente novamente.',
            ];
            echo htmlspecialchars($msgs[$error] ?? 'Erro ao fazer login.');
            ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($expired)): ?>
        <div class="alert alert-warning">
            Sua sessão expirou por inatividade. Faca login novamente.
        </div>
    <?php endif; ?>

    <form method="POST" action="/login">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autocomplete="email" autofocus
                   placeholder="seu.email@tsea.com.br">
        </div>

        <div class="form-group">
            <label for="senha">Senha</label>
            <div style="position:relative;">
                <input type="password" id="senha" name="senha" required autocomplete="current-password"
                       placeholder="Sua senha" style="padding-right:40px;">
                <button type="button" onclick="toggleSenha('senha', this)" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; padding:4px; color:var(--c-gray, #999);" title="Mostrar senha">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-login">Entrar</button>
    </form>
</div>

<script>
function toggleSenha(inputId, btn) {
    var input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
        btn.title = 'Ocultar senha';
    } else {
        input.type = 'password';
        btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
        btn.title = 'Mostrar senha';
    }
}</script>
