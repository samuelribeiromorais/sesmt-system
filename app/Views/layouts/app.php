<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'SESMT') ?> - TSE Engenharia</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <a href="/dashboard" class="sidebar-header" style="text-decoration:none; color:inherit;">
            <img src="/assets/img/logo-tse.png" alt="TSE" class="sidebar-logo">
            <span class="sidebar-title">SESMT</span>
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
            <a href="/logout" class="btn-logout" title="Sair">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="top-bar">
            <button class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <h1 class="page-title"><?= htmlspecialchars($pageTitle ?? '') ?></h1>
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

    <script src="/assets/js/app.js"></script>
</body>
</html>
