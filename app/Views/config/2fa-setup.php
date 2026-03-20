<div class="card">
    <div class="card-header">
        <h2>Autenticacao em Duas Etapas (2FA)</h2>
    </div>
    <div class="card-body">

        <?php if (!empty($totp_ativo)): ?>
            <!-- 2FA já ativo -->
            <div class="alert alert-success" style="margin-bottom:20px;">
                A autenticacao em duas etapas esta <strong>ativada</strong> para sua conta.
            </div>

            <form method="POST" action="/usuarios/2fa/desativar">
                <?= \App\Core\View::csrfField() ?>
                <button type="submit" class="btn btn-danger"
                        onclick="return confirm('Tem certeza que deseja desativar o 2FA? Sua conta ficara menos segura.')">
                    Desativar 2FA
                </button>
            </form>

        <?php else: ?>
            <!-- Configurar 2FA -->
            <p style="margin-bottom:16px;">
                Para ativar a autenticacao em duas etapas, siga os passos abaixo:
            </p>

            <div style="margin-bottom:24px;">
                <h3 style="margin-bottom:8px;">1. Escaneie o QR Code</h3>
                <p style="font-size:13px; color:#666; margin-bottom:12px;">
                    Abra seu aplicativo autenticador (Google Authenticator, Authy, etc.) e escaneie o codigo abaixo
                    ou copie a chave manualmente.
                </p>

                <div style="background:#f8f8f8; border:1px solid #e0e0e0; border-radius:8px; padding:20px; text-align:center; margin-bottom:12px;">
                    <!-- QR Code via API do Google Charts -->
                    <img src="https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=<?= urlencode($qr_url) ?>"
                         alt="QR Code 2FA" style="margin-bottom:12px;">

                    <div style="margin-top:12px;">
                        <span style="font-size:12px; color:#999;">Chave manual:</span><br>
                        <code style="font-size:14px; background:#fff; padding:4px 12px; border:1px solid #ddd; border-radius:4px; letter-spacing:2px;">
                            <?= htmlspecialchars($secret) ?>
                        </code>
                    </div>
                </div>
            </div>

            <div>
                <h3 style="margin-bottom:8px;">2. Confirme o codigo</h3>
                <p style="font-size:13px; color:#666; margin-bottom:12px;">
                    Digite o codigo de 6 digitos exibido no aplicativo para confirmar a configuracao.
                </p>

                <form method="POST" action="/usuarios/2fa/ativar">
                    <?= \App\Core\View::csrfField() ?>
                    <input type="hidden" name="secret" value="<?= htmlspecialchars($secret) ?>">

                    <div class="form-group" style="max-width:260px;">
                        <input type="text" name="totp_code" required
                               autocomplete="one-time-code" inputmode="numeric"
                               pattern="[0-9]{6}" maxlength="6"
                               placeholder="000000"
                               style="text-align:center; font-size:20px; letter-spacing:6px; padding:12px;">
                    </div>

                    <button type="submit" class="btn btn-primary">Ativar 2FA</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
