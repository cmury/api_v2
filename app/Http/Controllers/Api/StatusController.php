<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\DataDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class StatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = ['ok' => false, 'connection' => DataDatabase::name()];

        try {
            DB::connection(DataDatabase::name())->select('select 1');
            $database['ok'] = true;
        } catch (Throwable $e) {
            $database['error'] = $e->getMessage();
        }

        return response()->json([
            'app' => config('app.name'),
            'laravel' => app()->version(),
            'database' => $database,
        ]);
    }
}
