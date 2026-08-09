<?php

use App\Services\Inventory\InventoryLegacyBackfill;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(InventoryLegacyBackfill::class)->run();
    }

    public function down(): void
    {
        // Legacy backfill is not safely reversible without data loss.
    }
};
