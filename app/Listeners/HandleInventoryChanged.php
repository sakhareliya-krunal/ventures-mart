<?php

namespace App\Listeners;

use App\Models\InventoryBalance;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Support\Facades\Notification;

class HandleInventoryChanged
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $balance = InventoryBalance::query()
            ->with('product')
            ->lockForUpdate()
            ->where('product_id', $payload['product_id'] ?? 0)
            ->first();

        if (! $balance) {
            return;
        }

        $threshold = $balance->low_stock_threshold
            ?? (int) config('inventory.default_low_stock_threshold', 5);

        if ($balance->available() > $threshold) {
            if ($balance->low_stock_notified_at) {
                $balance->forceFill(['low_stock_notified_at' => null])->save();
            }

            return;
        }

        if ($balance->low_stock_notified_at) {
            return;
        }

        $admins = User::query()->where('is_admin', true)->get();
        if ($admins->isEmpty()) {
            return;
        }

        $balance->forceFill(['low_stock_notified_at' => now()])->save();
        Notification::send($admins, new LowStockNotification($balance));
    }
}
