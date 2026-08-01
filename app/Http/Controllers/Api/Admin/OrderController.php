<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminOrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Services\InvoiceService;
use App\Support\GstState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices)
    {
    }

    private const STATUSES = ['AwaitingPayment', 'Processing', 'Packed', 'Shipped', 'Delivered', 'Cancelled'];

    private const ADDRESS_FIELDS = [
        'full_name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
    ];

    private const COURIER_FIELDS = [
        'courier_partner',
        'awb_number',
        'tracking_number',
        'dispatched_at',
        'expected_delivery_at',
    ];

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

    public function invoice(Order $order)
    {
        return $this->invoices->streamPdf($order);
    }

    public function update(Request $request, Order $order)
    {
        if ($request->has('state')) {
            $request->merge([
                'state' => GstState::normalize((string) $request->input('state')) ?? $request->input('state'),
            ]);
        }

        $validated = $request->validate([
            'status' => ['sometimes', 'required', 'string', Rule::in(self::STATUSES)],
            'payment_status' => ['sometimes', 'required', 'string', Rule::in(['paid'])],
            'full_name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'email', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:40'],
            'address' => ['sometimes', 'required', 'string', 'max:255'],
            'city' => ['sometimes', 'required', 'string', 'max:120'],
            'state' => ['sometimes', 'required', 'string', 'max:120'],
            'postal_code' => ['sometimes', 'required', 'string', 'max:30'],
            'courier_partner' => ['sometimes', 'nullable', 'string', 'max:120'],
            'awb_number' => ['sometimes', 'nullable', 'string', 'max:120'],
            'tracking_number' => ['sometimes', 'nullable', 'string', 'max:120'],
            'dispatched_at' => ['sometimes', 'nullable', 'date'],
            'expected_delivery_at' => ['sometimes', 'nullable', 'date'],
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

        foreach (self::ADDRESS_FIELDS as $field) {
            if (array_key_exists($field, $validated)) {
                $order->{$field} = $validated[$field];
            }
        }

        foreach (self::COURIER_FIELDS as $field) {
            if (array_key_exists($field, $validated)) {
                $order->{$field} = $validated[$field];
            }
        }

        $order->save();
        $order->load(['items', 'user']);

        return new AdminOrderResource($order);
    }

    public function destroy(Order $order)
    {
        DB::transaction(function () use ($order) {
            $order->loadMissing('items');

            if ($this->shouldRestoreStock($order)) {
                foreach ($order->items as $item) {
                    if (! $item->product_id || $item->quantity < 1) {
                        continue;
                    }

                    Product::query()
                        ->whereKey($item->product_id)
                        ->increment('stock', (int) $item->quantity);
                }
            }

            $order->delete();
        });

        return response()->json(['ok' => true]);
    }

    private function shouldRestoreStock(Order $order): bool
    {
        if ($order->payment_method === 'cod') {
            return true;
        }

        return $order->payment_status === 'paid';
    }
}
