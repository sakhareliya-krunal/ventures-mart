<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use RuntimeException;
use Throwable;

class RazorpayService
{
    public function keyId(): string
    {
        $key = (string) config('services.razorpay.key_id', '');

        if ($key === '') {
            throw new RuntimeException('Razorpay key id is not configured.');
        }

        return $key;
    }

    public function createOrder(Order $order): array
    {
        $amountPaise = (int) round(((float) $order->total) * 100);

        if ($amountPaise < 100) {
            throw new RuntimeException('Order total is too small for Razorpay.');
        }

        try {
            $razorpayOrder = $this->api()->order->create([
                'receipt' => $order->number,
                'amount' => $amountPaise,
                'currency' => 'INR',
                'notes' => [
                    'order_id' => (string) $order->id,
                    'order_number' => $order->number,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Razorpay order create failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
            app(ApplicationErrorRecorder::class)->recordPaymentFailure(
                'Razorpay order create failed: '.$e->getMessage(),
                ['order_id' => $order->id, 'order_number' => $order->number],
                $e,
            );

            throw new RuntimeException('Unable to start Razorpay payment. Please try again.');
        }

        return [
            'id' => (string) $razorpayOrder['id'],
            'amount' => (int) $razorpayOrder['amount'],
            'currency' => (string) ($razorpayOrder['currency'] ?? 'INR'),
        ];
    }

    public function verifySignature(string $razorpayOrderId, string $razorpayPaymentId, string $signature): bool
    {
        try {
            $this->api()->utility->verifyPaymentSignature([
                'razorpay_order_id' => $razorpayOrderId,
                'razorpay_payment_id' => $razorpayPaymentId,
                'razorpay_signature' => $signature,
            ]);

            return true;
        } catch (SignatureVerificationError $e) {
            return false;
        }
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = (string) config('services.razorpay.webhook_secret', '');

        if ($secret === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * @return array<string, mixed>
     */
    public function checkoutPayload(Order $order): array
    {
        return [
            'key' => $this->keyId(),
            'order_id' => $order->razorpay_order_id,
            'amount' => (int) round(((float) $order->total) * 100),
            'currency' => 'INR',
            'name' => (string) config('app.name', 'Ventures Mart'),
            'description' => 'Order '.$order->number,
            'prefill' => [
                'name' => $order->full_name,
                'email' => $order->email,
                'contact' => $order->phone,
            ],
        ];
    }

    private function api(): Api
    {
        $key = $this->keyId();
        $secret = (string) config('services.razorpay.key_secret', '');

        if ($secret === '') {
            throw new RuntimeException('Razorpay key secret is not configured.');
        }

        return new Api($key, $secret);
    }
}
