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

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'");

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

// --- Manual (publico) ---
$router->get('/manual', ['ManualController', 'index']);

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
$router->post('/colaboradores/bulk-update', ['ColaboradorController', 'bulkUpdate'], ['AuthMiddleware', 'CsrfMiddleware']);

// --- Certificados ---
$router->get('/certificados', ['CertificadoController', 'index'], ['AuthMiddleware']);
$router->get('/certificados/emitir/{colaboradorId}', ['CertificadoController', 'emitir'], ['AuthMiddleware']);
$router->post('/certificados/salvar', ['CertificadoController', 'store'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->get('/certificados/preview/{id}', ['CertificadoController', 'preview'], ['AuthMiddleware']);
$router->get('/certificados/dados/{colaboradorId}', ['CertificadoController', 'dadosJson'], ['AuthMiddleware']);

// --- Treinamentos em Massa ---
$router->get('/treinamentos', ['TreinamentoController', 'index'], ['AuthMiddleware']);
$router->get('/treinamentos/novo', ['TreinamentoController', 'create'], ['AuthMiddleware']);
$router->get('/treinamentos/calendario', ['TreinamentoController', 'calendario'], ['AuthMiddleware']);
$router->get('/treinamentos/colaboradores-json', ['TreinamentoController', 'colaboradoresJson'], ['AuthMiddleware']);
$router->post('/treinamentos/salvar', ['TreinamentoController', 'store'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->get('/treinamentos/{id}/certificados', ['TreinamentoController', 'certificados'], ['AuthMiddleware']);
$router->get('/treinamentos/{id}/lista-presenca', ['TreinamentoController', 'listaPresenca'], ['AuthMiddleware']);
$router->get('/treinamentos/{id}', ['TreinamentoController', 'show'], ['AuthMiddleware']);

// --- Backup ---
$router->get('/backup', ['BackupController', 'index'], ['AuthMiddleware']);
$router->post('/backup/executar', ['BackupController', 'executar'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->get('/backup/download/{nome}', ['BackupController', 'download'], ['AuthMiddleware']);
$router->post('/backup/excluir/{nome}', ['BackupController', 'excluir'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/backup/configurar-cron', ['BackupController', 'configurarCron'], ['AuthMiddleware', 'CsrfMiddleware']);

// --- Agenda de Exames ---
$router->get('/agenda-exames', ['AgendaExamesController', 'index'], ['AuthMiddleware']);

// --- Checklist Pre-Obra ---
$router->get('/checklist', ['ChecklistController', 'index'], ['AuthMiddleware']);
$router->get('/checklist/{id}/verificar', ['ChecklistController', 'verificar'], ['AuthMiddleware']);

// --- Kit PJ ---
$router->get('/kit-pj', ['KitPjController', 'index'], ['AuthMiddleware']);
$router->get('/kit-pj/novo', ['KitPjController', 'create'], ['AuthMiddleware']);
$router->post('/kit-pj/salvar', ['KitPjController', 'store'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->get('/kit-pj/{id}/imprimir', ['KitPjController', 'imprimir'], ['AuthMiddleware']);

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
$router->post('/documentos/excluir-lote', ['DocumentoController', 'destroyBatch'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/documentos/{id}/aprovar', ['DocumentoController', 'aprovar'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/documentos/aprovar-todos/{colaboradorId}', ['DocumentoController', 'aprovarTodos'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/documentos/ocr-analise', ['DocumentoController', 'ocrAnalise'], ['AuthMiddleware']);

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
$router->get('/relatorios/tipo-documento', ['RelatorioController', 'porTipoDocumento'], ['AuthMiddleware']);

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
$router->post('/configuracoes/smtp/testar', ['ConfigController', 'testarSmtp'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->get('/configuracoes/preview-certificado/{id}', ['ConfigController', 'previewCertificado'], ['AuthMiddleware']);

// --- Notificacoes ---
$router->get('/notificacoes', ['NotificacaoController', 'index'], ['AuthMiddleware']);
$router->get('/notificacoes/json', ['NotificacaoController', 'jsonData'], ['AuthMiddleware']);
$router->post('/notificacoes/marcar-todas', ['NotificacaoController', 'marcarTodasLidas'], ['AuthMiddleware', 'CsrfMiddleware']);
$router->post('/notificacoes/{id}/lida', ['NotificacaoController', 'marcarLida'], ['AuthMiddleware', 'CsrfMiddleware']);

// --- Busca Global ---
$router->get('/busca', ['BuscaController', 'index'], ['AuthMiddleware']);
$router->get('/busca/json', ['BuscaController', 'jsonSearch'], ['AuthMiddleware']);

// --- Tema ---
$router->post('/usuarios/tema', ['UsuarioController', 'salvarTema'], ['AuthMiddleware', 'CsrfMiddleware']);

// --- API (teste) ---
$router->get('/api/tipos-certificado', ['CertificadoController', 'tiposJson'], ['AuthMiddleware']);

// --- API v1 (autenticacao via token) ---
$router->get('/api/v1/colaboradores', ['ApiController', 'colaboradores'], ['ApiAuthMiddleware']);
$router->get('/api/v1/colaboradores/{id}', ['ApiController', 'colaborador'], ['ApiAuthMiddleware']);
$router->get('/api/v1/documentos', ['ApiController', 'documentos'], ['ApiAuthMiddleware']);
$router->get('/api/v1/documentos/{id}', ['ApiController', 'documento'], ['ApiAuthMiddleware']);
$router->get('/api/v1/certificados', ['ApiController', 'certificados'], ['ApiAuthMiddleware']);
$router->get('/api/v1/certificados/{id}', ['ApiController', 'certificado'], ['ApiAuthMiddleware']);
$router->get('/api/v1/clientes', ['ApiController', 'clientes'], ['ApiAuthMiddleware']);
$router->get('/api/v1/obras', ['ApiController', 'obras'], ['ApiAuthMiddleware']);
$router->get('/api/v1/stats', ['ApiController', 'stats'], ['ApiAuthMiddleware']);

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
