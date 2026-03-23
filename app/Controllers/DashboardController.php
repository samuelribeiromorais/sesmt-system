<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    private const CACHE_TTL = 300;

    public function index(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $cacheKey = 'dashboard_data';
        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            $cached['pageTitle'] = 'Dashboard';
            $this->view('dashboard/index', $cached);
            return;
        }

        $service = new DashboardService();
        $data = $service->getAllData();
        $data['pageTitle'] = 'Dashboard';

        $cacheData = $data;
        unset($cacheData['pageTitle']);
        $this->setCache($cacheKey, $cacheData);

        $this->view('dashboard/index', $data);
    }

    public function dashboardData(): void
    {
        RoleMiddleware::requireAdminOrSesmt();

        $service = new DashboardService();
        $this->json($service->getChartData());
    }

    // ========================================================================
    // Cache helpers
    // ========================================================================

    private function getCachePath(string $key): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/cache';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return $dir . '/' . md5($key) . '.cache';
    }

    private function getCache(string $key): ?array
    {
        $file = $this->getCachePath($key);
        if (!file_exists($file)) return null;
        $data = unserialize(file_get_contents($file));
        if (!is_array($data) || !isset($data['expires']) || $data['expires'] < time()) {
            @unlink($file);
            return null;
        }
        return $data['payload'];
    }

    private function setCache(string $key, array $payload): void
    {
        file_put_contents($this->getCachePath($key), serialize([
            'expires' => time() + self::CACHE_TTL,
            'payload' => $payload,
        ]), LOCK_EX);
    }

    public static function clearCache(): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/cache';
        $file = $dir . '/' . md5('dashboard_data') . '.cache';
        if (file_exists($file)) @unlink($file);
    }
}
