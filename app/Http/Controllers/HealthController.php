<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $failing = [];

        try {
            DB::connection()->getPdo();
            DB::select('select 1');
        } catch (Throwable) {
            $failing[] = 'db';
        }

        try {
            Redis::ping();
        } catch (Throwable) {
            $failing[] = 'redis';
        }

        $lastProcessed = Redis::get('queue:last_processed_at');

        if ($lastProcessed === null || now()->timestamp - (int) $lastProcessed > 300) {
            $failing[] = 'queue';
        }

        if ($failing === []) {
            return response()->json(['status' => 'ok'], 200);
        }

        return response()->json([
            'status' => 'degraded',
            'failing' => $failing,
        ], 503);
    }
}
