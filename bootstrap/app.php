<?php

use App\Services\ApplicationErrorRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SPA has no named "login" route; never call route('login') (that 500s).
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return '/login';
        });

        $middleware->statefulApi();

        $middleware->validateCsrfTokens(except: [
            'api/razorpay/webhook',
        ]);

        // Guest cart/wishlist need a session even when Origin checks miss (same-origin SPA).
        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([ValidationException::class]);

        $exceptions->reportable(function (Throwable $e): void {
            app(ApplicationErrorRecorder::class)->recordThrowable($e);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return null;
            }

            $status = 500;
            $code = 'server_error';
            $message = 'Something went wrong. Please try again.';

            if ($e instanceof TokenMismatchException) {
                $status = 419;
                $code = 'session_expired';
                $message = 'Your session expired. Please refresh and try again.';
            } elseif ($e instanceof AuthenticationException) {
                $status = 401;
                $code = 'unauthenticated';
                $message = 'Please sign in to continue.';
            } elseif ($e instanceof AuthorizationException) {
                $status = 403;
                $code = 'forbidden';
                $message = "You don't have permission to do that.";
            } elseif ($e instanceof TooManyRequestsHttpException) {
                $status = 429;
                $code = 'too_many_requests';
                $message = 'Too many requests. Please wait a moment and try again.';
            } elseif ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $code = match ($status) {
                    403 => 'forbidden',
                    404 => 'not_found',
                    419 => 'session_expired',
                    429 => 'too_many_requests',
                    503 => 'service_unavailable',
                    default => $status >= 500 ? 'server_error' : 'http_error',
                };
                $message = match ($status) {
                    403 => "You don't have permission to do that.",
                    404 => 'The requested resource was not found.',
                    419 => 'Your session expired. Please refresh and try again.',
                    429 => 'Too many requests. Please wait a moment and try again.',
                    503 => 'The service is temporarily unavailable. Please try again soon.',
                    default => $status >= 500
                        ? 'Something went wrong. Please try again.'
                        : (config('app.debug') ? ($e->getMessage() ?: $message) : $message),
                };
            } elseif (config('app.debug') && $e->getMessage() !== '') {
                $message = $e->getMessage();
            }

            $payload = [
                'message' => $message,
                'code' => $code,
            ];

            if (config('app.debug')) {
                $payload['exception'] = $e::class;
                $payload['file'] = $e->getFile();
                $payload['line'] = $e->getLine();
            }

            return response()->json($payload, $status);
        });
    })->create();
