<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <h2 style="margin:0;">NRs Obrigatórias por Função</h2>
    <a href="/configuracoes" class="btn btn-outline btn-sm">← Configurações</a>
</div>

<p style="color:var(--c-gray); font-size:13px; margin-bottom:20px;">
    Configure quais certificados (NRs) são obrigatórios para cada função cadastrada nos colaboradores.
    Ao associar uma NR a uma função, o sistema irá indicar na ficha do colaborador quando o certificado estiver faltante.
</p>

<!-- Adicionar mapeamento -->
<div class="table-container" style="margin-bottom:24px;">
    <div class="table-header">
        <span class="table-title">Adicionar NR obrigatória por Função</span>
    </div>
    <div style="padding:20px;">
        <form method="POST" action="/configuracoes/funcoes/adicionar" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <?= \App\Core\View::csrfField() ?>

            <div style="flex:1; min-width:220px;">
                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:4px;">Função</label>
                <input type="text" name="funcao" list="funcoes-list" class="form-control"
                       placeholder="Digite ou selecione a função..." required>
                <datalist id="funcoes-list">
                    <?php foreach ($funcoesColabs as $f): ?>
                    <option value="<?= htmlspecialchars($f) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div style="flex:1; min-width:220px;">
                <label style="font-size:12px; font-weight:600; display:block; margin-bottom:4px;">Certificado (NR)</label>
                <select name="tipo_certificado_id" class="form-control" required>
                    <option value="">-- Selecionar --</option>
                    <?php foreach ($tiposCert as $tc): ?>
                    <option value="<?= $tc['id'] ?>"><?= htmlspecialchars($tc['codigo']) ?> — <?= htmlspecialchars($tc['titulo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button type="submit" class="btn btn-primary">Adicionar</button>
            </div>
        </form>
    </div>
</div>

<!-- Listagem agrupada por função -->
<?php if (empty($porFuncao)): ?>
<div class="table-container">
    <div style="padding:32px; text-align:center; color:var(--c-gray);">
        Nenhum mapeamento cadastrado ainda. Use o formulário acima para começar.
    </div>
</div>
<?php else: ?>

<?php foreach ($porFuncao as $funcao => $nrs): ?>
<div class="table-container" style="margin-bottom:16px;">
    <div class="table-header">
        <span class="table-title"><?= htmlspecialchars($funcao) ?></span>
        <span style="font-size:12px; color:var(--c-gray);"><?= count($nrs) ?> NR(s) obrigatória(s)</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Título</th>
                <th style="width:80px; text-align:center;">Ação</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($nrs as $nr): ?>
        <tr>
            <td><span class="badge" style="background:#e0f7fa; color:#00838f;"><?= htmlspecialchars($nr['codigo']) ?></span></td>
            <td style="font-size:13px;"><?= htmlspecialchars($nr['titulo']) ?></td>
            <td style="text-align:center;">
                <form method="POST" action="/configuracoes/funcoes/<?= $nr['id'] ?>/remover">
                    <?= \App\Core\View::csrfField() ?>
                    <button type="submit" class="btn btn-danger btn-sm"
                            onclick="return confirm('Remover esta NR da função?')">Remover</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>

<?php endif; ?>
