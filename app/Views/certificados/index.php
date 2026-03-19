<div class="table-container">
    <div class="table-header">
        <span class="table-title">Emissao de Certificados</span>
    </div>
    <div style="padding:24px;">
        <p style="color:var(--c-gray);font-size:14px;margin-bottom:20px;">Selecione um colaborador para emitir ou visualizar certificados.</p>

        <div class="form-group">
            <label>Colaborador</label>
            <select class="form-control" onchange="if(this.value) window.location='/certificados/emitir/'+this.value">
                <option value="">-- Selecionar Colaborador --</option>
                <?php foreach ($colaboradores as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome_completo']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<div class="table-container" style="margin-top:24px;">
    <div class="table-header"><span class="table-title">Tipos de Certificado Disponiveis (<?= count($tipos) ?>)</span></div>
    <table>
        <thead><tr><th>Codigo</th><th>Titulo</th><th>Duracao</th><th>Validade</th><th>Anuencia</th></tr></thead>
        <tbody>
        <?php foreach ($tipos as $t): ?>
        <tr>
            <td style="font-weight:600;"><?= htmlspecialchars($t['codigo']) ?></td>
            <td style="font-size:13px;"><?= htmlspecialchars($t['titulo']) ?></td>
            <td><?= htmlspecialchars($t['duracao']) ?></td>
            <td><?= $t['validade_meses'] ?> meses</td>
            <td><?= $t['tem_anuencia'] ? '<span class="badge badge-vigente">Sim</span>' : '-' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
