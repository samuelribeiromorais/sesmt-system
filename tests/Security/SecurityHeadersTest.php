<?php

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

class SecurityHeadersTest extends TestCase
{
    /**
     * Test that security headers are present in index.php
     */
    public function testSecurityHeadersExistInCode(): void
    {
        $indexContent = file_get_contents(dirname(__DIR__, 2) . '/public/index.php');

        $this->assertStringContainsString('X-Content-Type-Options: nosniff', $indexContent);
        $this->assertStringContainsString('X-Frame-Options: SAMEORIGIN', $indexContent);
        $this->assertStringContainsString('X-XSS-Protection', $indexContent);
        $this->assertStringContainsString('Referrer-Policy', $indexContent);
        $this->assertStringContainsString('Content-Security-Policy', $indexContent);
        $this->assertStringContainsString('Permissions-Policy', $indexContent);
    }

    /**
     * Test that API routes have ApiAuthMiddleware
     */
    public function testApiRoutesHaveAuthMiddleware(): void
    {
        $indexContent = file_get_contents(dirname(__DIR__, 2) . '/public/index.php');

        // All /api/v1/ routes should have ApiAuthMiddleware
        preg_match_all("/router->get\('\/api\/v1\/[^']+',\s*\[[^\]]+\],\s*\[([^\]]*)\]\)/", $indexContent, $matches);

        $this->assertNotEmpty($matches[0], 'No API v1 routes found');

        foreach ($matches[1] as $middleware) {
            $this->assertStringContainsString('ApiAuthMiddleware', $middleware,
                'API v1 route missing ApiAuthMiddleware');
        }
    }

    /**
     * Test that CSRF middleware is on all POST routes (except API and public upload)
     */
    public function testPostRoutesHaveCsrf(): void
    {
        $indexContent = file_get_contents(dirname(__DIR__, 2) . '/public/index.php');

        preg_match_all("/router->post\('([^']+)',\s*\[[^\]]+\](?:,\s*\[([^\]]*)\])?\)/", $indexContent, $matches, PREG_SET_ORDER);

        $exemptRoutes = ['/login', '/upload-externo/', '/api/', '/ocr-analise'];

        foreach ($matches as $match) {
            $route = $match[1];
            $middleware = $match[2] ?? '';

            $isExempt = false;
            foreach ($exemptRoutes as $exempt) {
                if (str_contains($route, $exempt)) {
                    $isExempt = true;
                    break;
                }
            }

            if (!$isExempt && !str_contains($route, 'api/v1')) {
                $this->assertStringContainsString('CsrfMiddleware', $middleware,
                    "POST route {$route} missing CsrfMiddleware");
            }
        }
    }
}
