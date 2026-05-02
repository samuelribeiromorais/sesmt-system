<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <h2 style="margin:0;">Kit PJ - Documentos para Colaboradores PJ</h2>
    <a href="/kit-pj/novo" class="btn btn-primary btn-sm">Novo Kit PJ</a>
</div>

<form method="GET" action="/kit-pj" style="margin-bottom:20px; display:flex; gap:8px; max-width:400px;">
    <input type="text" name="q" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Buscar por nome do colaborador..." class="form-control" style="flex:1;">
    <button type="submit" class="btn btn-primary btn-sm">Buscar</button>
    <?php if (!empty($search)): ?>
    <a href="/kit-pj" class="btn btn-outline btn-sm">Limpar</a>
    <?php endif; ?>
</form>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Colaborador</th>
                <th>Empresa PJ</th>
                <th>Tipo ASO</th>
                <th>Gerado em</th>
                <th>Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($kits)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--c-gray);padding:32px;">Nenhum kit PJ gerado ainda.</td></tr>
            <?php else: ?>
            <?php foreach ($kits as $k): ?>
            <tr>
                <td><a href="/colaboradores/<?= $k['colaborador_id'] ?>" style="color:var(--c-primary);font-weight:600;"><?= htmlspecialchars($k['nome_completo']) ?></a></td>
                <td style="font-size:13px;"><?= htmlspecialchars($k['razao_social']) ?><br><span style="color:#999;"><?= htmlspecialchars($k['cnpj']) ?></span></td>
                <td><span class="badge badge-vigente"><?= ucfirst($k['tipo_aso']) ?></span></td>
                <td style="font-size:13px;"><?= date('d/m/Y H:i', strtotime($k['criado_em'])) ?></td>
                <td style="white-space:nowrap;">
                    <a href="/kit-pj/<?= $k['id'] ?>/imprimir" class="btn btn-outline btn-sm" target="_blank">Imprimir</a>
                    <form method="POST" action="/kit-pj/<?= $k['id'] ?>/excluir" style="display:inline;"
                          onsubmit="return confirm('Excluir este Kit PJ de <?= htmlspecialchars($k['nome_completo'], ENT_QUOTES) ?>? Esta ação não pode ser desfeita.')">
                        <?= \App\Core\View::csrfField() ?>
                        <button type="submit" class="btn btn-danger btn-sm">Excluir</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
