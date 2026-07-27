<?php

namespace App\Services;

use App\Models\ApplicationError;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ApplicationErrorRecorder
{
    private const TRACE_LIMIT = 12000;

    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'access_token',
        'refresh_token',
        'authorization',
        'cookie',
        'secret',
        'credential',
        'credentials',
        'card',
        'cvv',
    ];

    public function recordThrowable(Throwable $exception, array $context = [], string $category = 'exception'): ?ApplicationError
    {
        if ($exception instanceof ValidationException) {
            return null;
        }

        try {
            if (! app(ExceptionHandler::class)->shouldReport($exception)) {
                return null;
            }
        } catch (Throwable) {
            // Continue best-effort if handler lookup fails.
        }

        if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500) {
            $category = $category === 'exception' ? 'http' : $category;
        }

        $fingerprint = $this->hash(
            $exception::class.'|'.$this->normalizeMessage($exception->getMessage())
        );

        return $this->persist([
            'fingerprint' => $fingerprint,
            'category' => $category,
            'level' => 'error',
            'message' => Str::limit($exception->getMessage() ?: $exception::class, 2000),
            'exception_class' => $exception::class,
            'file' => $exception->getFile() ?: null,
            'line' => $exception->getLine() ?: null,
            'trace' => Str::limit($exception->getTraceAsString(), self::TRACE_LIMIT),
            'context' => $this->scrub($context) ?: null,
        ]);
    }

    public function recordLog(string $level, string $message, array $context = []): ?ApplicationError
    {
        $normalized = strtolower($level);

        if (! in_array($normalized, ['error', 'critical', 'alert', 'emergency'], true)) {
            return null;
        }

        if ($this->looksLikeValidation($message, $context)) {
            return null;
        }

        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            return $this->recordThrowable(
                $context['exception'],
                array_diff_key($context, ['exception' => true]),
                'system'
            );
        }

        $fingerprint = $this->hash($normalized.'|'.$this->normalizeMessage($message));

        return $this->persist([
            'fingerprint' => $fingerprint,
            'category' => 'system',
            'level' => $normalized === 'error' ? 'error' : 'critical',
            'message' => Str::limit($message ?: 'Log error', 2000),
            'exception_class' => null,
            'file' => null,
            'line' => null,
            'trace' => null,
            'context' => $this->scrub(array_merge($context, ['log_level' => $normalized])) ?: null,
        ]);
    }

    public function recordJobFailure(string $jobName, Throwable $exception, array $context = []): ?ApplicationError
    {
        return $this->recordThrowable($exception, array_merge($context, [
            'job' => $jobName,
        ]), 'job');
    }

    public function recordPaymentFailure(string $message, array $context = [], ?Throwable $exception = null): ?ApplicationError
    {
        if ($exception) {
            return $this->recordThrowable($exception, array_merge($context, [
                'payment_message' => $message,
            ]), 'payment');
        }

        $fingerprint = $this->hash('payment|'.$this->normalizeMessage($message));

        return $this->persist([
            'fingerprint' => $fingerprint,
            'category' => 'payment',
            'level' => 'error',
            'message' => Str::limit($message ?: 'Payment failure', 2000),
            'exception_class' => null,
            'file' => null,
            'line' => null,
            'trace' => null,
            'context' => $this->scrub($context) ?: null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persist(array $payload): ?ApplicationError
    {
        try {
            $request = Request::instance();
            $fingerprint = (string) ($payload['fingerprint'] ?? '');

            $existing = ApplicationError::query()
                ->where('fingerprint', $fingerprint)
                ->whereNotIn('status', ['resolved', 'ignored'])
                ->orderByDesc('last_seen_at')
                ->first();

            $requestMeta = [
                'url' => $request?->fullUrl(),
                'route' => $request?->route()?->getName(),
                'method' => $request?->method(),
                'ip' => $request?->ip(),
                'user_id' => Auth::id(),
                'user_agent' => Str::limit((string) $request?->userAgent(), 1000) ?: null,
                'request' => $this->scrubRequestInput($request),
            ];

            if ($existing) {
                $existing->fill([
                    'occurrence_count' => $existing->occurrence_count + 1,
                    'last_seen_at' => now(),
                    'url' => $requestMeta['url'] ?: $existing->url,
                    'route' => $requestMeta['route'] ?: $existing->route,
                    'method' => $requestMeta['method'] ?: $existing->method,
                    'ip' => $requestMeta['ip'] ?: $existing->ip,
                    'user_id' => $requestMeta['user_id'] ?: $existing->user_id,
                    'user_agent' => $requestMeta['user_agent'] ?: $existing->user_agent,
                    'request' => $requestMeta['request'] ?: $existing->request,
                    'context' => $payload['context'] ?? $existing->context,
                    'level' => $payload['level'] ?? $existing->level,
                ])->save();

                return $existing->fresh();
            }

            return ApplicationError::query()->create([
                ...$payload,
                ...$requestMeta,
                'status' => 'new',
                'occurrence_count' => 1,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function scrubRequestInput($request): ?array
    {
        if (! $request) {
            return null;
        }

        try {
            $input = $request->except(self::SENSITIVE_KEYS);
            $clean = $this->scrub($input);

            return $clean === [] ? null : $clean;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function scrub(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            $keyString = strtolower((string) $key);

            if (collect(self::SENSITIVE_KEYS)->contains(fn ($needle) => str_contains($keyString, $needle))) {
                $clean[$key] = '[redacted]';
                continue;
            }

            if ($value instanceof Throwable) {
                $clean[$key] = $value::class.': '.$value->getMessage();
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->scrub($value);
                continue;
            }

            if (is_object($value)) {
                $clean[$key] = method_exists($value, '__toString')
                    ? (string) $value
                    : $value::class;
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function looksLikeValidation(string $message, array $context): bool
    {
        if (isset($context['exception']) && $context['exception'] instanceof ValidationException) {
            return true;
        }

        if (isset($context['errors']) && is_array($context['errors'])) {
            return true;
        }

        return str_contains(strtolower($message), 'validation');
    }

    private function normalizeMessage(string $message): string
    {
        return preg_replace('/\d+/', '#', Str::lower(trim($message))) ?: '';
    }

    private function hash(string $value): string
    {
        return hash('xxh128', $value);
    }
}
