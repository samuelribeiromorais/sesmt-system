<div class="page-header">
    <h1>eSocial SST - Eventos</h1>
</div>

<!-- Filtros -->
<div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
    <form method="GET" action="/esocial" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <select name="tipo_evento" class="form-control" style="width:auto;">
            <option value="">Todos os Tipos</option>
            <option value="S-2210" <?= $tipoEvento === 'S-2210' ? 'selected' : '' ?>>S-2210 (CAT)</option>
            <option value="S-2220" <?= $tipoEvento === 'S-2220' ? 'selected' : '' ?>>S-2220 (ASO)</option>
            <option value="S-2240" <?= $tipoEvento === 'S-2240' ? 'selected' : '' ?>>S-2240 (Exposicao)</option>
        </select>
        <select name="status" class="form-control" style="width:auto;">
            <option value="">Todos os Status</option>
            <option value="pendente" <?= $status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
            <option value="enviado" <?= $status === 'enviado' ? 'selected' : '' ?>>Enviado</option>
            <option value="aceito" <?= $status === 'aceito' ? 'selected' : '' ?>>Aceito</option>
            <option value="rejeitado" <?= $status === 'rejeitado' ? 'selected' : '' ?>>Rejeitado</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        <?php if ($tipoEvento || $status): ?>
            <a href="/esocial" class="btn btn-outline btn-sm">Limpar</a>
        <?php endif; ?>
    </form>
</div>

<div class="table-container">
    <div class="table-header">
        <span class="table-title"><?= $total ?> evento(s) encontrado(s)</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Tipo</th>
                <th>Colaborador</th>
                <th>Status</th>
                <th>Protocolo</th>
                <th>Criado em</th>
                <th>Acoes</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($eventos)): ?>
            <tr>
                <td colspan="7" style="text-align:center;padding:24px;color:#6b7280;">
                    Nenhum evento encontrado.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($eventos as $e): ?>
            <tr>
                <td><?= $e['id'] ?></td>
                <td>
                    <?php
                    $tipoLabels = [
                        'S-2210' => ['CAT - Acidente de Trabalho', '#dc2626'],
                        'S-2220' => ['ASO - Monitoramento', '#2563eb'],
                        'S-2240' => ['Exposicao a Agentes', '#d97706'],
                    ];
                    $label = $tipoLabels[$e['tipo_evento']] ?? [$e['tipo_evento'], '#6b7280'];
                    ?>
                    <span style="font-weight:600;color:<?= $label[1] ?>;">
                        <?= $e['tipo_evento'] ?>
                    </span>
                    <br><small style="color:#6b7280;"><?= $label[0] ?></small>
                </td>
                <td><?= htmlspecialchars($e['colaborador_nome']) ?></td>
                <td>
                    <?php
                    $statusClasses = [
                        'pendente'  => 'badge-pendente',
                        'enviado'   => 'badge-ativo',
                        'aceito'    => 'badge-ativo',
                        'rejeitado' => 'badge-vencido',
                    ];
                    $cls = $statusClasses[$e['status']] ?? 'badge-pendente';
                    ?>
                    <span class="badge <?= $cls ?>"><?= strtoupper($e['status']) ?></span>
                </td>
                <td>
                    <?= $e['protocolo'] ? htmlspecialchars($e['protocolo']) : '<span style="color:#9ca3af;">-</span>' ?>
                </td>
                <td><?= date('d/m/Y H:i', strtotime($e['criado_em'])) ?></td>
                <td style="display:flex;gap:4px;">
                    <a href="/esocial/<?= $e['id'] ?>" class="btn btn-outline btn-sm">Ver</a>
                    <a href="/esocial/<?= $e['id'] ?>/xml" class="btn btn-outline btn-sm">XML</a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Paginacao -->
<?php if ($totalPages > 1): ?>
<div style="display:flex;justify-content:center;gap:4px;margin-top:16px;">
    <?php
    $queryParams = [];
    if ($tipoEvento) $queryParams['tipo_evento'] = $tipoEvento;
    if ($status) $queryParams['status'] = $status;
    ?>
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <?php $queryParams['page'] = $p; ?>
        <a href="/esocial?<?= http_build_query($queryParams) ?>"
           class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>">
            <?= $p ?>
        </a>
    <?php endfor; ?>
</div>
<?php endif; ?>
