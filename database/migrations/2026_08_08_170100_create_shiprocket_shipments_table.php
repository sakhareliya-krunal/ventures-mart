<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shiprocket_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('sync_status', 32)->default('pending')->index();
            $table->string('stage', 32)->default('queued');
            $table->unsignedBigInteger('shiprocket_order_id')->nullable()->unique();
            $table->unsignedBigInteger('shipment_id')->nullable()->unique();
            $table->unsignedBigInteger('courier_company_id')->nullable();
            $table->string('courier_name')->nullable();
            $table->string('awb_code')->nullable()->index();
            $table->string('pickup_status')->nullable();
            $table->string('shipment_status')->nullable();
            $table->unsignedInteger('shipment_status_id')->nullable();
            $table->string('tracking_url', 1000)->nullable();
            $table->string('label_url', 1000)->nullable();
            $table->string('manifest_url', 1000)->nullable();
            $table->string('request_fingerprint', 64)->nullable()->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('order_created_at')->nullable();
            $table->timestamp('awb_assigned_at')->nullable();
            $table->timestamp('pickup_scheduled_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shiprocket_shipments');
    }
};
