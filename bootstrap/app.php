<?php

use App\ErrorReporter;
use App\Http\Middleware\RouteMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Lottery;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'route.permission' => RouteMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->dontReportDuplicates();

        $exceptions->throttle(function ($e) {
            return Lottery::odds(1, 10);
        });

        $exceptions->report(function (Throwable $e) {
            try {
                // Inisialisasi data default
                $user_id = null;
                $ip = '127.0.0.1';
                $method = 'CLI';
                $url = 'Console/Command';

                // Ambil data hanya jika diakses via web/http
                if (! app()->runningInConsole()) {
                    $user_id = auth()->check() ? auth()->id() : null;
                    $ip = Request::ip();
                    $method = Request::method();
                    $url = Request::fullUrl();
                }

                $hash = md5($e->getMessage().$e->getFile().$e->getLine());

                $hash = md5($e->getMessage().$e->getFile().$e->getLine());

                $key = 'err_'.$hash;

                // cek hash pernah dikirim belum agar tidak spam
                if (Cache::has($key)) {
                    return;
                }
                // set lock selama 2 menit
                Cache::put($key, true, 120);

                ErrorReporter::send([
                    'hash_error' => $hash,
                    'app_name' => config('app.name'),
                    'env' => app()->environment(),
                    'user_id' => $user_id,
                    'ip' => $ip,
                    'method' => $method,
                    'url' => $url,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'created_at' => now()->toDateTimeString(),
                ]);
            } catch (Throwable $ex) {
                // Biarkan kosong agar tidak looping error jika reporter gagal
            }
        });

    })->create();
