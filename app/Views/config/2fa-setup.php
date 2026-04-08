<div class="card">
    <div class="card-header">
        <h2>Autenticacao em Duas Etapas (2FA)</h2>
    </div>
    <div class="card-body">

        <div style="background:#e3f2fd; border-left:5px solid #1976d2; border-radius:6px; padding:16px; margin-bottom:24px; font-size:13px;">
            <strong>Microsoft Authenticator</strong> — Baixe o aplicativo gratuito na
            <a href="https://play.google.com/store/apps/details?id=com.azure.authenticator" target="_blank" rel="noopener">Google Play</a> ou
            <a href="https://apps.apple.com/app/microsoft-authenticator/id983156458" target="_blank" rel="noopener">App Store</a>.
            Tambem e compativel com Google Authenticator e Authy.
        </div>

        <?php
        $user = \App\Core\Session::user();
        $obrigatorio = in_array($user['perfil'] ?? '', ['admin', 'sesmt']);
        ?>

        <?php if (!empty($totp_ativo)): ?>
            <!-- 2FA ja ativo -->
            <div class="alert alert-success" style="margin-bottom:20px;">
                A autenticacao em duas etapas esta <strong>ativada</strong> para sua conta.
            </div>

            <?php if (!$obrigatorio): ?>
            <form method="POST" action="/usuarios/2fa/desativar">
                <?= \App\Core\View::csrfField() ?>
                <button type="submit" class="btn btn-danger"
                        onclick="return confirm('Tem certeza que deseja desativar o 2FA? Sua conta ficara menos segura.')">
                    Desativar 2FA
                </button>
            </form>
            <?php else: ?>
            <p style="color:#b45309; font-size:13px;">
                O 2FA e obrigatorio para o perfil <strong><?= strtoupper($user['perfil']) ?></strong> e nao pode ser desativado.
            </p>
            <?php endif; ?>

        <?php else: ?>
            <!-- Configurar 2FA -->
            <?php if ($obrigatorio): ?>
            <div style="background:#fff3cd; border:1px solid #ffc107; border-radius:8px; padding:16px; margin-bottom:20px;">
                <strong>Obrigatorio:</strong> A autenticacao em duas etapas e obrigatoria para o perfil <strong><?= strtoupper($user['perfil']) ?></strong>.
                Configure agora para continuar usando o sistema.
            </div>
            <?php endif; ?>

            <p style="margin-bottom:16px;">
                Para ativar a autenticacao em duas etapas, siga os passos abaixo:
            </p>

            <div style="margin-bottom:24px;">
                <h3 style="margin-bottom:8px;">1. Escaneie o QR Code com o Microsoft Authenticator</h3>
                <p style="font-size:13px; color:#666; margin-bottom:12px;">
                    Abra o <strong>Microsoft Authenticator</strong>, toque em <strong>"+"</strong> &gt; <strong>"Outra conta"</strong> e escaneie o codigo abaixo.
                </p>

                <div style="background:var(--c-bg-card, #f8f8f8); border:1px solid var(--c-border); border-radius:8px; padding:20px; text-align:center; margin-bottom:12px;">
                    <!-- QR Code via QRServer API (gratuita, sem dependencia Google) -->
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($qr_url) ?>"
                         alt="QR Code 2FA" style="margin-bottom:12px; border-radius:4px;">

                    <div style="margin-top:12px;">
                        <span style="font-size:12px; color:var(--c-gray);">Chave manual (caso nao consiga escanear):</span><br>
                        <code style="font-size:14px; background:var(--c-bg, #fff); padding:4px 12px; border:1px solid var(--c-border); border-radius:4px; letter-spacing:2px; user-select:all;">
                            <?= htmlspecialchars($secret) ?>
                        </code>
                    </div>
                </div>
            </div>

            <div>
                <h3 style="margin-bottom:8px;">2. Confirme o codigo</h3>
                <p style="font-size:13px; color:#666; margin-bottom:12px;">
                    Digite o codigo de 6 digitos exibido no Microsoft Authenticator para confirmar a configuracao.
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
