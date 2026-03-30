<div class="table-container">
    <div class="table-header">
        <span class="table-title">Logs de Acesso</span>
        <a href="/logs/exportar" class="btn btn-outline btn-sm">Exportar CSV</a>
    </div>

    <div style="padding:16px 20px; border-bottom:1px solid var(--c-border);">
        <form method="GET" action="/logs" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label style="font-size:12px;">Usuario</label>
                <select name="usuario_id" class="form-control" style="width:auto;" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $usuario_id == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label style="font-size:12px;">Acao</label>
                <select name="acao" class="form-control" style="width:auto;" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <?php foreach (['login','logout','login_falha','criar','editar','excluir','upload','download','usuario'] as $a): ?>
                    <option value="<?= $a ?>" <?= $acao === $a ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
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

    <table>
        <thead>
            <tr><th>Data/Hora</th><th>Usuario</th><th>Acao</th><th>Descrição</th><th>IP</th></tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
            <tr><td colspan="5" style="text-align:center;color:#6b7280;">Nenhum log encontrado.</td></tr>
            <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td style="white-space:nowrap;font-size:13px;"><?= date('d/m/Y H:i', strtotime($log['criado_em'])) ?></td>
                <td><?= htmlspecialchars($log['usuario_nome'] ?? '-') ?></td>
                <td><span class="badge badge-<?= in_array($log['acao'], ['login_falha','excluir']) ? 'vencido' : 'ativo' ?>"><?= htmlspecialchars($log['acao']) ?></span></td>
                <td style="font-size:13px;"><?= htmlspecialchars($log['descricao'] ?? '') ?></td>
                <td style="font-size:12px;color:var(--c-gray);"><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
