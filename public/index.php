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

// --- 2FA ---
$router->get('/login/2fa', ['AuthController', 'twoFactorForm']);
$router->post('/login/2fa', ['AuthController', 'twoFactorVerify']);

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
$router->get('/documentos/{id}/versoes', ['DocumentoController', 'versoes'], ['AuthMiddleware']);
$router->get('/documentos/{id}/assinar', ['DocumentoController', 'assinar'], ['AuthMiddleware']);
$router->post('/documentos/{id}/assinar', ['DocumentoController', 'registrarAssinatura'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/documentos/{id}/atualizar-emissao', ['DocumentoController', 'atualizarEmissao'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/documentos/{id}/excluir', ['DocumentoController', 'destroy'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/documentos/excluir-lote', ['DocumentoController', 'destroyBatch'], ['AuthMiddleware']);

// --- Lixeira ---
$router->get('/lixeira', ['LixeiraController', 'index'], ['AuthMiddleware']);
$router->post('/lixeira/{tipo}/{id}/restaurar', ['LixeiraController', 'restaurar'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/lixeira/{tipo}/{id}/excluir', ['LixeiraController', 'excluirPermanente'], ['AuthMiddleware', 'CsrfMiddleware']);

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
$router->get('/obras/{id}', ['ObraController', 'show'], ['AuthMiddleware']);
$router->get('/obras/{id}/editar', ['ObraController', 'edit'], ['AuthMiddleware']);
$router->get('/obras/{id}/download-zip', ['ObraController', 'downloadZip'], ['AuthMiddleware']);
$router->post('/obras/{id}/atualizar', ['ObraController', 'update'], ['AuthMiddleware', 'CsrfMiddleware']);

// --- Alertas ---
$router->get('/alertas', ['AlertaController', 'index'], ['AuthMiddleware']);
$router->post('/alertas/verificar', ['AlertaController', 'executarVerificacao'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/alertas/enviar-emails', ['AlertaController', 'enviarEmails'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/alertas/limpar-historico', ['AlertaController', 'limparHistorico'], ['AuthMiddleware', 'CsrfMiddleware']);

// --- Relatorios ---
$router->get('/relatorios', ['RelatorioController', 'index'], ['AuthMiddleware']);
$router->get('/relatorios/colaborador/{id}', ['RelatorioController', 'porColaborador'], ['AuthMiddleware']);
$router->get('/relatorios/cliente/{id}', ['RelatorioController', 'porCliente'], ['AuthMiddleware']);
$router->get('/relatorios/obra/{id}', ['RelatorioController', 'porObra'], ['AuthMiddleware']);
$router->get('/relatorios/mensal', ['RelatorioController', 'mensal'], ['AuthMiddleware']);

// --- Logs ---
$router->get('/logs', ['LogController', 'index'], ['AuthMiddleware']);
$router->get('/logs/exportar', ['LogController', 'exportar'], ['AuthMiddleware']);

// --- Usuarios ---
$router->get('/usuarios', ['UsuarioController', 'index'], ['AuthMiddleware']);
$router->post('/usuarios/salvar', ['UsuarioController', 'store'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/usuarios/{id}/resetar', ['UsuarioController', 'resetPassword'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/usuarios/{id}/excluir', ['UsuarioController', 'destroy'], ['AuthMiddleware', 'CsrfMiddleware']);

// --- 2FA Setup ---
$router->get('/usuarios/2fa/setup', ['UsuarioController', 'setup2fa'], ['AuthMiddleware']);
$router->post('/usuarios/2fa/ativar', ['UsuarioController', 'enable2fa'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/usuarios/2fa/desativar', ['UsuarioController', 'disable2fa'], ['AuthMiddleware', 'CsrfMiddleware']);

// --- Sessoes Ativas ---
$router->get('/usuarios/sessoes', ['UsuarioController', 'sessoes'], ['AuthMiddleware']);
$router->post('/usuarios/sessoes/{id}/encerrar', ['UsuarioController', 'encerrarSessao'], ['AuthMiddleware', 'CsrfMiddleware']);

// --- Alterar Senha ---
$router->get('/usuarios/alterar-senha', ['UsuarioController', 'alterarSenhaForm'], ['AuthMiddleware']);
$router->post('/usuarios/alterar-senha', ['UsuarioController', 'alterarSenha'], ['AuthMiddleware', 'CsrfMiddleware']);

// --- Configuracoes ---
$router->get('/configuracoes', ['ConfigController', 'index'], ['AuthMiddleware']);
$router->post('/configuracoes/tipo-doc', ['ConfigController', 'salvarTipoDoc'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/configuracoes/tipo-doc/{id}/excluir', ['ConfigController', 'excluirTipoDoc'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/configuracoes/tipo-cert', ['ConfigController', 'salvarTipoCert'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/configuracoes/ministrante', ['ConfigController', 'salvarMinistrante'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/configuracoes/ministrante/{id}/excluir', ['ConfigController', 'excluirMinistrante'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/configuracoes/smtp', ['ConfigController', 'salvarSmtp'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/configuracoes/smtp/testar', ['ConfigController', 'testarSmtp'], ['AuthMiddleware']);

// --- Notificacoes ---
$router->get('/notificacoes', ['NotificacaoController', 'index'], ['AuthMiddleware']);
$router->get('/notificacoes/json', ['NotificacaoController', 'jsonData'], ['AuthMiddleware']);
$router->post('/notificacoes/marcar-todas', ['NotificacaoController', 'marcarTodasLidas'], ['AuthMiddleware']);
$router->post('/notificacoes/{id}/lida', ['NotificacaoController', 'marcarLida'], ['AuthMiddleware']);

// --- Busca Global ---
$router->get('/busca', ['BuscaController', 'index'], ['AuthMiddleware']);
$router->get('/busca/json', ['BuscaController', 'jsonSearch'], ['AuthMiddleware']);

// --- Tema ---
$router->post('/usuarios/tema', ['UsuarioController', 'salvarTema'], ['AuthMiddleware']);

// --- API (teste) ---
$router->get('/api/tipos-certificado', ['CertificadoController', 'tiposJson']);

// --- API v1 (autenticacao via token, sem sessao) ---
$router->get('/api/v1/colaboradores', ['ApiController', 'colaboradores']);
$router->get('/api/v1/colaboradores/{id}', ['ApiController', 'colaborador']);
$router->get('/api/v1/documentos', ['ApiController', 'documentos']);
$router->get('/api/v1/documentos/{id}', ['ApiController', 'documento']);
$router->get('/api/v1/certificados', ['ApiController', 'certificados']);
$router->get('/api/v1/certificados/{id}', ['ApiController', 'certificado']);
$router->get('/api/v1/clientes', ['ApiController', 'clientes']);
$router->get('/api/v1/obras', ['ApiController', 'obras']);
$router->get('/api/v1/stats', ['ApiController', 'stats']);

// --- Tokens de API (web interface) ---
$router->get('/usuarios/api-tokens', ['UsuarioController', 'apiTokens'], ['AuthMiddleware']);
$router->post('/usuarios/api-tokens/criar', ['UsuarioController', 'createApiToken'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/usuarios/api-tokens/{id}/revogar', ['UsuarioController', 'revokeApiToken'], ['AuthMiddleware', 'CsrfMiddleware']);

// --- Importar Colaboradores ---
$router->get('/importar/colaboradores', ['ImportController', 'form'], ['AuthMiddleware']);
$router->post('/importar/colaboradores/preview', ['ImportController', 'preview'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/importar/colaboradores/executar', ['ImportController', 'executar'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->get('/importar/colaboradores/template', ['ImportController', 'templateDownload'], ['AuthMiddleware']);

// --- Upload Links Externos ---
$router->get('/upload-links', ['UploadLinkController', 'index'], ['AuthMiddleware']);
$router->post('/upload-links/gerar', ['UploadLinkController', 'gerar'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/upload-links/upload-direto', ['UploadLinkController', 'uploadDireto'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/upload-links/importar-link', ['UploadLinkController', 'importarLink'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/upload-links/{id}/revogar', ['UploadLinkController', 'revogar'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->get('/upload-externo/{token}', ['UploadLinkController', 'paginaUpload']);
$router->post('/upload-externo/{token}/enviar', ['UploadLinkController', 'processarUpload']);

// --- eSocial SST ---
$router->get('/esocial', ['EsocialController', 'index'], ['AuthMiddleware']);
$router->get('/esocial/gerar/{colaboradorId}', ['EsocialController', 'gerar'], ['AuthMiddleware']);
$router->post('/esocial/criar', ['EsocialController', 'criarEvento'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->get('/esocial/{id}', ['EsocialController', 'visualizar'], ['AuthMiddleware']);
$router->get('/esocial/{id}/xml', ['EsocialController', 'exportarXml'], ['AuthMiddleware']);

// Dispatch
$router->dispatch();
