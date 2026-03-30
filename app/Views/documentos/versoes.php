<div style="margin-bottom:16px;">
    <a href="/documentos/<?= $doc['id'] ?>" class="btn btn-outline btn-sm">&larr; Voltar ao Documento</a>
</div>

<div class="table-container">
    <div class="table-header">
        <span class="table-title">Histórico de Versoes - <?= htmlspecialchars($doc['arquivo_nome']) ?></span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Versao</th>
                <th>Data de Upload</th>
                <th>Enviado por</th>
                <th>Tamanho</th>
                <th>Status</th>
                <th>Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($versions)): ?>
            <tr><td colspan="6" style="text-align:center;color:#6b7280;">Nenhuma versao encontrada</td></tr>
            <?php else: ?>
            <?php foreach ($versions as $v): ?>
            <tr style="<?= $v['id'] === $doc['id'] ? 'background-color:#f0fdf4; font-weight:600;' : '' ?>">
                <td>
                    v<?= (int) $v['versao'] ?>
                    <?php if ($v['id'] === $doc['id']): ?>
                    <span class="badge badge-vigente" style="font-size:10px; margin-left:4px;">Atual</span>
                    <?php endif; ?>
                </td>
                <td><?= date('d/m/Y H:i', strtotime($v['criado_em'])) ?></td>
                <td><?= htmlspecialchars($v['enviado_por_nome'] ?? '-') ?></td>
                <td>
                    <?php
                    $size = (int) $v['arquivo_tamanho'];
                    if ($size >= 1048576) {
                        echo number_format($size / 1048576, 1) . ' MB';
                    } else {
                        echo number_format($size / 1024, 0) . ' KB';
                    }
                    ?>
                </td>
                <td><span class="badge badge-<?= $v['status'] ?>"><?= ucfirst(str_replace('_', ' ', $v['status'])) ?></span></td>
                <td style="white-space:nowrap;">
                    <a href="/documentos/download/<?= $v['id'] ?>" class="btn btn-outline btn-sm" title="Baixar">PDF</a>
                    <a href="/documentos/<?= $v['id'] ?>" class="btn btn-outline btn-sm" title="Detalhes">Ver</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
