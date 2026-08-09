<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustInventoryRequest;
use App\Http\Requests\Admin\BulkAdjustInventoryRequest;
use App\Http\Requests\Admin\ProcessInventoryReturnRequest;
use App\Http\Resources\InventoryBalanceResource;
use App\Http\Resources\InventoryLedgerResource;
use App\Http\Resources\InventoryReturnResource;
use App\Models\InventoryAuditFlag;
use App\Models\InventoryBalance;
use App\Models\InventoryLedgerEntry;
use App\Models\InventoryReturn;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Inventory\InventoryReturnService;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly InventoryReturnService $returns,
    ) {}

    public function index(Request $request)
    {
        $query = InventoryBalance::query()->with('product')->orderBy('product_id');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->whereHas('product', fn ($product) => $product
                ->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%"));
        }

        if ($request->boolean('low_stock')) {
            $query->whereRaw('(on_hand - reserved - committed) <= COALESCE(low_stock_threshold, ?)', [
                config('inventory.default_low_stock_threshold'),
            ]);
        }
        if ($request->string('status')->toString() === 'out_of_stock') {
            $query->whereRaw('(on_hand - reserved - committed) <= 0');
        } elseif ($request->string('status')->toString() === 'in_stock') {
            $query->whereRaw('(on_hand - reserved - committed) > COALESCE(low_stock_threshold, ?)', [
                config('inventory.default_low_stock_threshold'),
            ]);
        }

        return InventoryBalanceResource::collection(
            $query->paginate(min(max($request->integer('per_page', 25), 1), 100)),
        );
    }

    public function show(Product $product): InventoryBalanceResource
    {
        $balance = $this->inventory->ensureBalance($product);

        return new InventoryBalanceResource($balance->load('product'));
    }

    public function summary()
    {
        $base = InventoryBalance::query();

        return response()->json([
            'total_products' => (clone $base)->count(),
            'total_on_hand' => (int) (clone $base)->sum('on_hand'),
            'total_reserved' => (int) (clone $base)->sum('reserved'),
            'total_committed' => (int) (clone $base)->sum('committed'),
            'total_available' => (int) (clone $base)
                ->selectRaw('COALESCE(SUM(on_hand - reserved - committed), 0) as aggregate')
                ->value('aggregate'),
            'low_stock_count' => (clone $base)
                ->whereRaw('(on_hand - reserved - committed) <= COALESCE(low_stock_threshold, ?)', [
                    config('inventory.default_low_stock_threshold'),
                ])
                ->count(),
            'out_of_stock_count' => (clone $base)
                ->whereRaw('(on_hand - reserved - committed) <= 0')
                ->count(),
        ]);
    }

    public function adjust(AdjustInventoryRequest $request, Product $product): InventoryBalanceResource
    {
        $data = $request->validated();
        $result = $this->performAdjustment($product, $data, $request->user()->id);

        return new InventoryBalanceResource($result['balance']->load('product'));
    }

    public function bulkAdjust(BulkAdjustInventoryRequest $request)
    {
        $balances = DB::transaction(function () use ($request) {
            return collect($request->validated('adjustments'))
                ->sortBy('product_id')
                ->map(function (array $adjustment) use ($request) {
                    $product = Product::query()->findOrFail($adjustment['product_id']);

                    return $this->performAdjustment($product, $adjustment, $request->user()->id)['balance']
                        ->load('product');
                })
                ->values();
        });

        return InventoryBalanceResource::collection($balances);
    }

    public function ledger(Request $request)
    {
        $query = InventoryLedgerEntry::query()->latest('occurred_at');

        if ($productId = $request->integer('product_id')) {
            $query->where('product_id', $productId);
        }
        if ($orderId = $request->integer('order_id')) {
            $query->where('order_id', $orderId);
        }
        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        return InventoryLedgerResource::collection(
            $query->paginate(min(max($request->integer('per_page', 50), 1), 100)),
        );
    }

    public function movements(Request $request, Product $product)
    {
        return InventoryLedgerResource::collection(
            InventoryLedgerEntry::query()
                ->where('product_id', $product->id)
                ->latest('occurred_at')
                ->paginate(min(max($request->integer('per_page', 20), 1), 100)),
        );
    }

    public function export(Request $request)
    {
        $query = InventoryBalance::query()->with('product')->orderBy('product_id');
        if ($search = $request->string('search')->trim()->toString()) {
            $query->whereHas('product', fn ($product) => $product
                ->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%"));
        }

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Product', 'SKU', 'On hand', 'Reserved', 'Committed', 'Available', 'Version']);
            $query->chunkById(200, function ($balances) use ($output): void {
                foreach ($balances as $balance) {
                    fputcsv($output, [
                        $balance->product?->name,
                        $balance->product?->sku,
                        $balance->on_hand,
                        $balance->reserved,
                        $balance->committed,
                        $balance->available(),
                        $balance->version,
                    ]);
                }
            });
            fclose($output);
        }, 'inventory-'.now()->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }

    public function auditFlags(Request $request)
    {
        $query = InventoryAuditFlag::query()->with(['product', 'order'])->latest();
        if (! $request->boolean('include_resolved')) {
            $query->whereNull('resolved_at');
        }

        return response()->json($query->paginate(min(max($request->integer('per_page', 50), 1), 100)));
    }

    public function resolveAuditFlag(InventoryAuditFlag $inventoryAuditFlag)
    {
        $inventoryAuditFlag->forceFill(['resolved_at' => now()])->save();

        return response()->json(['ok' => true]);
    }

    public function returns(Request $request)
    {
        return InventoryReturnResource::collection(
            InventoryReturn::query()->latest()->paginate(min(max($request->integer('per_page', 50), 1), 100)),
        );
    }

    public function processReturn(ProcessInventoryReturnRequest $request)
    {
        $data = $request->validated();
        $return = $this->returns->receive(
            OrderItem::query()->findOrFail($data['order_item_id']),
            $data['quantity'],
            $data['disposition'],
            $data['reason'],
            $data['idempotency_key'],
            $request->user()->id,
        );

        return (new InventoryReturnResource($return))->response()->setStatusCode(201);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{balance: InventoryBalance, ledger: InventoryLedgerEntry}
     */
    private function performAdjustment(Product $product, array $data, int $actorId): array
    {
        if ($data['operation'] === 'damage') {
            return $this->inventory->writeOffDamage(
                $product,
                $data['quantity'],
                $data['reason'],
                $data['idempotency_key'],
                expectedVersion: $data['expected_version'],
                actorId: $actorId,
            );
        }

        return $this->inventory->adjust(
            $product,
            $data['operation'],
            $data['quantity'],
            $data['reason'],
            $data['idempotency_key'],
            expectedVersion: $data['expected_version'],
            actorId: $actorId,
        );
    }
}
