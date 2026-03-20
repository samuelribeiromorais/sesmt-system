<div class="page-header">
    <h1>Preview da Importacao</h1>
    <a href="/importar/colaboradores" class="btn btn-outline btn-sm">Voltar</a>
</div>

<div style="margin-bottom:16px;display:flex;gap:16px;">
    <div style="padding:12px 20px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;">
        <strong style="color:#065f46;"><?= $totalRows ?></strong>
        <span style="color:#047857;"> linhas encontradas</span>
    </div>
    <?php if ($errorCount > 0): ?>
    <div style="padding:12px 20px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;">
        <strong style="color:#991b1b;"><?= $errorCount ?></strong>
        <span style="color:#dc2626;"> linhas com erros (serao ignoradas)</span>
    </div>
    <?php endif; ?>
</div>

<div class="table-container">
    <div class="table-header">
        <span class="table-title">Primeiras <?= min(20, count($rows)) ?> linhas<?= $totalRows > 20 ? " (de {$totalRows} total)" : '' ?></span>
    </div>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Status</th>
                    <?php foreach ($headers as $h): ?>
                        <th><?= htmlspecialchars($h) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr style="<?= !empty($row['_errors']) ? 'background:#fef2f2;' : '' ?>">
                    <td><?= $row['_row_num'] ?></td>
                    <td>
                        <?php if (empty($row['_errors'])): ?>
                            <span class="badge badge-ativo">OK</span>
                        <?php else: ?>
                            <span class="badge badge-vencido" title="<?= htmlspecialchars(implode(', ', $row['_errors'])) ?>">
                                ERRO
                            </span>
                        <?php endif; ?>
                    </td>
                    <?php foreach ($headers as $h): ?>
                        <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?= htmlspecialchars($row[$h] ?? '') ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div style="margin-top:24px;display:flex;gap:12px;">
    <form method="POST" action="/importar/colaboradores/executar">
        <?= \App\Core\View::csrfField() ?>
        <button type="submit" class="btn btn-primary"
                data-confirm="Confirma a importacao de <?= $totalRows ?> registro(s)? Linhas com erros serao ignoradas.">
            Confirmar Importacao
        </button>
    </form>
    <a href="/importar/colaboradores" class="btn btn-outline">Cancelar e Enviar Outro Arquivo</a>
</div>
