<div class="login-container">
    <div class="login-logo">
        <img src="/assets/img/logo-tse.png" alt="TSE Engenharia">
        <div class="login-title">VERIFICACAO EM DUAS ETAPAS</div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error">
            <?php
            $msgs = [
                'invalid' => 'Codigo invalido. Tente novamente.',
                'empty'   => 'Informe o codigo de 6 digitos.',
                'csrf'    => 'Token de seguranca invalido. Tente novamente.',
            ];
            echo htmlspecialchars($msgs[$error] ?? 'Erro na verificacao.');
            ?>
        </div>
    <?php endif; ?>

    <p style="text-align:center; font-size:13px; color:#666; margin-bottom:20px;">
        Abra seu aplicativo autenticador e insira o codigo de 6 digitos.
    </p>

    <form method="POST" action="/login/2fa">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="form-group">
            <label for="totp_code">Codigo de Verificacao</label>
            <input type="text" id="totp_code" name="totp_code" required
                   autocomplete="one-time-code" inputmode="numeric"
                   pattern="[0-9]{6}" maxlength="6"
                   placeholder="000000"
                   style="text-align:center; font-size:24px; letter-spacing:8px;">
        </div>

        <button type="submit" class="btn-login">Verificar</button>
    </form>

    <div style="text-align:center; margin-top:16px;">
        <a href="/login" style="color:#005e4e; font-size:13px; text-decoration:none;">Voltar ao login</a>
    </div>
</div>
