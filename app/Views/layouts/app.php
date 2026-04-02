<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= htmlspecialchars(\App\Core\Session::get('user_tema', 'light')) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'SESMT') ?> - TSE Engenharia</title>
    <link rel="stylesheet" href="/assets/css/style.css?v=6">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#005e4e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <a href="/dashboard" class="sidebar-header" style="text-decoration:none; color:inherit; justify-content:center;">
            <img src="/assets/img/logo-tse.png?v=2" alt="TSESMT Engenharia e Automacao" class="sidebar-logo">
        </a>

        <nav class="sidebar-nav">
            <?php if ($user['perfil'] !== 'rh'): ?>
            <a href="/dashboard" class="nav-item <?= ($pageTitle ?? '') === 'Dashboard' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                <span>Dashboard</span>
            </a>
            <?php endif; ?>

            <a href="/colaboradores" class="nav-item <?= ($pageTitle ?? '') === 'Colaboradores' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                <span>Colaboradores</span>
            </a>

            <?php if ($user['perfil'] !== 'rh'): ?>
            <a href="/certificados" class="nav-item <?= ($pageTitle ?? '') === 'Certificados' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <span>Certificados</span>
            </a>

            <a href="/treinamentos" class="nav-item <?= ($pageTitle ?? '') === 'Treinamentos' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                <span>Treinamentos</span>
            </a>

            <a href="/documentos" class="nav-item <?= ($pageTitle ?? '') === 'Documentos' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <span>Documentos</span>
            </a>

            <a href="/kit-pj" class="nav-item <?= ($pageTitle ?? '') === 'Kit PJ' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <span>Kit PJ</span>
            </a>

            <a href="/agenda-exames" class="nav-item <?= ($pageTitle ?? '') === 'Agenda Exames' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span>Agenda de Exames</span>
            </a>

            <a href="/checklist" class="nav-item <?= ($pageTitle ?? '') === 'Checklist' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                <span>Checklist Pre-Obra</span>
            </a>

            <a href="/clientes" class="nav-item <?= ($pageTitle ?? '') === 'Clientes' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span>Clientes e Obras</span>
            </a>

            <a href="/alertas" class="nav-item <?= ($pageTitle ?? '') === 'Alertas' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                <span>Alertas</span>
            </a>

            <a href="/relatorios" class="nav-item <?= ($pageTitle ?? '') === 'Relatórios' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                <span>Relatórios</span>
            </a>

            <div class="nav-divider"></div>

            <a href="/usuarios" class="nav-item <?= ($pageTitle ?? '') === 'Usuarios' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>Usuarios</span>
            </a>

            <a href="/logs" class="nav-item <?= ($pageTitle ?? '') === 'Logs' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                <span>Logs</span>
            </a>

            <?php if ($user['perfil'] === 'admin'): ?>
            <a href="/upload-links" class="nav-item <?= ($pageTitle ?? '') === 'Links de Upload Externo' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
                <span>Upload Externo</span>
            </a>

            <a href="/lixeira" class="nav-item <?= ($pageTitle ?? '') === 'Lixeira' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                <span>Lixeira</span>
            </a>

            <a href="/backup" class="nav-item <?= ($pageTitle ?? '') === 'Backup' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span>Backup</span>
            </a>

            <a href="/gco" class="nav-item <?= ($pageTitle ?? '') === 'Integração GCO' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 17l4 4 4-4m-4-5v9"/><path d="M20.88 18.09A5 5 0 0018 9h-1.26A8 8 0 103 16.29"/></svg>
                <span>Integração GCO</span>
            </a>

            <a href="/configuracoes" class="nav-item <?= ($pageTitle ?? '') === 'Configurações' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                <span>Configurações</span>
            </a>
            <?php endif; ?>
            <?php endif; ?>
        </nav>

        <a href="/manual" target="_blank" style="display:block; margin:0 12px 8px; padding:8px 12px; background:rgba(175,216,90,0.15); color:#afd85a; border:1px solid rgba(175,216,90,0.3); border-radius:6px; text-align:center; text-decoration:none; font-size:12px; font-weight:600;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
            Manual de Operação
        </a>
        <div class="sidebar-footer">
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($user['nome'] ?? '') ?></span>
                <span class="user-role"><?= htmlspecialchars(strtoupper($user['perfil'] ?? '')) ?></span>
            </div>
            <div style="display:flex; align-items:center; gap:6px;">
                <button type="button" class="btn-logout" id="themeToggle" title="Alternar tema claro/escuro" style="background:none; border:none; cursor:pointer;">
                    <svg id="themeIconMoon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:<?= \App\Core\Session::get('user_tema', 'light') === 'dark' ? 'none' : 'block' ?>;"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                    <svg id="themeIconSun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:<?= \App\Core\Session::get('user_tema', 'light') === 'dark' ? 'block' : 'none' ?>;"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                </button>
                <a href="/logout" class="btn-logout" title="Sair">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="top-bar">
            <button class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <h1 class="page-title"><?= htmlspecialchars($pageTitle ?? '') ?></h1>

            <!-- Global Search -->
            <div class="global-search" style="position:relative; flex:1; max-width:400px; margin:0 20px;">
                <input type="text" id="globalSearch" placeholder="Buscar colaboradores, documentos, clientes..."
                       style="width:100%; padding:8px 12px 8px 34px; border:1px solid var(--c-border); border-radius:6px; font-size:13px; background:var(--c-bg); color:var(--c-text);" autocomplete="off">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--c-gray)" stroke-width="2" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <div id="globalSearchResults" class="search-dropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:var(--c-white); border:1px solid var(--c-border); border-top:none; border-radius:0 0 6px 6px; box-shadow:0 8px 24px rgba(0,0,0,0.12); max-height:400px; overflow-y:auto; z-index:500;"></div>
            </div>
        </header>

        <?php
        $flash = $_SESSION['flash'] ?? null;
        if ($flash):
            unset($_SESSION['flash']);
        ?>
        <div class="alert alert-<?= $flash['type'] ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>

        <div class="content-area">
            <?= $content ?>
        </div>
    </main>

    <script src="/assets/js/app.js?v=3"></script>
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }
    </script>
</body>
</html>
