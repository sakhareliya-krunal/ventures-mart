<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('inventory_status', 32)->default('unallocated')->after('status');
            $table->timestamp('payment_expires_at')->nullable()->after('paid_at');
            $table->timestamp('cancel_requested_at')->nullable()->after('payment_expires_at');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_requested_at');
            $table->string('cancellation_reason')->nullable()->after('cancelled_at');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('shipped_quantity')->default(0)->after('quantity');
            $table->unsignedInteger('returned_quantity')->default(0)->after('shipped_quantity');
            $table->unsignedInteger('restocked_quantity')->default(0)->after('returned_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['shipped_quantity', 'returned_quantity', 'restocked_quantity']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'inventory_status',
                'payment_expires_at',
                'cancel_requested_at',
                'cancelled_at',
                'cancellation_reason',
            ]);
        });
    }
};
