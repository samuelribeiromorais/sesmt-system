<div class="page-header">
    <h1>Tokens de API</h1>
    <button class="btn btn-primary btn-sm" data-modal="modal-novo-token">+ Novo Token</button>
</div>

<div class="table-container">
    <div class="table-header">
        <span class="table-title">Seus Tokens de API</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>Token</th>
                <th>Status</th>
                <th>Ultimo Uso</th>
                <th>Criado em</th>
                <th>Acoes</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($tokens)): ?>
            <tr>
                <td colspan="6" style="text-align:center;padding:24px;color:#6b7280;">
                    Nenhum token de API criado. Crie um para acessar a API REST do sistema.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($tokens as $t): ?>
            <tr>
                <td><?= htmlspecialchars($t['nome']) ?></td>
                <td>
                    <code style="background:#f3f4f6;padding:2px 8px;border-radius:4px;font-size:12px;">
                        <?= substr($t['token'], 0, 8) ?>...<?= substr($t['token'], -4) ?>
                    </code>
                </td>
                <td>
                    <?php if ($t['ativo']): ?>
                        <span class="badge badge-ativo">ATIVO</span>
                    <?php else: ?>
                        <span class="badge badge-inativo">REVOGADO</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?= $t['ultimo_uso'] ? date('d/m/Y H:i', strtotime($t['ultimo_uso'])) : 'Nunca utilizado' ?>
                </td>
                <td><?= date('d/m/Y H:i', strtotime($t['criado_em'])) ?></td>
                <td>
                    <?php if ($t['ativo']): ?>
                    <form method="POST" action="/usuarios/api-tokens/<?= $t['id'] ?>/revogar" style="display:inline;">
                        <?= \App\Core\View::csrfField() ?>
                        <button type="submit" class="btn btn-danger btn-sm" data-confirm="Revogar token '<?= htmlspecialchars($t['nome']) ?>'? Esta acao nao pode ser desfeita.">
                            Revogar
                        </button>
                    </form>
                    <?php else: ?>
                        <span style="color:#9ca3af;">-</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div style="margin-top:24px;padding:16px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;">
    <h3 style="margin:0 0 8px;color:#0369a1;">Como usar a API</h3>
    <p style="margin:0 0 8px;color:#475569;">Inclua o token no header de cada requisicao:</p>
    <code style="display:block;background:#1e293b;color:#e2e8f0;padding:12px;border-radius:4px;font-size:13px;">
        Authorization: Bearer &lt;seu_token&gt;
    </code>
    <p style="margin:8px 0 0;color:#475569;font-size:13px;">
        Endpoints disponiveis: <code>/api/v1/colaboradores</code>, <code>/api/v1/documentos</code>,
        <code>/api/v1/certificados</code>, <code>/api/v1/clientes</code>, <code>/api/v1/obras</code>,
        <code>/api/v1/stats</code>
    </p>
</div>

<!-- Modal Novo Token -->
<div class="modal-overlay" id="modal-novo-token">
    <div class="modal">
        <div class="modal-header">
            <h2>Novo Token de API</h2>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST" action="/usuarios/api-tokens/criar">
            <?= \App\Core\View::csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>Nome do Token</label>
                    <input type="text" name="nome" class="form-control" required
                           placeholder="Ex: Integracao Power BI, App Mobile...">
                    <small style="color:#6b7280;">
                        Um nome descritivo para identificar onde este token sera utilizado.
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline modal-close">Cancelar</button>
                <button type="submit" class="btn btn-primary">Gerar Token</button>
            </div>
        </form>
    </div>
</div>
