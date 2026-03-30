<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <h2>Backup do Sistema</h2>
    <form method="POST" action="/backup/executar" style="display:inline;">
        <?= \App\Core\View::csrfField() ?>
        <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Executar backup agora?')">Executar Backup Agora</button>
    </form>
</div>

<!-- Configuração de backup automático -->
<div class="table-container" style="margin-bottom:24px; padding:20px;">
    <h3 style="margin:0 0 12px; color:var(--c-primary);">Backup Automático Diario</h3>
    <form method="POST" action="/backup/configurar-cron" style="display:flex; align-items:center; gap:16px;">
        <?= \App\Core\View::csrfField() ?>
        <div>
            <label class="form-label" style="margin:0;">Horario do backup:</label>
        </div>
        <input type="time" name="horario" value="02:00" class="form-input" style="width:120px;">
        <button type="submit" class="btn btn-primary btn-sm">Configurar</button>
        <span style="font-size:13px; color:var(--c-gray);">
            Status: <?= $cronAtivo ? '<span style="color:#4caf50; font-weight:600;">Ativo</span>' : '<span style="color:#999;">Não configurado</span>' ?>
        </span>
    </form>
    <p style="font-size:12px; color:var(--c-gray); margin:8px 0 0;">O backup automático gera uma copia do banco de dados todos os dias no horario definido. Backups com mais de 30 dias sao removidos automaticamente.</p>
</div>

<!-- Lista de backups -->
<div class="table-container">
    <div class="table-header">
        <span class="table-title">Backups Disponiveis (<?= count($backups) ?>)</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Arquivo</th>
                <th>Tamanho</th>
                <th>Data</th>
                <th width="160">Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($backups)): ?>
            <tr><td colspan="4" style="text-align:center; color:var(--c-gray); padding:32px;">Nenhum backup encontrado. Clique em "Executar Backup Agora" para criar o primeiro.</td></tr>
            <?php else: ?>
            <?php foreach ($backups as $b): ?>
            <tr>
                <td style="font-weight:600; font-size:13px;"><?= htmlspecialchars($b['nome']) ?></td>
                <td style="font-size:13px;"><?= $b['tamanho'] ?></td>
                <td style="font-size:13px;"><?= $b['data'] ?></td>
                <td>
                    <a href="/backup/download/<?= urlencode($b['nome']) ?>" class="btn btn-outline btn-sm">Baixar</a>
                    <form method="POST" action="/backup/excluir/<?= urlencode($b['nome']) ?>" style="display:inline;" onsubmit="return confirm('Excluir este backup?')">
                        <?= \App\Core\View::csrfField() ?>
                        <button type="submit" class="btn btn-danger btn-sm">X</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div style="margin-top:16px; padding:16px; background:#e3f2fd; border-left:5px solid #1976d2; border-radius:6px; font-size:13px;">
    <strong>Informação:</strong> Os backups sao armazenados em <code>storage/backups/</code> dentro do container. Como o storage esta sincronizado com o OneDrive, os backups ficam salvos automaticamente na nuvem.
</div>
