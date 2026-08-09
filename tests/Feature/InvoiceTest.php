<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();

        config([
            'invoice.gstin' => '24AAAAA0000A1Z5',
            'invoice.legal_name' => 'Neelkanth Ventures',
            'invoice.trade_name' => 'Ventures Mart',
            'invoice.default_hsn' => '9503',
            'invoice.email' => 'support@venturesmart.in',
            'invoice.website' => 'https://venturesmart.in',
        ]);
    }

    public function test_cod_processing_order_can_download_invoice_pdf(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder([
            'user_id' => $user->id,
            'email' => $user->email,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'Processing',
        ]);

        Sanctum::actingAs($user);

        $response = $this->get("/api/orders/{$order->id}/invoice");

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertNotEmpty($response->getContent());
        $this->assertNotNull($order->fresh()->invoice_number);
        $this->assertNotNull($order->fresh()->invoice_issued_at);
        $this->assertStringStartsWith('VM/', (string) $order->fresh()->invoice_number);
    }

    public function test_unpaid_razorpay_order_cannot_download_invoice(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder([
            'user_id' => $user->id,
            'email' => $user->email,
            'payment_method' => 'razorpay',
            'payment_status' => 'pending',
            'status' => 'AwaitingPayment',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/orders/{$order->id}/invoice")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invoice']);

        $this->assertNull($order->fresh()->invoice_number);
    }

    public function test_paid_razorpay_order_can_download_invoice(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder([
            'user_id' => $user->id,
            'email' => $user->email,
            'payment_method' => 'razorpay',
            'payment_status' => 'paid',
            'status' => 'Processing',
        ]);

        Sanctum::actingAs($user);

        $this->get("/api/orders/{$order->id}/invoice")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_admin_can_download_invoice_for_cod_order(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder([
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'Processing',
        ]);

        Sanctum::actingAs($admin);

        $this->get("/api/admin/orders/{$order->id}/invoice")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_invoice_number_is_stable_across_downloads(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder([
            'user_id' => $user->id,
            'email' => $user->email,
            'payment_method' => 'cod',
            'status' => 'Processing',
        ]);

        Sanctum::actingAs($user);

        $this->get("/api/orders/{$order->id}/invoice")->assertOk();
        $first = $order->fresh()->invoice_number;

        $this->get("/api/orders/{$order->id}/invoice")->assertOk();
        $this->assertSame($first, $order->fresh()->invoice_number);
    }

    public function test_invoice_document_can_be_reused_as_an_email_attachment(): void
    {
        $order = $this->makeOrder([
            'payment_method' => 'cod',
            'status' => 'Processing',
        ]);

        $document = app(InvoiceService::class)->pdfDocument($order);

        $this->assertStringStartsWith('%PDF-', $document['contents']);
        $this->assertMatchesRegularExpression(
            '/^Invoice-VM-\d{2}-\d{2}-\d{4}\.pdf$/',
            $document['filename'],
        );
        $this->assertSame($order->id, $document['order']->id);
    }

    public function test_without_gstin_document_is_invoice_not_tax_invoice(): void
    {
        config(['invoice.gstin' => '']);

        $user = User::factory()->create();
        $order = $this->makeOrder([
            'user_id' => $user->id,
            'email' => $user->email,
            'payment_method' => 'cod',
            'status' => 'Processing',
            'payment_status' => 'pending',
        ]);

        $service = app(InvoiceService::class);
        $issued = $service->ensureIssued($order);
        $data = $service->buildViewData($issued);

        $this->assertFalse($data['has_gstin']);
        $this->assertSame('INVOICE', $data['document_title']);
        $this->assertStringContainsString('venturesmart.in/orders/'.$order->number, $data['order_url']);
        $this->assertNotEmpty($data['qr_data_uri']);
        $this->assertSame('Pending', $data['payment_badge']['label']);

        $html = view('invoices.tax-invoice', $data)->render();

        $this->assertStringContainsString('INVOICE', $html);
        $this->assertStringNotContainsString('TAX INVOICE', $html);
        $this->assertStringNotContainsString('Seller GSTIN is not configured', $html);
        $this->assertStringNotContainsString('GSTIN:', $html);

        Sanctum::actingAs($user);
        $this->get("/api/orders/{$order->id}/invoice")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_with_gstin_document_is_tax_invoice(): void
    {
        $order = $this->makeOrder([
            'payment_method' => 'cod',
            'status' => 'Processing',
        ]);

        $service = app(InvoiceService::class);
        $data = $service->buildViewData($service->ensureIssued($order));

        $this->assertTrue($data['has_gstin']);
        $this->assertSame('TAX INVOICE', $data['document_title']);

        $html = view('invoices.tax-invoice', $data)->render();
        $this->assertStringContainsString('TAX INVOICE', $html);
        $this->assertStringContainsString('GSTIN:', $html);
    }

    public function test_courier_block_renders_only_when_present(): void
    {
        $order = $this->makeOrder([
            'payment_method' => 'cod',
            'status' => 'Shipped',
            'courier_partner' => 'Delhivery',
            'awb_number' => 'AWB123',
            'tracking_number' => 'TRK456',
        ]);

        $service = app(InvoiceService::class);
        $data = $service->buildViewData($service->ensureIssued($order));

        $this->assertTrue($data['has_courier']);
        $html = view('invoices.tax-invoice', $data)->render();
        $this->assertStringContainsString('Courier Details', $html);
        $this->assertStringContainsString('Delhivery', $html);
        $this->assertStringContainsString('AWB123', $html);
    }

    public function test_invoice_uses_shiprocket_tracking_id_and_rejects_unsafe_url(): void
    {
        $order = $this->makeOrder([
            'payment_method' => 'cod',
            'fulfillment_method' => 'shiprocket',
            'status' => 'Shipped',
            'courier_partner' => 'Old courier',
            'awb_number' => 'OLD-AWB',
        ]);
        $order->shiprocketShipment()->create([
            'sync_status' => 'completed',
            'stage' => 'pickup_scheduled',
            'courier_name' => 'Delhivery Surface',
            'awb_code' => 'AWB-SR-123',
            'shipment_status' => 'IN TRANSIT',
            'tracking_url' => 'javascript:alert(1)',
        ]);

        $service = app(InvoiceService::class);
        $data = $service->buildViewData($service->ensureIssued($order));
        $html = view('invoices.tax-invoice', $data)->render();

        $this->assertSame('AWB-SR-123', $data['courier']['tracking_id']);
        $this->assertNull($data['courier']['tracking_url']);
        $this->assertStringContainsString('Tracking ID (AWB)', $html);
        $this->assertStringContainsString('AWB-SR-123', $html);
        $this->assertStringContainsString('IN TRANSIT', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(array $overrides = []): Order
    {
        $order = Order::query()->create(array_merge([
            'number' => 'VM-INV-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Invoice Buyer',
            'email' => 'invoice@example.com',
            'phone' => '9999999999',
            'address' => '12 Test Street',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
            'seller_state' => 'Gujarat',
            'subtotal' => 200,
            'shipping' => 0,
            'cod_fee' => 0,
            'cgst' => 5,
            'sgst' => 5,
            'igst' => 0,
            'tax' => 10,
            'total' => 210,
            'status' => 'Processing',
            'payment_status' => 'pending',
            'payment_method' => 'cod',
        ], $overrides));

        $product = $this->makeProduct();

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'hsn' => '9503',
            'product_slug' => $product->slug,
            'product_image' => $product->image,
            'unit_price' => 200,
            'quantity' => 1,
            'line_total' => 200,
        ]);

        return $order->fresh('items');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProduct(array $overrides = []): Product
    {
        $category = Category::query()->first() ?? Category::query()->create([
            'name' => 'Toys',
            'slug' => 'toys',
            'description' => 'Toys',
            'image' => '/products/toys/wooden-building-blocks/01.jpg',
            'featured' => true,
        ]);

        return Product::query()->create(array_merge([
            'external_id' => 'ext-'.uniqid(),
            'slug' => 'invoice-product-'.uniqid(),
            'name' => 'Invoice Product',
            'sku' => 'SKU-'.uniqid(),
            'hsn' => '9503',
            'category_id' => $category->id,
            'price' => 200,
            'compare_at_price' => null,
            'rating' => 4.5,
            'reviews' => 10,
            'image' => '/images/products/demo.jpg',
            'hover_image' => null,
            'badge' => null,
            'tags' => [],
            'description' => 'Invoice test product',
            'details' => [],
            'stock' => 10,
            'is_active' => true,
            'gallery' => [],
        ], $overrides));
    }
}
