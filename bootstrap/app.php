<?php

// bootstrap/app.php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {

        /*
        |----------------------------------------------------------------------
        | Model Not Found → 404
        |----------------------------------------------------------------------
        */
        $exceptions->render(function (ModelNotFoundException $e): JsonResponse {
            return response()->json([
                'error'   => 'Resource not found.',
                'message' => $e->getMessage(),
            ], 404);
        });

        /*
        |----------------------------------------------------------------------
        | Not Found HTTP → 404
        |----------------------------------------------------------------------
        */
        $exceptions->render(function (NotFoundHttpException $e): JsonResponse {
            return response()->json([
                'error'   => 'Endpoint not found.',
                'message' => $e->getMessage(),
            ], 404);
        });

        /*
        |----------------------------------------------------------------------
        | Validation → 422
        |----------------------------------------------------------------------
        */
        $exceptions->render(function (ValidationException $e): JsonResponse {
            return response()->json([
                'error'   => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        });
    })->create();
