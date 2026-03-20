<?php
$csrfToken = $_SESSION['csrf_token'] ?? '';
$totalVencidos = count($docs_vencidos);
$totalVencendo = count($docs_expiring);
$totalCertsVencendo = count($certs_expiring);
$pendentesEmail = (int)($alertaStats['pendentes'] ?? 0);
$enviadosEmail = (int)($alertaStats['enviados'] ?? 0);
$ultimoEnvio = $alertaStats['ultimo_envio'] ?? null;
$ultimaVerifData = $ultimaVerif['criado_em'] ?? null;
?>

<!-- ============ PAINEL DE ACOES ============ -->
<div class="table-container" style="margin-bottom: 24px;">
    <div class="table-header" style="flex-wrap: wrap; gap: 12px;">
        <span class="table-title">Central de Alertas por Email</span>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <form method="POST" action="/alertas/verificar" style="display:inline;">
                <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
                <button type="submit" class="btn btn-primary btn-sm" title="Atualizar status de validades e gerar alertas">
                    Verificar Validades
                </button>
            </form>
            <form method="POST" action="/alertas/enviar-emails" style="display:inline;">
                <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
                <button type="submit" class="btn btn-sm <?= $smtpOk ? 'btn-success' : 'btn-outline' ?>"
                    <?= !$smtpOk ? 'disabled title="SMTP nao configurado. Va em Configuracoes > SMTP."' : 'title="Enviar resumo diario por email"' ?>>
                    Enviar Emails (<?= $pendentesEmail ?> pendentes)
                </button>
            </form>
        </div>
    </div>
    <div style="padding: 16px; display: flex; gap: 24px; flex-wrap: wrap; font-size: 13px; color: #6b7280;">
        <div>
            <strong>Ultima verificacao:</strong>
            <?= $ultimaVerifData ? date('d/m/Y H:i', strtotime($ultimaVerifData)) : '<span style="color:#e74c3c;">Nunca executada</span>' ?>
        </div>
        <div>
            <strong>Ultimo email enviado:</strong>
            <?= $ultimoEnvio ? date('d/m/Y H:i', strtotime($ultimoEnvio)) : '<span style="color:#f39c12;">Nenhum</span>' ?>
        </div>
        <div>
            <strong>Emails enviados:</strong> <?= $enviadosEmail ?>
        </div>
        <div>
            <strong>Pendentes:</strong>
            <span style="color: <?= $pendentesEmail > 0 ? '#e74c3c' : '#00b279' ?>; font-weight: 600;">
                <?= $pendentesEmail ?>
            </span>
        </div>
        <?php if (!$smtpOk): ?>
        <div style="color: #e74c3c; font-weight: 600;">
            SMTP nao configurado — <a href="/configuracoes" style="color: #e74c3c; text-decoration: underline;">Configurar</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============ FILTROS ============ -->
<div class="filter-bar" style="display:flex; gap:1rem; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap;">
    <form method="GET" action="/alertas" style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
        <div>
            <label for="filter-cliente" style="font-size:0.85rem; font-weight:600; margin-right:0.25rem;">Cliente:</label>
            <select name="cliente" id="filter-cliente" onchange="this.form.submit()" style="padding:0.4rem 0.6rem; border:1px solid #d1d5db; border-radius:6px; font-size:0.9rem;">
                <option value="">Todos</option>
                <?php foreach ($clientes as $cl): ?>
                <option value="<?= $cl['id'] ?>" <?= ($clienteFilter == $cl['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cl['nome_fantasia'] ?? $cl['razao_social']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="filter-tipo" style="font-size:0.85rem; font-weight:600; margin-right:0.25rem;">Tipo:</label>
            <select name="tipo" id="filter-tipo" onchange="this.form.submit()" style="padding:0.4rem 0.6rem; border:1px solid #d1d5db; border-radius:6px; font-size:0.9rem;">
                <option value="">Todos</option>
                <option value="docs_vencidos" <?= ($tipoFilter === 'docs_vencidos') ? 'selected' : '' ?>>Docs Vencidos</option>
                <option value="docs_vencendo" <?= ($tipoFilter === 'docs_vencendo') ? 'selected' : '' ?>>Docs Vencendo</option>
                <option value="certs_vencendo" <?= ($tipoFilter === 'certs_vencendo') ? 'selected' : '' ?>>Certs Vencendo</option>
            </select>
        </div>
        <?php if ($clienteFilter !== '' || $tipoFilter !== ''): ?>
        <a href="/alertas" class="btn btn-outline btn-sm" style="font-size:0.85rem;">Limpar</a>
        <?php endif; ?>
    </form>
</div>

<!-- ============ STAT CARDS ============ -->
<div class="cards-row">
    <a href="#docs-vencidos" class="card-stat danger stat-card-clickable" style="text-decoration:none; color:inherit;" onclick="document.getElementById('docs-vencidos')?.scrollIntoView({behavior:'smooth'}); return false;">
        <div class="card-stat-value"><?= $totalVencidos ?></div>
        <div class="card-stat-label">Documentos Vencidos</div>
    </a>
    <a href="#docs-vencendo" class="card-stat warning stat-card-clickable" style="text-decoration:none; color:inherit;" onclick="document.getElementById('docs-vencendo')?.scrollIntoView({behavior:'smooth'}); return false;">
        <div class="card-stat-value"><?= $totalVencendo ?></div>
        <div class="card-stat-label">Documentos Vencendo (30 dias)</div>
    </a>
    <a href="#certs-vencendo" class="card-stat warning stat-card-clickable" style="text-decoration:none; color:inherit;" onclick="document.getElementById('certs-vencendo')?.scrollIntoView({behavior:'smooth'}); return false;">
        <div class="card-stat-value"><?= $totalCertsVencendo ?></div>
        <div class="card-stat-label">Certificados Vencendo (30 dias)</div>
    </a>
    <div class="card-stat info" style="border-left: 4px solid #005e4e;">
        <div class="card-stat-value"><?= $pendentesEmail ?></div>
        <div class="card-stat-label">Alertas Pendentes Email</div>
    </div>
</div>

<!-- ============ TABELAS DE ALERTAS ============ -->
<?php if (!empty($docs_vencidos)): ?>
<div id="docs-vencidos" class="table-container">
    <div class="table-header">
        <span class="table-title" style="color:var(--c-danger);">Documentos Vencidos (<?= $totalVencidos ?>)</span>
    </div>
    <table>
        <thead><tr><th>Colaborador</th><th>Cliente</th><th>Documento</th><th>Vencido ha</th></tr></thead>
        <tbody>
        <?php foreach ($docs_vencidos as $d): ?>
        <tr>
            <td><a href="/colaboradores/<?= $d['colaborador_id'] ?>"><?= htmlspecialchars($d['nome_completo']) ?></a></td>
            <td><?= htmlspecialchars($d['cliente_nome'] ?? '-') ?></td>
            <td><?= htmlspecialchars($d['tipo_nome']) ?></td>
            <td><span class="badge badge-vencido"><?= $d['dias_vencido'] ?> dias</span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($docs_expiring)): ?>
<div id="docs-vencendo" class="table-container">
    <div class="table-header">
        <span class="table-title" style="color:var(--c-warning);">Documentos Vencendo em 30 dias (<?= $totalVencendo ?>)</span>
    </div>
    <table>
        <thead><tr><th>Colaborador</th><th>Cliente</th><th>Documento</th><th>Vence em</th></tr></thead>
        <tbody>
        <?php foreach ($docs_expiring as $d): ?>
        <tr>
            <td><a href="/colaboradores/<?= $d['colaborador_id'] ?>"><?= htmlspecialchars($d['nome_completo']) ?></a></td>
            <td><?= htmlspecialchars($d['cliente_nome'] ?? '-') ?></td>
            <td><?= htmlspecialchars($d['tipo_nome']) ?></td>
            <td>
                <?php $dias = $d['dias_restantes']; ?>
                <span class="badge <?= $dias <= 7 ? 'badge-vencido' : 'badge-proximo' ?>"><?= $dias ?> dias</span>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($certs_expiring)): ?>
<div id="certs-vencendo" class="table-container">
    <div class="table-header">
        <span class="table-title" style="color:var(--c-warning);">Certificados Vencendo em 30 dias (<?= $totalCertsVencendo ?>)</span>
    </div>
    <table>
        <thead><tr><th>Colaborador</th><th>Cliente</th><th>Certificado</th><th>Vence em</th></tr></thead>
        <tbody>
        <?php foreach ($certs_expiring as $c): ?>
        <tr>
            <td><a href="/colaboradores/<?= $c['colaborador_id'] ?>"><?= htmlspecialchars($c['nome_completo']) ?></a></td>
            <td><?= htmlspecialchars($c['cliente_nome'] ?? '-') ?></td>
            <td><?= htmlspecialchars($c['codigo']) ?></td>
            <td>
                <?php $dias = $c['dias_restantes']; ?>
                <span class="badge <?= $dias <= 7 ? 'badge-vencido' : 'badge-proximo' ?>"><?= $dias ?> dias</span>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ============ HISTORICO DE ALERTAS ENVIADOS ============ -->
<div class="table-container" style="margin-top: 24px;">
    <div class="table-header" style="flex-wrap: wrap; gap: 8px;">
        <span class="table-title">Historico de Alertas (ultimos 50)</span>
        <?php if (!empty($historico) && $enviadosEmail > 50): ?>
        <form method="POST" action="/alertas/limpar-historico" style="display:inline;"
              onsubmit="return confirm('Remover alertas com mais de 90 dias do historico?')">
            <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
            <button type="submit" class="btn btn-outline btn-sm" style="font-size: 12px;">Limpar &gt; 90 dias</button>
        </form>
        <?php endif; ?>
    </div>
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Colaborador</th>
                <th>Tipo</th>
                <th>Documento/Certificado</th>
                <th>Dias</th>
                <th>Email</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($historico)): ?>
            <tr><td colspan="6" style="text-align:center; color:#6b7280; padding:24px;">
                Nenhum alerta gerado. Clique em "Verificar Validades" para iniciar.
            </td></tr>
            <?php else: ?>
            <?php foreach ($historico as $h): ?>
            <tr>
                <td style="white-space:nowrap; font-size:12px;"><?= date('d/m/Y H:i', strtotime($h['criado_em'])) ?></td>
                <td><a href="/colaboradores/<?= $h['colaborador_id'] ?>"><?= htmlspecialchars($h['nome_completo']) ?></a></td>
                <td>
                    <?php if ($h['tipo'] === 'vencido'): ?>
                        <span class="badge badge-vencido">Vencido</span>
                    <?php elseif ($h['tipo'] === 'vencimento_proximo'): ?>
                        <span class="badge badge-proximo">Vencendo</span>
                    <?php else: ?>
                        <span class="badge"><?= htmlspecialchars($h['tipo']) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($h['doc_tipo_nome'] ?? $h['cert_codigo'] ?? '-') ?></td>
                <td>
                    <?php $d = (int)$h['dias_restantes']; ?>
                    <?php if ($d < 0): ?>
                        <span style="color:#e74c3c; font-weight:600;"><?= abs($d) ?> dias vencido</span>
                    <?php elseif ($d <= 7): ?>
                        <span style="color:#e74c3c; font-weight:600;"><?= $d ?> dias</span>
                    <?php else: ?>
                        <span style="color:#f39c12;"><?= $d ?> dias</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($h['email_enviado']): ?>
                        <span style="color:#00b279; font-weight:600;" title="Enviado em <?= $h['email_enviado_em'] ? date('d/m/Y H:i', strtotime($h['email_enviado_em'])) : '' ?>">
                            Enviado
                        </span>
                    <?php else: ?>
                        <span style="color:#f39c12;">Pendente</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ============ INSTRUCOES CRON ============ -->
<div class="table-container" style="margin-top: 24px;">
    <div class="table-header">
        <span class="table-title">Automacao (Cron Jobs)</span>
    </div>
    <div style="padding: 20px; font-size: 13px; color: #374151;">
        <p style="margin: 0 0 12px; color: #6b7280;">
            Para automacao completa, adicione os seguintes cron jobs no servidor:
        </p>
        <div style="background: #f3f4f6; padding: 16px; border-radius: 8px; font-family: monospace; font-size: 12px; line-height: 2;">
            <div># Atualizar status de validades (diario 06:00)</div>
            <div style="color: #005e4e; font-weight: 600;">0 6 * * * php /var/www/html/cron/check_validades.php >> /var/www/html/storage/logs/cron.log 2>&1</div>
            <br>
            <div># Gerar alertas para vencimentos (diario 07:00)</div>
            <div style="color: #005e4e; font-weight: 600;">0 7 * * * php /var/www/html/cron/gerar_alertas.php >> /var/www/html/storage/logs/cron.log 2>&1</div>
            <br>
            <div># Enviar emails de alerta (diario 07:30)</div>
            <div style="color: #005e4e; font-weight: 600;">30 7 * * * php /var/www/html/cron/enviar_emails.php >> /var/www/html/storage/logs/cron.log 2>&1</div>
            <br>
            <div># Relatorio mensal (1o dia do mes 07:00)</div>
            <div style="color: #005e4e; font-weight: 600;">0 7 1 * * php /var/www/html/cron/relatorio_mensal.php >> /var/www/html/storage/logs/cron.log 2>&1</div>
        </div>
        <p style="margin: 16px 0 0; color: #6b7280;">
            Enquanto os cron jobs nao estiverem configurados, use os botoes acima para executar manualmente.
        </p>
    </div>
</div>
