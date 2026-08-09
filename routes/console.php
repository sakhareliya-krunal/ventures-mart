<?php

use App\Exceptions\ShiprocketException;
use App\Jobs\SyncShiprocketTracking;
use App\Models\ShiprocketShipment;
use App\Services\ShiprocketService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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
        ->whereNotNull('awb_code')
        ->whereNull('cancelled_at')
        ->whereNotIn('shipment_status', ['Delivered', 'DELIVERED', 'Cancelled', 'CANCELED'])
        ->chunkById(100, function ($shipments) use (&$count) {
            foreach ($shipments as $shipment) {
                SyncShiprocketTracking::dispatch($shipment->order_id);
                $count++;
            }
        });

    $this->info("Queued {$count} Shiprocket tracking sync job(s).");

    return self::SUCCESS;
})->purpose('Queue tracking synchronization for active Shiprocket shipments');

Schedule::command('shiprocket:sync')->everyThirtyMinutes()->withoutOverlapping();

Schedule::command('inventory:reconcile --check')->hourly()->withoutOverlapping();
Schedule::command('inventory:expire-reservations')->everyMinute()->withoutOverlapping();
Schedule::command('inventory:dispatch-outbox')->everyMinute()->withoutOverlapping();
