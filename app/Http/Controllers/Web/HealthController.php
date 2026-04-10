<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthController extends Controller
{
    /**
     * GET /health
     *
     * Returns 200 when every service is reachable, 503 when any one fails.
     * Each service entry is either "ok" or "error: {message}".
     */
    public function __invoke(): JsonResponse
    {
        $services = [
            'database'    => $this->checkDatabase(),
            'redis'       => $this->checkRedis(),
            'meilisearch' => $this->checkMeilisearch(),
            'horizon'     => $this->checkHorizon(),
            'storage'     => $this->checkStorage(),
        ];

        $allOk      = collect($services)->every(fn (string $v) => $v === 'ok');
        $httpStatus = $allOk ? 200 : 503;

        return response()->json([
            'status'    => $allOk ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'services'  => $services,
        ], $httpStatus);
    }

    // ── Service checks ────────────────────────────────────────────────────────

    private function checkDatabase(): string
    {
        try {
            DB::connection()->getPdo();
            return 'ok';
        } catch (Throwable $e) {
            return 'error: ' . $e->getMessage();
        }
    }

    private function checkRedis(): string
    {
        try {
            $pong = Redis::ping();

            // phpredis returns true; predis returns 'PONG'
            if ($pong === true || $pong === 'PONG') {
                return 'ok';
            }

            return 'error: unexpected ping response';
        } catch (Throwable $e) {
            return 'error: ' . $e->getMessage();
        }
    }

    private function checkMeilisearch(): string
    {
        try {
            $host = rtrim((string) config('scout.meilisearch.host', 'http://localhost:7700'), '/');
            $response = Http::timeout(3)->get($host . '/health');

            if ($response->ok()) {
                return 'ok';
            }

            return 'error: HTTP ' . $response->status();
        } catch (Throwable $e) {
            return 'error: ' . $e->getMessage();
        }
    }

    private function checkHorizon(): string
    {
        try {
            // Horizon stores its status in the cache under 'horizon:status'.
            $status = Cache::get('horizon:status', 'inactive');

            // 'running' is healthy; 'paused' or 'inactive' are degraded states.
            if ($status === 'running') {
                return 'ok';
            }

            return 'error: horizon is ' . $status;
        } catch (Throwable $e) {
            return 'error: ' . $e->getMessage();
        }
    }

    private function checkStorage(): string
    {
        try {
            $disk    = Storage::disk('public');
            $probe   = '.health-probe';

            // Write a tiny probe file then read it back to verify read+write.
            $disk->put($probe, 'ok');
            $content = $disk->get($probe);
            $disk->delete($probe);

            if ($content === 'ok') {
                return 'ok';
            }

            return 'error: read-back mismatch';
        } catch (Throwable $e) {
            return 'error: ' . $e->getMessage();
        }
    }
}
