<?php

namespace App\Services;

use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Order;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderConfirmationMailer
{
    public function __construct(
        private readonly ApplicationErrorRecorder $errors,
    ) {}

    /**
     * Send after the HTTP response so checkout/payment APIs stay fast,
     * while still running synchronously (no database queue worker required).
     */
    public function sendAfterResponse(Order $order): void
    {
        if (! $this->shouldEnqueue($order)) {
            return;
        }

        $orderId = (int) $order->id;

        try {
            app()->terminating(function () use ($orderId): void {
                try {
                    $this->run($orderId);
                } catch (Throwable $exception) {
                    $this->errors->recordThrowable($exception, [
                        'order_id' => $orderId,
                        'phase' => 'order_confirmation_email_send',
                    ], 'email');
                }
            });
        } catch (Throwable $exception) {
            $this->errors->recordThrowable($exception, [
                'order_id' => $orderId,
                'order_number' => $order->number,
                'phase' => 'order_confirmation_email_enqueue',
            ], 'email');
        }
    }

    /**
     * Admin/ops path: send immediately (sync). When $force is true, clear the
     * emailed stamp so a previously delivered confirmation can be resent.
     */
    public function sendNow(Order $order, bool $force = false): Order
    {
        $order = $order->fresh() ?? $order;

        if (! filter_var($order->email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => 'This order does not have a valid customer email address.',
            ]);
        }

        if (! $this->isEligible($order)) {
            throw ValidationException::withMessages([
                'email' => 'Confirmation email can only be sent for confirmed COD orders or paid online orders.',
            ]);
        }

        if ($order->order_confirmation_emailed_at && ! $force) {
            throw ValidationException::withMessages([
                'email' => 'Confirmation was already sent. Pass force=true to resend.',
            ]);
        }

        if ($force && $order->order_confirmation_emailed_at) {
            $order->forceFill(['order_confirmation_emailed_at' => null])->save();
        }

        $this->run((int) $order->id);

        return $order->fresh([
            'items.inventoryReservation',
            'user',
            'shiprocketShipment',
            'fulfillmentEvents' => fn ($query) => $query->latest()->limit(50),
        ]) ?? $order;
    }

    public function canResend(Order $order): bool
    {
        return (bool) filter_var($order->email, FILTER_VALIDATE_EMAIL)
            && $this->isEligible($order);
    }

    private function run(int $orderId): void
    {
        $job = new SendOrderConfirmationEmail($orderId);
        app()->call([$job, 'handle']);
    }

    private function shouldEnqueue(Order $order): bool
    {
        if (
            $order->order_confirmation_emailed_at
            || in_array($order->status, ['Cancelled', 'InventoryHold'], true)
            || ($order->payment_method === 'razorpay' && $order->payment_status !== 'paid')
        ) {
            return false;
        }

        return $this->isEligible($order);
    }

    private function isEligible(Order $order): bool
    {
        if ($order->payment_method === 'cod') {
            return in_array($order->status, ['Processing', 'Packed', 'Shipped', 'Delivered'], true);
        }

        return $order->payment_method === 'razorpay'
            && $order->payment_status === 'paid'
            && ! in_array($order->status, ['Cancelled', 'InventoryHold'], true);
    }
}
