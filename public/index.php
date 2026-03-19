<?php

declare(strict_types=1);

// Carregar .env
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}

// Timezone
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo');

// Autoloader
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;

    $relativeClass = substr($class, strlen($prefix));
    $file = dirname(__DIR__) . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Composer autoloader (se existir)
$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require $composerAutoload;
}

// Iniciar sessao
\App\Core\Session::start();

// Configurar rotas
$router = new \App\Core\Router();

// --- Auth ---
$router->get('/login', ['AuthController', 'loginForm']);
$router->post('/login', ['AuthController', 'login']);
$router->get('/logout', ['AuthController', 'logout']);

// --- Dashboard ---
$router->get('/', ['DashboardController', 'index'], ['AuthMiddleware']);
$router->get('/dashboard', ['DashboardController', 'index'], ['AuthMiddleware']);

// --- Colaboradores ---
$router->get('/colaboradores', ['ColaboradorController', 'index'], ['AuthMiddleware']);
$router->get('/colaboradores/novo', ['ColaboradorController', 'create'], ['AuthMiddleware']);
$router->post('/colaboradores/salvar', ['ColaboradorController', 'store'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->get('/colaboradores/{id}', ['ColaboradorController', 'show'], ['AuthMiddleware']);
$router->get('/colaboradores/{id}/download-zip', ['ColaboradorController', 'downloadZip'], ['AuthMiddleware']);
$router->get('/colaboradores/{id}/editar', ['ColaboradorController', 'edit'], ['AuthMiddleware']);
$router->post('/colaboradores/{id}/atualizar', ['ColaboradorController', 'update'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/colaboradores/{id}/excluir', ['ColaboradorController', 'destroy'], ['AuthMiddleware', 'CsrfMiddleware']);

// --- Certificados ---
$router->get('/certificados', ['CertificadoController', 'index'], ['AuthMiddleware']);
$router->get('/certificados/emitir/{colaboradorId}', ['CertificadoController', 'emitir'], ['AuthMiddleware']);
$router->post('/certificados/salvar', ['CertificadoController', 'store'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->get('/certificados/preview/{id}', ['CertificadoController', 'preview'], ['AuthMiddleware']);
$router->get('/certificados/dados/{colaboradorId}', ['CertificadoController', 'dadosJson'], ['AuthMiddleware']);

// --- Documentos ---
$router->get('/documentos', ['DocumentoController', 'index'], ['AuthMiddleware']);
$router->get('/documentos/upload/{colaboradorId}', ['DocumentoController', 'uploadForm'], ['AuthMiddleware']);
$router->post('/documentos/upload', ['DocumentoController', 'upload'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->get('/documentos/download/{id}', ['DocumentoController', 'download'], ['AuthMiddleware']);
$router->get('/documentos/visualizar/{id}', ['DocumentoController', 'visualizar'], ['AuthMiddleware']);
$router->get('/documentos/{id}', ['DocumentoController', 'show'], ['AuthMiddleware']);
$router->post('/documentos/{id}/excluir', ['DocumentoController', 'destroy'], ['AuthMiddleware', 'CsrfMiddleware']);

// --- Exportar ---
$router->get('/exportar/colaboradores', ['ExportController', 'colaboradores'], ['AuthMiddleware']);
$router->get('/exportar/documentos', ['ExportController', 'documentos'], ['AuthMiddleware']);
$router->get('/exportar/certificados', ['ExportController', 'certificados'], ['AuthMiddleware']);

// --- Clientes ---
$router->get('/clientes', ['ClienteController', 'index'], ['AuthMiddleware']);
$router->get('/clientes/novo', ['ClienteController', 'create'], ['AuthMiddleware']);
$router->post('/clientes/salvar', ['ClienteController', 'store'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->get('/clientes/{id}', ['ClienteController', 'show'], ['AuthMiddleware']);
$router->get('/clientes/{id}/editar', ['ClienteController', 'edit'], ['AuthMiddleware']);
$router->post('/clientes/{id}/atualizar', ['ClienteController', 'update'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/clientes/{id}/requisitos', ['ClienteController', 'addRequisito'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/clientes/{id}/requisitos/{reqId}/excluir', ['ClienteController', 'removeRequisito'], ['AuthMiddleware', 'CsrfMiddleware']);

// --- Obras ---
$router->get('/obras/novo/{clienteId}', ['ObraController', 'create'], ['AuthMiddleware']);
$router->post('/obras/salvar', ['ObraController', 'store'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->get('/obras/{id}/editar', ['ObraController', 'edit'], ['AuthMiddleware']);
$router->post('/obras/{id}/atualizar', ['ObraController', 'update'], ['AuthMiddleware', 'CsrfMiddleware']);

// --- Alertas ---
$router->get('/alertas', ['AlertaController', 'index'], ['AuthMiddleware']);

// --- Relatorios ---
$router->get('/relatorios', ['RelatorioController', 'index'], ['AuthMiddleware']);
$router->get('/relatorios/colaborador/{id}', ['RelatorioController', 'porColaborador'], ['AuthMiddleware']);
$router->get('/relatorios/cliente/{id}', ['RelatorioController', 'porCliente'], ['AuthMiddleware']);

// --- Logs ---
$router->get('/logs', ['LogController', 'index'], ['AuthMiddleware']);
$router->get('/logs/exportar', ['LogController', 'exportar'], ['AuthMiddleware']);

// --- Usuarios ---
$router->get('/usuarios', ['UsuarioController', 'index'], ['AuthMiddleware']);
$router->post('/usuarios/salvar', ['UsuarioController', 'store'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/usuarios/{id}/resetar', ['UsuarioController', 'resetPassword'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/usuarios/{id}/excluir', ['UsuarioController', 'destroy'], ['AuthMiddleware', 'CsrfMiddleware']);

// --- Configuracoes ---
$router->get('/configuracoes', ['ConfigController', 'index'], ['AuthMiddleware']);
$router->post('/configuracoes/salvar', ['ConfigController', 'save'], ['AuthMiddleware', 'CsrfMiddleware']);

// --- API (teste) ---
$router->get('/api/tipos-certificado', ['CertificadoController', 'tiposJson']);

// Dispatch
$router->dispatch();
