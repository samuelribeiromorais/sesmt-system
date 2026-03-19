<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <div>
        <h2 style="font-size:24px;"><?= htmlspecialchars($cliente['nome_fantasia'] ?? $cliente['razao_social']) ?></h2>
        <span style="color:var(--c-gray);"><?= htmlspecialchars($cliente['razao_social']) ?> | CNPJ: <?= htmlspecialchars($cliente['cnpj'] ?? '-') ?></span>
        <span style="color:var(--c-gray); margin-left:16px;"><?= $totalColabs ?> colaborador(es) ativo(s)</span>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="/relatorios/cliente/<?= $cliente['id'] ?>" class="btn btn-outline btn-sm">Relatorio</a>
        <a href="/clientes/<?= $cliente['id'] ?>/editar" class="btn btn-outline btn-sm">Editar</a>
        <a href="/obras/novo/<?= $cliente['id'] ?>" class="btn btn-primary btn-sm">+ Nova Obra</a>
    </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
    <!-- Obras -->
    <div class="table-container">
        <div class="table-header"><span class="table-title">Obras</span></div>
        <table>
            <thead><tr><th>Nome</th><th>Local</th><th>Inicio</th><th>Status</th><th>Acoes</th></tr></thead>
            <tbody>
            <?php if (empty($obras)): ?>
            <tr><td colspan="5" style="text-align:center;color:#6b7280;">Nenhuma obra cadastrada.</td></tr>
            <?php else: ?>
            <?php foreach ($obras as $o): ?>
            <tr>
                <td><?= htmlspecialchars($o['nome']) ?></td>
                <td><?= htmlspecialchars($o['local_obra'] ?? '-') ?></td>
                <td><?= $o['data_inicio'] ? date('d/m/Y', strtotime($o['data_inicio'])) : '-' ?></td>
                <td><span class="badge badge-<?= $o['status'] === 'ativa' ? 'ativo' : ($o['status'] === 'suspensa' ? 'afastado' : 'inativo') ?>"><?= ucfirst($o['status']) ?></span></td>
                <td><a href="/obras/<?= $o['id'] ?>/editar" class="btn btn-outline btn-sm">Editar</a></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Requisitos Documentais -->
    <div class="table-container">
        <div class="table-header"><span class="table-title">Requisitos Documentais</span></div>
        <table>
            <thead><tr><th>Tipo</th><th>Categoria</th><th>Obrigatorio</th><th>Acoes</th></tr></thead>
            <tbody>
            <?php if (empty($requisitos)): ?>
            <tr><td colspan="4" style="text-align:center;color:#6b7280;">Nenhum requisito configurado.</td></tr>
            <?php else: ?>
            <?php foreach ($requisitos as $req): ?>
            <tr>
                <td style="font-size:13px;">
                    <?php if ($req['doc_nome']): ?>
                        <?= htmlspecialchars($req['doc_nome']) ?>
                    <?php else: ?>
                        <?= htmlspecialchars($req['cert_codigo']) ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($req['doc_categoria']): ?>
                        <span class="badge" style="background:<?= match($req['doc_categoria']) {
                            'aso' => '#e8f5e9; color:#2e7d32',
                            'epi' => '#e3f2fd; color:#1565c0',
                            'os' => '#fff3e0; color:#e65100',
                            'treinamento' => '#f3e5f5; color:#7b1fa2',
                            default => '#f5f5f5; color:#616161'
                        } ?>;"><?= strtoupper($req['doc_categoria']) ?></span>
                    <?php else: ?>
                        <span class="badge" style="background:#e0f7fa; color:#00838f;">CERT</span>
                    <?php endif; ?>
                </td>
                <td><?= $req['obrigatorio'] ? 'Sim' : 'Nao' ?></td>
                <td>
                    <form method="POST" action="/clientes/<?= $cliente['id'] ?>/requisitos/<?= $req['id'] ?>/excluir" style="display:inline;">
                        <?= \App\Core\View::csrfField() ?>
                        <button type="submit" class="btn btn-danger btn-sm" data-confirm="Remover este requisito?">X</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <!-- Adicionar requisito -->
        <div style="padding:16px 20px; border-top:1px solid #e5e7eb; background:#fafafa;">
            <form method="POST" action="/clientes/<?= $cliente['id'] ?>/requisitos" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <?= \App\Core\View::csrfField() ?>
                <select name="tipo_documento_id" class="form-control" style="width:180px;" id="req-doc-select">
                    <option value="">-- Documento --</option>
                    <?php foreach ($tiposDocs as $td): ?>
                    <option value="<?= $td['id'] ?>"><?= htmlspecialchars($td['nome']) ?> (<?= strtoupper($td['categoria']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <span style="color:#999; font-size:12px;">ou</span>
                <select name="tipo_certificado_id" class="form-control" style="width:180px;" id="req-cert-select">
                    <option value="">-- Certificado --</option>
                    <?php foreach ($tiposCerts as $tc): ?>
                    <option value="<?= $tc['id'] ?>"><?= htmlspecialchars($tc['codigo']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label style="font-size:13px; display:flex; align-items:center; gap:4px;">
                    <input type="checkbox" name="obrigatorio" value="1" checked> Obrigatorio
                </label>
                <button type="submit" class="btn btn-primary btn-sm">Adicionar</button>
            </form>
        </div>
    </div>
</div>

<?php if ($cliente['contato_nome'] || $cliente['contato_email']): ?>
<div class="table-container" style="margin-top:24px;">
    <div class="table-header"><span class="table-title">Contato</span></div>
    <div style="padding:20px;">
        <p><strong>Nome:</strong> <?= htmlspecialchars($cliente['contato_nome'] ?? '-') ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($cliente['contato_email'] ?? '-') ?></p>
        <p><strong>Telefone:</strong> <?= htmlspecialchars($cliente['contato_telefone'] ?? '-') ?></p>
    </div>
</div>
<?php endif; ?>

<script>
// Disable one select when the other is used
document.getElementById('req-doc-select').addEventListener('change', function() {
    document.getElementById('req-cert-select').disabled = !!this.value;
    if (this.value) document.getElementById('req-cert-select').value = '';
});
document.getElementById('req-cert-select').addEventListener('change', function() {
    document.getElementById('req-doc-select').disabled = !!this.value;
    if (this.value) document.getElementById('req-doc-select').value = '';
});
</script>
