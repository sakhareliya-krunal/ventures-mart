<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('meta_purchase_event_id', 64)->nullable()->unique()->after('razorpay_signature');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['meta_purchase_event_id']);
            $table->dropColumn('meta_purchase_event_id');
        });
    }
};
