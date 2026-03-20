<div class="table-container">
    <div class="table-header">
        <span class="table-title">Resultados da Busca</span>
    </div>

    <div style="padding:16px 20px; border-bottom:1px solid var(--c-border); background:#fafafa;">
        <form method="GET" action="/busca" style="display:flex; gap:10px; align-items:center;">
            <div class="search-box" style="flex:1;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="q" class="form-control" placeholder="Buscar colaboradores, documentos, clientes, obras..." value="<?= htmlspecialchars($q ?? '') ?>" autofocus>
            </div>
            <button type="submit" class="btn btn-secondary btn-sm">Buscar</button>
        </form>
    </div>

    <?php if (empty($q)): ?>
    <div style="text-align:center; color:var(--c-gray); padding:40px;">
        <p>Digite um termo para buscar.</p>
    </div>
    <?php elseif (empty($results)): ?>
    <div style="text-align:center; color:var(--c-gray); padding:40px;">
        <p>Nenhum resultado encontrado para "<strong><?= htmlspecialchars($q) ?></strong>".</p>
    </div>
    <?php else: ?>
        <?php
        $categoryLabels = [
            'colaboradores' => 'Colaboradores',
            'clientes'      => 'Clientes',
            'obras'         => 'Obras',
            'documentos'    => 'Documentos',
            'certificados'  => 'Certificados',
        ];
        $categoryIcons = [
            'colaboradores' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>',
            'clientes'      => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>',
            'obras'         => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="6" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg>',
            'documentos'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
            'certificados'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M8 14l-2 8 6-3 6 3-2-8"/></svg>',
        ];
        ?>
        <?php foreach ($results as $category => $items): ?>
        <div style="padding:12px 20px; background:#fafafa; border-bottom:1px solid var(--c-border);">
            <span style="font-size:13px; font-weight:600; color:var(--c-gray); display:flex; align-items:center; gap:6px;">
                <?= $categoryIcons[$category] ?? '' ?>
                <?= $categoryLabels[$category] ?? ucfirst($category) ?>
                <span style="font-weight:400;">(<?= count($items) ?>)</span>
            </span>
        </div>
        <?php foreach ($items as $item): ?>
        <a href="<?= htmlspecialchars($item['link']) ?>" style="display:flex; justify-content:space-between; align-items:center; padding:12px 20px; border-bottom:1px solid var(--c-border); text-decoration:none; color:var(--c-text); transition:background 0.15s;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='transparent'">
            <span style="font-weight:600; font-size:14px;"><?= htmlspecialchars($item['titulo']) ?></span>
            <span style="font-size:13px; color:var(--c-gray);"><?= htmlspecialchars($item['subtitulo'] ?? '') ?></span>
        </a>
        <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
