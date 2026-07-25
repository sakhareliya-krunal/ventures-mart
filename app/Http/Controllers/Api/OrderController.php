<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

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
        $order = $this->orders->create($request, $request->validated());

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    public function show(Request $request, Order $order)
    {
        if ($request->user()) {
            abort_unless(
                $order->user_id === $request->user()->id || $order->email === $request->user()->email,
                403
            );
        } else {
            abort(401);
        }

        return new OrderResource($order->load('items'));
    }
}
