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

            <a href="/documentos" class="nav-item <?= ($pageTitle ?? '') === 'Documentos' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <span>Documentos</span>
            </a>

            <a href="/clientes" class="nav-item <?= ($pageTitle ?? '') === 'Clientes' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span>Clientes e Obras</span>
            </a>

            <a href="/alertas" class="nav-item <?= ($pageTitle ?? '') === 'Alertas' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                <span>Alertas</span>
            </a>

            <a href="/relatorios" class="nav-item <?= ($pageTitle ?? '') === 'Relatorios' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                <span>Relatorios</span>
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

            <a href="/configuracoes" class="nav-item <?= ($pageTitle ?? '') === 'Configuracoes' ? 'active' : '' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                <span>Configuracoes</span>
            </a>
            <?php endif; ?>
            <?php endif; ?>
        </nav>

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

            <!-- Notification Bell -->
            <div class="notif-bell" id="notifBell" style="position:relative; cursor:pointer; margin-left:auto;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                <span class="notif-badge" id="notifBadge" style="display:none; position:absolute; top:-4px; right:-4px; background:var(--c-danger); color:#fff; font-size:10px; font-weight:700; min-width:18px; height:18px; border-radius:9px; align-items:center; justify-content:center; padding:0 4px;">0</span>

                <div class="notif-dropdown" id="notifDropdown" style="display:none; position:absolute; top:100%; right:0; width:360px; background:var(--c-white); border:1px solid var(--c-border); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,0.15); z-index:500; margin-top:8px;">
                    <div style="padding:14px 16px; border-bottom:1px solid var(--c-border); display:flex; justify-content:space-between; align-items:center;">
                        <strong style="font-size:14px; color:var(--c-text);">Notificacoes</strong>
                        <a href="/notificacoes" style="font-size:12px; color:var(--c-link); text-decoration:none;">Ver todas</a>
                    </div>
                    <div id="notifList" style="max-height:320px; overflow-y:auto;">
                        <div style="padding:20px; text-align:center; color:var(--c-gray); font-size:13px;">Carregando...</div>
                    </div>
                    <div style="padding:10px 16px; border-top:1px solid var(--c-border); text-align:center;">
                        <button type="button" id="notifMarkAllRead" style="background:none; border:none; color:var(--c-link); font-size:12px; cursor:pointer; font-weight:600;">Marcar todas como lidas</button>
                    </div>
                </div>
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
