<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('courier_partner')->nullable()->after('paid_at');
            $table->string('awb_number')->nullable()->after('courier_partner');
            $table->string('tracking_number')->nullable()->after('awb_number');
            $table->timestamp('dispatched_at')->nullable()->after('tracking_number');
            $table->timestamp('expected_delivery_at')->nullable()->after('dispatched_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'courier_partner',
                'awb_number',
                'tracking_number',
                'dispatched_at',
                'expected_delivery_at',
            ]);
        });
    }
};
