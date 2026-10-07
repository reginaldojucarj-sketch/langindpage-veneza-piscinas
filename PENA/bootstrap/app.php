<?php

use App\Exceptions\ContentUnavailable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/admin/login');
        $middleware->redirectUsersTo('/admin');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (QueryException $error, Request $request) {
            if (! $request->is('api/admin/*') && ! $request->is('openapi/*') && ! $request->is('docs')) {
                return null;
            }
            $correlationId = (string) Str::uuid();
            Log::error('Falha no banco da API administrativa.', [
                'correlation_id' => $correlationId, 'sqlstate' => $error->errorInfo[0] ?? 'unknown',
            ]);

            return response()->json([
                'message' => 'API administrativa temporariamente indisponível.',
                'correlation_id' => $correlationId,
            ], 503)->header('X-Correlation-ID', $correlationId);
        });
        $exceptions->render(function (ContentUnavailable $exception, Request $request) {
            $correlationId = (string) Str::uuid();
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'O recurso editorial está temporariamente indisponível.',
                    'correlation_id' => $correlationId,
                ], 503)->header('X-Correlation-ID', $correlationId);
            }

            return response()->view('errors.503', ['exception' => null, 'correlationId' => $correlationId], 503)
                ->header('X-Correlation-ID', $correlationId);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
