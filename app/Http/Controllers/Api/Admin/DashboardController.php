<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminOrderResource;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats()
    {
        $todayStart = Carbon::today();
        $weekStart = Carbon::today()->subDays(6);

        $statuses = ['Processing', 'Shipped', 'Delivered', 'Cancelled'];
        $statusCounts = Order::query()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $ordersByStatus = [];
        foreach ($statuses as $status) {
            $ordersByStatus[$status] = (int) ($statusCounts[$status] ?? 0);
        }

        $revenueByDay = Order::query()
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('SUM(total) as total'))
            ->where('status', '!=', 'Cancelled')
            ->where('created_at', '>=', $weekStart->copy()->startOfDay())
            ->groupBy('day')
            ->pluck('total', 'day');

        $revenueLast7Days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->copy()->addDays($i);
            $key = $day->toDateString();
            $revenueLast7Days[] = [
                'date' => $key,
                'label' => $day->format('D'),
                'total' => (float) ($revenueByDay[$key] ?? 0),
            ];
        }

        $recentOrders = Order::query()
            ->with(['items', 'user'])
            ->latest()
            ->limit(8)
            ->get();

        $lowStockProducts = Product::query()
            ->where('stock', '<=', 5)
            ->orderBy('stock')
            ->orderBy('name')
            ->limit(6)
            ->get(['id', 'name', 'sku', 'stock', 'image']);

        $recentMessages = ContactMessage::query()
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'email', 'message', 'created_at']);

        $recentPosts = Post::query()
            ->latest()
            ->limit(4)
            ->get(['id', 'title', 'slug', 'published_at', 'cover_image']);

        $nonCancelled = fn ($query) => $query->where('status', '!=', 'Cancelled');

        return response()->json([
            'orders_count' => Order::query()->count(),
            'products_count' => Product::query()->count(),
            'users_count' => User::query()->count(),
            'customers_count' => User::query()->where('is_admin', false)->count(),
            'contact_messages_count' => ContactMessage::query()->count(),
            'posts_count' => Post::query()->count(),
            'published_posts_count' => Post::query()->published()->count(),
            'categories_count' => Category::query()->count(),
            'orders_today' => Order::query()->where('created_at', '>=', $todayStart)->count(),
            'revenue_today' => (float) Order::query()
                ->where('created_at', '>=', $todayStart)
                ->tap($nonCancelled)
                ->sum('total'),
            'orders_this_week' => Order::query()->where('created_at', '>=', $weekStart)->count(),
            'revenue_this_week' => (float) Order::query()
                ->where('created_at', '>=', $weekStart)
                ->tap($nonCancelled)
                ->sum('total'),
            'revenue_total' => (float) Order::query()->tap($nonCancelled)->sum('total'),
            'orders_by_status' => $ordersByStatus,
            'revenue_last_7_days' => $revenueLast7Days,
            'low_stock_products' => $lowStockProducts,
            'recent_messages' => $recentMessages,
            'recent_posts' => $recentPosts,
            'recent_orders' => AdminOrderResource::collection($recentOrders),
        ]);
    }
}
