<div style="margin-bottom:24px;">
    <h2 style="margin:0 0 4px;">Painel RH</h2>
    <p style="color:var(--c-gray); font-size:13px; margin:0;">Acompanhe os documentos que você enviou e o status de aprovação pelo SESMT.</p>
</div>

<!-- Contadores -->
<div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:28px;">
    <div class="stat-card" style="border-left:4px solid #f39c12;">
        <div class="stat-value" style="color:#d97706;"><?= $pendentes ?></div>
        <div class="stat-label">Aguardando Aprovação</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #059669;">
        <div class="stat-value" style="color:#059669;"><?= $aprovados ?></div>
        <div class="stat-label">Aprovados</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #dc2626;">
        <div class="stat-value" style="color:#dc2626;"><?= $rejeitados ?></div>
        <div class="stat-label">Rejeitados</div>
    </div>
</div>

<!-- Documentos enviados por mim -->
<div class="table-container" style="margin-bottom:24px;">
    <div class="table-header">
        <span class="table-title">Documentos Enviados por Mim</span>
        <a href="/colaboradores" class="btn btn-primary btn-sm">Enviar novo documento</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>Colaborador</th>
                <th>Documento</th>
                <th>Emissão</th>
                <th>Enviado em</th>
                <th>Status</th>
                <th>Obs. SESMT</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($meusEnvios)): ?>
            <tr><td colspan="6" style="text-align:center; color:var(--c-gray); padding:32px;">
                Você ainda não enviou nenhum documento. Acesse a ficha de um colaborador para enviar.
            </td></tr>
        <?php else: ?>
            <?php foreach ($meusEnvios as $e): ?>
            <?php
                $statusLabel = match($e['aprovacao_status']) {
                    'aprovado'  => ['Aprovado', 'badge-vigente'],
                    'rejeitado' => ['Rejeitado', 'badge-vencido'],
                    default     => ['Pendente', 'badge-proximo_vencimento'],
                };
            ?>
            <tr>
                <td>
                    <a href="/colaboradores/<?= $e['colaborador_id'] ?>" style="color:var(--c-primary); font-weight:600;">
                        <?= htmlspecialchars($e['nome_completo']) ?>
                    </a>
                </td>
                <td style="font-size:13px;"><?= htmlspecialchars($e['tipo_nome']) ?></td>
                <td style="font-size:13px;"><?= $e['data_emissao'] ? date('d/m/Y', strtotime($e['data_emissao'])) : '—' ?></td>
                <td style="font-size:13px;"><?= date('d/m/Y H:i', strtotime($e['criado_em'])) ?></td>
                <td><span class="badge <?= $statusLabel[1] ?>"><?= $statusLabel[0] ?></span></td>
                <td style="font-size:12px; color:var(--c-gray);"><?= htmlspecialchars($e['aprovacao_obs'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Colaboradores sem documento -->
<?php if (!empty($semDocs)): ?>
<div class="table-container">
    <div class="table-header">
        <span class="table-title" style="color:var(--c-danger);">Colaboradores Sem Nenhum Documento (<?= count($semDocs) ?>)</span>
    </div>
    <table>
        <thead>
            <tr><th>Nome</th><th>Função</th><th>Cargo</th><th>Ação</th></tr>
        </thead>
        <tbody>
        <?php foreach ($semDocs as $c): ?>
        <tr>
            <td><a href="/colaboradores/<?= $c['id'] ?>" style="color:var(--c-primary); font-weight:600;"><?= htmlspecialchars($c['nome_completo']) ?></a></td>
            <td style="font-size:13px;"><?= htmlspecialchars($c['funcao'] ?? '—') ?></td>
            <td style="font-size:13px;"><?= htmlspecialchars($c['cargo'] ?? '—') ?></td>
            <td>
                <a href="/documentos/upload/<?= $c['id'] ?>" class="btn btn-primary btn-sm">Enviar Docs</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
