<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceLayoutSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_typical_invoice_pdf_is_single_page_with_logo(): void
    {
        config([
            'invoice.gstin' => '',
            'invoice.logo' => 'images/ventures-mart-logo-invoice.png',
            'invoice.legal_name' => 'Neelkanth Ventures',
            'invoice.trade_name' => 'Ventures Mart',
            'invoice.default_hsn' => '9503',
        ]);

        $user = User::factory()->create();
        $order = Order::query()->create([
            'number' => 'VM-SMOKE-'.uniqid(),
            'user_id' => $user->id,
            'full_name' => 'Smoke Buyer',
            'email' => $user->email,
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
        ]);

        $category = Category::query()->first() ?? Category::query()->create([
            'name' => 'Toys',
            'slug' => 'toys-smoke',
            'description' => 'Toys',
            'image' => '/products/toys/wooden-building-blocks/01.jpg',
            'featured' => true,
        ]);

        $product = Product::query()->create([
            'external_id' => 'ext-'.uniqid(),
            'slug' => 'smoke-product-'.uniqid(),
            'name' => 'Smoke Product',
            'sku' => 'SKU-SMOKE',
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
            'description' => 'Smoke',
            'details' => [],
            'stock' => 10,
            'is_active' => true,
            'gallery' => [],
        ]);

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

        $service = app(InvoiceService::class);
        $issued = $service->ensureIssued($order->fresh('items'));
        $data = $service->buildViewData($issued);

        $this->assertNotEmpty($data['logo_data_uri']);
        $this->assertStringContainsString('data:image/', $data['logo_data_uri']);

        $html = view('invoices.tax-invoice', $data)->render();
        $this->assertStringContainsString('width="194"', $html);
        $this->assertStringContainsString('height="37"', $html);
        $this->assertStringNotContainsString('Seller GSTIN is not configured', $html);

        $pdf = Pdf::loadView('invoices.tax-invoice', $data)->setPaper('a4');
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        $this->assertSame(1, $dompdf->getCanvas()->get_page_count());
    }
}
