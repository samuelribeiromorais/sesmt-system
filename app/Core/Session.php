<?php

namespace App\Core;

class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => $secure,
                'httponly'  => true,
                'samesite'  => 'Strict',
            ]);
            session_start();
        }

        self::checkInactivity();
        self::updateActiveSession();
    }

    private static function checkInactivity(): void
    {
        $config = require dirname(__DIR__) . '/config/app.php';
        $lifetime = $config['session']['lifetime'];

        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > $lifetime) {
                self::destroy();
                header('Location: /login?expired=1');
                exit;
            }
        }
        $_SESSION['last_activity'] = time();
    }

    /**
     * Atualiza o timestamp de último acesso da sessão ativa no banco.
     * Executa no máximo a cada 60 segundos para não sobrecarregar o DB.
     */
    private static function updateActiveSession(): void
    {
        if (!self::isLoggedIn()) {
            return;
        }

        $lastUpdate = $_SESSION['_sessao_ativa_update'] ?? 0;
        if (time() - $lastUpdate < 60) {
            return;
        }

        try {
            $sessaoModel = new \App\Models\SessaoAtiva();
            $sessaoModel->atualizarAcesso(session_id());
            $_SESSION['_sessao_ativa_update'] = time();
        } catch (\Throwable $e) {
            // Silenciar erros para não quebrar a sessão
        }
    }

    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    public static function isLoggedIn(): bool
    {
        return self::has('user_id');
    }

    public static function user(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }
        return [
            'id'     => self::get('user_id'),
            'nome'   => self::get('user_nome'),
            'email'  => self::get('user_email'),
            'perfil' => self::get('user_perfil'),
        ];
    }

    public static function generateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        self::set('csrf_token', $token);
        return $token;
    }

    public static function verifyCsrfToken(string $token): bool
    {
        return hash_equals(self::get('csrf_token', ''), $token);
    }
}
