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
            <input type="password" id="senha" name="senha" required autocomplete="current-password"
                   placeholder="Sua senha">
        </div>

        <button type="submit" class="btn-login">Entrar</button>
    </form>
</div>
