<?php

use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $operation, $target, $key, $barrier] = $argv;
$deadline = microtime(true) + 10;
while (! is_file($barrier) && microtime(true) < $deadline) {
    usleep(1000);
}

try {
    $inventory = $app->make(InventoryService::class);

    match ($operation) {
        'reserve' => $inventory->reserve(
            OrderItem::query()->findOrFail((int) $target),
            now()->addMinutes(15),
            $key,
        ),
        'commit_reserved' => $inventory->commit(
            OrderItem::query()->findOrFail((int) $target),
            $key,
            fromReserved: true,
        ),
        'commit_cod' => $inventory->commit(
            OrderItem::query()->findOrFail((int) $target),
            $key,
            fromReserved: false,
        ),
        'expire' => $inventory->expire(
            OrderItem::query()->findOrFail((int) $target),
            $key,
        ),
        'release' => $inventory->release(
            OrderItem::query()->findOrFail((int) $target),
            'Concurrency cancellation test',
            $key,
        ),
        'consume' => $inventory->consume(
            OrderItem::query()->findOrFail((int) $target),
            $key,
        ),
        'adjust_decrease' => $inventory->adjust(
            Product::query()->findOrFail((int) $target),
            'decrease',
            1,
            'Concurrency adjustment test',
            $key,
        ),
        'lock_many' => DB::transaction(function () use ($inventory, $target): void {
            $inventory->lockBalances(array_map('intval', explode(',', $target)));
            usleep(250000);
        }, 3),
    };

    echo json_encode(['ok' => true, 'operation' => $operation], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'operation' => $operation,
        'exception' => $exception::class,
    ], JSON_THROW_ON_ERROR).PHP_EOL;
}
