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
            $message = 'Razorpay key id is not configured.';
            app(ApplicationErrorRecorder::class)->recordPaymentFailure(
                $message,
                ['phase' => 'config', 'missing' => 'key_id'],
            );

            throw new RuntimeException($message);
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

            throw new RuntimeException($this->customerMessageForCreateFailure($e));
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
            $message = 'Razorpay key secret is not configured.';
            app(ApplicationErrorRecorder::class)->recordPaymentFailure(
                $message,
                ['phase' => 'config', 'missing' => 'key_secret'],
            );

            throw new RuntimeException($message);
        }

        return new Api($key, $secret);
    }

    /**
     * Safe customer-facing copy derived from Razorpay / SDK errors (no secrets or stack traces).
     */
    private function customerMessageForCreateFailure(Throwable $e): string
    {
        $raw = strtolower($e->getMessage());

        if (
            str_contains($raw, 'authentication')
            || str_contains($raw, 'auth failed')
            || str_contains($raw, 'invalid key')
            || str_contains($raw, 'invalid api key')
            || str_contains($raw, 'access denied')
            || str_contains($raw, 'unauthorized')
            || str_contains($raw, 'bad request') && str_contains($raw, 'key')
        ) {
            return 'Razorpay authentication failed. Check live API keys on the server.';
        }

        if (str_contains($raw, 'amount') && (str_contains($raw, 'minimum') || str_contains($raw, 'less'))) {
            return 'Order total is too small for online payment.';
        }

        if (str_contains($raw, 'currency')) {
            return 'Unable to start payment due to a currency configuration issue.';
        }

        if (
            str_contains($raw, 'not activated')
            || str_contains($raw, 'account') && str_contains($raw, 'activ')
            || str_contains($raw, 'under review')
        ) {
            return 'Razorpay live payments are not activated for this account yet.';
        }

        return 'Unable to start Razorpay payment. Please try again.';
    }
}
