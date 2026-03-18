<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class HealthController extends Controller
{
    /**
     * Health check endpoint
     */
    public function index(Request $request)
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'checks' => [],
        ];

        // Database check
        try {
            DB::connection()->getPdo();
            $health['checks']['database'] = [
                'status' => 'healthy',
                'connection' => config('database.default'),
            ];
        } catch (\Exception $e) {
            $health['checks']['database'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
            $health['status'] = 'degraded';
        }

        // Redis check
        try {
            Redis::ping();
            $health['checks']['redis'] = [
                'status' => 'healthy',
            ];
        } catch (\Exception $e) {
            $health['checks']['redis'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
            $health['status'] = 'degraded';
        }

        // Cache check
        try {
            Cache::put('health_check', 'ok', 60);
            $cacheResult = Cache::get('health_check');
            $health['checks']['cache'] = [
                'status' => $cacheResult === 'ok' ? 'healthy' : 'unhealthy',
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            $health['checks']['cache'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
            $health['status'] = 'degraded';
        }

        // Queue check
        try {
            $queueSize = \Illuminate\Support\Facades\Queue::size();
            $health['checks']['queue'] = [
                'status' => $queueSize < 100 ? 'healthy' : 'degraded',
                'size' => $queueSize,
                'connection' => config('queue.default'),
            ];
            
            if ($queueSize >= 100) {
                $health['status'] = 'degraded';
            }
        } catch (\Exception $e) {
            $health['checks']['queue'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
            $health['status'] = 'degraded';
        }

        // Storage check
        try {
            $testFile = 'health_check_' . time() . '.tmp';
            \Storage::put($testFile, 'test');
            \Storage::delete($testFile);
            $health['checks']['storage'] = [
                'status' => 'healthy',
                'driver' => config('filesystems.default'),
            ];
        } catch (\Exception $e) {
            $health['checks']['storage'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
            $health['status'] = 'degraded';
        }

        // Memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $health['system'] = [
            'memory_usage' => [
                'current' => $this->formatBytes($memoryUsage),
                'limit' => $memoryLimit,
                'percentage' => $this->getMemoryPercentage($memoryUsage, $memoryLimit),
            ],
            'disk_usage' => $this->getDiskUsage(),
        ];

        // Determine HTTP status code
        $statusCode = $health['status'] === 'healthy' ? 200 : 503;

        return response()->json($health, $statusCode);
    }

    /**
     * Detailed health check with more system information
     */
    public function detailed(Request $request)
    {
        $health = $this->index($request);
        $data = $health->getData(true);

        // Add more detailed information
        $data['system']['php_version'] = PHP_VERSION;
        $data['system']['laravel_version'] = app()->version();
        $data['system']['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
        $data['system']['timezone'] = config('app.timezone');
        $data['system']['uptime'] = $this->getServerUptime();
        
        // Database information
        try {
            $data['database'] = [
                'version' => DB::select('SELECT VERSION() as version')[0]->version,
                'connections' => DB::select('SHOW STATUS LIKE "Threads_connected"')[0]->Value,
                'queries_per_second' => $this->getQueriesPerSecond(),
            ];
        } catch (\Exception $e) {
            $data['database'] = ['error' => $e->getMessage()];
        }

        // Cache statistics
        try {
            if (config('cache.default') === 'redis') {
                $info = Redis::info();
                $data['cache']['redis'] = [
                    'used_memory' => $this->formatBytes($info['used_memory']),
                    'used_memory_peak' => $this->formatBytes($info['used_memory_peak']),
                    'connected_clients' => $info['connected_clients'],
                    'total_commands_processed' => $info['total_commands_processed'],
                ];
            }
        } catch (\Exception $e) {
            $data['cache']['error'] = $e->getMessage();
        }

        return response()->json($data, $health->getStatusCode());
    }

    /**
     * Simple ping endpoint for load balancers
     */
    public function ping()
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
        ]);
    }

    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function getMemoryPercentage($current, $limit)
    {
        $limitBytes = $this->parseMemoryLimit($limit);
        return round(($current / $limitBytes) * 100, 2);
    }

    private function parseMemoryLimit($limit)
    {
        $limit = strtolower($limit);
        $multiplier = 1;
        
        if (strpos($limit, 'g') !== false) {
            $multiplier = 1024 * 1024 * 1024;
        } elseif (strpos($limit, 'm') !== false) {
            $multiplier = 1024 * 1024;
        } elseif (strpos($limit, 'k') !== false) {
            $multiplier = 1024;
        }
        
        return (int) $limit * $multiplier;
    }

    private function getDiskUsage()
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        $used = $total - $free;
        
        return [
            'total' => $this->formatBytes($total),
            'used' => $this->formatBytes($used),
            'free' => $this->formatBytes($free),
            'percentage' => round(($used / $total) * 100, 2),
        ];
    }

    private function getServerUptime()
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                'load_1min' => $load[0] ?? 0,
                'load_5min' => $load[1] ?? 0,
                'load_15min' => $load[2] ?? 0,
            ];
        }
        
        return ['error' => 'Uptime information not available'];
    }

    private function getQueriesPerSecond()
    {
        try {
            $status = DB::select('SHOW GLOBAL STATUS LIKE "Queries"')[0];
            $uptime = DB::select('SHOW GLOBAL STATUS LIKE "Uptime"')[0];
            
            return round($status->Value / $uptime->Value, 2);
        } catch (\Exception $e) {
            return 0;
        }
    }
}
