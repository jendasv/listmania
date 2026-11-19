<?php

use App\Exceptions\ApiException;
use App\Exceptions\Domain\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every API error has the same shape as Laravel's own validation errors:
        // { message: string, errors?: { field: string[] } }. Framework exceptions
        // (404, 405, 429, ValidationException...) already render that way for
        // JSON requests — only the app's own exception types need mapping here.
        $isApi = fn (Request $request): bool => $request->expectsJson() || $request->is('api/*');

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi): ?JsonResponse {
            return $isApi($request) ? new JsonResponse(['message' => 'Unauthenticated.'], 401) : null;
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($isApi): ?JsonResponse {
            return $isApi($request)
                ? new JsonResponse(['message' => $e->getMessage() ?: 'This action is not authorized.'], 403)
                : null;
        });

        $exceptions->render(function (ApiException $e): JsonResponse {
            $body = ['message' => $e->getMessage()];

            if ($e instanceof ValidationException && $e->getDetails() !== []) {
                $body['errors'] = array_map(fn (string $message): array => [$message], $e->getDetails());
            }

            return new JsonResponse($body, $e->getStatusCode());
        });
    })->create();
