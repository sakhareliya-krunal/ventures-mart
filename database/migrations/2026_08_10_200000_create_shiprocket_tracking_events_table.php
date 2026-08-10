<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shiprocket_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shiprocket_shipment_id')->constrained('shiprocket_shipments')->cascadeOnDelete();
            $table->string('event_hash', 64);
            $table->string('status')->nullable();
            $table->unsignedInteger('status_id')->nullable();
            $table->string('location')->nullable();
            $table->string('source', 32)->default('unknown');
            $table->json('raw')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['shiprocket_shipment_id', 'event_hash'], 'shiprocket_tracking_events_unique');
            $table->index('shiprocket_shipment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shiprocket_tracking_events');
    }
};
