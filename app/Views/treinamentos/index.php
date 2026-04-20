<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <h2>Treinamentos</h2>
    <div style="display:flex; gap:8px;">
        <a href="/treinamentos/calendario" class="btn btn-outline btn-sm">Calendario</a>
        <a href="/treinamentos/novo" class="btn btn-primary btn-sm">Novo Treinamento</a>
    </div>
</div>

<!-- Cards resumo -->
<div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px; margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-value"><?= $contadoresMes['total_treinamentos'] ?? 0 ?></div>
        <div class="stat-label">Treinamentos este mes</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $contadoresMes['total_participantes'] ?? 0 ?></div>
        <div class="stat-label">Participantes este mes</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $totalGeral ?? 0 ?></div>
        <div class="stat-label">Total geral</div>
    </div>
</div>

<!-- Filtros -->
<div class="table-container" style="margin-bottom:16px;">
    <form method="GET" action="/treinamentos" style="padding:12px 16px; display:flex; gap:12px; align-items:end; flex-wrap:wrap;">
        <div class="form-group" style="margin:0; flex:1; min-width:160px;">
            <label style="font-size:12px;">Tipo</label>
            <select name="tipo" class="form-control" style="font-size:13px;">
                <option value="">Todos</option>
                <?php foreach ($tipos as $t): ?>
                <option value="<?= $t['id'] ?>" <?= ($filters['tipo_certificado_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['codigo']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0; min-width:140px;">
            <label style="font-size:12px;">De</label>
            <input type="date" name="data_de" class="form-control" style="font-size:13px;" value="<?= htmlspecialchars($filters['data_de'] ?? '') ?>">
        </div>
        <div class="form-group" style="margin:0; min-width:140px;">
            <label style="font-size:12px;">Ate</label>
            <input type="date" name="data_ate" class="form-control" style="font-size:13px;" value="<?= htmlspecialchars($filters['data_ate'] ?? '') ?>">
        </div>
        <div class="form-group" style="margin:0; flex:1; min-width:160px;">
            <label style="font-size:12px;">Busca</label>
            <input type="text" name="q" class="form-control" style="font-size:13px;" placeholder="Buscar..." value="<?= htmlspecialchars($filters['q'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        <?php if (!empty($filters)): ?>
        <a href="/treinamentos" class="btn btn-outline btn-sm">Limpar</a>
        <?php endif; ?>
    </form>
</div>

<!-- Tabela -->
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Treinamento</th>
                <th>Ministrante</th>
                <th style="text-align:center;">Participantes</th>
                <th style="text-align:center;">Status</th>
                <th>Criado por</th>
                <th style="text-align:center;">Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($treinamentos)): ?>
            <tr><td colspan="7" style="text-align:center; color:#6b7280; padding:40px; font-size:14px;">Nenhum treinamento registrado.</td></tr>
            <?php else: ?>
            <?php foreach ($treinamentos as $t): ?>
            <?php
                $statusLabel = ['em_andamento' => 'Em Andamento', 'aguardando_assinaturas' => 'Aguard. Assinaturas', 'finalizada' => 'Finalizada'];
                $statusClass = ['em_andamento' => 'badge-proximo_vencimento', 'aguardando_assinaturas' => 'badge-vigente', 'finalizada' => 'badge-vigente'];
                $st = $t['status'] ?? 'em_andamento';
            ?>
            <tr>
                <td style="font-size:13px; white-space:nowrap;">
                    <?= date('d/m/Y', strtotime($t['data_realizacao'])) ?>
                    <?php if ($t['data_realizacao_fim'] && $t['data_realizacao_fim'] !== $t['data_realizacao']): ?>
                    <br><small style="color:#6b7280;">a <?= date('d/m/Y', strtotime($t['data_realizacao_fim'])) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <strong style="font-size:13px;"><?= htmlspecialchars($t['tipo_codigo']) ?></strong>
                    <br><small style="color:#6b7280;"><?= htmlspecialchars($t['duracao']) ?></small>
                </td>
                <td style="font-size:13px;"><?= htmlspecialchars($t['ministrante_nome'] ?? '-') ?></td>
                <td style="text-align:center;">
                    <span class="badge badge-vigente"><?= $t['total_participantes'] ?></span>
                </td>
                <td style="text-align:center;">
                    <span class="badge <?= $statusClass[$st] ?>" style="font-size:11px;"><?= $statusLabel[$st] ?></span>
                </td>
                <td style="font-size:13px;"><?= htmlspecialchars($t['criador_nome'] ?? '-') ?></td>
                <td style="text-align:center; white-space:nowrap;">
                    <a href="/treinamentos/<?= $t['id'] ?>" class="btn btn-outline btn-sm">Ver</a>
                    <a href="/treinamentos/<?= $t['id'] ?>/editar" class="btn btn-outline btn-sm">Editar</a>
                    <a href="/treinamentos/<?= $t['id'] ?>/certificados" class="btn btn-outline btn-sm" target="_blank">Certs</a>
                    <form method="POST" action="/treinamentos/<?= $t['id'] ?>/excluir" style="display:inline;"
                          onsubmit="return confirm('Excluir este treinamento e todos os certificados vinculados?')">
                        <?= \App\Core\View::csrfField() ?>
                        <button type="submit" class="btn btn-sm" style="background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;">Excluir</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Paginacao -->
<?php if ($totalPages > 1): ?>
<div style="display:flex; justify-content:center; gap:4px; margin-top:16px;">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <?php
        $queryParams = $filters;
        $queryParams['page'] = $i;
        $qs = http_build_query($queryParams);
    ?>
    <a href="/treinamentos?<?= $qs ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
