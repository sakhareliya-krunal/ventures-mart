<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Notifications\LowStockNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'data' => $request->user()->notifications()->latest()->limit(50)->get(),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $notification)
    {
        $record = $request->user()->notifications()->findOrFail($notification);
        $record->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function readInventory(Request $request)
    {
        $request->user()
            ->unreadNotifications()
            ->where('type', LowStockNotification::class)
            ->update(['read_at' => now()]);

        return response()->json(['inventory_unread_count' => 0]);
    }

    public function navigationCounts(Request $request)
    {
        return response()->json([
            'inventory_unread_count' => $request->user()
                ->unreadNotifications()
                ->where('type', LowStockNotification::class)
                ->count(),
            'contact_unread_count' => ContactMessage::query()->unread()->count(),
        ]);
    }
}
