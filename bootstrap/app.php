<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = __DIR__.'/../app/'.str_replace('\\', '/', substr($class, 4)).'.php';
        if (file_exists($file)) {
            require_once $file;
        }
    } elseif (str_starts_with($class, 'Database\\Factories\\')) {
        $file = __DIR__.'/../database/factories/'.str_replace('\\', '/', substr($class, 19)).'.php';
        if (file_exists($file)) {
            require_once $file;
        }
    } elseif (str_starts_with($class, 'Database\\Seeders\\')) {
        $file = __DIR__.'/../database/seeders/'.str_replace('\\', '/', substr($class, 17)).'.php';
        if (file_exists($file)) {
            require_once $file;
        }
    } elseif (str_starts_with($class, 'Tests\\')) {
        $file = __DIR__.'/../tests/'.str_replace('\\', '/', substr($class, 6)).'.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
}, true, true);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/telegram/webhook',
            'telegram/mini-app-auth',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (SymfonyResponse $response, Throwable $exception, Request $request): SymfonyResponse {
            $status = $response->getStatusCode();

            if ($status < 400 || $request->is('api/*') || $request->expectsJson()) {
                return $response;
            }

            try {
                $isAuthenticated = auth()->check();
            } catch (Throwable) {
                $isAuthenticated = false;
            }

            try {
                $rendered = response()->view('errors.error', [
                    'status' => $status,
                    'isAuthenticated' => $isAuthenticated,
                ], $status);

                foreach (['Allow', 'Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining'] as $header) {
                    if ($response->headers->has($header)) {
                        $rendered->headers->set($header, $response->headers->get($header));
                    }
                }

                foreach ($response->headers->getCookies() as $cookie) {
                    $rendered->headers->setCookie($cookie);
                }

                return $rendered;
            } catch (Throwable) {
                return $response;
            }
        });
    })->create();
