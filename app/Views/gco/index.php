<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <div>
        <h2 style="margin:0;">Integração GCO</h2>
        <p style="color:var(--c-gray); font-size:14px; margin:4px 0 0;">
            Sincroniza colaboradores do sistema GCO com o SESMT automaticamente.
        </p>
    </div>
</div>

<!-- Status da configuração -->
<?php if (!$gcoConfigurado): ?>
<div style="background:#fff3cd; border:1px solid #ffc107; border-radius:8px; padding:16px; margin-bottom:24px; display:flex; gap:12px; align-items:flex-start;">
    <span style="font-size:20px;">⚠️</span>
    <div>
        <strong>Token GCO não configurado.</strong><br>
        <span style="font-size:14px; color:#555;">
            Adicione <code>GCO_TOKEN=seu_token_aqui</code> no arquivo <code>.env</code> para habilitar a sincronização.
        </span>
    </div>
</div>
<?php endif; ?>

<!-- Cards de totais -->
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px;">
    <div class="table-container" style="padding:20px; text-align:center;">
        <div style="font-size:2rem; font-weight:700; color:var(--c-primary);"><?= number_format($totais['total']) ?></div>
        <div style="font-size:13px; color:var(--c-gray);">Total Colaboradores</div>
    </div>
    <div class="table-container" style="padding:20px; text-align:center;">
        <div style="font-size:2rem; font-weight:700; color:#16a34a;"><?= number_format($totais['ativos']) ?></div>
        <div style="font-size:13px; color:var(--c-gray);">Ativos</div>
    </div>
    <div class="table-container" style="padding:20px; text-align:center;">
        <div style="font-size:2rem; font-weight:700; color:#dc2626;"><?= number_format($totais['inativos']) ?></div>
        <div style="font-size:13px; color:var(--c-gray);">Inativos</div>
    </div>
    <div class="table-container" style="padding:20px; text-align:center;">
        <div style="font-size:2rem; font-weight:700; color:#2563eb;"><?= number_format($totais['com_gco']) ?></div>
        <div style="font-size:13px; color:var(--c-gray);">Vinculados ao GCO</div>
    </div>
</div>

<!-- Painel de sincronização -->
<div class="table-container" style="margin-bottom:24px;">
    <div class="table-header"><span class="table-title">Sincronizar Agora</span></div>
    <div style="padding:24px;">
        <p style="color:var(--c-gray); font-size:14px; margin-bottom:16px;">
            A sincronização vai buscar todos os colaboradores ativos do GCO e:
        </p>
        <ul style="color:var(--c-gray); font-size:14px; margin-bottom:20px; padding-left:20px; line-height:2;">
            <li><strong>Criar</strong> colaboradores que ainda não existem no SESMT</li>
            <li><strong>Atualizar</strong> dados (cargo, setor, telefone, e-mail, etc.) dos já cadastrados</li>
            <li><strong>Desativar</strong> colaboradores que não aparecerem mais na resposta da API (desligados)</li>
        </ul>
        <?php if ($gcoConfigurado): ?>
        <form method="POST" action="/gco/sincronizar" onsubmit="return confirmarSync(this)">
            <?= \App\Core\View::csrfField() ?>
            <button type="submit" class="btn btn-primary" style="min-width:200px;">
                Iniciar Sincronização
            </button>
        </form>
        <?php else: ?>
        <button class="btn btn-primary" disabled style="min-width:200px; opacity:0.5; cursor:not-allowed;">
            Iniciar Sincronização
        </button>
        <p style="color:#b45309; font-size:13px; margin-top:8px;">Configure o GCO_TOKEN no .env para habilitar.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Histórico de sincronizações -->
<div class="table-container">
    <div class="table-header"><span class="table-title">Histórico de Sincronizações</span></div>
    <?php if (empty($logs)): ?>
    <div style="padding:32px; text-align:center; color:var(--c-gray);">
        Nenhuma sincronização realizada ainda.
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Duração</th>
                    <th>Total API</th>
                    <th>Criados</th>
                    <th>Atualizados</th>
                    <th>Desativados</th>
                    <th>Erros</th>
                    <th>Status</th>
                    <th>Executado por</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
            <?php
                $duracao = '—';
                if ($log['concluido_em'] && $log['iniciado_em']) {
                    $seg = strtotime($log['concluido_em']) - strtotime($log['iniciado_em']);
                    $duracao = $seg < 60 ? "{$seg}s" : round($seg/60, 1) . 'min';
                }
                $statusCor = match($log['status']) {
                    'concluido'    => '#16a34a',
                    'erro'         => '#dc2626',
                    'em_andamento' => '#b45309',
                    default        => '#666',
                };
                $statusLabel = match($log['status']) {
                    'concluido'    => 'Concluído',
                    'erro'         => 'Erro',
                    'em_andamento' => 'Em andamento',
                    default        => $log['status'],
                };
            ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($log['iniciado_em'])) ?></td>
                <td><?= $duracao ?></td>
                <td><?= $log['total_api'] ?? '—' ?></td>
                <td style="color:#16a34a; font-weight:600;"><?= $log['criados'] ?></td>
                <td style="color:#2563eb; font-weight:600;"><?= $log['atualizados'] ?></td>
                <td style="color:#b45309; font-weight:600;"><?= $log['desativados'] ?></td>
                <td style="color:<?= $log['erros'] > 0 ? '#dc2626' : '#666' ?>; font-weight:<?= $log['erros'] > 0 ? '600' : '400' ?>;">
                    <?= $log['erros'] ?>
                </td>
                <td>
                    <span style="color:<?= $statusCor ?>; font-weight:600; font-size:13px;">
                        <?= $statusLabel ?>
                    </span>
                    <?php if ($log['mensagem']): ?>
                    <br><span style="font-size:11px; color:#999;" title="<?= htmlspecialchars($log['mensagem']) ?>">
                        <?= htmlspecialchars(mb_substr($log['mensagem'], 0, 60)) ?>...
                    </span>
                    <?php endif; ?>
                </td>
                <td style="font-size:13px; color:var(--c-gray);">
                    <?= htmlspecialchars($log['usuario_nome'] ?? 'Sistema') ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
function confirmarSync(form) {
    return confirm(
        'Confirma a sincronização com o GCO?\n\n' +
        'Esta ação irá criar, atualizar e possivelmente desativar colaboradores com base na API do GCO.'
    );
}
</script>
