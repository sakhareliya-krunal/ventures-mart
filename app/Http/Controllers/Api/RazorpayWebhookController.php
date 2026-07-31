<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
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

        if ($razorpayOrderId === '' || $paymentId === '') {
            return response()->json(['status' => 'ignored']);
        }

        $order = Order::query()->where('razorpay_order_id', $razorpayOrderId)->first();

        if (! $order) {
            Log::warning('Razorpay webhook order not found', [
                'razorpay_order_id' => $razorpayOrderId,
                'payment_id' => $paymentId,
            ]);

            return response()->json(['status' => 'ok']);
        }

        try {
            $this->orders->markPaidFromWebhook($order, $paymentId);
        } catch (ValidationException $e) {
            Log::error('Razorpay webhook could not finalize order', [
                'order_id' => $order->id,
                'errors' => $e->errors(),
            ]);

            return response()->json(['message' => 'Unable to finalize order.'], 422);
        } catch (Throwable $e) {
            Log::error('Razorpay webhook failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Webhook processing failed.'], 500);
        }

        return response()->json(['status' => 'ok']);
    }
}
