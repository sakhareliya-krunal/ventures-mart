<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminOrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    private const STATUSES = ['AwaitingPayment', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];

    public function index(Request $request)
    {
        $query = Order::query()->with(['items', 'user'])->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return AdminOrderResource::collection(
            $query->paginate(min((int) $request->integer('per_page', 20), 100))
        );
    }

    public function show(Order $order)
    {
        $order->load(['items', 'user']);

        return new AdminOrderResource($order);
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'required', 'string', Rule::in(self::STATUSES)],
            'payment_status' => ['sometimes', 'required', 'string', Rule::in(['paid'])],
        ]);

        if ($validated === []) {
            return response()->json([
                'message' => 'Nothing to update.',
            ], 422);
        }

        if (array_key_exists('payment_status', $validated) && $validated['payment_status'] === 'paid') {
            if ($order->payment_method !== 'cod') {
                return response()->json([
                    'message' => 'Only COD orders can be marked paid manually.',
                ], 422);
            }

            if ($order->payment_status !== 'paid') {
                $order->forceFill([
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ]);
            }
        }

        if (array_key_exists('status', $validated)) {
            $order->status = $validated['status'];

            if (
                $validated['status'] === 'Delivered'
                && $order->payment_method === 'cod'
                && $order->payment_status === 'pending'
            ) {
                $order->payment_status = 'paid';
                $order->paid_at = now();
            }
        }

        $order->save();
        $order->load(['items', 'user']);

        return new AdminOrderResource($order);
    }
}
