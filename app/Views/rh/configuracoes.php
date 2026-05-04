<?php $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); ?>

<?php if ($flash): ?>
<div style="background:<?= $flash['type']==='success'?'#d1fae5':'#fee2e2' ?>; color:<?= $flash['type']==='success'?'#065f46':'#991b1b' ?>; padding:12px; border-radius:6px; margin-bottom:16px;">
    <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <p style="color:#6b7280; margin:0;">Janelas de alerta, SLA de reprotocolo e destinatários do e-mail digest.</p>
    <a href="/rh" class="btn btn-secondary btn-sm">← Voltar ao painel</a>
</div>

<form method="POST" action="/rh/configuracoes/salvar">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Session::get('csrf_token','')) ?>">

    <!-- Janelas de alerta -->
    <div class="table-container" style="margin-bottom:20px;">
        <div class="table-header">
            <span class="table-title">Janelas de alerta antes do vencimento</span>
        </div>
        <div style="padding:20px;">
            <p style="color:#6b7280; font-size:13px; margin:0 0 16px 0;">Selecione com quantos dias antes do vencimento de um documento o sistema deve gerar alertas.</p>
            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="janela_60" value="1" <?= $cfg['janela_60'] ? 'checked' : '' ?>>
                    <span><strong>60 dias</strong> antes</span>
                </label>
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="janela_30" value="1" <?= $cfg['janela_30'] ? 'checked' : '' ?>>
                    <span><strong>30 dias</strong> antes</span>
                </label>
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="janela_15" value="1" <?= $cfg['janela_15'] ? 'checked' : '' ?>>
                    <span><strong>15 dias</strong> antes</span>
                </label>
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="janela_7" value="1" <?= $cfg['janela_7'] ? 'checked' : '' ?>>
                    <span><strong>7 dias</strong> antes</span>
                </label>
            </div>
        </div>
    </div>

    <!-- SLA -->
    <div class="table-container" style="margin-bottom:20px;">
        <div class="table-header">
            <span class="table-title">SLA de reprotocolo</span>
        </div>
        <div style="padding:20px;">
            <label class="form-label">Prazo máximo para o RH protocolar no cliente após o documento ser renovado (dias úteis):</label>
            <input type="number" name="sla" min="1" max="30" value="<?= (int)$cfg['sla_reprotocolo_dias_uteis'] ?>" class="form-control" style="max-width:120px;">
            <p style="color:#6b7280; font-size:12px; margin-top:8px;">Pendências em aberto após esse prazo aparecem como "atrasadas" no dashboard e no e-mail digest.</p>
        </div>
    </div>

    <!-- E-mail digest -->
    <div class="table-container" style="margin-bottom:20px;">
        <div class="table-header">
            <span class="table-title">E-mail digest diário</span>
        </div>
        <div style="padding:20px;">
            <label class="form-label">Destinatários (e-mails separados por vírgula)</label>
            <input type="text" name="email_digest_destinatarios" class="form-control"
                   value="<?= htmlspecialchars($cfg['email_digest_destinatarios'] ?? '') ?>"
                   placeholder="ana@tsea.com.br, marcelo@tsea.com.br">
            <p style="color:#6b7280; font-size:12px; margin-top:6px;">Deixe em branco para enviar para todos os usuários do perfil RH.</p>

            <label class="form-label" style="margin-top:16px;">Horário de envio</label>
            <input type="time" name="email_digest_horario" value="<?= htmlspecialchars(substr($cfg['email_digest_horario'] ?? '07:00:00',0,5)) ?>" class="form-control" style="max-width:160px;">
        </div>
    </div>

    <div style="display:flex; justify-content:flex-end;">
        <button type="submit" class="btn btn-primary">Salvar configurações</button>
    </div>
</form>
