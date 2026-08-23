<?php

use App\Enums\FulfillmentMethod;
use App\Exceptions\ShiprocketException;
use App\Jobs\SyncShiprocketTracking;
use App\Models\ShiprocketShipment;
use App\Services\ImageVariantService;
use App\Services\ShiprocketService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('shiprocket:validate', function (ShiprocketService $shiprocket) {
    try {
        $pickup = $shiprocket->resolvePickupLocation();
    } catch (ShiprocketException $exception) {
        $this->error($exception->getMessage());

        return self::FAILURE;
    }

    $this->info('Shiprocket authentication succeeded.');
    $this->line('Pickup: '.($pickup['pickup_location'] ?? 'Unknown'));
    $this->line('Location: '.implode(', ', array_filter([
        $pickup['city'] ?? null,
        $pickup['state'] ?? null,
        $pickup['pin_code'] ?? null,
    ])));

    return self::SUCCESS;
})->purpose('Validate Shiprocket credentials and pickup configuration');

Artisan::command('shiprocket:sync', function () {
    if (! config('services.shiprocket.enabled')) {
        $this->warn('Shiprocket integration is disabled.');

        return self::SUCCESS;
    }

    $count = 0;
    ShiprocketShipment::query()
        ->whereHas('order', fn ($query) => $query->where(
            'fulfillment_method',
            FulfillmentMethod::Shiprocket->value,
        ))
        ->whereNotNull('awb_code')
        ->whereNull('cancelled_at')
        ->where(function ($query) {
            $query->whereNull('last_synced_at')
                ->orWhere('last_synced_at', '<=', now()->subMinutes(20));
        })
        ->whereRaw("LOWER(COALESCE(shipment_status, '')) NOT IN ('delivered', 'cancelled', 'canceled')")
        ->chunkById(100, function ($shipments) use (&$count) {
            foreach ($shipments as $shipment) {
                SyncShiprocketTracking::dispatch($shipment->order_id);
                $count++;
            }
        });

    $this->info("Queued {$count} Shiprocket tracking sync job(s).");

    return self::SUCCESS;
})->purpose('Queue tracking synchronization for active Shiprocket shipments');

Artisan::command('images:optimize-products', function (ImageVariantService $images) {
    ini_set('memory_limit', '512M');

    $roots = [
        public_path('products'),
        public_path('storage/products'),
    ];
    $widths = array_values(array_unique([...ImageVariantService::CARD_WIDTHS, ...ImageVariantService::DETAIL_WIDTHS]));
    $processed = 0;

    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }

        foreach (File::allFiles($root) as $file) {
            $path = $file->getPathname();

            if (! preg_match('/\.(jpe?g|png|webp)$/i', $path) || preg_match('/-\d+w\.webp$/i', $path)) {
                continue;
            }

            $created = $images->createForAbsolutePath($path, $widths);
            $processed++;
            $this->line(sprintf('Processed %s (%d variant URLs available)', $path, count($created)));
        }
    }

    $this->info("Processed {$processed} product image(s).");

    return self::SUCCESS;
})->purpose('Create responsive WebP variants for product images');
Schedule::command('shiprocket:sync')->everyThirtyMinutes()->withoutOverlapping()->onOneServer();

Schedule::command('inventory:reconcile --check')->hourly()->withoutOverlapping();
Schedule::command('inventory:expire-reservations')->everyMinute()->withoutOverlapping();
Schedule::command('inventory:dispatch-outbox')->everyMinute()->withoutOverlapping();
