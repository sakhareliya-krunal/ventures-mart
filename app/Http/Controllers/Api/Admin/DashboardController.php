<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Http\Resources\AdminOrderResource;

class DashboardController extends Controller
{
    public function stats()
    {
        $recentOrders = Order::query()
            ->with(['items', 'user'])
            ->latest()
            ->limit(8)
            ->get();

        return response()->json([
            'orders_count' => Order::query()->count(),
            'products_count' => Product::query()->count(),
            'users_count' => User::query()->count(),
            'contact_messages_count' => ContactMessage::query()->count(),
            'revenue_total' => (float) Order::query()->sum('total'),
            'recent_orders' => AdminOrderResource::collection($recentOrders),
        ]);
    }
}
