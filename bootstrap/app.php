<?php

use App\Helpers\ApiResponse;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsCustomer;
use App\Http\Middleware\EnsureUserIsProvider;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'customer' => EnsureUserIsCustomer::class,
            'provider' => EnsureUserIsProvider::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception) {
            return ApiResponse::error('Validation failed.', $exception->errors(), 422);
        });

        $exceptions->render(function (AuthenticationException $exception) {
            return ApiResponse::error($exception->getMessage() ?: 'Unauthenticated.', [], 401);
        });

        $exceptions->render(function (ModelNotFoundException $exception) {
            return ApiResponse::error('Resource not found.', [], 404);
        });

        $exceptions->render(function (HttpExceptionInterface $exception) {
            return ApiResponse::error($exception->getMessage() ?: 'HTTP request failed.', [], $exception->getStatusCode());
        });

        $exceptions->render(function (Throwable $exception) {
            report($exception);

            return ApiResponse::error(
                config('app.debug') ? $exception->getMessage() : 'Something went wrong.',
                [],
                500
            );
        });
    })->create();
