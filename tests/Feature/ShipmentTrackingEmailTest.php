<?php

namespace Tests\Feature;

use App\Enums\FulfillmentMethod;
use App\Jobs\SendShipmentTrackingEmail;
use App\Mail\ShipmentTracking;
use App\Models\Order;
use App\Services\ShipmentEmailDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class ShipmentTrackingEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config([
            'app.url' => 'https://venturesmart.in',
            'invoice.order_url_template' => 'https://venturesmart.in/orders/{number}',
            'invoice.logo' => 'images/ventures-mart-logo-invoice.png',
        ]);
    }

    public function test_awb_email_is_branded_contains_tracking_and_is_sent_once(): void
    {
        $order = $this->makeOrder(FulfillmentMethod::Shiprocket);
        $shipment = $order->shiprocketShipment()->create([
            'sync_status' => 'completed',
            'stage' => 'awb_assigned',
            'courier_name' => 'Delhivery Surface',
            'awb_code' => 'AWB-EMAIL-123',
            'tracking_url' => 'https://shiprocket.co/tracking/AWB-EMAIL-123',
        ]);

        $dispatcher = app(ShipmentEmailDispatcher::class);
        $dispatcher->dispatch($order, $shipment);
        $dispatcher->dispatch($order, $shipment);

        $this->assertNotNull($order->fresh()->shipping_notification_emailed_at);
        Mail::assertSent(ShipmentTracking::class, 1);
        Mail::assertSent(ShipmentTracking::class, function (ShipmentTracking $mail) use ($order) {
            $html = $mail->render();

            return $mail->hasTo($order->email)
                && $mail->envelope()->subject === 'Your order is ready to track - '.$order->number
                && $mail->attachments() === []
                && str_contains($html, 'AWB-EMAIL-123')
                && str_contains($html, 'Delhivery Surface')
                && str_contains($html, 'https://venturesmart.in/orders/'.$order->number)
                && str_contains($html, 'https://shiprocket.co/tracking/AWB-EMAIL-123')
                && str_contains($html, 'https://venturesmart.in/images/ventures-mart-logo-invoice.png');
        });
    }

    public function test_unsafe_provider_url_is_not_rendered(): void
    {
        $order = $this->makeOrder(FulfillmentMethod::Shiprocket);
        $shipment = $order->shiprocketShipment()->create([
            'sync_status' => 'completed',
            'stage' => 'awb_assigned',
            'courier_name' => 'Courier',
            'awb_code' => 'AWB-SAFE',
            'tracking_url' => 'javascript:alert(1)',
        ]);

        app(ShipmentEmailDispatcher::class)->dispatch($order, $shipment);

        Mail::assertSent(ShipmentTracking::class, function (ShipmentTracking $mail) {
            return ! str_contains($mail->render(), 'javascript:');
        });
    }

    public function test_manual_cancelled_and_awb_less_orders_do_not_send_tracking_email(): void
    {
        $manual = $this->makeOrder(FulfillmentMethod::Manual);
        $manualShipment = $manual->shiprocketShipment()->create([
            'sync_status' => 'cancelled',
            'stage' => 'switched_to_manual',
            'awb_code' => 'OLD-AWB',
        ]);
        $cancelled = $this->makeOrder(FulfillmentMethod::Shiprocket, 'Cancelled');
        $cancelledShipment = $cancelled->shiprocketShipment()->create([
            'sync_status' => 'cancelled',
            'stage' => 'cancelled',
            'awb_code' => 'CANCELLED-AWB',
            'cancelled_at' => now(),
        ]);
        $awbLess = $this->makeOrder(FulfillmentMethod::Shiprocket);
        $awbLessShipment = $awbLess->shiprocketShipment()->create([
            'sync_status' => 'processing',
            'stage' => 'order_created',
        ]);

        $dispatcher = app(ShipmentEmailDispatcher::class);
        $dispatcher->dispatch($manual, $manualShipment);
        $dispatcher->dispatch($cancelled, $cancelledShipment);
        $dispatcher->dispatch($awbLess, $awbLessShipment);

        Mail::assertNothingSent();
    }

    public function test_terminal_queue_failure_is_recorded(): void
    {
        $order = $this->makeOrder(FulfillmentMethod::Shiprocket);

        (new SendShipmentTrackingEmail($order->id))
            ->failed(new RuntimeException('SMTP delivery failed'));

        $this->assertDatabaseHas('application_errors', [
            'category' => 'job',
            'message' => 'SMTP delivery failed',
        ]);
    }

    private function makeOrder(
        FulfillmentMethod $method,
        string $status = 'Processing',
    ): Order {
        return Order::query()->create([
            'number' => 'VM-MAIL-'.uniqid(),
            'full_name' => 'Tracking Buyer',
            'email' => 'tracking@example.com',
            'phone' => '9999999999',
            'address' => '1 Tracking Street',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
            'seller_state' => 'Gujarat',
            'subtotal' => 100,
            'shipping' => 0,
            'tax' => 5,
            'total' => 105,
            'status' => $status,
            'payment_status' => 'pending',
            'payment_method' => 'cod',
            'fulfillment_method' => $method,
            'expected_delivery_at' => now()->addDays(3),
        ]);
    }
}
