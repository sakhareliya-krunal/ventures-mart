<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationError;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApplicationErrorController extends Controller
{
    public function summary()
    {
        try {
            $todayStart = Carbon::today();

            $top = ApplicationError::query()
                ->orderByDesc('occurrence_count')
                ->orderByDesc('last_seen_at')
                ->limit(5)
                ->get(['uuid', 'message', 'category', 'status', 'occurrence_count', 'last_seen_at']);

            return response()->json([
                'total' => ApplicationError::query()->count(),
                'unresolved' => ApplicationError::query()->unresolved()->count(),
                'today' => ApplicationError::query()->where('last_seen_at', '>=', $todayStart)->count(),
                'new' => ApplicationError::query()->where('status', 'new')->count(),
                'top' => $top->map(fn (ApplicationError $error) => [
                    'uuid' => $error->uuid,
                    'message' => $error->message,
                    'category' => $error->category,
                    'status' => $error->status,
                    'occurrence_count' => $error->occurrence_count,
                    'last_seen_at' => $error->last_seen_at?->toIso8601String(),
                ]),
            ]);
        } catch (QueryException) {
            return $this->unavailableResponse();
        }
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in([...ApplicationError::STATUSES, 'open', 'unresolved', 'all'])],
            'resolved' => ['nullable', 'in:open,resolved,all'],
            'category' => ['nullable', Rule::in(ApplicationError::CATEGORIES)],
            'level' => ['nullable', 'string', 'max:32'],
            'user_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:200'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sort' => ['nullable', 'in:last_seen_at,occurrence_count,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $status = $validated['status'] ?? $validated['resolved'] ?? 'unresolved';
            $sort = $validated['sort'] ?? 'last_seen_at';
            $direction = $validated['direction'] ?? 'desc';

            $query = ApplicationError::query()->with('user');

            if ($status === 'open' || $status === 'unresolved') {
                $query->unresolved();
            } elseif ($status !== 'all') {
                $query->where('status', $status);
            }

            if (! empty($validated['category'])) {
                $query->where('category', $validated['category']);
            }

            if (! empty($validated['level'])) {
                $query->where('level', $validated['level']);
            }

            if (! empty($validated['user_id'])) {
                $query->where('user_id', $validated['user_id']);
            }

            if (! empty($validated['from'])) {
                $query->where('last_seen_at', '>=', Carbon::parse($validated['from'])->startOfDay());
            }

            if (! empty($validated['to'])) {
                $query->where('last_seen_at', '<=', Carbon::parse($validated['to'])->endOfDay());
            }

            if (! empty($validated['q'])) {
                $term = '%'.$validated['q'].'%';
                $query->where(function ($builder) use ($term) {
                    $builder
                        ->where('message', 'like', $term)
                        ->orWhere('exception_class', 'like', $term)
                        ->orWhere('file', 'like', $term)
                        ->orWhere('url', 'like', $term)
                        ->orWhere('route', 'like', $term)
                        ->orWhere('uuid', 'like', $term);
                });
            }

            $errors = $query->orderBy($sort, $direction)->paginate($validated['per_page'] ?? 20);

            return response()->json([
                'data' => $errors->getCollection()->map(fn (ApplicationError $error) => $this->summaryRow($error)),
                'meta' => [
                    'current_page' => $errors->currentPage(),
                    'last_page' => $errors->lastPage(),
                    'per_page' => $errors->perPage(),
                    'total' => $errors->total(),
                    'open_count' => ApplicationError::query()->unresolved()->count(),
                ],
            ]);
        } catch (QueryException) {
            return $this->unavailableResponse();
        }
    }

    public function show(ApplicationError $error)
    {
        $error->loadMissing('user');

        return response()->json([
            'data' => $this->detail($error),
        ]);
    }

    public function update(Request $request, ApplicationError $error)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(ApplicationError::STATUSES)],
            'resolved' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('status', $validated) && $validated['status']) {
            $error->markStatus($validated['status']);
        } elseif (array_key_exists('resolved', $validated)) {
            $error->markStatus($validated['resolved'] ? 'resolved' : 'new');
        }

        return response()->json([
            'data' => $this->detail($error->fresh()->loadMissing('user')),
        ]);
    }

    public function destroy(ApplicationError $error)
    {
        $error->delete();

        return response()->json(['ok' => true]);
    }

    public function destroyMany(Request $request)
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'in:resolved,ignored,all'],
            'older_than_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        try {
            $query = ApplicationError::query();

            $scope = $validated['scope'] ?? null;
            if ($scope === 'resolved') {
                $query->where('status', 'resolved');
            } elseif ($scope === 'ignored') {
                $query->where('status', 'ignored');
            }

            if (! empty($validated['older_than_days'])) {
                $query->where('last_seen_at', '<', now()->subDays($validated['older_than_days']));
            }

            if ($scope === null && empty($validated['older_than_days'])) {
                $query->where('status', 'resolved');
            }

            $deleted = $query->delete();

            return response()->json([
                'ok' => true,
                'deleted' => $deleted,
            ]);
        } catch (QueryException) {
            return $this->unavailableResponse();
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    private function unavailableResponse()
    {
        return response()->json([
            'message' => 'Error logs are not ready. Run database migrations.',
            'code' => 'error_logs_unavailable',
        ], 503);
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryRow(ApplicationError $error): array
    {
        return [
            'uuid' => $error->uuid,
            'fingerprint' => $error->fingerprint,
            'occurrence_count' => $error->occurrence_count,
            'category' => $error->category,
            'status' => $error->status,
            'level' => $error->level,
            'message' => $error->message,
            'exception_class' => $error->exception_class,
            'file' => $error->file,
            'line' => $error->line,
            'url' => $error->url,
            'route' => $error->route,
            'method' => $error->method,
            'ip' => $error->ip,
            'user_id' => $error->user_id,
            'user' => $error->user ? [
                'id' => $error->user->id,
                'name' => $error->user->name,
                'email' => $error->user->email,
            ] : null,
            'resolved_at' => $error->resolved_at?->toIso8601String(),
            'first_seen_at' => $error->first_seen_at?->toIso8601String(),
            'last_seen_at' => $error->last_seen_at?->toIso8601String(),
            'created_at' => $error->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(ApplicationError $error): array
    {
        return [
            ...$this->summaryRow($error),
            'trace' => $error->trace,
            'context' => $error->context,
            'request' => $error->request,
            'user_agent' => $error->user_agent,
        ];
    }
}
