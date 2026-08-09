<?php

namespace Tests\Feature;

use App\Enums\InventoryReservationState;
use App\Enums\OrderInventoryStatus;
use App\Models\Category;
use App\Models\InventoryAuditFlag;
use App\Models\InventoryBalance;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Inventory\InventoryLegacyBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_preserves_available_stock_for_active_cod_orders(): void
    {
        $product = $this->makeProduct(['stock' => 4]);
        $order = $this->makeCodOrder($product, quantity: 2);

        $result = app(InventoryLegacyBackfill::class)->run();

        $product->refresh();
        $balance = InventoryBalance::query()->where('product_id', $product->id)->first();

        $this->assertTrue($result['availability_preserved']);
        $this->assertSame(4, $product->stock);
        $this->assertSame(6, $balance->on_hand);
        $this->assertSame(2, $balance->committed);
        $this->assertSame(InventoryReservationState::Committed, $order->items->first()->fresh()->inventoryReservation->state);
    }

    public function test_backfill_flags_cancelled_orders_instead_of_adjusting_stock(): void
    {
        $product = $this->makeProduct(['stock' => 7]);
        $this->makeCodOrder($product, quantity: 1, status: 'Cancelled');

        app(InventoryLegacyBackfill::class)->run();

        $this->assertDatabaseHas('inventory_audit_flags', [
            'product_id' => $product->id,
            'code' => 'cancelled_legacy_stock_unknown',
        ]);
        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_reconcile_command_reports_clean_state_after_backfill(): void
    {
        $product = $this->makeProduct(['stock' => 5]);
        app(InventoryLegacyBackfill::class)->run();

        $this->artisan('inventory:reconcile --check')
            ->assertSuccessful();
    }

    private function makeCodOrder(Product $product, int $quantity, string $status = 'Processing'): Order
    {
        $order = Order::query()->create([
            'number' => 'VM-BF-'.uniqid(),
            'full_name' => 'Backfill User',
            'email' => 'backfill@example.com',
            'phone' => '9999999999',
            'address' => '123 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'postal_code' => '400001',
            'seller_state' => 'Maharashtra',
            'subtotal' => 100,
            'shipping' => 0,
            'tax' => 0,
            'total' => 100,
            'status' => $status,
            'inventory_status' => OrderInventoryStatus::Unallocated,
            'payment_status' => 'pending',
            'payment_method' => 'cod',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'product_slug' => $product->slug,
            'product_image' => $product->image,
            'unit_price' => $product->price,
            'quantity' => $quantity,
            'line_total' => $product->price * $quantity,
        ]);

        return $order->fresh(['items']);
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
            'slug' => 'backfill-product-'.uniqid(),
            'name' => 'Backfill Product',
            'sku' => 'SKU-'.uniqid(),
            'category_id' => $category->id,
            'price' => 100,
            'compare_at_price' => null,
            'rating' => 4.5,
            'reviews' => 0,
            'image' => '/images/products/demo.jpg',
            'hover_image' => null,
            'badge' => null,
            'tags' => [],
            'description' => 'Backfill test product',
            'details' => [],
            'stock' => 10,
            'is_active' => true,
            'gallery' => [],
        ], $overrides));
    }
}
