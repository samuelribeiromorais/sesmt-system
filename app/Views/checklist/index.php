<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <h2>Checklist Pre-Obra</h2>
</div>

<p style="color:var(--c-gray); margin-bottom:24px;">Selecione uma obra para verificar se todos os colaboradores estao com a documentacao em dia antes de envia-los ao campo.</p>

<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(350px, 1fr)); gap:16px;">
    <?php if (empty($obras)): ?>
    <p style="color:var(--c-gray); text-align:center; grid-column:1/-1; padding:40px;">Nenhuma obra ativa encontrada. Cadastre obras em <a href="/clientes">Clientes e Obras</a>.</p>
    <?php else: ?>
    <?php foreach ($obras as $o): ?>
    <a href="/checklist/<?= $o['id'] ?>/verificar" class="table-container" style="display:block; padding:20px; text-decoration:none; color:inherit; transition:box-shadow 0.2s; border-left:4px solid var(--c-primary);">
        <div style="font-size:18px; font-weight:700; color:var(--c-primary);"><?= htmlspecialchars($o['nome']) ?></div>
        <div style="font-size:13px; color:var(--c-gray); margin-top:4px;"><?= htmlspecialchars($o['nome_fantasia']) ?></div>
        <?php if (!empty($o['endereco'])): ?>
        <div style="font-size:12px; color:var(--c-gray); margin-top:4px;"><?= htmlspecialchars($o['endereco']) ?></div>
        <?php endif; ?>
        <div style="margin-top:12px; font-size:13px; color:var(--c-primary); font-weight:600;">Verificar conformidade &rarr;</div>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
