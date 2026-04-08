<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <h2>Log de Backup</h2>
    <a href="/backup/exportar" class="btn btn-outline btn-sm">Exportar CSV</a>
</div>

<!-- Filtros -->
<div class="table-container" style="margin-bottom:24px;">
    <div style="padding:16px 20px; border-bottom:1px solid var(--c-border);">
        <form method="GET" action="/backup" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label style="font-size:12px;">De</label>
                <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($data_inicio) ?>" onchange="this.form.submit()">
            </div>
            <div class="form-group" style="margin:0;">
                <label style="font-size:12px;">Ate</label>
                <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($data_fim) ?>" onchange="this.form.submit()">
            </div>
        </form>
    </div>

    <div class="table-header">
        <span class="table-title">Historico de Operacoes (<?= count($logs) ?>)</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Data/Hora</th>
                <th>Usuario</th>
                <th>Operacao</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
            <tr><td colspan="4" style="text-align:center; color:var(--c-gray); padding:32px;">Nenhum registro de backup encontrado.</td></tr>
            <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td style="white-space:nowrap; font-size:13px;"><?= date('d/m/Y H:i:s', strtotime($log['criado_em'])) ?></td>
                <td><?= htmlspecialchars($log['usuario_nome'] ?? 'Sistema') ?></td>
                <td style="font-size:13px;">
                    <?php
                    $desc = $log['descricao'] ?? '';
                    $badgeClass = 'ativo';
                    if (str_contains($desc, 'Falha') || str_contains($desc, 'falha')) {
                        $badgeClass = 'vencido';
                    } elseif (str_contains($desc, 'excluido') || str_contains($desc, 'Excluido')) {
                        $badgeClass = 'vencido';
                    } elseif (str_contains($desc, 'Download')) {
                        $badgeClass = 'proximo';
                    }
                    ?>
                    <span class="badge badge-<?= $badgeClass ?>" style="margin-right:6px;">
                        <?php if (str_contains($desc, 'manual') || str_contains($desc, 'realizado') || str_contains($desc, 'concluido')): ?>
                            Executado
                        <?php elseif (str_contains($desc, 'Falha') || str_contains($desc, 'falha')): ?>
                            Falha
                        <?php elseif (str_contains($desc, 'excluido') || str_contains($desc, 'Excluido')): ?>
                            Excluido
                        <?php elseif (str_contains($desc, 'Download')): ?>
                            Download
                        <?php elseif (str_contains($desc, 'configurado') || str_contains($desc, 'automatico')): ?>
                            Config
                        <?php else: ?>
                            Backup
                        <?php endif; ?>
                    </span>
                    <?= htmlspecialchars($desc) ?>
                </td>
                <td style="font-size:12px; color:var(--c-gray);"><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div style="margin-top:16px; padding:16px; background:#e3f2fd; border-left:5px solid #1976d2; border-radius:6px; font-size:13px;">
    <strong>Informacao:</strong> O backup do banco de dados e executado automaticamente pelo container Docker a cada 24 horas. Este log registra todas as operacoes de backup realizadas no sistema.
</div>
