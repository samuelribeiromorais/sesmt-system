<div class="card">
    <div class="card-header">
        <h2>Sessões Ativas</h2>
    </div>
    <div class="card-body">
        <p style="margin-bottom:16px; font-size:13px; color:#666;">
            Abaixo estao listadas todas as sessões ativas da sua conta. Voce pode encerrar sessões que não reconhece.
        </p>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Dispositivo / Navegador</th>
                        <th>Endereco IP</th>
                        <th>Ultimo Acesso</th>
                        <th>Inicio</th>
                        <th>Acao</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sessoes)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; color:#999;">Nenhuma sessão ativa encontrada.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sessoes as $sessao): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars(mb_substr($sessao['user_agent'], 0, 80)) ?>
                                    <?php if ($sessao['session_id'] === session_id()): ?>
                                        <span style="background:#00b279; color:#fff; font-size:11px; padding:2px 6px; border-radius:4px; margin-left:4px;">
                                            Sessão atual
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($sessao['ip_address']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($sessao['ultimo_acesso'])) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($sessao['criado_em'])) ?></td>
                                <td>
                                    <?php if ($sessao['session_id'] !== session_id()): ?>
                                        <form method="POST" action="/usuarios/sessoes/<?= $sessao['id'] ?>/encerrar" style="display:inline;">
                                            <?= \App\Core\View::csrfField() ?>
                                            <button type="submit" class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Encerrar esta sessão?')">
                                                Encerrar
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:#999; font-size:12px;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
