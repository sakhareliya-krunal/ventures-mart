<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderReplacementService;
use Illuminate\Http\Request;

class OrderReplacementController extends Controller
{
    public function __construct(
        private readonly OrderReplacementService $replacements,
    ) {}

    public function store(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:damaged,defective,incorrect'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'order_item_id' => ['nullable', 'integer'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['file', 'image', 'max:4096'],
        ]);

        $replacement = $this->replacements->request($order, $request->user(), [
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
            'order_item_id' => $validated['order_item_id'] ?? null,
            'photos' => $request->file('photos', []),
        ]);

        return response()->json([
            'message' => 'Replacement request submitted.',
            'data' => $this->replacements->toArray($replacement),
        ], 201);
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless(
            $order->user_id === $user->id || $order->email === $user->email,
            403
        );
    }
}
