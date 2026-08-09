<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderReplacementRequest;
use App\Services\OrderReplacementService;
use Illuminate\Http\Request;

class ReplacementRequestController extends Controller
{
    public function __construct(
        private readonly OrderReplacementService $replacements,
    ) {}

    public function index(Request $request)
    {
        $query = OrderReplacementRequest::query()
            ->with(['order', 'user', 'orderItem', 'replacementOrder', 'reviewer'])
            ->latest('requested_at');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->whereHas('order', function ($builder) use ($search) {
                $builder
                    ->where('number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (OrderReplacementRequest $item) => array_merge(
                    $this->replacements->toArray($item),
                    [
                        'order' => [
                            'id' => $item->order?->id,
                            'number' => $item->order?->number,
                            'email' => $item->order?->email,
                            'full_name' => $item->order?->full_name,
                            'status' => $item->order?->status,
                        ],
                        'customer' => $item->user ? [
                            'id' => $item->user->id,
                            'name' => $item->user->name,
                            'email' => $item->user->email,
                        ] : null,
                    ],
                ))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(OrderReplacementRequest $replacementRequest)
    {
        $replacementRequest->load(['order.items', 'user', 'orderItem', 'replacementOrder', 'reviewer']);

        return response()->json([
            'data' => array_merge($this->replacements->toArray($replacementRequest), [
                'order' => [
                    'id' => $replacementRequest->order?->id,
                    'number' => $replacementRequest->order?->number,
                    'email' => $replacementRequest->order?->email,
                    'full_name' => $replacementRequest->order?->full_name,
                    'status' => $replacementRequest->order?->status,
                    'delivered_at' => $replacementRequest->order?->delivered_at?->toIso8601String(),
                ],
            ]),
        ]);
    }

    public function approve(Request $request, OrderReplacementRequest $replacementRequest)
    {
        $result = $this->replacements->approveAndFulfill($replacementRequest, $request->user());

        return response()->json([
            'message' => 'Replacement approved and fulfillment queued.',
            'data' => $this->replacements->toArray($result),
        ]);
    }

    public function reject(Request $request, OrderReplacementRequest $replacementRequest)
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $result = $this->replacements->reject(
            $replacementRequest,
            $request->user(),
            $validated['rejection_reason'],
        );

        return response()->json([
            'message' => 'Replacement request rejected.',
            'data' => $this->replacements->toArray($result),
        ]);
    }
}
