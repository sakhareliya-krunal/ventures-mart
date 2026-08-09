<?php

namespace App\Notifications;

use App\Models\InventoryBalance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly InventoryBalance $balance)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return config('inventory.send_low_stock_email', false)
            ? ['database', 'mail']
            : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $product = $this->balance->product;

        return (new MailMessage)
            ->subject('Low stock: '.($product?->name ?? 'Product'))
            ->line(($product?->name ?? 'A product').' has reached its low-stock threshold.')
            ->line('SKU: '.($product?->sku ?: '—'))
            ->line('Available: '.$this->balance->available())
            ->action('Review inventory', url('/admin/inventory?status=low_stock'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'product_id' => $this->balance->product_id,
            'product_name' => $this->balance->product?->name,
            'sku' => $this->balance->product?->sku,
            'available' => $this->balance->available(),
            'threshold' => $this->balance->low_stock_threshold,
            'path' => '/admin/inventory?status=low_stock',
        ];
    }
}
