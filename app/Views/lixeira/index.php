<?php $activeTab = $_GET['tab'] ?? 'colaboradores'; ?>

<div style="margin-bottom:20px;">
    <div style="display:flex; gap:8px; border-bottom:2px solid #e5e7eb; padding-bottom:0;">
        <a href="/lixeira?tab=colaboradores"
           style="padding:10px 20px; font-weight:600; font-size:14px; text-decoration:none; border-bottom:2px solid <?= $activeTab === 'colaboradores' ? '#005e4e' : 'transparent' ?>; margin-bottom:-2px; color:<?= $activeTab === 'colaboradores' ? '#005e4e' : '#6b7280' ?>;">
            Colaboradores (<?= count($colaboradores) ?>)
        </a>
        <a href="/lixeira?tab=documentos"
           style="padding:10px 20px; font-weight:600; font-size:14px; text-decoration:none; border-bottom:2px solid <?= $activeTab === 'documentos' ? '#005e4e' : 'transparent' ?>; margin-bottom:-2px; color:<?= $activeTab === 'documentos' ? '#005e4e' : '#6b7280' ?>;">
            Documentos (<?= count($documentos) ?>)
        </a>
        <a href="/lixeira?tab=certificados"
           style="padding:10px 20px; font-weight:600; font-size:14px; text-decoration:none; border-bottom:2px solid <?= $activeTab === 'certificados' ? '#005e4e' : 'transparent' ?>; margin-bottom:-2px; color:<?= $activeTab === 'certificados' ? '#005e4e' : '#6b7280' ?>;">
            Certificados (<?= count($certificados) ?>)
        </a>
    </div>
</div>

<?php if ($activeTab === 'colaboradores'): ?>
<div class="table-container">
    <div class="table-header">
        <span class="table-title">Colaboradores Excluidos</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>Cargo</th>
                <th>Cliente</th>
                <th>Excluido em</th>
                <th>Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($colaboradores)): ?>
            <tr><td colspan="5" style="text-align:center;color:#6b7280;">Nenhum colaborador na lixeira</td></tr>
            <?php else: ?>
            <?php foreach ($colaboradores as $c): ?>
            <tr>
                <td><a href="/colaboradores/<?= $c['id'] ?>"><?= htmlspecialchars($c['nome_completo']) ?></a></td>
                <td><?= htmlspecialchars($c['cargo'] ?? $c['funcao'] ?? '-') ?></td>
                <td><?= htmlspecialchars($c['cliente_nome'] ?? '-') ?></td>
                <td><?= date('d/m/Y H:i', strtotime($c['excluido_em'])) ?></td>
                <td style="white-space:nowrap;">
                    <form method="POST" action="/lixeira/colaborador/<?= $c['id'] ?>/restaurar" style="display:inline;">
                        <?= \App\Core\View::csrfField() ?>
                        <button type="submit" class="btn btn-primary btn-sm" data-confirm="Restaurar este colaborador?">Restaurar</button>
                    </form>
                    <form method="POST" action="/lixeira/colaborador/<?= $c['id'] ?>/excluir" style="display:inline;">
                        <?= \App\Core\View::csrfField() ?>
                        <button type="submit" class="btn btn-danger btn-sm" data-confirm="Excluir PERMANENTEMENTE este colaborador? Esta acao não pode ser desfeita.">Excluir Permanente</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($activeTab === 'documentos'): ?>
<div class="table-container">
    <div class="table-header">
        <span class="table-title">Documentos Excluidos</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Arquivo</th>
                <th>Tipo</th>
                <th>Colaborador</th>
                <th>Excluido em</th>
                <th>Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($documentos)): ?>
            <tr><td colspan="5" style="text-align:center;color:#6b7280;">Nenhum documento na lixeira</td></tr>
            <?php else: ?>
            <?php foreach ($documentos as $d): ?>
            <tr>
                <td><?= htmlspecialchars($d['arquivo_nome']) ?></td>
                <td><?= htmlspecialchars($d['tipo_nome']) ?></td>
                <td><a href="/colaboradores/<?= $d['colaborador_id'] ?>"><?= htmlspecialchars($d['nome_completo']) ?></a></td>
                <td><?= date('d/m/Y H:i', strtotime($d['excluido_em'])) ?></td>
                <td style="white-space:nowrap;">
                    <form method="POST" action="/lixeira/documento/<?= $d['id'] ?>/restaurar" style="display:inline;">
                        <?= \App\Core\View::csrfField() ?>
                        <button type="submit" class="btn btn-primary btn-sm" data-confirm="Restaurar este documento?">Restaurar</button>
                    </form>
                    <form method="POST" action="/lixeira/documento/<?= $d['id'] ?>/excluir" style="display:inline;">
                        <?= \App\Core\View::csrfField() ?>
                        <button type="submit" class="btn btn-danger btn-sm" data-confirm="Excluir PERMANENTEMENTE este documento? O arquivo fisico também sera removido.">Excluir Permanente</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($activeTab === 'certificados'): ?>
<div class="table-container">
    <div class="table-header">
        <span class="table-title">Certificados Excluidos</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Título</th>
                <th>Colaborador</th>
                <th>Excluido em</th>
                <th>Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($certificados)): ?>
            <tr><td colspan="5" style="text-align:center;color:#6b7280;">Nenhum certificado na lixeira</td></tr>
            <?php else: ?>
            <?php foreach ($certificados as $cert): ?>
            <tr>
                <td><?= htmlspecialchars($cert['codigo']) ?></td>
                <td><?= htmlspecialchars($cert['titulo']) ?></td>
                <td><a href="/colaboradores/<?= $cert['colaborador_id'] ?>"><?= htmlspecialchars($cert['nome_completo']) ?></a></td>
                <td><?= date('d/m/Y H:i', strtotime($cert['excluido_em'])) ?></td>
                <td style="white-space:nowrap;">
                    <form method="POST" action="/lixeira/certificado/<?= $cert['id'] ?>/restaurar" style="display:inline;">
                        <?= \App\Core\View::csrfField() ?>
                        <button type="submit" class="btn btn-primary btn-sm" data-confirm="Restaurar este certificado?">Restaurar</button>
                    </form>
                    <form method="POST" action="/lixeira/certificado/<?= $cert['id'] ?>/excluir" style="display:inline;">
                        <?= \App\Core\View::csrfField() ?>
                        <button type="submit" class="btn btn-danger btn-sm" data-confirm="Excluir PERMANENTEMENTE este certificado? Esta acao não pode ser desfeita.">Excluir Permanente</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
