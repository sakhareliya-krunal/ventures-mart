<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('cancellation_emailed_at')
                ->nullable()
                ->after('shipping_notification_emailed_at');
            $table->string('order_type', 32)
                ->default('standard')
                ->after('number');
            $table->foreignId('parent_order_id')
                ->nullable()
                ->after('user_id')
                ->constrained('orders')
                ->nullOnDelete();
            $table->timestamp('delivered_at')
                ->nullable()
                ->after('dispatched_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_order_id');
            $table->dropColumn([
                'cancellation_emailed_at',
                'order_type',
                'delivered_at',
            ]);
        });
    }
};
