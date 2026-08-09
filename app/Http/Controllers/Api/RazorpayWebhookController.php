<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentWebhookEvent;
use App\Services\OrderService;
use App\Services\RazorpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class RazorpayWebhookController extends Controller
{
    public function __construct(
        private readonly RazorpayService $razorpay,
        private readonly OrderService $orders,
    ) {
    }

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('X-Razorpay-Signature', '');

        if (! $this->razorpay->verifyWebhookSignature($payload, $signature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 400);
        }

        $data = json_decode($payload, true);

        if (! is_array($data)) {
            return response()->json(['message' => 'Invalid webhook payload.'], 400);
        }

        $event = (string) ($data['event'] ?? '');

        if ($event !== 'payment.captured') {
            return response()->json(['status' => 'ignored']);
        }

        $payment = $data['payload']['payment']['entity'] ?? null;

        if (! is_array($payment)) {
            return response()->json(['status' => 'ignored']);
        }

        $razorpayOrderId = (string) ($payment['order_id'] ?? '');
        $paymentId = (string) ($payment['id'] ?? '');
        $eventId = (string) ($request->header('X-Razorpay-Event-Id')
            ?: ($data['id'] ?? hash('sha256', $payload)));

        if ($razorpayOrderId === '' || $paymentId === '' || $eventId === '') {
            return response()->json(['status' => 'ignored']);
        }

        $webhook = PaymentWebhookEvent::query()->firstOrCreate(
            ['provider' => 'razorpay', 'event_id' => $eventId],
            [
                'event_type' => $event,
                'payment_id' => $paymentId,
                'status' => 'received',
                'payload' => $data,
            ],
        );
        if (! $webhook->wasRecentlyCreated && $webhook->status === 'processed') {
            return response()->json(['status' => 'ok']);
        }

        $order = Order::query()->where('razorpay_order_id', $razorpayOrderId)->first();

        if (! $order) {
            Log::warning('Razorpay webhook order not found', [
                'razorpay_order_id' => $razorpayOrderId,
                'payment_id' => $paymentId,
            ]);

            return response()->json(['status' => 'ok']);
        }

        $amount = (int) ($payment['amount'] ?? 0);
        $currency = strtoupper((string) ($payment['currency'] ?? ''));
        $paymentStatus = strtolower((string) ($payment['status'] ?? ''));
        $expectedAmount = (int) round(((float) $order->total) * 100);
        $paymentUsedByAnotherOrder = Order::query()
            ->where('razorpay_payment_id', $paymentId)
            ->whereKeyNot($order->id)
            ->exists();

        if (
            $amount !== $expectedAmount
            || $currency !== 'INR'
            || ! in_array($paymentStatus, ['captured', 'authorized'], true)
            || $paymentUsedByAnotherOrder
        ) {
            $webhook->forceFill([
                'order_id' => $order->id,
                'status' => 'rejected',
                'last_error' => 'Payment attributes did not match the local order.',
            ])->save();

            Log::warning('Razorpay webhook payment mismatch', [
                'order_id' => $order->id,
                'payment_id' => $paymentId,
                'amount' => $amount,
                'currency' => $currency,
                'status' => $paymentStatus,
            ]);

            return response()->json(['message' => 'Payment attributes do not match order.'], 422);
        }

        try {
            $this->orders->markPaidFromWebhook($order, $paymentId);
            $webhook->forceFill([
                'order_id' => $order->id,
                'status' => 'processed',
                'processed_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (ValidationException $e) {
            $webhook->forceFill([
                'order_id' => $order->id,
                'status' => 'failed',
                'last_error' => json_encode($e->errors()),
            ])->save();
            Log::error('Razorpay webhook could not finalize order', [
                'order_id' => $order->id,
                'errors' => $e->errors(),
            ]);

            return response()->json(['message' => 'Unable to finalize order.'], 422);
        } catch (Throwable $e) {
            $webhook->forceFill([
                'order_id' => $order->id,
                'status' => 'failed',
                'last_error' => mb_substr($e->getMessage(), 0, 4000),
            ])->save();
            Log::error('Razorpay webhook failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Webhook processing failed.'], 500);
        }

        return response()->json(['status' => 'ok']);
    }
}
