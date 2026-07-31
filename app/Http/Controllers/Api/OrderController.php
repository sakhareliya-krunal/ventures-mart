<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use RuntimeException;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    public function index(Request $request)
    {
        $query = Order::query()->with('items')->latest();

        if ($request->user()) {
            $query->where(function ($builder) use ($request) {
                $builder->where('user_id', $request->user()->id)
                    ->orWhere('email', $request->user()->email);
            });
        } else {
            $ids = $request->session()->get('guest_order_ids', []);
            abort_if(empty($ids), 401, 'Authentication required to view orders.');
            $query->whereIn('id', $ids);
        }

        return OrderResource::collection($query->get());
    }

    public function store(CheckoutRequest $request)
    {
        $validated = $request->validated();

        try {
            $order = $this->orders->create($request, $validated);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'payment_init_failed',
            ], 502);
        }

        $payload = [
            'data' => (new OrderResource($order))->resolve(),
        ];

        if (($validated['payment_method'] ?? null) === 'razorpay') {
            $payload['razorpay'] = $this->orders->razorpayCheckoutPayload($order);
        }

        return response()->json($payload, 201);
    }

    public function verifyPayment(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);

        $validated = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        $order = $this->orders->verifyPayment($request, $order, $validated);

        return new OrderResource($order);
    }

    public function show(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);

        return new OrderResource($order->load('items'));
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
