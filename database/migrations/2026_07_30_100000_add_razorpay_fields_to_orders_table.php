<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status', 32)->default('pending')->after('status');
            $table->string('payment_method', 32)->nullable()->after('payment_status');
            $table->string('razorpay_order_id')->nullable()->after('payment_method');
            $table->string('razorpay_payment_id')->nullable()->after('razorpay_order_id');
            $table->string('razorpay_signature')->nullable()->after('razorpay_payment_id');
            $table->timestamp('paid_at')->nullable()->after('razorpay_signature');

            $table->index('payment_status');
            $table->index('razorpay_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['razorpay_order_id']);
            $table->dropColumn([
                'payment_status',
                'payment_method',
                'razorpay_order_id',
                'razorpay_payment_id',
                'razorpay_signature',
                'paid_at',
            ]);
        });
    }
};
