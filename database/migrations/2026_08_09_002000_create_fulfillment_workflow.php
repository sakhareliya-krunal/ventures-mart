<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfillment_method', 24)->default('manual')->index()->after('inventory_status');
        });

        Schema::table('shiprocket_shipments', function (Blueprint $table) {
            $table->timestamp('last_provider_event_at')->nullable()->index()->after('last_synced_at');
        });

        Schema::create('order_fulfillment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shiprocket_shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 32);
            $table->string('event_type', 64);
            $table->string('previous_method', 24)->nullable();
            $table->string('new_method', 24)->nullable();
            $table->string('previous_status', 64)->nullable();
            $table->string('new_status', 64)->nullable();
            $table->string('provider_status', 160)->nullable();
            $table->string('provider_status_id', 32)->nullable();
            $table->string('external_event_id', 160)->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->string('reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('shipment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->default('shiprocket');
            $table->string('external_event_key', 191)->unique();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shiprocket_shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('awb', 120)->nullable()->index();
            $table->string('remote_order_id', 120)->nullable()->index();
            $table->string('event_type', 120)->nullable();
            $table->string('provider_status_id', 32)->nullable();
            $table->timestamp('provider_occurred_at')->nullable()->index();
            $table->string('status', 24)->default('received')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->json('payload');
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        DB::table('orders')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('shiprocket_shipments')
                    ->whereColumn('shiprocket_shipments.order_id', 'orders.id');
            })
            ->update(['fulfillment_method' => 'shiprocket']);

        DB::table('orders')
            ->whereNotNull('courier_partner')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('shiprocket_shipments')
                    ->whereColumn('shiprocket_shipments.order_id', 'orders.id');
            })
            ->update(['fulfillment_method' => 'manual']);

        $default = config('services.shiprocket.enabled')
            && config('services.shiprocket.default_fulfillment_method') === 'shiprocket'
                ? 'shiprocket'
                : 'manual';

        if ($default === 'shiprocket') {
            DB::table('orders')
                ->where('status', '!=', 'Cancelled')
                ->whereNull('courier_partner')
                ->update(['fulfillment_method' => 'shiprocket']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_webhook_events');
        Schema::dropIfExists('order_fulfillment_events');

        Schema::table('shiprocket_shipments', function (Blueprint $table) {
            $table->dropColumn('last_provider_event_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('fulfillment_method');
        });
    }
};
